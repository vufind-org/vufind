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
     * Constructor
     *
     * @param OptionsManager $optionsManager Search options plugin manager
     * @param array          $config         Configuration for search box
     */
    public function __construct(OptionsManager $optionsManager, $config = array())
    {
        $this->optionsManager = $optionsManager;
        $this->config = $config;
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
        $results = array();
        $i = 0;
        foreach ($filterList as $field => $data) {
            foreach ($data as $value) {
                $results[] = array(
                    'id' => 'applied_filter_' . ++$i,
                    'value' => "$field:\"$value\""
                );
            }
        }
        $i = 0;
        foreach ($checkboxFilters as $current) {
            if ($current['selected']) {
                $results[] = array(
                    'id' => 'applied_checkbox_filter_' . ++$i,
                    'value' => $current['filter']
                );
            }
        }
        return $results;
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
        $handlers = array();
        $options = $this->optionsManager->get($activeSearchClass);
        foreach ($options->getBasicHandlers() as $searchVal => $searchDesc) {
            $handlers[] = array(
                'value' => $searchVal, 'label' => $searchDesc, 'indent' => false,
                'selected' => ($activeHandler == $searchVal)
            );
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
        // Load and validate configuration:
        $settings = isset($this->config['CombinedHandlers'])
            ? $this->config['CombinedHandlers'] : array();
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

        return $settings;
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
        $handlers = array();
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
                foreach ($options->getBasicHandlers() as $searchVal => $searchDesc) {
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
                    $handlers[] = array(
                        'value' => $type . ':' . $target . '|' . $searchVal,
                        'label' => $j == 1 ? $label : $searchDesc,
                        'indent' => $j == 1 ? false : true,
                        'selected' => $selected
                    );
                }
            } else if ($type == 'External') {
                $handlers[] = array(
                    'value' => $type . ':' . $target, 'label' => $label,
                    'indent' => false, 'selected' => false
                );
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