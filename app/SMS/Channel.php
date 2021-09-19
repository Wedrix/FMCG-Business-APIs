<?php

namespace App\SMS;

use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\App;

class Channel
{
    /**
     * The texter implementation.
     *
     * @var TextManager
     */
    protected $texter;

    /**
     * Create a new SMS channel instance.
     *
     * @param  TextManager  $texter
     * @return void
     */
    public function __construct()
    {
        $this->texter = App::make('sms.manager');
    }

    /**
     * Send the given notification.
     *
     * @param  mixed  $notifiable
     * @param  \Illuminate\Notifications\Notification  $notification
     * @return void
     */
    public function send($notifiable, Notification $notification)
    {
        $message = $notification->toSMS($notifiable);

        if (!$notifiable->routeNotificationFor('SMS', $notification) &&
            !$message instanceof Textable) {
            return;
        }

        if ($message instanceof Textable) {
            return $message->send($this->texter);
        }

        $this->texter->send(
            $message->content,
            $this->messageBuilder($notifiable, $notification, $message)
        );
    }

    /**
     * Get the texter Closure for the message.
     *
     * @param  mixed  $notifiable
     * @param  \Illuminate\Notifications\Notification  $notification
     * @param  Message  $message
     * @return \Closure
     */
    protected function messageBuilder($notifiable, $notification, $message)
    {
        return function ($textMessage) use ($notifiable, $notification, $message) {
            $this->buildMessage($textMessage, $notifiable, $notification, $message);
        };
    }

    /**
     * Build the text message.
     *
     * @param  Message  $textMessage
     * @param  mixed  $notifiable
     * @param  \Illuminate\Notifications\Notification  $notification
     * @param  Message  $message
     * @return void
     */
    protected function buildMessage($textMessage, $notifiable, $notification, $message)
    {
        $textMessage->to($this->getRecipient($notifiable, $notification));

        if (!empty($message->from)) {
            $textMessage->from($message->from);
        }

        if (!empty($message->asFlash)) {
            $textMessage->asFlash($message->asFlash);
        }

        if (!empty($message->callbackURI)) {
            $textMessage->callbackURI($message->callbackURI);
        }
    }

    /**
     * Get the recipients of the given message.
     *
     * @param  mixed  $notifiable
     * @param  \Illuminate\Notifications\Notification  $notification
     * @return mixed
     */
    protected function getRecipient($notifiable, $notification)
    {
        if (is_string($recipient = $notifiable->routeNotificationFor('SMS', $notification))) {
            return $recipient;
        }

        return $notifiable->contact_phone;
    }
}
