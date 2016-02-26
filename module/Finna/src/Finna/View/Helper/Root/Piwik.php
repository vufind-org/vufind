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
 * @category VuFind
 * @package  View_Helpers
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org   Main Site
 */
namespace Finna\View\Helper\Root;

/**
 * Piwik Web Analytics view helper
 *
 * @category VuFind
 * @package  View_Helpers
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org   Main Site
 */
class Piwik extends \VuFind\View\Helper\Root\Piwik
{
    /**
     * MetaLib search results
     *
     * @var \Finna\Search\MetaLib\Results
     */
    protected $results = null;

    /**
     * Translator
     *
     * @var \VuFind\Translator
     */
    protected $translator;

    /**
     * Constructor
     *
     * @param string|bool        $url        Piwik address (false if disabled)
     * @param int                $siteId     Piwik site ID
     * @param bool               $customVars Whether to track additional information
     * in custom variables
     * @param \VuFind\Translator $translator Translator
     */
    public function __construct($url, $siteId, $customVars, $translator)
    {
        parent::__construct($url, $siteId, $customVars);
        $this->translator = $translator;
    }

    /**
     * Returns Piwik code (if active) or empty string if not.
     *
     * @param \Finna\Search\MetaLib\Results $results MetaLib search results
     *
     * @return string
     */
    public function __invoke($results = null)
    {
        $this->results = $results;

        $viewModel = $this->getView()->plugin('view_model');
        if ($current = $viewModel->getCurrent()) {
            $children = $current->getChildren();
            if (isset($children[0])
                && isset($children[0]->disablePiwik) && $children[0]->disablePiwik
            ) {
                return '';
            }
        }

        return parent::__invoke();
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
        if (!$this->customVars) {
            return [];
        }

        $vars = parent::getRecordPageCustomVars($recordDriver);

        $source = $recordDriver->getSourceIdentifier();
        $sourceMap
            = ['Solr' => 'Local', 'Primo' => 'PCI', 'MetaLib' => 'MetaLib'];

        $vars['RecordIndex']
            = isset($sourceMap[$source]) ? $sourceMap[$source] : $source;

        if ($source == 'Primo') {
            $vars['PCIRecordSource'] = $recordDriver->getSource();
            unset($vars['RecordInstitution']);

            if ($type = $recordDriver->getType()) {
                $vars['RecordFormat'] = $type;
            }
            foreach (['RecordFormat', 'RecordData', 'RecordSource'] as $var) {
                if (isset($vars[$var])) {
                    $vars["PCI{$var}"] = $vars[$var];
                    unset($vars[$var]);
                }
            }
        } else if ($source == 'MetaLib') {
            $vars['MetaLibRecordSource'] = $recordDriver->getSource();
            $vars['MetaLibRecordData'] = $vars['RecordData'];
            unset($vars['RecordFormat']);
            unset($vars['RecordData']);
            unset($vars['RecordInstitution']);
        } else {
            $format = $formats = $recordDriver->tryMethod('getFormats');
            if (is_array($formats)) {
                $format = isset($formats[1]) ? $formats[1] : $formats[0];
            }
            $format = urldecode($format);
            $format = rtrim($format, '/');
            $format = preg_replace('/^\d\//', '', $format);
            $vars['RecordFormat'] = $format;
        }

        return $vars;
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

        $vars = parent::getSearchCustomVars($results);

        $facetType = null;
        $facets = [];
        $facetTypes = [];
        $params = $results->getParams();

        if ($params->getSearchType() == 'basic') {
            $vars['SearchHandler'] = $results->getParams()->getQuery()->getHandler();
        }

        $currentType = $vars['SearchType'];
        $backendId = method_exists($results, 'getBackendId')
            ? $results->getBackendId() : '';

        if ($backendId === 'MetaLib') {
            unset($vars['Facets']);
            unset($vars['FacetTypes']);
            unset($vars['View']);
            unset($vars['Limit']);
            unset($vars['Sort']);

            $vars['SearchType'] = 'MetaLib';
            if ($currentType == 'advanced') {
                $vars['SearchType'] = 'MetaLibAdvanced';
            }

            return $vars;
        } else if ($backendId == 'Primo') {
            unset($vars['View']);
            $vars['SearchType'] = 'PCI';
            if ($currentType == 'advanced') {
                $vars['SearchType'] = 'PCIAdvanced';
            }
        }

        foreach ($params->getFilterList() as $filterType => $filters) {
            $facetType = null;
            foreach ($filters as $filter) {
                if (!$facetType) {
                    $facetTypes[] = $filter['field'];
                }
                $parts = $filter['value'];
                if ($backendId === 'Solr') {
                    $parts = explode('/', $parts);
                    $parts = array_slice($parts, 1, -1);

                    $facetLevels = [];
                    for ($i = 0; $i < count($parts); $i++) {
                        $facetLevel = "$i/";
                        for ($j = 0; $j <= $i; $j++) {
                            $facetLevel .= $parts[$j] . '/';
                        }
                        $facetLevels[] = $this->translator->translate($facetLevel);
                    }
                    $facetStr = implode(' > ', $facetLevels);
                } else {
                    $facetStr = $parts;
                }
                $facets[] = $filter['field'] . '|' . $facetStr;
            }
        }
        $vars['Facets'] = implode("\t", $facets);
        $vars['FacetTypes'] = implode("\t", $facetTypes);

        return $vars;
    }

    /**
     * Get Search Results if on a Results Page
     *
     * @return VuFind\Search\Base\Results|null Search results or null if not
     * on a search page
     */
    protected function getSearchResults()
    {
        return $this->results ?: parent::getSearchResults();
    }
}
