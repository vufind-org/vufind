<?php

/**
 * Abstract menu base class
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
 * @package  Navigation
 * @author   Aleksi Peebles <aleksi.peebles@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */

namespace VuFind\Navigation;

/**
 * Abstract menu base class
 *
 * @category VuFind
 * @package  Navigation
 * @author   Aleksi Peebles <aleksi.peebles@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
abstract class AbstractMenu implements NavigationInterface
{
    /**
     * Constructor
     *
     * @param array $config Menu configuration
     */
    public function __construct(protected array $config)
    {
    }

    /**
     * Get all groups with items to display.
     *
     * @return array
     */
    public function getMenu(): array
    {
        $menu = $this->config ?: static::getDefaultMenuConfig();

        $availableGroups = [];
        foreach ($this->filterAvailable($menu) as $name => $group) {
            // skip groups without items to display
            if ($items = $this->filterAvailable($group['MenuItems'])) {
                $group['MenuItems'] = $items;
                $availableGroups[$name] = $group;
            }
        }

        return $availableGroups;
    }

    /**
     * Get available items from a given list.
     *
     * @param array $list Items to filter
     *
     * @return array
     */
    protected function filterAvailable(array $list): array
    {
        return array_filter(
            $list,
            function ($item) {
                return !isset($item['checkMethod']) || $this->{$item['checkMethod']}();
            }
        );
    }

    /**
     * Get default menu configuration
     *
     * @return array
     */
    abstract public static function getDefaultMenuConfig(): array;
}
