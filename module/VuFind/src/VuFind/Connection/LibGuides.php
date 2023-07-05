<?php

namespace VuFind\Connection;

use Laminas\Log\LoggerAwareInterface;

// note: for the LibGuides API used by the profiles recommendation
// not the LibGuides search widget used by LibGuides and LibGuidesAZ data sources
class LibGuides implements
    OauthServiceInterface,
    \VuFindHttp\HttpServiceAwareInterface,
    LoggerAwareInterface
{
    use OauthServiceTrait;
    use \VuFindHttp\HttpServiceAwareTrait;
    use \VuFind\Log\LoggerAwareTrait {
        logError as error;
    }

    protected $client;
    protected $baseUrl;
    protected $clientId;
    protected $clientSecret;

    /**
     * Constructor
     *
     * @param \Laminas\Http\Client $client HTTP client
     */
    public function __construct(
        $client,
        $baseUrl,
        $clientId,
        $clientSecret,
        $forceNewConnection = false
    ) {
        $this->client = $client;
        $this->baseUrl = $baseUrl;
        $this->clientId = $clientId;
        $this->clientSecret = $clientSecret;
    }


    // Adapted from OverdriveConnector.
    // TODO: refactor?
    public function getAccounts()
    {
        $tokenData = $this->authenticateWithClientCredentials(
            $this->baseUrl . "/oauth/token",
            $this->clientId,
            $this->clientSecret
        );
        if (!$tokenData){
            return [];
        }

        $headers = [];
        if (
            isset($tokenData->token_type)
            && isset($tokenData->access_token)
        ) {
            $headers[] = "Authorization: {$tokenData->token_type} "
                . $tokenData->access_token;
        }
        $headers[] = "User-Agent: VuFind";

        $this->client->setHeaders($headers);
        $this->client->setMethod("GET");
        $this->client->setUri(
            $this->baseUrl . "/accounts?expand=profile,subjects"
        );
        try {
            // throw new Exception('testException');
            $response = $this->client->send();
        } catch (Exception $ex) {
            $this->error(
                "Exception during request: " .
                $ex->getMessage()
            );
            return [];
        }

        if ($response->isServerError()) {
            $this->error(
                "LibGuides API HTTP Error: " .
                $response->getStatusCode()
            );
            $this->debug("Request: " . $client->getRequest());
            $this->debug("Response: " . $client->getResponse());
            return [];
        }
        $body = $response->getBody();
        $returnVal = json_decode($body);
        $this->debug(
            "Return from LibGuides API Call: " . print_r($returnVal, true)
        );
        if ($returnVal != null) {
            if (isset($returnVal->errorCode)) {
                // In some cases, this should be returned perhaps...
                $this->error("LibGuides Error: " . $returnVal->errorCode);
                return $returnVal;
            } else {
                return $returnVal;
            }
        } else {
            $this->error(
                "LibGuides API Error: Nothing returned from API call."
            );
            $this->debug(
                "Body return from LibGuides API Call: " . print_r($body, true)
            );
        }
    }
}
