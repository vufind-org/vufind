<?php

/**
 * Abstract base class for OAuth2 actions.
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
use VuFind\Action\AbstractTemplateRenderingAction;
use VuFind\ActionHelper\ResponseHelper;
use VuFind\OAuth2\OAuth2ServerService;
use VuFind\ServiceManager\Factory\Autowire;

/**
 * Abstract base class for OAuth2 actions.
 *
 * @category VuFind
 * @package  Action
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */
abstract class AbstractOAuth2Action extends AbstractTemplateRenderingAction
{
    /**
     * Constructor.
     *
     * @param OAuth2ServerService $oauth2Service OAuth2 server service
     */
    #[Autowire]
    public function __construct(
        protected OAuth2ServerService $oauth2Service,
    ) {
        parent::__construct();
    }

    /**
     * Preprocess a request before the actual action is executed.
     *
     * This method is executed just before the actual action (i.e. after permission checks etc.).
     * It is meant for preprocessing of requests in a shared base class of multiple actions.
     * It may return a suitable response or throw an exception if there are issues.
     *
     * @param ServerRequestInterface $request  Request
     * @param ResponseInterface      $response Response
     *
     * @return ?ResponseInterface
     */
    protected function preprocessRequest(
        ServerRequestInterface $request,
        ResponseInterface $response
    ): ?ResponseInterface {
        if ($request->getMethod() === 'OPTIONS') {
            // Disable session writes
            $this->disableSessionWrites();
            return $this->getHelper(ResponseHelper::class)->addCorsHeaders($response->withStatus(204));
        }
        return null;
    }

    /**
     * Create a server error response from a returnable OAuth2 exception.
     *
     * @param ResponseInterface    $response Response
     * @param string               $function Function description
     * @param OAuthServerException $e        Exception
     *
     * @return ResponseInterface
     */
    protected function handleOAuth2ServerException(
        ResponseInterface $response,
        string $function,
        OAuthServerException $e
    ): ResponseInterface {
        $this->logError("$function failed: " . (string)$e);

        return $this->convertOAuthServerExceptionToResponse($response, $e);
    }

    /**
     * Create a server error response from a non-OAuth2 exception.
     *
     * @param ResponseInterface $response Response
     * @param string            $function Function description
     * @param \Exception        $e        Exception
     *
     * @return ResponseInterface
     */
    protected function handleOAuth2GenericException(
        ResponseInterface $response,
        string $function,
        \Exception $e
    ): ResponseInterface {
        $this->logError("$function exception: " . (string)$e);

        return $this->convertOAuthServerExceptionToResponse(
            $response,
            OAuthServerException::serverError('Server side issue')
        );
    }

    /**
     * Convert an instance of OAuthServerException to a response.
     *
     * @param ResponseInterface    $response  Response
     * @param OAuthServerException $exception Exception
     *
     * @return ResponseInterface
     */
    protected function convertOAuthServerExceptionToResponse(
        ResponseInterface $response,
        OAuthServerException $exception
    ): ResponseInterface {
        $response = $exception->generateHttpResponse($response);
        return $this->getHelper(ResponseHelper::class)->addCorsHeaders($response);
    }
}
