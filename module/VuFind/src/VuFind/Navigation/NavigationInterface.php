<?php

/**
 * Interface for VuFind navigation classes.
 *
 * PHP version 8
 *
 * Copyright (C) The National Library of Finland 2024-2026.
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
 * @package  Navigation
 * @author   Aleksi Peebles <aleksi.peebles@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */

namespace VuFind\Navigation;

use VuFind\Section\Plugin\SectionInterface;

/**
 * Interface for VuFind navigation classes.
 *
 * @category VuFind
 * @package  Navigation
 * @author   Aleksi Peebles <aleksi.peebles@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
interface NavigationInterface extends SectionInterface
{
    /**
     * Get processed and filtered menu configuration with groups and items to
     * display.
     *
     * @return array
     */
    public function getMenu(): array;

    /**
     * Get menu tabs for the current request.
     *
     * @return array Tab items under 'submenuItems' key, or empty array if no tabs
     */
    public function getCurrentTabs(): array;

    /**
     * Get parent path for the currently active item.
     *
     * @return array URIs of menu items in the path
     */
    public function getActiveItemParentPath(): array;
}
