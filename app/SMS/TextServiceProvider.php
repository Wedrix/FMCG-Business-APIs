<?php

namespace App\SMS;

use App\SMS\Contracts\Factory;
use Illuminate\Contracts\Support\DeferrableProvider;
use Illuminate\Support\ServiceProvider;

class TextServiceProvider extends ServiceProvider implements DeferrableProvider
{
    /**
     * Register the service provider.
     *
     * @return void
     */
    public function register()
    {
        $this->registerIlluminateTexter();
    }

    /**
     * Register the Illuminate texter instance.
     *
     * @return void
     */
    protected function registerIlluminateTexter()
    {
        $this->app->singleton('sms.manager', function ($app) {
            return new TextManager($app);
        });

        $this->app->alias('sms.manager', Factory::class);

        $this->app->bind('texter', function ($app) {
            return $app->make('sms.manager')->texter();
        });
    }

    /**
     * Get the services provided by the provider.
     *
     * @return array
     */
    public function provides()
    {
        return [
            'sms.manager',
            'texter'
        ];
    }
}
