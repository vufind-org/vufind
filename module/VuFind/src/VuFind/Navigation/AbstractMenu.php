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
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301  USA
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
     * Get available menu items
     *
     * @return array
     */
    public function getItems(): array
    {
        $items = array_filter(
            $this->config['MenuItems'] ?? $this->getDefaultItems(),
            function ($item) {
                return !isset($item['checkMethod']) || $this->{$item['checkMethod']}();
            }
        );
        return $items;
    }

    /**
     * Get default menu items
     *
     * @return array
     */
    abstract protected function getDefaultItems(): array;
}
