<?php

/**
 * Account menu view helper
 *
 * PHP version 8
 *
 * Copyright (C) Moravian library 2024.
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
 * @author   Josef Moravec <josef.moravec@mzk.cz>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */

namespace VuFind\View\Helper\Root;

/**
 * Account menu view helper
 *
 * @category VuFind
 * @package  View_Helpers
 * @author   Josef Moravec <josef.moravec@mzk.cz>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */
class AccountMenu extends AbstractMenuHelper
{
    /**
     * Create icon name for fines item
     *
     * @return string
     */
    public function finesIcon(): string
    {
        $icon = 'currency-'
            . strtolower($this->getView()->plugin('config')->get('config')->Site->defaultCurrency ?? 'usd');
        return $icon;
    }

    /**
     * Render account menu
     *
     * @param ?string $activeItem The name of current active item (optional)
     * @param string  $idPrefix   Element ID prefix
     *
     * @return string
     */
    public function render(?string $activeItem = null, string $idPrefix = ''): string
    {
        $contextHelper = $this->getView()->plugin('context');
        $menu = $this->getMenu();

        return $contextHelper->renderInContext(
            'myresearch/menu.phtml',
            [
                'menu' => $menu,
                'active' => $activeItem,
                'idPrefix' => $idPrefix,
                // set items for legacy backward compatibility, might be removed in future releases
                'items' => $menu['Account']['MenuItems'],
            ]
        );
    }
}
