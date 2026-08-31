<?php

/**
 * Action helper for generating responses.
 *
 * PHP version 8
 *
 * Copyright (C) The National Library of Finland 2026.
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
 * @package  Action_Helper
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:hierarchy_components Wiki
 */

namespace VuFind\ActionHelper;

use Psr\Http\Message\ResponseInterface;

/**
 * Action helper for generating responses.
 *
 * @category VuFind
 * @package  Action_Helper
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:hierarchy_components Wiki
 */
class ResponseHelper implements HelperInterface
{
    /**
     * Construct an HTTP 205 (refresh) response. Useful for reporting success in the lightbox without actually rendering
     * content.
     *
     * @param ResponseInterface $response Response
     * @param bool              $forceGet If true, sends a custom header indicating that the page should be reloaded
     * with a GET request. This can be useful when it is known that the current page only receives transient params in
     * a POST request (such as canceling of holds).
     *
     * @return ResponseInterface
     */
    public function getRefreshResponse(ResponseInterface $response, bool $forceGet = false): ResponseInterface
    {
        $response = $response->withStatus(205);
        if ($forceGet) {
            $response = $response->withHeader('X-VuFind-Refresh-Method', 'GET');
        }
        return $response;
    }
}
