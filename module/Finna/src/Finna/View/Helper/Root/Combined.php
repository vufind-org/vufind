<?php
/**
 * Combined results view helper
 *
 * PHP version 7
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
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301  USA
 *
 * @category VuFind
 * @package  View_Helpers
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:developer_manual Wiki
 */
namespace Finna\View\Helper\Root;

/**
 * Combined results view helper
 *
 * @category VuFind
 * @package  View_Helpers
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:developer_manual Wiki
 */
class Combined extends \Laminas\View\Helper\AbstractHelper
{
    /**
     * Combined search configuration
     *
     * @var \Laminas\Config\Config
     */
    protected $config;

    /**
     * Constructor
     *
     * @param \Laminas\Config\Config $config Combined configuration
     */
    public function __construct(\Laminas\Config\Config $config)
    {
        $this->config = $config;
    }

    /**
     * Check if combined results view is available
     *
     * @return bool
     */
    public function isAvailable()
    {
        return !isset($this->config['General']['enabled'])
            || $this->config['General']['enabled'];
    }
}
