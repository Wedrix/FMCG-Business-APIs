<?php

namespace App\SMS\Contracts;

use Illuminate\Contracts\Queue\Factory as Queue;

interface Textable
{
    /**
     * Send the message using the given texter.
     *
     * @param  Factory|Texter  $texter
     * @return void
     */
    public function send($texter);

    /**
     * Queue the given message.
     *
     * @param  \Illuminate\Contracts\Queue\Factory  $queue
     * @return mixed
     */
    public function queue(Queue $queue);

    /**
     * Deliver the queued message after the given delay.
     *
     * @param  \DateTimeInterface|\DateInterval|int  $delay
     * @param  \Illuminate\Contracts\Queue\Factory  $queue
     * @return mixed
     */
    public function later($delay, Queue $queue);

    /**
     * Set the recipients of the message.
     *
     * @param  object|array|string  $phone
     * @return $this
     */
    public function to($phone);

    /**
     * Set the name of the texter that should be used to send the message.
     *
     * @param  string  $texter
     * @return $this
     */
    public function texter($texter);
}
