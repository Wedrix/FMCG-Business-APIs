<?php

namespace App\SMS;

use Illuminate\Support\Facades\Facade;

/**
 * @method static \App\SMS\PendingText to($users)
 * @method static void send(\App\SMS\Textable|string|array $view, array $data = [], \Closure|string $callback = null)
 * @method static array failures()
 * @method static mixed queue(\App\SMS\Textable|string|array $view, string $queue = null)
 * @method static mixed later(\DateTimeInterface|\DateInterval|int $delay, \App\SMS\Textable|string|array $view, string $queue = null)
 *
 */
class SMS extends Facade
{
    /**
     * Get the registered name of the component.
     *
     * @return string
     */
    protected static function getFacadeAccessor()
    {
        return 'sms.manager';
    }
}
