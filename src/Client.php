<?php
namespace Vanderbilt\PhpFirebaseCloudMessaging;

use GuzzleHttp;

/**
 * @author Vanderbilt
 */
class Client implements ClientInterface
{
    const DEFAULT_API_URL = 'https://fcm.googleapis.com/fcm/send';
    const HTTPV1_API_URL_PREFIX = 'https://fcm.googleapis.com/v1/projects/';
    const HTTPV1_API_URL_POSTEFIX = '/messages:send';
    const DEFAULT_TOPIC_ADD_SUBSCRIPTION_API_URL = 'https://iid.googleapis.com/iid/v1:batchAdd';
    const DEFAULT_TOPIC_REMOVE_SUBSCRIPTION_API_URL = 'https://iid.googleapis.com/iid/v1:batchRemove';

    private $apiKey;
    private $accessToken;
    private $projectId;
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
     * add your server access token here
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
        $param = ['message' => $message];

        $recipients = $message->getRecipients();
        if (count($recipients) > 1) {
            $json = json_encode($message);
            $messageArr = json_decode($json, true);
            if (isset($messageArr['registration_ids'])) {
                $registration_ids = $messageArr['registration_ids'];
                unset($messageArr['registration_ids']);
                foreach ($registration_ids as $recipient_token) {
                    $messageArr['token'] = $recipient_token;
                    unset($messageArr['registration_ids']);
                    $param = ['message' => $messageArr];
                    $output = $this->guzzleClient->post(
                                    $this->getHTTPV1ApiUrl(),
                                    [
                                        'headers' => [
                                            'Authorization' => sprintf('Bearer %s', $this->accessToken),
                                            'Content-Type' => 'application/json'
                                        ],
                                        'body' => json_encode($param)
                                    ]
                    );
                }
            }
        } else {
            return $this->guzzleClient->post(
                $this->getHTTPV1ApiUrl(),
                [
                    'headers' => [
                        'Authorization' => sprintf('Bearer %s', $this->accessToken),
                        'Content-Type' => 'application/json'
                    ],
                    'body' => json_encode($param)
                ]
            );
        }
        return $output;

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

    /**
     * add your project ID
     *
     * @param string $projectId
     *
     * @return \Vanderbilt\PhpFirebaseCloudMessaging\Client
     */
    public function setProjectId($projectId)
    {
        $this->projectId = $projectId;
    }
    private function getHTTPV1ApiUrl()
    {
        return self::HTTPV1_API_URL_PREFIX.$this->getProjectId().self::HTTPV1_API_URL_POSTEFIX;
    }

    public function getProjectId()
    {
        return $this->projectId;
    }
}