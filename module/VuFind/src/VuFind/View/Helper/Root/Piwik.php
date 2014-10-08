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

        $search = false;
        $facets = '';
        $facetTypes = '';
        $searchTerms = '';
        $searchType = 'false';
        $record = false;
        $formats = '';
        $id = '';
        $author = '';
        $title = '';
        $institutions = '';

        $view = $this->getView();
        $escape = $view->plugin('escapeHtmlAttr');
        $viewModel = $view->plugin('view_model');
        $children = $viewModel->getCurrent()->getChildren();
        if (isset($children[0])) {
            $template = $children[0]->getTemplate();
            if (!strstr($template, '/home')) {
                $results = $children[0]->getVariable('results');
            }
            $recordDriver = $children[0]->getVariable('driver');
        }
        if ($results && is_a($results, 'VuFind\Search\Base\Results')) {
            $search = true;
            $resultCount = $results->getResultTotal();
            if ($this->customVars) {
                $facets = array();
                $facetTypes = array();
                $params = $results->getParams();
                foreach ($params->getFilterList() as $filterType => $filters) {
                    $facetTypes[] = $escape($filterType);
                    foreach ($filters as $filter) {
                        $facets[] = $escape($filter['field']) . '|'
                            . $escape($filter['value']);
                    }
                }
                $facets = implode('\t', $facets);
                $facetTypes = implode('\t', $facetTypes);
                $searchType = $escape($params->getSearchType());
                $searchTerms = $escape($params->getDisplayQuery());
            }
        } elseif ($recordDriver
            && is_a($recordDriver, 'VuFind\RecordDriver\AbstractBase')
        ) {
            $record = true;
            $id = $escape($recordDriver->getUniqueID());
            if (is_callable(array($recordDriver, 'getFormats'))) {
                $formats = $recordDriver->getFormats();
                if (is_array($formats)) {
                    $formats = implode(',', $formats);
                }
                $formats = $escape($formats);
            }
            if (is_callable(array($recordDriver, 'getPrimaryAuthor'))) {
                $author = $escape($recordDriver->getPrimaryAuthor());
                if (!$author) {
                    $author = '-';
                }
            }
            if (is_callable(array($recordDriver, 'getTitle'))) {
                $title = $escape($recordDriver->getTitle());
                if (!$title) {
                    $title = '-';
                }
            }
            if (is_callable(array($recordDriver, 'getInstitutions'))) {
                $institutions = $recordDriver->getInstitutions();
                if (is_array($institutions)) {
                    $institutions = implode(',', $institutions);
                }
                $institutions = $escape($institutions);
            }
        }

        $code = <<<EOT
var _paq = _paq || [];
(function(){
_paq.push(['setSiteId', {$this->siteId}]);
_paq.push(['setTrackerUrl', '{$this->url}piwik.php']);
_paq.push(['setCustomUrl', location.protocol + '//'
     + location.host + location.pathname]);

EOT;

        if ($search) {
            if ($this->customVars) {
                $code .= <<<EOT
_paq.push(['setCustomVariable', 1, 'Facets', "$facets", 'page']);
_paq.push(['setCustomVariable', 2, 'FacetTypes', "$facetTypes", 'page']);
_paq.push(['setCustomVariable', 3, 'SearchType', "$searchType", 'page']);

EOT;
            }
            // Use trackSiteSearch *instead* of trackPageView in searches
            $code .= <<<EOT
_paq.push(['trackSiteSearch', '$searchTerms', "$searchType", $resultCount]);

EOT;
        } else if ($record && $this->customVars) {
            $code .= <<<EOT
_paq.push(['setCustomVariable', 1, 'RecordFormat', "$formats", 'page']);
_paq.push(['setCustomVariable', 2, 'RecordData', "$id|$author|$title", 'page']);
_paq.push(['setCustomVariable', 3, 'RecordInstitution', "$institutions", 'page']);

EOT;
        }

        if (!$search) {
            $code .= <<<EOT
_paq.push(['trackPageView']);

EOT;
        };
        $code .= <<<EOT
_paq.push(['enableLinkTracking']);
var d=document, g=d.createElement('script'), s=d.getElementsByTagName('script')[0];
    g.type='text/javascript'; g.defer=true; g.async=true;
    g.src='{$this->url}piwik.js';
s.parentNode.insertBefore(g,s); })();

EOT;

        $inlineScript = $view->plugin('inlinescript');
        return $inlineScript(\Zend\View\Helper\HeadScript::SCRIPT, $code, 'SET');
    }
}
