<?php

namespace App\SMS\Contracts;

use App\SMS\Message;

interface Driver
{
    /**
     * Send a Message instance
     * 
     * @param Message $message
     * @return array
     */
    public function send(Message $message);
}
