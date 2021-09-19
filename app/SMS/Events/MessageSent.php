<?php

namespace App\SMS\Events;

final class MessageSent
{
    /**
     * The Swift message instance.
     *
     * @var App\SMS\Message
     */
    public $message;

    /**
     * Create a new event instance.
     *
     * @param  App\SMS\Message  $message
     * @return void
     */
    public function __construct($message)
    {
        $this->message = $message;
    }
}
