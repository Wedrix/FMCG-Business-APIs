<?php

namespace App\SMS;

final class PendingText
{
    /**
     * The texter instance.
     *
     * @var Texter
     */
    protected $texter;

    /**
     * The "to" recipients of the message.
     *
     * @var array
     */
    protected $to = [];

    /**
     * Create a new textablle texter instance.
     *
     * @param  Texter  $texter
     * @return void
     */
    public function __construct(Texter $texter)
    {
        $this->texter = $texter;
    }

    /**
     * Set the recipients of the message.
     *
     * @param  mixed  $users
     * @return $this
     */
    public function to($users)
    {
        $this->to = $users;

        return $this;
    }

    /**
     * Send a new textable message instance.
     *
     * @param  Textable  $textable
     *
     * @return mixed
     */
    public function send(Textable $textable)
    {
        return $this->texter
                    ->send($this->fill($textable));
    }

    /**
     * Send a textable message immediately.
     *
     * @param  Textable  $textable
     * @return mixed
     * @deprecated Use send() instead.
     */
    public function sendNow(Textable $textable)
    {
        return $this->texter
                    ->send($this->fill($textable));
    }

    /**
     * Push the given textable onto the queue.
     *
     * @param  Textable  $textable
     * @return mixed
     */
    public function queue(Textable $textable)
    {
        return $this->texter
                    ->queue($this->fill($textable));
    }

    /**
     * Deliver the queued message after the given delay.
     *
     * @param  \DateTimeInterface|\DateInterval|int  $delay
     * @param  Textable  $textable
     * @return mixed
     */
    public function later($delay, Textable $textable)
    {
        return $this->texter
                    ->later($delay, $this->fill($textable));
    }

    /**
     * Populate the textable with the data.
     *
     * @param  Textable  $textable
     * @return Textable
     */
    protected function fill(Textable $textable)
    {
        return $textable->to($this->to);
    }
}
