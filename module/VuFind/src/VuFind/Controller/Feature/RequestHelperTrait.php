<?php

/**
 * VuFind Action Feature Trait - Alphabetic browse support
 * Depends on direct access to the Service Manager.
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2025
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
 * @package  Controller_Plugins
 * @author   Juha Luoma <juha.luoma@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */

namespace VuFind\Controller\Feature;

use function is_callable;

/**
 * VuFind Action Feature Trait - Alphabetic browse support
 *
 * @category VuFind
 * @package  Controller_Plugins
 * @author   Juha Luoma <juha.luoma@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */
trait RequestHelperTrait
{
    /**
     * Get the url parameters
     *
     * @param string $param          A key to check the url params for. If set to null, will return array
     *                               with combined keys and values from both post and query.
     * @param bool   $prioritizePost If true, check the POST params first
     * @param mixed  $default        Default value if no value found
     *
     * @return string|array
     */
    protected function getParam($param, $prioritizePost = true, $default = null)
    {
        $primary = $prioritizePost ? 'fromPost' : 'fromQuery';
        $secondary = $prioritizePost ? 'fromQuery' : 'fromPost';
        return $this->params()->$primary($param)
            ?? $this->params()->$secondary($param)
            ?? $default;
    }

    /**
     * Get all parameters from post and query as an associative array.
     *
     * @return array
     */
    protected function getParamArray(): array
    {
        return $this->params()->fromPost() + $this->params()->fromQuery();
    }

    /**
     * Get header field value
     *
     * @param string $headerKey Header field key to get
     *
     * @return mixed
     */
    protected function getHeader(string $headerKey): mixed
    {
        $header = $this->params()->fromHeader($headerKey);
        return is_callable([$header, 'getFieldValue']) ? $header->getFieldValue() : null;
    }
}
