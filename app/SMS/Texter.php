<?php

namespace App\SMS;

use App\SMS\Contracts\Texter as __;
use App\SMS\Contracts\TextQueue;
use App\SMS\Events\MessageSending;
use App\SMS\Events\MessageSent;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Contracts\Queue\Factory as Queue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Traits\Macroable;

class Texter implements __, TextQueue
{
    use Macroable;

    /**
     * The name that is configured for the texter.
     *
     * @var string
     */
    protected $name;

    /**
     * The text client instance.
     *
     * @var Client
     */
    protected $client;

    /**
     * The event dispatcher instance.
     *
     * @var \Illuminate\Contracts\Events\Dispatcher|null
     */
    protected $events;

    /**
     * The global from name.
     *
     * @var array
     */
    protected $from;

    /**
     * The global to phone number.
     *
     * @var array
     */
    protected $to;

    /**
     * The global as Flash.
     * @var bool
     */
    protected $asFlash;

    /**
     * The global callbackURI.
     * @var bool
     */
    protected $callbackURI;

    /**
     * The queue factory implementation.
     *
     * @var Queue
     */
    protected $queue;

    /**
     * Array of failed recipients.
     *
     * @var array
     */
    protected $failedRecipients = [];

    /**
     * Create a new Texter instance.
     *
     * @param  string  $name
     * @param  Client  $client
     * @param  \Illuminate\Contracts\Events\Dispatcher|null  $events
     * @return void
     */
    public function __construct(string $name, Client $client, Dispatcher $events = null)
    {
        $this->name = $name;
        $this->client = $client;
        $this->events = $events;
    }

    /**
     * Set the global from name or phone.
     *
     * @param  string  $name
     * @return void
     */
    public function alwaysFrom($name)
    {
        $this->from = $name;
    }

    /**
     * Set the global to phone
     *
     * @param  string  $phone
     * @return void
     */
    public function alwaysTo($phone)
    {
        $this->to = $phone;
    }

    /**
     * Set the global as flash
     *
     * @param  string  $asFlash
     * @return void
     */
    public function alwaysAsFlash($asFlash)
    {
        $this->asFlash = $asFlash;
    }

    /**
     * Set the global to callback_uri
     *
     * @param  string  $callbackURI
     * @return void
     */
    public function alwaysCallbackURI($callbackURI)
    {
        $this->callbackURI = $callbackURI;
    }

    /**
     * Begin the process of texting a textable class instance.
     *
     * @param  mixed  $users
     * @return PendingText
     */
    public function to($users)
    {
        return (new PendingText($this))->to($users);
    }

    /**
     * Send a new message using a view.
     *
     * @param  Textable|string  $textable
     * @param  \Closure|string|null  $callback
     * @return void
     */
    public function send($textable, $callback = null)
    {
        if ($textable instanceof Textable) {
            return $this->sendTextable($textable);
        }

        $message = $this->createMessage($textable);

        // Build the textable's data into the message
        $callback($message);

        // If a global "to" address has been set, we will set that address on the text
        // message. This is primarily useful during local development in which each
        // message should be delivered into a single text address for inspection.
        if (isset($this->to)) {
            $this->setGlobalTo($message);
        }

        // Next we will determine if the message should be sent. We give the developer
        // one final chance to stop this message and then we will send it to all of
        // its recipients. We will then fire the sent event for the sent message.

        if ($this->shouldSendMessage($message)) {

            $this->sendClientMessage($message);

            $this->dispatchSentEvent($message);
        }
    }

    /**
     * Send the given textable.
     *
     * @param  Textable  $textable
     * @return mixed
     */
    protected function sendTextable(Textable $textable)
    {
        return $textable instanceof ShouldQueue
                        ? $textable->texter($this->name)->queue($this->queue)
                        : $textable->texter($this->name)->send($this);
    }

    /**
     * Set the global "to" address on the given message.
     *
     * @param  Message  $message
     * @return void
     */
    protected function setGlobalTo($message)
    {
        $message->to($this->to);
    }

