<?php

namespace App\SMS;

use App\SMS\Contracts\Driver;

final class Client 
{
    /**
     * The Client Driver instance.
     *
     * @var Driver
     */
    protected $driver;

    /**
     * The Recipients of the message.
     * 
     * @var array
     */
    protected $to;
    
    public function __construct(Driver $driver) {
        $this->driver = $driver;
    }
    
    public function send(Message $message, array &$failedRecipients) {
        $failedRecipients = $this->driver->send($message);
    }
}
