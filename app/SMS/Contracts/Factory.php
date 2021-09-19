<?php

namespace App\SMS\Contracts;

interface Factory
{
    /**
     * Get a texter instance by name.
     *
     * @param  string|null  $name
     * @return App\SMS\Texter
     */
    public function texter($name = null);
}
