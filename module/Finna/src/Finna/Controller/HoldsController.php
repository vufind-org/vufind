<?php

/**
 * Holds Controller
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2010.
 * Copyright (C) The National Library of Finland 2021.
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
 * along with this program; if not, see
 * <https://www.gnu.org/licenses/>.
 *
 * @category VuFind
 * @package  Controller
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */

namespace Finna\Controller;

use function in_array;
use function is_array;

/**
 * Controller for the user holds area.
 *
 * @category VuFind
 * @package  Controller
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */
class HoldsController extends \VuFind\Controller\HoldsController
{
    use FinnaUnsupportedFunctionViewTrait;
    use FinnaPersonalInformationSupportTrait;

    /**
     * Send list of holds to view
     *
     * @return mixed
     */
    public function listAction()
    {
        // Stop now if the user does not have valid catalog credentials available:
        if (!is_array($patron = $this->catalogLogin())) {
            return $patron;
        }

        if ($view = $this->createViewIfUnsupported('getMyHolds')) {
            return $view;
        }
        $view = parent::listAction();
        if (isset($view->recordList)) {
            $sort = $this->params()->fromQuery('sort', 'available');
            $supported = [
                'expire_asc',
                'expire_desc',
                'create_asc',
                'create_desc',
                'title_desc',
            ];
            if (in_array($sort, $supported)) {
                $view->recordList = $this->sortRequests($view->recordList, $sort);
            } else {
                $view->recordList = $this->sortRequestsByAvailability($view->recordList);
            }
            $sortList = [
                'available' => [
                    'desc' =>  'hold_sort_available',
                    'url' => '?sort=available',
                    'selected' => $sort === 'available',
                ],
                'expire_asc' => [
                    'desc' =>  'hold_sort_expire_asc',
                    'url' => '?sort=expire_asc',
                    'selected' => $sort === 'expire_asc',
                ],
                'expire_desc' => [
                    'desc' =>  'hold_sort_expire_desc',
                    'url' => '?sort=expire_desc',
                    'selected' => $sort === 'expire_desc',
                ],
                'create_asc' => [
                    'desc' =>  'hold_sort_create_asc',
                    'url' => '?sort=create_asc',
                    'selected' => $sort === 'create_asc',
                ],
                'create_desc' => [
                    'desc' =>  'hold_sort_create_desc',
                    'url' => '?sort=create_desc',
                    'selected' => $sort === 'create_desc',
                ],
                'title_desc' => [
                    'desc' => 'hold_sort_title',
                    'url' => '?sort=title_desc',
                    'selected' => $sort === 'title_desc',
                ],
            ];
            $view->sortList = $sortList;
        }
        $view->blocks = $this->getAccountBlocks($patron);
        return $view;
    }
}
