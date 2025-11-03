<?php

/**
 * AbstractMenu view helper
 *
 * PHP version 8
 *
 * Copyright (C) The National Library of Finland 2024.
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
 * @author   Aleksi Peebles <aleksi.peebles@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */

namespace VuFind\View\Helper\Root;

use VuFind\Navigation\AbstractMenu;

/**
 * AbstractMenu view helper
 *
 * @category VuFind
 * @package  View_Helpers
 * @author   Aleksi Peebles <aleksi.peebles@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */

abstract class AbstractMenuHelper extends \Laminas\View\Helper\AbstractHelper
{
    /**
     * Constructor
     *
     * @param AbstractMenu $menu Menu
     */
    public function __construct(protected AbstractMenu $menu)
    {
    }

    /**
     * Get all groups with items to display.
     *
     * @return array
     */
    public function getMenu(): array
    {
        return $this->menu->getMenu();
    }

    /**
     * Render menu
     *
     * @param ?string $activeItem The name of current active item (optional)
     * @param string  $idPrefix   Element ID prefix
     *
     * @return string
     */
    abstract public function render(?string $activeItem = null, string $idPrefix = ''): string;
}
