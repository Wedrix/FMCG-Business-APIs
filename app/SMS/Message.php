<?php

namespace App\SMS;

class Message 
{
    /**
     * Sender's phone number.
     * @var string
     */
    public $from;

    /**
     * Recipients phone numbers.
     * @var array
     */
    public $to = [];

    /**
     * Wether to send the message as a flash message.
     * @var bool
     */
    public $asFlash;

    /**
     * Webhook url to post status reports to.
     * @var bool
     */
    public $callbackURI;

    /**
     * Message content.
     * @var string
     */
    public $content;

    /**
     * Set the message sender's phone number or name.
     * 
     * @param string $from
     * @return $this
     */
    public function from($from)
    {
        $this->from = $from;

        return $this;
    }

    /**
     * Set the recipients' phone numbers.
     * 
     * @param array $to
     * @return $this
     */
    public function to($to, $override = false)
    {
        if ($override) {
            $this->to = $to;

            return $this;
        }

        return $this->addTo($to);
    }

    /**
     * Add a recipient's phone number.
     * 
     * @param string $to
     * @return $this
     */
    public function addTo($to) {
        array_push($this->to, $to);

        return $this;
    }

    /**
     * Set the message content.
     * 
     * @param string $content
     * @return $this
     */
    public function content($content)
    {
        $this->content = $content;

        return $this;
    }

    /**
     * Set callback URI.
     * 
     * @param string $callbackURI
     * @return $this
     */
    public function callbackURI($callbackURI)
    {
        $this->callbackURI = $callbackURI;

        return $this;
    }

    /**
     * Set the message asFlash value.
     * 
     * @param bool $asFlash
     * @return $this
     */
    public function asFlash($asFlash)
    {
        $this->asFlash = $asFlash;

        return $this;
    }
}
