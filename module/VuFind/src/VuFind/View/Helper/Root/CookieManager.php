<?php

/**
 * CookieManager view helper
 *
 * PHP version 8
 *
 * Copyright (C) Hebis Verbundzentrale 2024.
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
 * @author   Thomas Wagener <wagener@hebis.uni-frankfurt.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */

namespace VuFind\View\Helper\Root;

/**
 * CookieManager view helper
 *
 * @category VuFind
 * @package  View_Helpers
 * @author   Thomas Wagener <wagener@hebis.uni-frankfurt.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */
class CookieManager extends \Laminas\View\Helper\AbstractHelper
{
    /**
     * Constructor
     *
     * @param \VuFind\Cookie\CookieManager $cookieManager Cookie manager
     */
    public function __construct(
        protected \VuFind\Cookie\CookieManager $cookieManager,
    ) {
    }

    /**
     * Get cookie manager.
     *
     * @return \VuFind\Cookie\CookieManager
     */
    public function __invoke(): \VuFind\Cookie\CookieManager
    {
        return $this->cookieManager;
    }
}
