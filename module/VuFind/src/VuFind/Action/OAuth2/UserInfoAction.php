<?php

/**
 * OAuth2 user info action.
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
 * OAuth2 user info action.
 *
 * @category VuFind
 * @package  Action
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */
class UserInfoAction extends AbstractOAuth2Action
{
    /**
     * Handle a user info request.
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
        try {
            $responseHelper = $this->getHelper(ResponseHelper::class);
            $response = $responseHelper->getJsonResponse(
                $response,
                $this->oauth2Service->getUserInfo($request)
            );
            return $responseHelper->addCorsHeaders($response);
        } catch (OAuthServerException $e) {
            return $this->handleOAuth2Exception($response, 'User info request', $e);
        } catch (\Exception $e) {
            return $this->handleOAuth2ServerException($response, 'User info request', $e);
        }
    }
}
