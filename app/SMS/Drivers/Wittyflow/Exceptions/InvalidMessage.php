<?php

namespace App\SMS\Drivers\Wittyflow\Exceptions;

class InvalidMessage extends \Exception
{
    public static function recipientsNotSetError()
    {
        return new static('A Recipient is not set on the message');
    }

    public static function senderNotSetError()
    {
        return new static('A Sender is not set on the message');
    }

    public static function messageNotSetError()
    {
        return new static('The Message content is empty');
    }
}
