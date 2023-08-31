<?php

/**
 * ILS Pagination Helper
 *
 * This class helps build paginators for ILS-provided data.
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2018.
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
 * @package  ILS_Drivers
 * @author   Andrew S. Nagy <vufind-tech@lists.sourceforge.net>
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:ils_drivers Wiki
 */

namespace VuFind\ILS;

use function in_array;

/**
 * ILS Pagination Helper
 *
 * This class helps build paginators for ILS-provided data.
 *
 * @category VuFind
 * @package  ILS_Drivers
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:ils_drivers Wiki
 */
class PaginationHelper
{
    /**
     * Support method for getPagingSetup() -- validate the active sort option,
     * returning either a valid sort method or false.
     *
     * @param array  $functionConfig Function config returned from the ILS
     * @param string $sort           The unvalidated user sort parameter
     *
     * @return string|bool
     */
    protected function validateSort($functionConfig, $sort)
    {
        // If sort is disabled, all settings are invalid...
        if (empty($functionConfig['sort'])) {
            return false;
        }
        // If provided setting is valid, use it...
        if (isset($functionConfig['sort'][$sort])) {
            return $sort;
        }
        // At this point, we need to find a reasonable value, either the configured
        // default or the first valid sort value...
        if (isset($functionConfig['default_sort'])) {
            return $functionConfig['default_sort'];
        }
        return array_key_first($functionConfig['sort']);
    }

    /**
     * Support method for getPagingSetup() -- determine the list of sort options.
     *
     * @param array  $functionConfig Function config returned from the ILS
     * @param string $sort           Currently active sort option
     *
     * @return array
     */
    protected function getSortList($functionConfig, $sort)
    {
        $sortList = [];
        if (!empty($functionConfig['sort'])) {
            foreach ($functionConfig['sort'] as $key => $value) {
                $sortList[$key] = [
                    'desc' => $value,
                    'url' => '?sort=' . urlencode($key),
                    'selected' => $sort == $key,
                ];
            }
        }
        return $sortList;
    }

    /**
     * Get paging settings and request data for paged ILS requests.
     *
     * @param int    $page            Current page (1-based)
     * @param string $sort            Current sort setting (null for none)
     * @param int    $defaultPageSize Default page size
     * @param array  $functionConfig  Function config returned from the ILS
     *
     * @return array
     */
    public function getOptions(
        $page,
        $sort,
        $defaultPageSize,
        $functionConfig
    ) {
        // Get page and page size:
        $limit = $defaultPageSize;
        $ilsPaging = true;
        if (isset($functionConfig['max_results'])) {
            $limit = min([$functionConfig['max_results'], $limit]);
        } elseif (isset($functionConfig['page_size'])) {
            if (!in_array($limit, $functionConfig['page_size'])) {
                $limit = $functionConfig['default_page_size']
                    ?? $functionConfig['page_size'][0];
            }
        } else {
            $ilsPaging = false;
        }
        // Collect ILS call params
        $ilsParams = [];
        if ($sort = $this->validateSort($functionConfig, $sort)) {
            $ilsParams['sort'] = $sort;
        }
        if ($ilsPaging) {
            $ilsParams['page'] = $page >= 1 ? $page : 1;
            $ilsParams['limit'] = $limit;
        }
        $sortList = $this->getSortList($functionConfig, $sort);
        return compact('page', 'limit', 'ilsPaging', 'ilsParams', 'sortList');
    }

    /**
     * Build a paginator with the paging options and ILS results if necessary
     *
     * @param array $pageOptions Paging options and parameters (returned by the
     * getOptions method)
     * @param int   $count       Result count
     * @param array $records     Result records
     *
     * @return false|\Laminas\Paginator\Paginator
     */
    public function getPaginator($pageOptions, $count, $records)
    {
        $limit = $pageOptions['limit'];
        $page = $pageOptions['page'];
        if (($page - 1) * $limit >= $count && $page > 1) {
            throw new \VuFind\Exception\BadRequest('Page number out of range.');
        }
        if ($pageOptions['ilsPaging'] && $limit < $count) {
            $adapter = new \Laminas\Paginator\Adapter\NullFill($count);
        } elseif ($limit > 0 && $limit < $count) {
            $adapter = new \Laminas\Paginator\Adapter\ArrayAdapter($records);
        } else {
            return false;
        }
        $paginator = new \Laminas\Paginator\Paginator($adapter);
        $paginator->setItemCountPerPage($limit);
        $paginator->setCurrentPageNumber($page);
        return $paginator;
    }
}
