<?php

/**
 * Search box view helper
 *
 * PHP version 8
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
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301  USA
 *
 * @category VuFind
 * @package  View_Helpers
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */

namespace VuFind\View\Helper\Root;

use VuFind\Search\Options\PluginManager as OptionsManager;

use function count;
use function in_array;
use function is_array;

/**
 * Search box view helper
 *
 * @category VuFind
 * @package  View_Helpers
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
class SearchBox extends \Laminas\View\Helper\AbstractHelper
{
    /**
     * Configuration for search box.
     *
     * @var array
     */
    protected $config;

    /**
     * Alphabrowse settings for search box.
     *
     * @var array
     */
    protected $alphabrowseConfig;

    /**
     * Placeholders from config.ini
     *
     * @var array
     */
    protected $placeholders;

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
     * @param OptionsManager $optionsManager    Search options plugin manager
     * @param array          $config            Configuration for search box
     * @param array          $placeholders      Array of placeholders keyed by
     * backend
     * @param array          $alphabrowseConfig source => label config for
     * alphabrowse options to display in combined box (empty for none)
     */
    public function __construct(
        OptionsManager $optionsManager,
        $config = [],
        $placeholders = [],
        $alphabrowseConfig = []
    ) {
        $this->optionsManager = $optionsManager;
        $this->config = $config;
        $this->alphabrowseConfig = $alphabrowseConfig;
        $this->placeholders = $placeholders;
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
     * Is autocomplete enabled for the current context?
     *
     * @param string $activeSearchClass Active search class ID
     *
     * @return bool
     */
    public function autocompleteAutoSubmit($activeSearchClass)
    {
        $options = $this->optionsManager->get($activeSearchClass);
        return $options->autocompleteAutoSubmit();
    }

    /**
     * Get JSON-encoded configuration for autocomplete query formatting.
     *
     * @param string $activeSearchClass Active search class ID
     *
     * @return string
     */
    public function autocompleteFormattingRulesJson($activeSearchClass): string
    {
        if ($this->combinedHandlersActive()) {
            $rules = [];
            $settings = $this->getCombinedHandlerConfig($activeSearchClass);
            foreach ($settings['target'] ?? [] as $i => $target) {
                if (($settings['type'][$i] ?? null) === 'VuFind') {
                    $options = $this->optionsManager->get($target);
                    $handlerRules = $options->getAutocompleteFormattingRules();
                    foreach ($handlerRules as $key => $val) {
                        $rules["VuFind:$target|$key"] = $val;
                    }
                }
            }
        } else {
            $options = $this->optionsManager->get($activeSearchClass);
            $rules = $options->getAutocompleteFormattingRules();
        }
        return json_encode($rules);
    }

    /**
     * Are alphabrowse options configured to display in the search options
     * drop-down?
     *
     * @return bool
     */
    public function alphaBrowseOptionsEnabled()
    {
        // Alphabrowse options depend on combined handlers:
        return $this->combinedHandlersActive() && !empty($this->alphabrowseConfig);
    }

    /**
     * Are combined handlers enabled?
     *
     * @return bool
     */
    public function combinedHandlersActive()
    {
        return $this->config['General']['combinedHandlers'] ?? false;
    }

    /**
     * Helper method: get special character to represent operator in filter
     *
     * @param string $operator Operator
     *
     * @return string
     */
    protected function getOperatorCharacter($operator)
    {
        static $map = ['NOT' => '-', 'OR' => '~'];
        return $map[$operator] ?? '';
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
                $results[] = is_array($value)
                    ? $this->getOperatorCharacter($value['operator'] ?? '')
                    . $value['field'] . ':"' . $value['value'] . '"'
                    : "$field:\"$value\"";
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
            if (
                $current['selected'] && !in_array($normalized, $results)
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
     * Get placeholder text from config using the activeSearchClass as key
     *
     * @param string $activeSearchClass Active search class ID
     *
     * @return string
     */
    public function getPlaceholderText($activeSearchClass)
    {
        // Searchbox place
        if (!empty($this->placeholders)) {
            return $this->placeholders[$activeSearchClass]
                ?? $this->placeholders['default']
                ?? null;
        }
        return null;
    }

    /**
     * Get an array of the configured virtual keyboard layouts
     *
     * @return array
     */
    public function getKeyboardLayouts()
    {
        return $this->config['VirtualKeyboard']['layouts'] ?? [];
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
     * Get number of active filters
     *
     * @param array $checkboxFilters Checkbox filters
     * @param array $filterList      Other filters
     *
     * @return int
     */
    public function getFilterCount($checkboxFilters, $filterList)
    {
        $result = 0;
        foreach ($checkboxFilters as $filter) {
            if ($filter['selected']) {
                ++$result;
            }
        }
        foreach ($filterList as $filter) {
            $result += count($filter);
        }
        return $result;
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
                'selected' => ($activeHandler == $searchVal),
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
            $settings = $this->config['CombinedHandlers'] ?? [];
            if (empty($settings)) {
                throw new \Exception('CombinedHandlers configuration missing.');
            }
            $typeCount = count($settings['type']);
            if (
                $typeCount != count($settings['target'])
                || $typeCount != count($settings['label'])
            ) {
                throw new \Exception('CombinedHandlers configuration incomplete.');
            }

            // Fill in missing group settings, if necessary:
            if (count($settings['group'] ?? []) < $typeCount) {
                $settings['group'] = array_fill(0, $typeCount, false);
            }

            // Add configuration for the current search class if it is not already
            // present:
            if (!in_array($activeSearchClass, $settings['target'])) {
                $settings['type'][] = 'VuFind';
                $settings['target'][] = $activeSearchClass;
                $settings['label'][] = $activeSearchClass;
                $settings['group'][]
                    = $this->config['General']['defaultGroupLabel'] ?? false;
            }

            $this->cachedConfigs[$activeSearchClass] = $settings;
        }

        return $this->cachedConfigs[$activeSearchClass];
    }

    /**
     * Support method for getCombinedHandlers(): get alphabrowse options.
     *
     * @param string $activeHandler Current active search handler
     * @param bool   $indent        Should we indent these options?
     *
     * @return array
     */
    protected function getAlphabrowseHandlers($activeHandler, $indent = true)
    {
        $alphaBrowseBase = ($this->getView()->plugin('url'))('alphabrowse-home');
        $labelPrefix = $this->getView()->translate('Browse Alphabetically') . ': ';
        $handlers = [];
        foreach ($this->alphabrowseConfig as $source => $label) {
            $alphaBrowseUrl = $alphaBrowseBase . '?source=' . urlencode($source)
                . '&from=';
            $handlers[] = [
                'value' => 'External:' . $alphaBrowseUrl,
                'label' => $labelPrefix . $this->getView()->translate($label),
                'indent' => $indent,
                'selected' => $activeHandler == 'AlphaBrowse:' . $source,
                'group' => $this->config['General']['alphaBrowseGroup'] ?? false,
            ];
        }
        return $handlers;
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
        $backupSelectedIndex = false;
        $addedBrowseHandlers = false;
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
                    if (
                        !$selected
                        && $backupSelectedIndex === false
                        && $target == $activeSearchClass
                    ) {
                        $backupSelectedIndex = count($handlers);
                    }
                    // Depending on whether or not the current section has a label,
                    // we'll either want to override the first label and indent
                    // subsequent ones, or else use all default labels without
                    // any indentation.
                    if (empty($label)) {
                        $finalLabel = $searchDesc;
                        $indent = false;
                    } else {
                        $finalLabel = $j == 1 ? $label : $searchDesc;
                        $indent = $j == 1 ? false : true;
                    }
                    $handlers[] = [
                        'value' => $type . ':' . $target . '|' . $searchVal,
                        'label' => $finalLabel,
                        'indent' => $indent,
                        'selected' => $selected,
                        'group' => $settings['group'][$i],
                    ];
                }

                // Should we add alphabrowse links?
                if ($target === 'Solr' && $this->alphaBrowseOptionsEnabled()) {
                    $addedBrowseHandlers = true;
                    $handlers = array_merge(
                        $handlers,
                        // Only indent alphabrowse handlers if label is non-empty:
                        $this->getAlphaBrowseHandlers($activeHandler, !empty($label))
                    );
                }
            } elseif ($type == 'External') {
                $handlers[] = [
                    'value' => $type . ':' . $target, 'label' => $label,
                    'indent' => false, 'selected' => false,
                    'group' => $settings['group'][$i],
                ];
            }
        }

        // If we didn't add alphabrowse links above as part of the Solr section
        // but we are configured to include them, we should add them now:
        if (!$addedBrowseHandlers && $this->alphaBrowseOptionsEnabled()) {
            $handlers = array_merge(
                $handlers,
                $this->getAlphaBrowseHandlers($activeHandler, false)
            );
        }

        // If we didn't find an exact match for a selected index, use a fuzzy
        // match (do the check here since it could be an AlphaBrowse index too):
        $selectedFound = in_array(true, array_column($handlers, 'selected'), true);
        if (!$selectedFound && $backupSelectedIndex !== false) {
            $handlers[$backupSelectedIndex]['selected'] = true;
        }
        return $handlers;
    }
}
