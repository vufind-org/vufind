<?php

/**
 * OAuth2 token request action.
 *
 * PHP version 8
 *
 * Copyright (C) The National Library of Finland 2022-2026.
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
 * @package  Action
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */

namespace VuFind\Action\OAuth2;

use League\OAuth2\Server\Exception\OAuthServerException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use VuFind\ActionHelper\ResponseHelper;

/**
 * OAuth2 token request action.
 *
 * @category VuFind
 * @package  Action
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */
class TokenAction extends AbstractOAuth2Action
{
    /**
     * Handle a token request.
     *
     * @param ServerRequestInterface $request  Server request
     * @param ResponseInterface      $response Response
     *
     * @return ResponseInterface
     */
    public function action(
        ServerRequestInterface $request,
        ResponseInterface $response,
    ): ResponseInterface {
        $this->disableSessionWrites();
        $server = $this->oauth2Service->getAuthorizationServer(null);
        try {
            $response = $server->respondToAccessTokenRequest($request, $response);
            return $this->getHelper(ResponseHelper::class)->addCorsHeaders($response);
        } catch (OAuthServerException $e) {
            return $this->handleOAuth2Exception($response, 'Access token request', $e);
        } catch (\Exception $e) {
            return $this->handleOAuth2ServerException($response, 'Access token request', $e);
        }
    }
}
