<?php

/**
 * Piwik view helper
 *
 * PHP version 8
 *
 * Copyright (C) The National Library of Finland 2014-2018.
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License version 2,
 * as published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301  USA
 *
 * @category VuFind
 * @package  View_Helpers
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */

namespace VuFind\View\Helper\Root;

use function is_array;
use function strlen;

/**
 * Piwik Web Analytics view helper
 *
 * @category VuFind
 * @package  View_Helpers
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */
class Piwik extends \Laminas\View\Helper\AbstractHelper
{
    /**
     * Piwik URL (false if disabled)
     *
     * @var string|bool
     */
    protected $url;

    /**
     * Piwik Site ID
     *
     * @var int
     */
    protected $siteId;

    /**
     * Search prefix (see config.ini for details)
     *
     * @var string
     */
    protected $searchPrefix;

    /**
     * Whether to disable cookies (see config.ini for details)
     *
     * @var bool
     */
    protected $disableCookies;

    /**
     * Whether to track use custom variables to track additional information
     *
     * @var bool
     */
    protected $customVars;

    /**
     * Request object
     *
     * @var \Laminas\Http\PhpEnvironment\Request
     */
    protected $request;

    /**
     * Router object
     *
     * @var \Laminas\Router\Http\RouteMatch
     */
    protected $router;

    /**
     * Whether the tracker was initialized from lightbox.
     *
     * @var bool
     */
    protected $lightbox;

    /**
     * Additional parameters
     *
     * @var array
     */
    protected $params;

    /**
     * A timestamp used to identify the init function to avoid name clashes when
     * opening lightboxes.
     *
     * @var int
     */
    protected $timestamp;

    /**
     * Constructor
     *
     * @param string|bool                         $url        Piwik address
     * (false if disabled)
     * @param int|array                           $options    Options array (or,
     * if a single value, the Piwik site ID -- for backward compatibility)
     * @param bool                                $customVars Whether to track
     * additional information in custom variables
     * @param Laminas\Router\Http\RouteMatch      $router     Request
     * @param Laminas\Http\PhpEnvironment\Request $request    Request
     */
    public function __construct($url, $options, $customVars, $router, $request)
    {
        $this->url = $url;
        if ($url && !str_ends_with($url, '/')) {
            $this->url .= '/';
        }
        if (is_array($options)) {
            $this->siteId = $options['siteId'];
            $this->searchPrefix = $options['searchPrefix'] ?? '';
            $this->disableCookies = $options['disableCookies'] ?? '';
        } else {
            $this->siteId = $options;
        }
        $this->customVars = $customVars;
        $this->router = $router;
        $this->request = $request;
        $this->timestamp = round(microtime(true) * 1000);
    }

    /**
     * Returns Piwik code (if active) or empty string if not.
     *
     * @param array $params Parameters
     *
     * @return string
     */
    public function __invoke($params = null)
    {
        if (!$this->url) {
            return '';
        }

        $this->params = $params;
        if (isset($this->params['lightbox'])) {
            $this->lightbox = $this->params['lightbox'];
        }

        $results = $this->getSearchResults();
        if ($results && ($combinedResults = $this->getCombinedSearchResults())) {
            $code = $this->trackCombinedSearch($results, $combinedResults);
        } elseif ($results) {
            $code = $this->trackSearch($results);
        } elseif ($recordDriver = $this->getRecordDriver()) {
            $code = $this->trackRecordPage($recordDriver);
        } else {
            $code = $this->trackPageView();
        }

        $inlineScript = $this->getView()->plugin('inlinescript');
        return $inlineScript(\Laminas\View\Helper\HeadScript::SCRIPT, $code, 'SET');
    }

    /**
     * Track a Search
     *
     * @param VuFind\Search\Base\Results $results Search Results
     *
     * @return string Tracking Code
     */
    protected function trackSearch($results)
    {
        $customVars = $this->lightbox
            ? $this->getLightboxCustomVars()
            : $this->getSearchCustomVars($results);

        $code = $this->getOpeningTrackingCode();
        $code .= $this->getCustomVarsCode($customVars);
        $code .= $this->getTrackSearchCode($results);
        $code .= $this->getClosingTrackingCode();

        return $code;
    }

