<?php
/**
 * Piwik view helper
 *
 * PHP version 5
 *
 * Copyright (C) The National Library of Finland 2014.
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
 * Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
 *
 * @category VuFind2
 * @package  View_Helpers
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org   Main Site
 */
namespace VuFind\View\Helper\Root;

/**
 * Piwik Web Analytics view helper
 *
 * @category VuFind2
 * @package  View_Helpers
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org   Main Site
 */
class Piwik extends \Zend\View\Helper\AbstractHelper
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
     * Whether to track use custom variables to track additional information
     *
     * @var bool
     */
    protected $customVars;

    /**
     * Constructor
     *
     * @param string|bool $url        Piwik address (false if disabled)
     * @param int         $siteId     Piwik site ID
     * @param bool        $customVars Whether to track additional information in
     * custom variables
     */
    public function __construct($url, $siteId, $customVars)
    {
        $this->url = $url;
        if ($url && substr($url, -1) != '/') {
            $this->url .= '/';
        }
        $this->siteId = $siteId;
        $this->customVars = $customVars;
    }

    /**
     * Returns Piwik code (if active) or empty string if not.
     *
     * @return string
     */
    public function __invoke()
    {
        if (!$this->url) {
            return '';
        }

        if ($results = $this->getSearchResults()) {
            $code = $this->trackSearch($results);
        } else if ($recordDriver = $this->getRecordDriver()) {
            $code = $this->trackRecordPage($recordDriver);
        } else {
            $code = $this->trackPageView();
        }

        $inlineScript = $this->getView()->plugin('inlinescript');
        return $inlineScript(\Zend\View\Helper\HeadScript::SCRIPT, $code, 'SET');
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
        $customVars = $this->getSearchCustomVars($results);

        $code = $this->getOpeningTrackingCode();
        $code .= $this->getCustomVarsCode($customVars);
        $code .= $this->getTrackSearchCode($results);
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
        $customVars = $this->getRecordPageCustomVars($recordDriver);

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
        $customVars = $this->getGenericCustomVars();

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
        $children = $viewModel->getCurrent()->getChildren();
        if (isset($children[0])) {
            $template = $children[0]->getTemplate();
            if (!strstr($template, '/home')) {
                $results = $children[0]->getVariable('results');
                if (is_a($results, 'VuFind\Search\Base\Results')) {
                    return $results;
                }
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
        $viewModel = $this->getView()->plugin('view_model');
        $children = $viewModel->getCurrent()->getChildren();
        if (isset($children[0])) {
            $driver = $children[0]->getVariable('driver');
            if (is_a($driver, 'VuFind\RecordDriver\AbstractBase')) {
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
            'View' => $params->getView()
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
            'RecordInstitution' => $institutions
        ];
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
        return <<<EOT

function initVuFindPiwikTracker(){
    var VuFindPiwikTracker = Piwik.getTracker();

    VuFindPiwikTracker.setSiteId({$this->siteId});
    VuFindPiwikTracker.setTrackerUrl('{$this->url}piwik.php');
    VuFindPiwikTracker.setCustomUrl(location.protocol + '//'
        + location.host + location.pathname);

EOT;
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
var d=document, g=d.createElement('script'), s=d.getElementsByTagName('script')[0];
    g.type='text/javascript'; g.defer=true; g.async=true;
    g.src='{$this->url}piwik.js';
    g.onload=initVuFindPiwikTracker;
s.parentNode.insertBefore(g,s); })();
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

        // Use trackSiteSearch *instead* of trackPageView in searches
        return <<<EOT
    VuFindPiwikTracker.trackSiteSearch('$searchTerms', '$searchType', $resultCount);

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
