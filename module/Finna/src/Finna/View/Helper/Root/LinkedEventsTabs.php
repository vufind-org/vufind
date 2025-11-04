<?php

/**
 * View helper for LinkedEvents tabs.
 *
 * PHP version 8
 *
 * Copyright (C) The National Library of Finland 2020-2023.
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
 * @package  View_Helpers
 * @author   Jaro Ravila <jaro.ravila@helsinki.fi>
 * @author   Pasi Tiisanoja <pasi.tiisanoja@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org   Main Site
 */

namespace Finna\View\Helper\Root;

/**
 * View helper for LinkedEvents tabs.
 *
 * @category VuFind
 * @package  View_Helpers
 * @author   Jaro Ravila <jaro.ravila@helsinki.fi>
 * @author   Pasi Tiisanoja <pasi.tiisanoja@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org   Main Site
 */
class LinkedEventsTabs extends \Laminas\View\Helper\AbstractHelper
{
    /**
     * Returns HTML for the widget.
     *
     * @param array $params parameters
     *
     * @return string
     */
    public function __invoke($params)
    {
        $tabs = $params['tabs'] ?? [];
        $active = $params['active'] ?? $tabs[0]['title'];
        $allEventsLink = $params['link'] ?? '';
        $searchTools = $params['searchTools'] ?? 'show';
        $navStyle = $params['navStyle'] ?? '';
        $showMobileAccordion = $params['showMobileAccordion'] ?? true;
        $limit = $params['limit'] ?? 30;

        return $this->getView()->render(
            'Helpers/linkedeventstabs.phtml',
            [
                'tabs' => $tabs,
                'active' => $active,
                'allEventsLink' => $allEventsLink,
                'searchTools' => $searchTools,
                'navStyle' => $navStyle,
                'showMobileAccordion' => $showMobileAccordion,
                'limit' => $limit,
                'id' => md5(json_encode($tabs)),
            ]
        );
    }
}
