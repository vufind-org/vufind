<?php

namespace VuFind\Recommend;

use Exception;
use Laminas\Http\Client as HttpClient;
use Laminas\Log\LoggerAwareInterface;

class LibGuidesProfile implements 
    RecommendInterface, 
    LoggerAwareInterface,
    \VuFindHttp\HttpServiceAwareInterface
{
    use \VuFind\Log\LoggerAwareTrait {
        logError as error;
    }
    use \VuFindHttp\HttpServiceAwareTrait;

    protected $tokenData = null;

    protected $idToAccount = [];
    protected $subjectToId = [];

    /**
     * Constructor
     *
     * @param string     $apiKey API key
     * @param HttpClient $client VuFind HTTP client
     */
    public function __construct($config, HttpClient $client)
    {
        $this->config = $config;
        $this->client = $client;
    }

    /**
     * Store the configuration of the recommendation module.
     *
     * @param string $settings Settings from searches.ini.
     *
     * @return void
     */
    public function setConfig($settings)
    {
        // No action needed.
    }

    /**
     * Called before the Search Results object performs its main search
     * (specifically, in response to \VuFind\Search\SearchRunner::EVENT_CONFIGURED).
     * This method is responsible for setting search parameters needed by the
     * recommendation module and for reading any existing search parameters that may
     * be needed.
     *
     * @param \VuFind\Search\Base\Params $params  Search parameter object
     * @param \Laminas\Stdlib\Parameters $request Parameter object representing user
     * request.
     *
     * @return void
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function init($params, $request)
    {
        // No action needed.
    }

    /**
     * Called after the Search Results object has performed its main search.  This
     * may be used to extract necessary information from the Search Results object
     * or to perform completely unrelated processing.
     *
     * @param \VuFind\Search\Base\Results $results Search results object
     *
     * @return void
     */
    public function process($results)
    {
        $this->searchObject = $results;
    }

    /**
     * Get terms related to the query.
     *
     * @return array
     */
    public function getResults()
    {
        // TODO: cache data, check it for staleness
        $this->refreshData();

        $query = $this->searchObject->getParams()->getQuery();
        $account = $this->findBestMatch($query);
        return $account;
    }

    protected function findBestMatch($query)
    {
        $queryString = $query->getAllTerms();
        if (!$queryString) {
            return false;
        }
        $queryString = strtolower($queryString);

        // Find the closest levenshtein match.
        $minDistance = PHP_INT_MAX;
        $subjects = array_keys($this->subjectToId);
        foreach($subjects as $subject) {
            $distance = levenshtein($subject, $queryString);
            if ($distance < $minDistance) {
                $id = $this->subjectToId[$subject];
                $minDistance = $distance;
            }
        }

        // // Find an exact match
        // if (!array_key_exists($queryString, $this->subjectToId)) {
        //     return false;
        // }
        // $id = $this->subjectToId[$queryString];
        // if (!$id) {
        //     return false;
        // }

        $account = $this->idToAccount[$id];
        if (!$account) {
            return false;
        }
        
        return $account;
    }

    protected function refreshData()
    {
        $idToAccount = [];
        $subjectToId = [];
        $accounts = $this->getAccounts();
        foreach ($accounts as $account) {
            $id = $account->id;
            $idToAccount[$id] = $account;

            foreach ($account->subjects ?? [] as $subject) {
                $subjectName = strtolower($subject->name);

                // Yes, this will override any previous account ID with the same subject.
                // Could be modified if someone has a library with more than one librarian
                // linked to the same Subject Guide if they have some way to decide who to display.
                $subjectToId[$subjectName] = $id;
            }
        }

        //TODO cache
        $this->idToAccount = $idToAccount;
        $this->subjectToId = $subjectToId;
    }

    // Adapted from OverdriveConnector.
    // TODO: refactor?
    protected function getAccounts()
    {
        $tokenData = $this->connectToApi();
        if (!$tokenData){
            return [];
        }

        try {
            $client = $this->getHttpClient();
        } catch (Exception $e) {
            $this->error(
                "error while setting up the client: " . $e->getMessage()
            );
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

        $client->setHeaders($headers);
        $client->setMethod("GET");
        $client->setUri(
            $this->config->General->api_base_url . "/accounts?expand=profile,subjects"
        );
        try {
            // throw new Exception('testException');
            $response = $client->send();
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
                $this->error("Overdrive Error: " . $returnVal->errorCode);
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

    // Adapted from OverdriveConnector.
    // TODO: refactor?
    protected function connectToApi($forceNewConnection = false)
    {
        $this->debug("connecting to API");
        $tokenData = $this->tokenData;
        $this->debug("Last API Token: " . print_r($tokenData, true));
        if (
            $forceNewConnection || $tokenData == null
            || !isset($tokenData->access_token)
            || time() >= $tokenData->expirationTime
        ) {
            $authHeader = base64_encode(
                $this->config->General->client_id . ":" . $this->config->General->client_secret
            );
            $headers = [
                'Content-Type: application/x-www-form-urlencoded;charset=UTF-8',
                "Authorization: Basic $authHeader",
            ];

            try {
                $client = $this->getHttpClient();
            } catch (Exception $e) {
                $this->error(
                    "error while setting up the client: " . $e->getMessage()
                );
                return false;
            }
            $client->setHeaders($headers);
            $client->setMethod("POST");
            $client->setRawBody("grant_type=client_credentials");
            $response = $client
                ->setUri($this->config->General->api_base_url . "/oauth/token")
                ->send();

            if ($response->isServerError()) {
                $this->error(
                    "LibGuides API HTTP Error: " .
                    $response->getStatusCode()
                );
                $this->debug("Request: " . $client->getRequest());
                return false;
            }

            $body = $response->getBody();
            $tokenData = json_decode($body);
            $this->debug(
                "TokenData returned from LibGuides API Call: " . print_r(
                    $tokenData,
                    true
                )
            );
            if ($tokenData != null) {
                if (isset($tokenData->errorCode)) {
                    // In some cases, this should be returned perhaps...
                    $this->error("LibGuides API Error: " . $tokenData->errorCode);
                    return false;
                } else {
                    $tokenData->expirationTime = time()
                        + ($tokenData->expires_in ?? 0);
                    $this->tokenData = $tokenData;
                    return $tokenData;
                }
            } else {
                $this->error(
                    "Overdrive Error: Nothing returned from API call."
                );
                $this->debug(
                    "Body return from OD API Call: " . print_r($body, true)
                );
            }
        }
        return $tokenData;
    }

    /**
     * Get an HTTP client
     *
     * @param string $url URL for client to use
     *
     * @return \Laminas\Http\Client
     * @throws Exception
     */
    // copied from OverdriveConnector
    protected function getHttpClient($url = null)
    {
        if (null === $this->httpService) {
            throw new Exception('HTTP service missing.');
        }
        if (!$this->client) {
            $this->client = $this->httpService->createClient($url);
            // set keep alive to true since we are sending to the same server
            $this->client->setOptions(['keepalive', true]);
        }
        $this->client->resetParameters();
        return $this->client;
    }

    /**
     * Return the list of facets configured to be collapsed
     *
     * @return array
     */
    public function isCollapsed()
    {
        return false;
    }
}