<?php

/**
 * Cart Class
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
 * @package  Cart
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */

namespace Finna;

/**
 * Cart Class
 *
 * The data model object representing a user's book cart.
 *
 * @category VuFind
 * @package  Cart
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
class Cart extends \VuFind\Cart
{
    /**
     * Finna back-compatibility: Get cookie domain context (null if unset).
     *
     * @return string
     *
     * @TODO Eliminate calls to $this->cart()->getCookieDomain() and remove this method
     */
    public function getCookieDomain()
    {
        return $this->cookieManager->getDomain();
    }

    /**
     * Finna back-compatibility: Get cookie path ('/' if unset).
     *
     * @return string
     *
     * @TODO Eliminate calls to $this->cart()->getCookiePath() and remove this method
     */
    public function getCookiePath()
    {
        return $this->cookieManager->getPath();
    }

    /**
     * Finna back-compatibility: Get cookie SameSite attribute.
     *
     * @return string
     *
     * @TODO Eliminate calls to $this->cart()->getCookieSameSite() and remove this method
     */
    public function getCookieSameSite()
    {
        return $this->cookieManager->getSameSite();
    }
}
