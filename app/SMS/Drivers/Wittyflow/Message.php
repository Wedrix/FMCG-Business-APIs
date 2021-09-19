<?php

namespace App\SMS\Drivers\Wittyflow;

class Message
{
    /**
     * Sender's phone number.
     * @var string
     */
    public $from;

    /**
     * Recipient phone number.
     * @var string
     */
    public $to;

    /**
     * Message.
     * @var string
     */
    public $message;

    /**
     *Webhook url to post status reports to.
     *@var bool
     */
    public $callback_uri;

    /**
     * Indicates the type of message to be sent. 0 flash, 1 plain text.
     *@var int
     */
    public $type;

    public function __construct($from = null, $to = null, $message = null, $type = 1)
    {
        $this->from = $from;
        $this->to = $to;
        $this->message = $message;
        $this->type = $type;
    }

    /**
     * Set the message sender's phone number.
     * @param string $from
     * @return $this
     */
    public function from($from)
    {
        $this->from = $from;

        return $this;
    }

    /**
     * Set the recipient's phone number.
     * @param string $to
     * @return $this
     */
    public function to($to)
    {
        $this->to = $to;

        return $this;
    }

    /**
     * Set the message message.
     * @param string $message
     * @return $this
     */
    public function message($message)
    {
        $this->message = $message;

        return $this;
    }

    /**
     *Set callback url
     * @return $this
     */
    public function callbackUri($callbackUri)
    {
        $this->callback_uri = $callbackUri;

        return $this;
    }

    /**
     * Set the message type.
     * @param int $type
     * @return $this
     */
    public function type($type)
    {
        $this->type = $type;

        return $this;
    }
}
