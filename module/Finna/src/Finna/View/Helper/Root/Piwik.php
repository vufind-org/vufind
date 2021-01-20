<?php
/**
 * Piwik view helper
 *
 * PHP version 7
 *
 * Copyright (C) The National Library of Finland 2014-2016.
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
    implements \VuFind\I18n\Translator\TranslatorAwareInterface
{
    use \VuFind\I18n\Translator\TranslatorAwareTrait;

    /**
     * Current results, if any
     *
     * @var \VuFind\Search\Base\Results
     */
    protected $results = null;

    /**
     * Custom variables added using the trackCustomVar method.
     *
     * @var array
     */
    protected $addedCustomVars = [];

    /**
     * Returns Piwik code (if active) or empty string if not.
     *
     * @param array $params Parameters
     *
     * @return string
     */
    public function __invoke($params = null)
    {
        if (isset($params['results'])) {
            $this->results = $params['results'];
            unset($params['results']);
        }

        $viewModel = $this->getView()->plugin('view_model');
        if ($current = $viewModel->getCurrent()) {
            $children = $current->getChildren();
            if (isset($children[0])
                && isset($children[0]->disablePiwik) && $children[0]->disablePiwik
            ) {
                return '';
            }
        }

        return parent::__invoke($params);
    }

    /**
     * Get the custom URL of the Tracking Code
     *
     * @return string URL
     */
    protected function getCustomUrl()
    {
        // Prettify image popup page URL (AJAX/JSON?method=... > /record/[id]/image
        if ($this->calledFromImagePopup()
            && !empty($this->params['recordUrl'])
        ) {
            return $this->params['recordUrl'] . '/image';
        }
        return parent::getCustomUrl();
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
            = ['Solr' => 'Local', 'Primo' => 'PCI'];

        $vars['RecordIndex']
            = $sourceMap[$source] ?? $source;

        $vars['Language'] = $this->translator->getLocale();

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
        } else {
            $format = $formats = $recordDriver->tryMethod('getFormats');
            if (is_array($formats)) {
                $format = end($formats);
                if (false === $format) {
                    $format = '';
                }
            }
            $format = rtrim($format, '/');
            $format = preg_replace('/^\d\//', '', $format);
            $vars['RecordFormat'] = $format;

            $fields = $recordDriver->getRawData();
            $online = !empty($fields['online_boolean']);
            $vars['RecordAvailableOnline'] = $online ? 'yes' : 'no';
            $vars['RecordData' . ($online ? 'Online' : 'Offline')]
                = $vars['RecordData'];
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

        if ($backendId == 'Primo') {
            unset($vars['View']);
            $vars['SearchType'] = 'PCI';
            if ($currentType == 'advanced') {
                $vars['SearchType'] = 'PCIAdvanced';
            }
        }

        $vars['Language'] = $this->translator->getLocale();

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
     * Get Custom Variables for lightbox actions
     *
     * @return array Associative array of custom variables
     */
    protected function getLightboxCustomVars()
    {
        if ($this->calledFromImagePopup()) {
            // Custom vars for image popup (same data as for record page)

            // Prepend variable names with 'ImagePopup' unless listed here:
            $preserveName = ['RecordAvailableOnline'];

            $customVars = $this->getRecordPageCustomVars($this->params['record']);
            $lightboxCustomVars = [];
            foreach ($customVars as $key => $val) {
                if (!in_array($key, $preserveName)) {
                    $key = "ImagePopup{$key}";
                }
                $lightboxCustomVars[$key] = $val;
            }
            return $lightboxCustomVars;
        }
        return [];
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
        if (!empty($this->addedCustomVars)) {
            $customVars = array_merge($customVars, $this->addedCustomVars);
        }
        return parent::getCustomVarsCode($customVars);
    }

    /**
     * Add a custom variable to be tracked.
     *
     * @param string $name  Name
     * @param string $value Value
     *
     * @return void
     */
    public function trackCustomVar($name, $value)
    {
        $this->addedCustomVars[$name] = $value;
    }

    /**
     * Check if the view helper was called from image popup template.
     *
     * @return boolean
     */
    protected function calledFromImagePopup()
    {
        return isset($this->params['action'])
            && $this->params['action'] == 'imagePopup'
            && isset($this->params['record']);
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
