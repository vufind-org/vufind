<?php

/**
 * Session view helper
 *
 * PHP version 8
 *
 * Copyright (C) The National Library of Finland 2023.
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
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */

namespace VuFind\View\Helper\Root;

use Laminas\Session\Container as SessionContainer;

/**
 * Session view helper
 *
 * @category VuFind
 * @package  View_Helpers
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
class Session extends \Laminas\View\Helper\AbstractHelper
{
    /**
     * Session container
     *
     * @var SessionContainer
     */
    protected $sessionContainer;

    /**
     * Config constructor.
     *
     * @param SessionContainer $sessionContainer Session container
     */
    public function __construct(SessionContainer $sessionContainer)
    {
        $this->sessionContainer = $sessionContainer;
    }

    /**
     * Return this object
     *
     * @return Session
     */
    public function __invoke(): Session
    {
        return $this;
    }

    /**
     * Get an item from the session container
     *
     * @param string $name Item name
     *
     * @return mixed
     */
    public function get(string $name)
    {
        return $this->sessionContainer->$name ?? null;
    }

    /**
     * Put an item to the session container
     *
     * @param string $name  Item name
     * @param mixed  $value Item value
     *
     * @return mixed Previous value
     */
    public function put(string $name, $value)
    {
        $oldValue = $this->sessionContainer->$name ?? null;
        $this->sessionContainer->$name = $value;
        return $oldValue;
    }
}