    /**
     * Queue a new text message for sending.
     *
     * @param  Textable  $textable
     * @param  string|null  $queue
     * @return mixed
     *
     * @throws \InvalidArgumentException
     */
    public function queue($textable, $queue = null)
    {
        if (!$textable instanceof Textable) {
            throw new \InvalidArgumentException('Only textables may be queued.');
        }

        if (is_string($queue)) {
            $textable->onQueue($queue);
        }

        return $textable->texter($this->name)->queue($this->queue);
    }

    /**
     * Queue a new text message for sending on the given queue.
     *
     * @param  string  $queue
     * @param  Textable  $textable
     * @return mixed
     */
    public function onQueue($queue, $textable)
    {
        return $this->queue($textable, $queue);
    }

    /**
     * Queue a new text message for sending on the given queue.
     *
     * This method didn't match rest of framework's "onQueue" phrasing. Added "onQueue".
     *
     * @param  string  $queue
     * @param  Textable  $textable
     * @return mixed
     */
    public function queueOn($queue, $textable)
    {
        return $this->onQueue($queue, $textable);
    }

    /**
     * Queue a new text message for sending after (n) seconds.
     *
     * @param  \DateTimeInterface|\DateInterval|int  $delay
     * @param  Textable  $textable
     * @param  string|null  $queue
     * @return mixed
     *
     * @throws \InvalidArgumentException
     */
    public function later($delay, $textable, $queue = null)
    {
        if (!$textable instanceof Textable) {
            throw new \InvalidArgumentException('Only textables may be queued.');
        }

        return $textable->texter($this->name)->later(
            $delay, is_null($queue) ? $this->queue : $queue
        );
    }

    /**
     * Queue a new text message for sending after (n) seconds on the given queue.
     *
     * @param  string  $queue
     * @param  \DateTimeInterface|\DateInterval|int  $delay
     * @param  Textable  $textable
     * @return mixed
     */
    public function laterOn($queue, $delay, $textable)
    {
        return $this->later($delay, $textable, $queue);
    }

    /**
     * Create a new message instance.
     *
     * @param Textable
     * @return Message
     */
    protected function createMessage($textable)
    {
        $message = new Message();

        // Set the messages content
        $message->content($textable);

        // If a global from address has been specified we will set it on every message
        // instance so the developer does not have to repeat themselves every time
        // they create a new message. We'll just go ahead and push this address.
        if (! empty($this->from)) {
            $message->from($this->from);
        }

        // If a global asFlash value has been specified we will set it on every message
        // instance so the developer does not have to repeat themselves every time
        // they create a new message. We'll just go ahead and use this value.
        if (! empty($this->asFlash)) {
            $message->asFlash($this->asFlash);
        }

        // If a global callback URI has been specified we will set it on every message
        // instance so the developer does not have to repeat themselves every time
        // they create a new message. We'll just go ahead and use this callback URI.
        if (! empty($this->callbackURI)) {
            $message->callbackURI($this->callbackURI);
        }

        return $message;
    }

    /**
     * Send the Message.
     *
     * @param  Message  $message
     * @return int|null
     */
    protected function sendClientMessage($message)
    {
        return $this->client->send($message, $this->failedRecipients);
    }

    /**
     * Determines if the message can be sent.
     *
     * @param  Message  $message
     * @return bool
     */
    protected function shouldSendMessage($message)
    {
        if (!$this->events) {
            return true;
        }

        return $this->events->until(
            new MessageSending($message)
        ) !== false;
    }

    /**
     * Dispatch the message sent event.
     *
     * @param  Message  $message
     * @return void
     */
    protected function dispatchSentEvent($message)
    {
        if ($this->events) {
            $this->events->dispatch(
                new MessageSent($message)
            );
        }
    }

    /**
     * Get the array of failed recipients.
     *
     * @return array
     */
    public function failures()
    {
        return $this->failedRecipients;
    }

    /**
     * Set the queue manager instance.
     *
     * @param  Queue  $queue
     * @return $this
     */
    public function setQueue(Queue $queue)
    {
        $this->queue = $queue;

        return $this;
    }
}
