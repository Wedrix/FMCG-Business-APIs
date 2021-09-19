<?php

namespace App\SMS\Drivers\Wittyflow\Exceptions;

class InvalidConfiguration extends \Exception
{
    public static function apiKeyNotSet()
    {
        return new static('Wittyflow API key not set');
    }

    public static function apiSecretNotSet()
    {
        return new static ('Wittyflow API secret not set');
    }

    public static function baseURINotSet()
    {
        return new static ('Wittyflow API base URI not set');
    }

    public static function sendMessageEndpointNotSet()
    {
        return new static ('Wittyflow API send message endpoint not set');
    }
}
