<?php

namespace App\SMS\Contracts;

interface Texter
{
    /**
     * Begin the process of texting a textable class instance.
     *
     * @param  mixed  $users
     * @return App\SMS\PendingText
     */
    public function to($users);

    /**
     * Send a new message.
     *
     * @param  string  $text
     * @param  mixed  $callback
     * @return void
     */
    public function send($text, $callback);

    /**
     * Get the array of failed recipients.
     *
     * @return array
     */
    public function failures();
}
