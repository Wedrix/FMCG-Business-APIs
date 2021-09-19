<?php

namespace App\SMS;

use App\SMS\Contracts\Factory as _Factory;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

/**
 * @mixin App\Illuminate\SMS\Texter
 */
class TextManager implements _Factory
{
    /**
     * The application instance.
     *
     * @var \Illuminate\Contracts\Foundation\Application
     */
    protected $app;

    /**
     * The array of resolved texters.
     *
     * @var array
     */
    protected $texters = [];

    /**
     * Create a new Text manager instance.
     *
     * @param  \Illuminate\Contracts\Foundation\Application  $app
     * @return void
     */
    public function __construct($app)
    {
        $this->app = $app;
    }

    /**
     * Get a texter instance by name.
     *
     * @param  string|null  $name
     * @return ATexter
     */
    public function texter($name = null)
    {
        $name = $name ?: $this->getDefaultDriver();

        return $this->texters[$name] = $this->get($name);
    }

    /**
     * Get a texter driver instance.
     *
     * @param  string|null  $driver
     * @return Texter
     */
    public function driver($driver = null)
    {
        return $this->texter($driver);
    }

    /**
     * Attempt to get the texter from the local cache.
     *
     * @param  string  $name
     * @return Texter
     */
    protected function get($name)
    {
        return $this->texters[$name] ?? $this->resolve($name);
    }

    /**
     * Resolve the given texter.
     *
     * @param  string  $name
     * @return Texter
     *
     * @throws \InvalidArgumentException
     */
    protected function resolve($name)
    {
        $config = $this->getConfig($name);

        if (is_null($config)) {
            throw new \InvalidArgumentException("Texter [{$name}] is not defined.");
        }

        // Once we have created the texter instance we will set a container instance
        // on the texter. This allows us to resolve texter classes via containers
        // for maximum testability on said classes instead of passing Closures.
        $texter = new Texter(
            $name,
            $this->createTexterClient($config),
            $this->app['events']
        );

        if ($this->app->bound('queue')) {
            $texter->setQueue($this->app['queue']);
        }

        // Next we will set all of the global values on this texter, which allows
        // for easy unification of all values as well as easy debugging
        // of sent messages.
        foreach (['from', 'to', 'as_flash', 'callback_URI'] as $type) {
            $this->setGlobal($texter, $config, $type);
        }

        return $texter;
    }

    /**
     * Create the Texter client for the given driver.
     *
     * @param  string  $name
     * @param  array  $config
     * @return Client
     */
    protected function createTexterClient(array $config)
    {
        $driver = $config['driver'];

        $driver = new $driver();

        return new Client($driver);
    }

    /**
     * Set a global address on the texter by type.
     *
     * @param  Texter  $texter
     * @param  array  $config
     * @param  string  $type
     * @return void
     */
    protected function setGlobal($texter, array $config, string $type)
    {
        $value = Arr::get($config, $type, $this->app['config']['sms.'.$type]);

        $texter->{'always'.Str::studly($type)}($value);
    }

    /**
     * Get the sms connection configuration.
     *
     * @param  string  $name
     * @return array
     */
    protected function getConfig(string $name)
    {
        // Here we will check if the "driver" key exists and if it does we will use
        // the entire sms configuration file as the "driver" config in order to
        // provide "BC" for any Laravel <= 6.x style sms configuration files.
        return $this->app['config']['sms.driver']
            ? $this->app['config']['sms']
            : $this->app['config']["sms.texters.{$name}"];
    }

    /**
     * Get the default texter driver name.
     *
     * @return string
     */
    public function getDefaultDriver()
    {
        // Here we will check if the "driver" key exists and if it does we will use
        // that as the default driver in order to provide support for old styles
        // of the Laravel sms configuration file for backwards compatibility.
        return $this->app['config']['sms.driver'] ??
            $this->app['config']['sms.default'];
    }

    /**
     * Set the default sms driver name.
     *
     * @param  string  $name
     * @return void
     */
    public function setDefaultDriver(string $name)
    {
        if ($this->app['config']['sms.driver']) {
            $this->app['config']['sms.driver'] = $name;
        }

        $this->app['config']['sms.default'] = $name;
    }

    /**
     * Dynamically call the default driver instance.
     *
     * @param  string  $method
     * @param  array  $parameters
     * @return mixed
     */
    public function __call($method, $parameters)
    {
        return $this->texter()->$method(...$parameters);
    }
}
