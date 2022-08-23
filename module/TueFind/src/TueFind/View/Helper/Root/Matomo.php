<?php

namespace TueFind\View\Helper\Root;

use VuFind\Search\Base\Results;

class Matomo extends \VuFind\View\Helper\Root\Matomo {

    protected $auth;

    /**
     * Constructor
     *
     * @param \Laminas\Config\Config               $config  VuFind configuration
     * @param \Laminas\Router\Http\TreeRouteStack  $router  Router
     * @param \Laminas\Http\PhpEnvironment\Request $request Request
     * @param \VuFind\Auth\Manager                 $auth    VuFind Auth Manager
     */
    public function __construct(
        \Laminas\Config\Config $config,
        \Laminas\Router\Http\TreeRouteStack $router,
        \Laminas\Http\PhpEnvironment\Request $request,
        \VuFind\Auth\Manager $auth
    ) {
        parent::__construct($config, $router, $request);
        $this->auth = $auth;

        // TueFind: Select site_id according to current instance / domain
        if (isset($config->Matomo->site_ids)) {
            $siteIds = array_reduce(explode(",", $config->Matomo->site_ids), function ($array, $value) {
                $values = array_map("trim", explode(":", $value));
                $array[$values[0]] = $values[1];
                return $array;
            }, []);
            $http_host_without_port = preg_replace('":[^:]+$"', '', $request->getServer('HTTP_HOST'));
            if (isset($siteIds[$http_host_without_port])) {
                $this->siteId = $siteIds[$http_host_without_port];
            } else {
                $this->url = ''; // set url to empty string so no JS code will be produced
            }
        } elseif (isset($config->Matomo->site_id)) {
            $this->siteId = $config->Matomo->site_id;
        } else {
            $this->url = ''; // set url to empty string so no JS code will be produced
        }
    }

    protected function getCustomVarsCode(array $customData): string
    {
        $customData['isLoggedIn'] = ((isset($this->auth) && $this->auth->isLoggedIn()) ? 'true' : 'false');
        if ($this->isValidFulltextSearch()) {
            $customData['SearchType'] = 'fulltext';
        }
        return parent::getCustomVarsCode($customData);
    }

    //function checks whether the fulltext search is being used and that the search input isn't empty
    protected function isValidFulltextSearch(): bool
    {
        $condFulltextSearch = strpos($this->request->getUriString(), 'Search2/Results') !== false;
        $condSearchNotEmpty = preg_match("/lookfor=[^&]+/", $this->request->getUriString());
        return $condFulltextSearch && $condSearchNotEmpty;
    }

    protected function getTrackSearchCode(Results $results, array $customData): string
    {
        $escape = $this->getView()->plugin('escapejs');
        $params = $results->getParams();
        $searchTerms = $escape($params->getDisplayQuery());
        $searchType = $escape($params->getSearchType());
        if ($this->isValidFulltextSearch()) {
            $searchType = $escape('fulltext');
        }
        $resultCount = $results->getResultTotal();
        $backendId = $results->getOptions()->getSearchClassId();
        $dimensions = $this->getCustomDimensionsCode($customData);

        // Use trackSiteSearch *instead* of trackPageView in searches
        return "_paq.push(['trackSiteSearch', '{$this->searchPrefix}$backendId|"
            . "$searchTerms', '$searchType', $resultCount, $dimensions]);\n";
    }
}
