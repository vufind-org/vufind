<?php

/**
 * Browse database/journal view helper
 *
 * PHP version 8
 *
 * Copyright (C) The National Library of Finland 2015.
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
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */

namespace Finna\View\Helper\Root;

/**
 * Browse database/journal view helper
 *
 * @category VuFind
 * @package  View_Helpers
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
class Browse extends \VuFind\View\Helper\Root\Browse
{
    /**
     * Browser configuration
     *
     * @var \VuFind\Config\Config
     */
    protected $config;

    /**
     * Constructor
     *
     * @param \VuFind\Config\Config $config Browse configuration
     */
    public function __construct(\VuFind\Config\Config $config)
    {
        $this->config = $config;
    }

    /**
     * Check if Browse is available
     *
     * @param string $type Type (Database or Journal).
     *
     * @return bool
     */
    public function isAvailable($type)
    {
        return isset($this->config['General'][$type])
            && $this->config['General'][$type];
    }
}
