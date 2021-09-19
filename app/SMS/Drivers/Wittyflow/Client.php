<?php

namespace App\SMS\Drivers\Wittyflow;

use App\SMS\Contracts\Driver;
use App\SMS\Message as GenericMessage;
use App\SMS\Drivers\Wittyflow\Exceptions\InvalidMessage;
use App\SMS\Drivers\Wittyflow\Exceptions\InvalidConfiguration;
use Illuminate\Support\Facades\Http;
use Illuminate\Http\Client\RequestException;

class Client implements Driver
{
    /**
     * The app_id security key.
     * 
     * @var string
     */
    protected $appId;

    /**
     * The app_secret security key.
     * 
     * @var string
     */
    protected $appSecret;

    /**
     * The request's base URI.
     * 
     * @var string
     */
    protected $baseURI;

    /**
     * The send endpoint.
     * 
     * @var string
     */
    protected $sendEndpoint;

    /**
     * The recipients phone numbers.
     * 
     * @var array
     */
    protected $recipients = [];

    /**
     * The Wittyflow Message instance.
     * 
     * @var App\SMS\Drivers\Wittyflow\Message
     */
    protected $message;

    /**
     * Create a new Wittyflow Client instance.
     * 
     * @return void
     */
    public function __construct()
    {
        $this->validateConfig(config('services.wittyflow'));

        $this->appId = config('services.wittyflow.app_id');
        $this->appSecret = config('services.wittyflow.app_secret');
        $this->baseURI = config('services.wittyflow.base_uri');
        $this->sendEndpoint = config('services.wittyflow.send_message_endpoint');

        $this->message = new Message();
    }

    /**
     * Send a Generic Message instance.
     * 
     * @param App\SMS\Message  $message
     * @return array
     */
    public function send(GenericMessage $message)
    {
        return $this->parseGenericMessage($message)
                    ->sendGenericMessage();
    }

    /**
     * Set the Generic Message instance.
     * 
     * @param App\SMS\Message  $message
     * @return $this
     */
    protected function parseGenericMessage(GenericMessage $message)
    {
        if (!isset($message->from) || is_null($message->from)) {
            throw InvalidMessage::senderNotSetError();
        }

        if (!isset($message->to) || is_null($message->to)) {
            throw InvalidMessage::recipientsNotSetError();
        }

        if (!isset($message->content) || is_null($message->content)) {
            throw InvalidMessage::messageNotSetError();
        }
        
        $this->message->from = $message->from;

        $this->recipients = $message->to;

        $this->message->message = $message->content;

        $this->message->type = $message->asFlash ? 0 : 1;

        return $this;
    }

    /**
     * Send the Generic Message instance.
     * 
     * @return array
     */
    protected function sendGenericMessage()
    {
        $failedRecipients = [];

        foreach ($this->recipients as $recipient) {
            try {
                $this->textRecipient($recipient);
            } catch (RequestException $exception) {
                array_push($failedRecipients, $recipient);

                continue;
            }
        }

        return $failedRecipients;
    }

    /**
     * Send the Wittflow Message instance to the recipient.
     * 
     * @param string  $recipient
     * @return Illuminate\Http\Client\Response
     */
    protected function textRecipient($recipient)
    {
        $this->setRecipient($recipient);

        $response = Http::get($this->baseURI.$this->sendEndpoint.$this->buildRequestParams());
       
        $response->throw();

        return $response;
    }

    /**
     * Set the recipient of the Text message.
     * 
     * @param string  $recipient
     * @return $this
     */
    protected function setRecipient($recipient)
    {
        $this->message->to = $this->formatPhoneNumber($recipient);

        return $this;
    }

    /**
     * Format recipient phone number (from ei64 standard)
     * 
     * @param string $recipient
     * @return string
     */
    protected function formatPhoneNumber($recipent)
    {
        return substr($recipent,1);
    }

    /**
     * Build the request's parameters.
     * 
     * @return string
     */
    protected function buildRequestParams()
    {
        $params = ['app_id' => $this->appId, 'app_secret' => $this->appSecret];

        foreach (get_object_vars($this->message) as $property => $value) {
            if (! is_null($value)) {
                $params[$property] = $value;
            }
        }

        return '?'.http_build_query($params);
    }

    /**
     * Validate the services configuration
     * 
     * @param array  $config
     * @return void
     * @throws App\SMS\Drivers\Wittyflow\Exceptions\InvalidConfiguration
     */
    protected function validateConfig($config)
    {
        if (is_null($config['app_id'])) {
            throw InvalidConfiguration::apiKeyNotSet();
        }

        if (is_null($config['app_secret'])) {
            throw InvalidConfiguration::apiSecretNotSet();
        }

        if (is_null($config['base_uri'])) {
            throw InvalidConfiguration::baseURINotSet();
        }

        if (is_null($config['send_message_endpoint'])) {
            throw InvalidConfiguration::sendMessageEndpointNotSet();
        }

        return $this;
    }
}
