<?php

namespace VuFind\Recommend;

use Laminas\Http\Client as HttpClient;
use VuFind\Connection\LibGuides;

class LibGuidesProfile implements 
    RecommendInterface
{
    use \VuFindHttp\HttpServiceAwareTrait;

    protected $idToAccount = [];
    protected $subjectToId = [];

    protected $libGuides;

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
        $this->libGuides = new LibGuides(
            $client,
            $this->config->General->api_base_url,
            $this->config->General->client_id,
            $this->config->General->client_secret
        );
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
        $accounts = $this->libGuides->getAccounts();
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