    /**
     * Track a Combined Search
     *
     * @param VuFind\Search\Base\Results $results         Search Results
     * @param array                      $combinedResults Combined Search Results
     *
     * @return string Tracking Code
     */
    protected function trackCombinedSearch($results, $combinedResults)
    {
        $customVars = $this->lightbox
            ? $this->getLightboxCustomVars()
            : $this->getSearchCustomVars($results);

        $code = $this->getOpeningTrackingCode();
        $code .= $this->getCustomVarsCode($customVars);
        $code .= $this->getTrackCombinedSearchCode($results, $combinedResults);
        $code .= $this->getClosingTrackingCode();

        return $code;
    }

    /**
     * Track a Record View
     *
     * @param VuFind\RecordDriver\AbstractBase $recordDriver Record Driver
     *
     * @return string Tracking Code
     */
    protected function trackRecordPage($recordDriver)
    {
        $customVars = $this->lightbox
            ? $this->getLightboxCustomVars()
            : $this->getRecordPageCustomVars($recordDriver);

        $code = $this->getOpeningTrackingCode();
        $code .= $this->getCustomVarsCode($customVars);
        $code .= $this->getTrackPageViewCode();
        $code .= $this->getClosingTrackingCode();

        return $code;
    }

    /**
     * Track a Generic Page View
     *
     * @return string Tracking Code
     */
    protected function trackPageView()
    {
        $customVars = $this->lightbox
            ? $this->getLightboxCustomVars()
            : $this->getGenericCustomVars();

        $code = $this->getOpeningTrackingCode();
        $code .= $this->getCustomVarsCode($customVars);
        $code .= $this->getTrackPageViewCode();
        $code .= $this->getClosingTrackingCode();

        return $code;
    }

    /**
     * Get Search Results if on a Results Page
     *
     * @return VuFind\Search\Base\Results|null Search results or null if not
     * on a search page
     */
    protected function getSearchResults()
    {
        $viewModel = $this->getView()->plugin('view_model');
        $current = $viewModel->getCurrent();
        if (null === $current || 'layout/lightbox' === $current->getTemplate()) {
            return null;
        }
        $children = $current->getChildren();
        if (isset($children[0])) {
            $template = $children[0]->getTemplate();
            if (!strstr($template, '/home') && !strstr($template, 'facet-list')) {
                $results = $children[0]->getVariable('results');
                if ($results instanceof \VuFind\Search\Base\Results) {
                    return $results;
                }
            }
        }
        return null;
    }

    /**
     * Get Combined Search Results if on a Results Page
     *
     * @return array|null Array of search results or null if not on a combined search
     * page
     */
    protected function getCombinedSearchResults()
    {
        $viewModel = $this->getView()->plugin('view_model');
        $current = $viewModel->getCurrent();
        if (null === $current) {
            return null;
        }
        $children = $current->getChildren();
        if (isset($children[0])) {
            $results = $children[0]->getVariable('combinedResults');
            if (is_array($results)) {
                return $results;
            }
        }
        return null;
    }

    /**
     * Get Record Driver if on a Record Page
     *
     * @return VuFind\RecordDriver\AbstractBase|null Record driver or null if not
     * on a record page
     */
    protected function getRecordDriver()
    {
        $view = $this->getView();
        $viewModel = $view->plugin('view_model');
        $current = $viewModel->getCurrent();
        if (null === $current) {
            $driver = $view->vars('driver');
            if ($driver instanceof \VuFind\RecordDriver\AbstractBase) {
                return $driver;
            }
            return null;
        }
        $children = $current->getChildren();
        if (isset($children[0])) {
            $driver = $children[0]->getVariable('driver');
            if ($driver instanceof \VuFind\RecordDriver\AbstractBase) {
                return $driver;
            }
        }
        return null;
    }

