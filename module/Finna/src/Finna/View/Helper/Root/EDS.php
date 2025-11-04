<?php

/**
 * Ebsco Discovery Service view helper
 *
 * PHP version 8
 *
 * Copyright (C) The National Library of Finland 2017.
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
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */

namespace Finna\View\Helper\Root;

/**
 * Ebsco Discovery Service view helper
 *
 * @category VuFind
 * @package  View_Helpers
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
class EDS extends \Laminas\View\Helper\AbstractHelper
{
    /**
     * EDS configuration
     *
     * @var \VuFind\Config\Config
     */
    protected $config = null;

    /**
     * Constructor
     *
     * @param \VuFind\Config\Config $config EDS configuration
     */
    public function __construct(\VuFind\Config\Config $config)
    {
        $this->config = $config;
    }

    /**
     * Check if EDS is available
     *
     * @return bool
     */
    public function isAvailable()
    {
        return !empty($this->config['General']['enabled']);
    }
}
