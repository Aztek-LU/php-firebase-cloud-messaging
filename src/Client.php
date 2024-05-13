<?php
namespace Vanderbilt\PhpFirebaseCloudMessaging;

use GuzzleHttp;

/**
 * @author Vanderbilt
 */
class Client implements ClientInterface
{
    const DEFAULT_API_URL = 'https://fcm.googleapis.com/fcm/send';
    const DEFAULT_TOPIC_ADD_SUBSCRIPTION_API_URL = 'https://iid.googleapis.com/iid/v1:batchAdd';
    const DEFAULT_TOPIC_REMOVE_SUBSCRIPTION_API_URL = 'https://iid.googleapis.com/iid/v1:batchRemove';

    private $apiKey;
    private $accessToken;
    private $proxyApiUrl;
    private $guzzleClient;

    public function injectGuzzleHttpClient(GuzzleHttp\ClientInterface $client)
    {
        $this->guzzleClient = $client;
    }

    /**
     * add your server api key here
     * read how to obtain an api key here: https://firebase.google.com/docs/server/setup#prerequisites
     *
     * @param string $apiKey
     *
     * @return \Vanderbilt\PhpFirebaseCloudMessaging\Client
     */
    public function setApiKey($apiKey)
    {
        $this->apiKey = $apiKey;
        return $this;
    }

    /**
     * people can overwrite the api url with a proxy server url of their own
     *
     * @param string $url
     *
     * @return \Vanderbilt\PhpFirebaseCloudMessaging\Client
     */
    public function setProxyApiUrl($url)
    {
        $this->proxyApiUrl = $url;
        return $this;
    }

    /**
     * add your server api token here
     *
     * @param string $accessToken
     *
     * @return \Vanderbilt\PhpFirebaseCloudMessaging\Client
     */
    public function setAccessToken($accessToken)
    {
        $this->accessToken = $accessToken;
        return $this;
    }
    /**
     * sends your notification to the google servers and returns a guzzle repsonse object
     * containing their answer.
     *
     * @param Message $message
     *
     * @return \Psr\Http\Message\ResponseInterface
     * @throws \GuzzleHttp\Exception\RequestException
     */
    public function send(Message $message)
    {
        /*return $this->guzzleClient->post(
            $this->getApiUrl(),
            [
                'headers' => [
                    'Authorization' => sprintf('key=%s', $this->apiKey),
                    'Content-Type' => 'application/json'
                ],
                'body' => json_encode($message)
            ]
        );*/
        return $this->guzzleClient->post(
            "https://fcm.googleapis.com/v1/projects/test-project-8d27a/messages:send",
            [
                'headers' => [
                    'Authorization' => sprintf('Bearer %s', $this->accessToken),
                    'Content-Type' => 'application/json'
                ],
                'body' => '{"message":{"token":"eeCzYLdmQWu2Tp2gpp3KWs:APA91bGhZZJd80CNlppVuKQghe5y7JRs3BgEHjA16LmmWDhxvPCDbn7B8W2rdiC1x9ZQKGWEEriIlooJJcPPRS3vtZIr9gjpHpEyGvW32PpONzxNax5Ga0NKx9tT1vDDuSGJqPdiXhDF","notification":{"body":"This is an FCM notification message!","title":""}}}'
            ]
        );
    }

    /**
     * @param integer $topic_id
     * @param array|string $recipients_tokens
     *
     * @return \Psr\Http\Message\ResponseInterface
     */
    public function addTopicSubscription($topic_id, $recipients_tokens)
    {
        return $this->processTopicSubscription($topic_id, $recipients_tokens, self::DEFAULT_TOPIC_ADD_SUBSCRIPTION_API_URL);
    }


    /**
     * @param integer $topic_id
     * @param array|string $recipients_tokens
     *
     * @return \Psr\Http\Message\ResponseInterface
     */
    public function removeTopicSubscription($topic_id, $recipients_tokens)
    {
        return $this->processTopicSubscription($topic_id, $recipients_tokens, self::DEFAULT_TOPIC_REMOVE_SUBSCRIPTION_API_URL);
    }


    /**
     * @param integer $topic_id
     * @param array|string $recipients_tokens
     * @param string $url
     *
     * @return \Psr\Http\Message\ResponseInterface
     */
    protected function processTopicSubscription($topic_id, $recipients_tokens, $url)
    {
        if (!is_array($recipients_tokens))
            $recipients_tokens = [$recipients_tokens];

        return $this->guzzleClient->post(
            $url,
            [
                'headers' => [
                    'Authorization' => sprintf('key=%s', $this->apiKey),
                    'Content-Type' => 'application/json'
                ],
                'body' => json_encode([
                    'to' => '/topics/' . $topic_id,
                    'registration_tokens' => $recipients_tokens,
                ])
            ]
        );
    }


    private function getApiUrl()
    {
        return isset($this->proxyApiUrl) ? $this->proxyApiUrl : self::DEFAULT_API_URL;
    }
}