<?php
/**
 * Cookie view helper
 *
 * PHP version 5
 *
 * Copyright (C) The National Library of Finland 2018.
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
 * @link     http://vufind.org   Main Site
 */
namespace Finna\View\Helper\Root;

/**
 * Cookie view helper
 *
 * @category VuFind
 * @package  View_Helpers
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org   Main Site
 */
class Cookie extends \Zend\View\Helper\AbstractHelper
{
    /**
     * Cookie manager
     *
     * @var \VuFind\Cookie\Manager
     */
    protected $cookieManager;

    /**
     * Constructor
     *
     * @param \VuFind\Cookie\Manager $cookieManager Cookie manager
     */
    public function __construct($cookieManager)
    {
        $this->cookieManager = $cookieManager;
    }

    /**
     * Get a cookie
     *
     * @param string $cookie Cookie name
     *
     * @return mixed
     */
    public function get($cookie)
    {
        return $this->cookieManager->get($cookie);
    }
}
