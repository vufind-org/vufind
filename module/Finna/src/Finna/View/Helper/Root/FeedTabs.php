<?php
/**
 * View helper for feed tabs.
 *
 * PHP version 7
 *
 * Copyright (C) The National Library of Finland 2019.
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
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org   Main Site
 */
namespace Finna\View\Helper\Root;

/**
 * View helper for feed tabs.
 *
 * @category VuFind
 * @package  View_Helpers
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org   Main Site
 */
class FeedTabs extends \Laminas\View\Helper\AbstractHelper
{
    /**
     * Returns HTML for the widget.
     *
     * @param array $feedIds Feed ids to display.
     *
     * @return string
     */
    public function __invoke($feedIds)
    {
        $title = $feedIds['title'] ?? '';
        $ids = $feedIds['ids'];
        return $this->getView()->render(
            'Helpers/feedtabs.phtml',
            [
                'title' => $title,
                'id' => md5(json_encode($ids)),
                'feedIds' => $ids,
                'active' => array_shift($ids)
            ]
        );
    }
}
