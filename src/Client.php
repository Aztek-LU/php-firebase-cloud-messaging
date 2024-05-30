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
     * generates a base64 encoded string for the google servers
     *
     * @param string $text
     *
     * @return string
     */
    private function base64URLEncode($text)
    {
        return str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($text));
    }

    /**
     * generates a JWT token for the google servers
     *
     * @param string $issuer
     * @param string $privateKey
     *
     * @return string
     */
    private function generateJWT($issuer, $privateKey)
    {
        $header = json_encode([
            'alg' => 'RS256',
            'typ' => 'JWT',
        ]);

        $nowSeconds = time();

        $payload = json_encode([
            'iss' => $issuer,
            'scope' => 'https://www.googleapis.com/auth/firebase.messaging',
            'aud' => 'https://www.googleapis.com/oauth2/v4/token',
            'exp' => $nowSeconds + 3600,
            'iat' => $nowSeconds,
        ]);

        $signature = '';

        $jwt = $this->base64URLEncode($header) . '.' . $this->base64URLEncode($payload);
        openssl_sign($jwt, $signature, $privateKey, 'sha256WithRSAEncryption');

        $jwt .= '.' . $this->base64URLEncode($signature);

        return $jwt;
    }

    /**
     * fetches an access token from the google servers
     */
    public function fetchAccessToken($issuer, $privateKey)
    {
        $jwt = $this->generateJWT($issuer, $privateKey);

        $response = $this->guzzleClient->post(
            'https://www.googleapis.com/oauth2/v4/token',
            [
                'form_params' => [
                    'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                    'assertion' => $jwt
                ]
            ]
        );

        $body = json_decode($response->getBody());
        $this->accessToken = $body->access_token;
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

        // FCM HTTP V1 does not support sending notifications to multiple devices (supported only in legacy API via registration_tokens
        // So, Adding topic subscription, sending notification to topic, then remove topic subscription
        $recipients = $message->getRecipients();
        if (count($recipients) > 1) {
            $tokens = [];
            foreach ($recipients as $recipient) {
                $tokens[] = $recipient->getToken();
            }
            $topic = "Topic_".date("YmdHis")."_".substr(md5(rand()), 0, 4);
            $response = $this->addTopicSubscription($topic, $tokens);
            if ($response->getStatusCode() == 200) {
                $param['message']->clearRecipients();
                $param['message']->addRecipient(new Recipient\Topic($topic));

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

                if ($output->getStatusCode() == 200) {
                    $this->removeTopicSubscription($topic, $tokens);
                }
            }
            else {
                $output = $response;
            }
        } else {
            var_dump(__LINE__, json_encode($param));
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
                    'Authorization' => sprintf('Bearer %s', $this->accessToken),
                    'access_token_auth' => true,
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
