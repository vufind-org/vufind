<?php

/**
 * OAuth2 Controller
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
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.    See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, see
 * <https://www.gnu.org/licenses/>.
 *
 * @category VuFind
 * @package  Controller
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */

namespace Finna\Controller;

use Laminas\Http\Response;

/**
 * OAuth2 Controller
 *
 * Provides authorization support for external systems
 *
 * @category VuFind
 * @package  Controller
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */
class OAuth2Controller extends \VuFind\Controller\OAuth2Controller
{
    /**
     * Create a server error response from a returnable exception.
     *
     * @param string     $function Function description
     * @param \Exception $e        Exception
     *
     * @return Response
     */
    protected function handleOAuth2Exception(string $function, \Exception $e): Response
    {
        $this->logError("$function exception: " . (string)$e);

        return $this->convertOAuthServerExceptionToResponse($e);
    }
}