    /**
     * Get Custom Variables for Search Results
     *
     * @param VuFind\Search\Base\Results $results Search results
     *
     * @return array Associative array of custom variables
     */
    protected function getSearchCustomVars($results)
    {
        if (!$this->customVars) {
            return [];
        }

        $facets = [];
        $facetTypes = [];
        $params = $results->getParams();
        foreach ($params->getFilterList() as $filterType => $filters) {
            $facetTypes[] = $filterType;
            foreach ($filters as $filter) {
                $facets[] = $filter['field'] . '|' . $filter['value'];
            }
        }
        $facets = implode("\t", $facets);
        $facetTypes = implode("\t", $facetTypes);

        return [
            'Facets' => $facets,
            'FacetTypes' => $facetTypes,
            'SearchType' => $params->getSearchType(),
            'SearchBackend' => $params->getSearchClassId(),
            'Sort' => $params->getSort(),
            'Page' => $params->getPage(),
            'Limit' => $params->getLimit(),
            'View' => $params->getView(),
        ];
    }

    /**
     * Get Custom Variables for a Record Page
     *
     * @param VuFind\RecordDriver\AbstractBase $recordDriver Record driver
     *
     * @return array Associative array of custom variables
     */
    protected function getRecordPageCustomVars($recordDriver)
    {
        $id = $recordDriver->getUniqueID();
        $formats = $recordDriver->tryMethod('getFormats');
        if (is_array($formats)) {
            $formats = implode(',', $formats);
        }
        $formats = $formats;
        $author = $recordDriver->tryMethod('getPrimaryAuthor');
        if (empty($author)) {
            $author = '-';
        }
        // Use breadcrumb for title since it's guaranteed to return something
        $title = $recordDriver->tryMethod('getBreadcrumb');
        if (empty($title)) {
            $title = '-';
        }
        $institutions = $recordDriver->tryMethod('getInstitutions');
        if (is_array($institutions)) {
            $institutions = implode(',', $institutions);
        }
        $institutions = $institutions;

        return [
            'RecordFormat' => $formats,
            'RecordData' => "$id|$author|$title",
            'RecordInstitution' => $institutions,
        ];
    }

    /**
     * Get Custom Variables for lightbox actions
     *
     * @return array Associative array of custom variables
     */
    protected function getLightboxCustomVars()
    {
        return [];
    }

    /**
     * Get Custom Variables for a Generic Page View
     *
     * @return array Associative array of custom variables
     */
    protected function getGenericCustomVars()
    {
        return [];
    }

    /**
     * Get the Initialization Part of the Tracking Code
     *
     * @return string JavaScript Code Fragment
     */
    protected function getOpeningTrackingCode()
    {
        $escape = $this->getView()->plugin('escapejs');
        $code = <<<EOT

            function initVuFindPiwikTracker{$this->timestamp}(){
                var VuFindPiwikTracker = Piwik.getTracker();

                VuFindPiwikTracker.setSiteId({$this->siteId});
                VuFindPiwikTracker.setTrackerUrl('{$this->url}piwik.php');
                VuFindPiwikTracker.setCustomUrl('{$escape($this->getCustomUrl())}');

            EOT;
        if ($this->disableCookies) {
            $code .= <<<EOT
                    VuFindPiwikTracker.disableCookies();

                EOT;
        }

        return $code;
    }

    /**
     * Get the custom URL of the Tracking Code
     *
     * @return string URL
     */
    protected function getCustomUrl()
    {
        $path = $this->request->getUri()->toString();
        $routeMatch = $this->router->match($this->request);
        if (
            $routeMatch
            && $routeMatch->getMatchedRouteName() == 'vufindrecord-ajaxtab'
        ) {
            // Replace 'AjaxTab' with tab name in record page URLs
            $replace = 'AjaxTab';
            $tab = $this->request->getPost('tab');
            if (null !== ($pos = strrpos($path, $replace))) {
                $path = substr_replace($path, $tab, $pos, $pos + strlen($replace));
            }
        }
        return $path;
    }

    /**
     * Get the Finalization Part of the Tracking Code
     *
     * @return string JavaScript Code Fragment
     */
    protected function getClosingTrackingCode()
    {
        return <<<EOT
                VuFindPiwikTracker.enableLinkTracking();
            };
            (function(){
                if (typeof Piwik === 'undefined') {
                    var d=document, g=d.createElement('script'),
                        s=d.getElementsByTagName('script')[0];
                    g.type='text/javascript'; g.defer=true; g.async=true;
                    g.src='{$this->url}piwik.js';
                    g.onload=initVuFindPiwikTracker{$this->timestamp};
                    s.parentNode.insertBefore(g,s);
                } else {
                    initVuFindPiwikTracker{$this->timestamp}();
                }
            })();
            EOT;
    }

