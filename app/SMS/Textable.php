<?php

namespace App\SMS;

use App\SMS\Contracts\Textable as __;
use Illuminate\Container\Container;
use Illuminate\Contracts\Queue\Factory as Queue;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Traits\ForwardsCalls;

class Textable implements __
{
    use ForwardsCalls;

    /**
     * The text message.
     *
     * @var string
     */
    public $message;

    /**
     * The person the message is from.
     *
     * @var string
     */
    public $from;

    /**
     * The asFlash value of the message.
     *
     * @var bool
     */
    public $asFlash;

    /**
     * The callback URI of the message.
     *
     * @var string
     */
    public $callbackURI;

    /**
     * The "to" recipients of the message.
     *
     * @var array
     */
    public $to = [];

    /**
     * The name of the texter that should send the message.
     *
     * @var string
     */
    public $texter;

    /**
     * Send the message using the given texter.
     *
     * @param  TextManager|Texter  $texter
     * @return void
     */
    public function send($texter)
    {
        Container::getInstance()->call([$this, 'build']);

        $texter = $texter instanceof TextManager
                        ? $texter->texter($this->texter)
                        : $texter;

        return $texter->send($this->message, function ($message) {
            $this->buildFrom($message)
                 ->buildRecipients($message)
                 ->buildAsFlash($message)
                 ->buildCallbackURI($message);
        });
    }

    /**
     * Queue the message for sending.
     *
     * @param  \Illuminate\Contracts\Queue\Factory  $queue
     * @return mixed
     */
    public function queue(Queue $queue)
    {
        if (isset($this->delay)) {
            return $this->later($this->delay, $queue);
        }

        $connection = property_exists($this, 'connection') ? $this->connection : null;

        $queueName = property_exists($this, 'queue') ? $this->queue : null;

        return $queue->connection($connection)->pushOn(
            $queueName ?: null, $this->newQueuedJob()
        );
    }

    /**
     * Deliver the queued message after the given delay.
     *
     * @param  \DateTimeInterface|\DateInterval|int  $delay
     * @param  \Illuminate\Contracts\Queue\Factory  $queue
     * @return mixed
     */
    public function later($delay, Queue $queue)
    {
        $connection = property_exists($this, 'connection') ? $this->connection : null;

        $queueName = property_exists($this, 'queue') ? $this->queue : null;

        return $queue->connection($connection)->laterOn(
            $queueName ?: null, $delay, $this->newQueuedJob()
        );
    }

    /**
     * Make the queued textable job instance.
     *
     * @return mixed
     */
    protected function newQueuedJob()
    {
        return new SendQueuedTextable($this);
    }

    /**
     * Add the sender to the message.
     *
     * @param  Message  $message
     * @return $this
     */
    protected function buildFrom($message)
    {
        if (! empty($this->from)) {
            $message->from($this->from);
        }

        return $this;
    }

    /**
     * Add all of the recipients to the message.
     *
     * @param  Message  $message
     * @return $this
     */
    protected function buildRecipients($message)
    {
        foreach ($this->to as $recipient) {
            $message->to($recipient);
        }

        return $this;
    }


    /**
     * Add the textable's asFlash value to the message.
     *
     * @param  Message  $message
     * @return $this
     */
    protected function buildAsFlash($message)
    {
        if (! empty($this->asFlash)) {
            $message->asFlash($this->asFlash);
        }

        return $this;
    }


    /**
     * Add the textable's callback URI to the message.
     *
     * @param  Message  $message
     * @return $this
     */
    protected function buildCallbackURI($message)
    {
        if (! empty($this->callbackURI)) {
            $message->callbackURI($this->callbackURI);
        }

        return $this;
    }

    /**
     * Add the message to the textable
     * 
     * @param string $message
     * @return $this
     */
    protected function message($message)
    {
        $this->message = $message; 

        return $this;
    }

    /**
     * Set the sender of the message.
     *
     * @param  string  $name
     * @return $this
     */
    public function from(string $name)
    {
        $this->from = $name;

        return $this;
    }

    /**
     * Set the recipients of the message.
     *
     * @param  object|string  $phone
     * @return $this
     */
    public function to($phone)
    {
        return $this->setRecipients($phone);
    }

    /**
     * Set the asFalsh value of the message.
     *
     * @param  bool  $asFlash
     * @return $this
     */
    public function asFlash(bool $asFlash)
    {
        $this->asFlash = $asFlash;

        return $this;
    }

    /**
     * Set the callbackURI of the message.
     *
     * @param  string  $callbackURI
     * @return $this
     */
    public function callbackURI(string $callbackURI)
    {
        $this->callbackURI = $callbackURI;

        return $this;
    }

    /**
     * Determine if the given recipient is set on the textable.
     *
     * @param  string  $name
     * @return bool
     */
    public function hasFrom(string $name)
    {
        return $this->from == $name;
    }

    /**
     * Determine if the given recipient is set on the textable.
     *
     * @param  object|string  $phone
     * @return bool
     */
    public function hasTo($phone)
    {
        return $this->hasRecipient($phone);
    }

    /**
     * Set the recipients of the message.
     *
     * The 'phone' property on the object will be used
     *
     * @param  object|string  $phone
     * @param  string  $property
     * @return $this
     */
    protected function setRecipients($phone)
    {
        foreach ($this->phonesToArray($phone) as $recipient) {
            $to = $this->normalizeRecipient($recipient);

            array_push($this->to, $to);
        }

        return $this;
    }

    /**
     * Determine if the given recipient is set on the textable.
     *
     * @param  object|string  $phone
     * @return bool
     */
    protected function hasRecipient($phone)
    {
        $expected = $this->normalizeRecipient(
            $this->phonesToArray($phone)[0]
        );

        return in_array($expected, $this->to);
    }

    /**
     * Convert the given recipient arguments to an array.
     *
     * @param  object|array|string  $address
     * @param  string|null  $name
     * @return array
     */
    protected function phonesToArray($phone)
    {
        if (! is_array($phone) && ! $phone instanceof Collection) {
            $phone = [$phone];
        }

        return $phone;
    }

    /**
     * Get the to values from the recipients.
     * 
     * @param mixed $recipient
     * @return string
     * @throws \InvalidArgumentException
     */
    protected function normalizeRecipient($recipient)
    {
        if (is_string($recipient)) {
            return $recipient;
        }

        if ($recipient instanceof Model) {
            if (method_exists($recipient, 'routeSMSValue')) {
                return $recipient->routeSMSValue();
            }
        }

        if (is_object($recipient)) {
            if ($recipient->phone) {
                return $recipient->phone;
            }
        }

        throw new \InvalidArgumentException('Could not retrieve a "phone" value from this object');
    }

    /**
     * Set the name of the texter that should send the message.
     *
     * @param  string  $texter
     * @return $this
     */
    public function texter($texter)
    {
        $this->texter = $texter;

        return $this;
    }

    /**
     * Apply the callback's message changes if the given "value" is true.
     *
     * @param  mixed  $value
     * @param  callable  $callback
     * @param  mixed  $default
     * @return mixed|$this
     */
    public function when($value, $callback, $default = null)
    {
        if ($value) {
            return $callback($this, $value) ?: $this;
        } 
        else if ($default) {
            return $default($this, $value) ?: $this;
        }

        return $this;
    }
}
