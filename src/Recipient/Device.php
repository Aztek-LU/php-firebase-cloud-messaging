<?php
namespace Vanderbilt\PhpFirebaseCloudMessaging\Recipient;

use Vanderbilt\PhpFirebaseCloudMessaging\Message;

class Device extends Recipient
{
    private $token;
    public function __construct($token)
    {
        $this->token = $token;
        return $this;
    }

    public function getToken()
    {
        return $this->token;
    }

}