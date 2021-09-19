<?php

namespace App\SMS;

use App\SMS\Contracts\Factory as TexterFactory;
use App\SMS\Contracts\Textable as TextableContract;

class SendQueuedTextable
{
    /**
     * The textable message instance.
     *
     * @var Textable
     */
    public $textable;

    /**
     * The number of times the job may be attempted.
     *
     * @var int
     */
    public $tries;

    /**
     * The number of seconds the job can run before timing out.
     *
     * @var int
     */
    public $timeout;

    /**
     * Create a new job instance.
     *
     * @param  Textable  $textable
     * @return void
     */
    public function __construct(TextableContract $textable)
    {
        $this->textable = $textable;
        $this->tries = property_exists($textable, 'tries') ? $textable->tries : null;
        $this->timeout = property_exists($textable, 'timeout') ? $textable->timeout : null;
    }

    /**
     * Handle the queued job.
     *
     * @return void
     */
    public function handle(TexterFactory $texter)
    {   
        $this->textable->send($texter);
    }

    /**
     * Get the display name for the queued job.
     *
     * @return string
     */
    public function displayName()
    {
        return get_class($this->textable);
    }

    /**
     * Call the failed method on the textable instance.
     *
     * @param  \Throwable  $e
     * @return void
     */
    public function failed($e)
    {
        if (method_exists($this->textable, 'failed')) {
            $this->textable->failed($e);
        }
    }

    /**
     * Get the retry delay for the textable object.
     *
     * @return mixed
     */
    public function retryAfter()
    {
        if (! method_exists($this->textable, 'retryAfter') && ! isset($this->textable->retryAfter)) {
            return;
        }

        return $this->textable->retryAfter ?? $this->textable->retryAfter();
    }

    /**
     * Prepare the instance for cloning.
     *
     * @return void
     */
    public function __clone()
    {
        $this->textable = clone $this->textable;
    }
}
