<?php
/**
 * Search box view helper
 *
 * PHP version 5
 *
 * Copyright (C) Villanova University 2010.
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
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:developer_manual Wiki
 */
namespace VuFind\View\Helper\Root;
use VuFind\Search\Options\PluginManager as OptionsManager;

/**
 * Search box view helper
 *
 * @category VuFind2
 * @package  View_Helpers
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:developer_manual Wiki
 */
class SearchBox extends \Zend\View\Helper\AbstractHelper
{
    /**
     * Configuration for search box.
     *
     * @var array
     */
    protected $config;

    /**
     * Search options plugin manager
     *
     * @var OptionsManager
     */
    protected $optionsManager;

    /**
     * Cache for configurations
     *
     * @var array
     */
    protected $cachedConfigs = [];

    /**
     * Constructor
     *
     * @param OptionsManager $optionsManager Search options plugin manager
     * @param array          $config         Configuration for search box
     */
    public function __construct(OptionsManager $optionsManager, $config = [])
    {
        $this->optionsManager = $optionsManager;
        $this->config = $config;
    }

    /**
     * Is autocomplete enabled for the current context?
     *
     * @param string $activeSearchClass Active search class ID
     *
     * @return bool
     */
    public function autocompleteEnabled($activeSearchClass)
    {
        // Simple case -- no combined handlers:
        if (!$this->combinedHandlersActive()) {
            $options = $this->optionsManager->get($activeSearchClass);
            return $options->autocompleteEnabled();
        }

        // Complex case -- combined handlers:
        $settings = $this->getCombinedHandlerConfig($activeSearchClass);
        $typeCount = count($settings['type']);
        for ($i = 0; $i < $typeCount; $i++) {
            $type = $settings['type'][$i];
            $target = $settings['target'][$i];

            if ($type == 'VuFind') {
                $options = $this->optionsManager->get($target);
                if ($options->autocompleteEnabled()) {
                    return true;
                }
            }
        }
        return false;
    }

    /**
     * Are combined handlers enabled?
     *
     * @return bool
     */
    public function combinedHandlersActive()
    {
        return isset($this->config['General']['combinedHandlers'])
            && $this->config['General']['combinedHandlers'];
    }

    /**
     * Get an array of filter information for use by the "retain filters" feature
     * of the search box. Returns an array of arrays with 'id' and 'value' keys used
     * for generating hidden checkboxes.
     *
     * @param array $filterList      Standard filter information
     * @param array $checkboxFilters Checkbox filter information
     *
     * @return array
     */
    public function getFilterDetails($filterList, $checkboxFilters)
    {
        $results = [];
        foreach ($filterList as $field => $data) {
            foreach ($data as $value) {
                $results[] = "$field:\"$value\"";
            }
        }
        foreach ($checkboxFilters as $current) {
            // Check a normalized version of the checkbox facet against the existing
            // filter list to avoid unnecessary duplication. Note that we don't
            // actually use this normalized version for anything beyond dupe-checking
            // in case it breaks advanced syntax.
            $regex = '/^([^:]*):([^"].*[^"]|[^"]{1,2})$/';
            $normalized
                = preg_match($regex, $current['filter'], $match)
                ? "{$match[1]}:\"{$match[2]}\"" : $current['filter'];
            if ($current['selected'] && !in_array($normalized, $results)
                && !in_array($current['filter'], $results)
            ) {
                $results[] = $current['filter'];
            }
        }
        $final = [];
        foreach ($results as $i => $val) {
            $final[] = ['id' => 'applied_filter_' . ($i + 1), 'value' => $val];
        }
        return $final;
    }

    /**
     * Get an array of information on search handlers for use in generating a
     * drop-down or hidden field. Returns an array of arrays with 'value', 'label',
     * 'indent' and 'selected' keys.
     *
     * @param string $activeSearchClass Active search class ID
     * @param string $activeHandler     Active search handler
     *
     * @return array
     */
    public function getHandlers($activeSearchClass, $activeHandler)
    {
        return $this->combinedHandlersActive()
            ? $this->getCombinedHandlers($activeSearchClass, $activeHandler)
            : $this->getBasicHandlers($activeSearchClass, $activeHandler);
    }

