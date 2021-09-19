<?php

namespace App\SMS\Contracts;

interface TextQueue
{
    /**
     * Queue a new SMS message for sending.
     *
     * @param  Textable|string|array  $view
     * @param  string|null  $queue
     * @return mixed
     */
    public function queue($view, $queue = null);

    /**
     * Queue a new SMS message for sending after (n) seconds.
     *
     * @param  \DateTimeInterface|\DateInterval|int  $delay
     * @param  Textable|string|array  $view
     * @param  string|null  $queue
     * @return mixed
     */
    public function later($delay, $view, $queue = null);
}
