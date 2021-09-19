<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Defaul Texter
    |--------------------------------------------------------------------------
    |
    | This option controls the default texter that is used to send any text message
    | messages sent by your application. Alternative texters may be setup
    | and used as needed; however, this texter will be used by default.
    |
    */

    'default' => env('SMS_TEXTER', 'wittyflow'),

    /*
    |--------------------------------------------------------------------------
    | Texter Configurations
    |--------------------------------------------------------------------------
    |
    | Here you may configure all of the texters used by your application plus
    | their respective settings. Several examples have been configured for
    | you and you are free to add your own as your application requires.
    |
    | Laravel supports a variety of text drivers to be used while
    | sending a text message. You will specify which one you are using for your
    | texters below. You are free to add additional texters as required.
    |
    | Supported: "wittyflow"
    |
    */

    'texters' => [
        'wittyflow' => [
            'driver' => \App\SMS\Drivers\Wittyflow\Client::class,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Global "From" Name
    |--------------------------------------------------------------------------
    |
    | You may wish for all text messages sent by your application to be sent from
    | the same name. Here, you may specify a name that is
    | used globally for all texts that are sent by your application.
    |
    */

    'from' => env('SMS_FROM','Eben Gen'),

    /*
    |--------------------------------------------------------------------------
    | Global "To" Phone
    |--------------------------------------------------------------------------
    |
    | You may wish for all text messages sent by your application to be sent to
    | the same number. Here, you may specify a number that is
    | used globally for all texts that are sent by your application.
    |
    */

    'to' => env('SMS_TO'),

    /*
    |--------------------------------------------------------------------------
    | Global SMS as Flash
    |--------------------------------------------------------------------------
    |
    | You may wish for all text messages sent by your application to be sent as
    | a flash message. Here, you may specify that it is
    | used globally for all texts that are sent by your application.
    |
    */

    'as_flash' => env('SMS_AS_FLASH', false),

    /*
    |--------------------------------------------------------------------------
    | Global SMS Callback URI
    |--------------------------------------------------------------------------
    |
    | You may wish for all text messages sent by your application to use the same
    | callback link. Here, you may specify a callback URI that is
    | used globally for all texts that are sent by your application.
    |
    */

    'callback_URI' => env('SMS_CALLBACK_URI'),
];
