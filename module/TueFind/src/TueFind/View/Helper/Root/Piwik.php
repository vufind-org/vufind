<?php

namespace TueFind\View\Helper\Root;

class Piwik extends \VuFind\View\Helper\Root\Piwik
{
    protected $auth;

    public function __construct($url, $options, $customVars, $router, $request, ?\VuFind\Auth\Manager $auth)
    {
        parent::__construct($url, $options, $customVars, $router, $request);
        $this->auth = $auth;
    }

    protected function getCustomVarsCode($customVars)
    {
        $customVars['isLoggedIn'] = ((isset($this->auth) && $this->auth->isLoggedIn()) ? 'true' : 'false');
        if ($this->isValidFulltextSearch()) {
                $customVars['SearchType'] = 'fulltext';
	}
        return parent::getCustomVarsCode($customVars);
    }

    protected function getTrackSearchCode($results)
    {
        $escape = $this->getView()->plugin('escapeHtmlAttr');
        $params = $results->getParams();
        $searchTerms = $escape($params->getDisplayQuery());
        $searchType = $escape($params->getSearchType());
        if ($this->isValidFulltextSearch()) {
                $searchType = $escape('fulltext');
	}
        $resultCount = $results->getResultTotal();
        $backendId = $results->getOptions()->getSearchClassId();

        // Use trackSiteSearch *instead* of trackPageView in searches
        return <<<EOT
    VuFindPiwikTracker.trackSiteSearch(
        '{$this->searchPrefix}$backendId|$searchTerms', '$searchType', $resultCount
    );

EOT;
    }

    //function checks whether the fulltext search is being used and that the search input isn't empty(ignoring blank spaces)
    protected function isValidFulltextSearch(): bool {
        $condFulltextSearch = strpos($this->request->getUriString(), 'Search2/Results') !== false;
        $condSearchNotEmpty = preg_replace("/\s+/", "",$_GET["lookfor"]) !== '';
        return $condFulltextSearch && $condSearchNotEmpty;
    }
}
?>