    /**
     * Support method for getHandlers() -- load basic settings.
     *
     * @param string $activeSearchClass Active search class ID
     * @param string $activeHandler     Active search handler
     *
     * @return array
     */
    protected function getBasicHandlers($activeSearchClass, $activeHandler)
    {
        $handlers = [];
        $options = $this->optionsManager->get($activeSearchClass);
        foreach ($options->getBasicHandlers() as $searchVal => $searchDesc) {
            $handlers[] = [
                'value' => $searchVal, 'label' => $searchDesc, 'indent' => false,
                'selected' => ($activeHandler == $searchVal)
            ];
        }
        return $handlers;
    }

    /**
     * Support method for getCombinedHandlers() -- retrieve/validate configuration.
     *
     * @param string $activeSearchClass Active search class ID
     *
     * @return array
     */
    protected function getCombinedHandlerConfig($activeSearchClass)
    {
        if (!isset($this->cachedConfigs[$activeSearchClass])) {
            // Load and validate configuration:
            $settings = isset($this->config['CombinedHandlers'])
                ? $this->config['CombinedHandlers'] : [];
            if (empty($settings)) {
                throw new \Exception('CombinedHandlers configuration missing.');
            }
            $typeCount = count($settings['type']);
            if ($typeCount != count($settings['target'])
                || $typeCount != count($settings['label'])
            ) {
                throw new \Exception('CombinedHandlers configuration incomplete.');
            }

            // Add configuration for the current search class if it is not already
            // present:
            if (!in_array($activeSearchClass, $settings['target'])) {
                $settings['type'][] = 'VuFind';
                $settings['target'][] = $activeSearchClass;
                $settings['label'][] = $activeSearchClass;
            }

            $this->cachedConfigs[$activeSearchClass] = $settings;
        }

        return $this->cachedConfigs[$activeSearchClass];
    }

    /**
     * Support method for getHandlers() -- load combined settings.
     *
     * @param string $activeSearchClass Active search class ID
     * @param string $activeHandler     Active search handler
     *
     * @return array
     */
    protected function getCombinedHandlers($activeSearchClass, $activeHandler)
    {
        // Build settings:
        $handlers = [];
        $selectedFound = false;
        $backupSelectedIndex = false;
        $settings = $this->getCombinedHandlerConfig($activeSearchClass);
        $typeCount = count($settings['type']);
        for ($i = 0; $i < $typeCount; $i++) {
            $type = $settings['type'][$i];
            $target = $settings['target'][$i];
            $label = $settings['label'][$i];

            if ($type == 'VuFind') {
                $options = $this->optionsManager->get($target);
                $j = 0;
                $basic = $options->getBasicHandlers();
                if (empty($basic)) {
                    $basic = ['' => ''];
                }
                foreach ($basic as $searchVal => $searchDesc) {
                    $j++;
                    $selected = $target == $activeSearchClass
                        && $activeHandler == $searchVal;
                    if ($selected) {
                        $selectedFound = true;
                    } else if ($backupSelectedIndex === false
                        && $target == $activeSearchClass
                    ) {
                        $backupSelectedIndex = count($handlers);
                    }
                    $handlers[] = [
                        'value' => $type . ':' . $target . '|' . $searchVal,
                        'label' => $j == 1 ? $label : $searchDesc,
                        'indent' => $j == 1 ? false : true,
                        'selected' => $selected
                    ];
                }
            } else if ($type == 'External') {
                $handlers[] = [
                    'value' => $type . ':' . $target, 'label' => $label,
                    'indent' => false, 'selected' => false
                ];
            }
        }

        // If we didn't find an exact match for a selected index, use a fuzzy
        // match:
        if (!$selectedFound && $backupSelectedIndex !== false) {
            $handlers[$backupSelectedIndex]['selected'] = true;
        }
        return $handlers;
    }
}