    /**
     * Convert a Custom Variables Array to JavaScript Code
     *
     * @param array $customVars Custom Variables
     *
     * @return string JavaScript Code Fragment
     */
    protected function getCustomVarsCode($customVars)
    {
        $escape = $this->getView()->plugin('escapeHtmlAttr');
        $code = '';
        $i = 0;
        foreach ($customVars as $key => $value) {
            ++$i;

            // Workaround to prevent overwriting of custom variables 4 and 5 by
            // trackSiteSearch, see http://forum.piwik.org/read.php?2,115537,115538
            if ($i === 4) {
                $i = 6;
            }

            $value = $escape($value);
            $code .= <<<EOT
                    VuFindPiwikTracker.setCustomVariable($i, '$key', '$value', 'page');

                EOT;
        }
        return $code;
    }

    /**
     * Get Site Search Tracking Code
     *
     * @param VuFind\Search\Base\Results $results Search results
     *
     * @return string JavaScript Code Fragment
     */
    protected function getTrackSearchCode($results)
    {
        $escape = $this->getView()->plugin('escapeHtmlAttr');
        $params = $results->getParams();
        $searchTerms = $escape($params->getDisplayQuery());
        $searchType = $escape($params->getSearchType());
        $resultCount = $results->getResultTotal();
        $backendId = $results->getOptions()->getSearchClassId();

        // Use trackSiteSearch *instead* of trackPageView in searches
        return <<<EOT
                VuFindPiwikTracker.trackSiteSearch(
                    '{$this->searchPrefix}$backendId|$searchTerms', '$searchType', $resultCount
                );

            EOT;
    }

    /**
     * Get Site Search Tracking Code for Combined Search
     *
     * @param VuFind\Search\Base\Results $results         Search results
     * @param array                      $combinedResults Combined Search Results
     *
     * @return string JavaScript Code Fragment
     */
    protected function getTrackCombinedSearchCode($results, $combinedResults)
    {
        $escape = $this->getView()->plugin('escapeHtmlAttr');
        $params = $results->getParams();
        $searchTerms = $escape($params->getDisplayQuery());
        $searchType = $escape($params->getSearchType());
        $resultCount = 0;
        foreach ($combinedResults as $currentSearch) {
            if (!empty($currentSearch['ajax'])) {
                // Some results fetched via ajax, so report that we don't know the
                // result count.
                $resultCount = 'false';
                break;
            }
            $resultCount += $currentSearch['view']->results
                ->getResultTotal();
        }

        // Use trackSiteSearch *instead* of trackPageView in searches
        return <<<EOT
                VuFindPiwikTracker.trackSiteSearch(
                    '{$this->searchPrefix}Combined|$searchTerms', '$searchType', $resultCount
                );

            EOT;
    }

    /**
     * Get Page View Tracking Code
     *
     * @return string JavaScript Code Fragment
     */
    protected function getTrackPageViewCode()
    {
        return <<<EOT
                VuFindPiwikTracker.trackPageView();

            EOT;
    }
}
