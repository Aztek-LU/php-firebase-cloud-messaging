<?php
namespace Vanderbilt\PhpFirebaseCloudMessaging\Recipient;

use Vanderbilt\PhpFirebaseCloudMessaging\Message;

class Device extends Recipient
{
    private $token;
    private $android;

    public function __construct($token)
    {
        $this->token = $token;
        return $this;
    }

    public function getToken()
    {
        return $this->token;
    }
    public function setMessagePriority(Message $message)
    {
        $this->android = $message;
    }
    public function getMessagePriority()
    {
        return $this->android;
    }

}