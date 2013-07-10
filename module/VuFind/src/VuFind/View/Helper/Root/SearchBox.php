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
     * drop-down or hidden field. Returns an array of arrays with 'value', 'label'
     * and 'selected' keys.
     *
     * @param string                      $activeSearchClass Active search class ID
     * @param string                      $activeHandler     Active search handler
     * @param \VuFind\Search\Base\Options $options           Current search options
     *
     * @return array
     */
    public function getHandlers($activeSearchClass, $activeHandler, $options)
    {
        $handlers = array();
        foreach ($options->getBasicHandlers() as $searchVal => $searchDesc) {
            $handlers[] = array(
                'value' => $searchVal, 'label' => $searchDesc,
                'selected' => ($activeHandler == $searchVal)
            );
        }
        return $handlers;
    }
}