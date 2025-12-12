<?php

/**
 * Controller for Model Context Protocol (MCP)
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2025.
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
 * @package  Controller
 * @author   Maccabee Levine <msl321@lehigh.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */

namespace VuFindApi\Controller;

use Laminas\Diactoros\ServerRequestFactory;
use Laminas\Http\Header\ContentType;
use Laminas\Http\Response;
use Laminas\Psr7Bridge\Psr7Response;
use Laminas\ServiceManager\ServiceLocatorInterface;
use Mcp\Exception\ServiceNotFoundException;
use Mcp\Schema\JsonRpc\Error;
use Mcp\Server;
use Mcp\Server\Transport\StreamableHttpTransport;
use Psr\Http\Message\ResponseInterface;
use VuFind\Controller\AbstractBase;

/**
 * Controller for Model Context Protocol (MCP)
 *
 * @category VuFind
 * @package  Controller
 * @author   Maccabee Levine <msl321@lehigh.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
class McpController extends AbstractBase
{
    /**
     * Permission required for all MCP endpoints
     *
     * @var string
     */
    protected $baseAccessPermission = 'access.mcp';

    /**
     * JSON schema response code to represent an authorization error. This
     * is not defined by any standard, except that the 32xxx range is app-specific.
     *
     * @var int
     */
    protected int $AUTH_ERROR = -32003;

    /**
     * Constructor
     *
     * @param ServiceLocatorInterface $sm     Service manager
     * @param ?Server                 $server MCP Server
     */
    public function __construct(ServiceLocatorInterface $sm, protected ?Server $server)
    {
        parent::__construct($sm);
    }

    /**
     * MCP action
     *
     * @return \Laminas\Http\Response
     */
    public function mcpAction()
    {
        if (!$this->server) {
            throw new ServiceNotFoundException('This MCP server is not enabled.');
        }

        // $content = json_decode($this->params()->getController()->getRequest()->getContent(), true);
        // $mcpMethod = $content['method'] ?? '';
        $mcpMethod = null;
        if ($this->isAccessDenied($mcpMethod)) {
            $messageId = $content['messageId'] ?? '';
            return $this->outputAuthError($messageId);
        }

        // Adapting: https://github.com/modelcontextprotocol/php-sdk/blob/main/docs/transports.md
        // and https://github.com/vufind-org/vufind/pull/4672/files#diff-89cf777c1454a4e7f97e51f800ca68001e874a555cb19ec27135779b76ccd8f4

        // Convert to PSR-7 request
        $psrRequest = ServerRequestFactory::fromGlobals();
        foreach ($this->params()->fromRoute() as $routeParam => $value) {
            $psrRequest = $psrRequest->withAttribute($routeParam, $value);
        }

        // Process with MCP
        $transport = new StreamableHttpTransport($psrRequest);
        $psrResponse = $this->server->run($transport);

        // Convert back to Laminas response
        if ($psrResponse instanceof ResponseInterface) {
            return Psr7Response::toLaminas($psrResponse);
        }
        throw new \Exception('Unexpected state reached.');
    }

    /**
     * Check whether access is denied based on the MCP method.
     *
     * @param string $mcpMethod MCP method
     *
     * @return bool
     */
    protected function isAccessDenied($mcpMethod): bool
    {
        $auth = $this->getService(\Lmc\Rbac\Mvc\Service\AuthorizationService::class);
        return $mcpMethod
            ? !$auth->isGranted($this->baseAccessPermission . '.' . $mcpMethod)
            : !$auth->isGranted($this->baseAccessPermission);
    }

    /**
     * Output an authorization error.
     *
     * @param string $messageId The MCP message Id.
     *
     * @return Response
     */
    protected function outputAuthError(string $messageId): Response
    {
        $error = new Error($messageId, $this->AUTH_ERROR, 'Access denied');
        $response = $this->getResponse();
        $response->setStatusCode(403);
        $contentTypeHeader = new ContentType();
        $contentTypeHeader->setMediaType('application/json');
        $headers = $response->getHeaders();
        $headers->addHeader($contentTypeHeader);
        $response->setContent(json_encode($error->jsonSerialize()));
        return $response;
    }
}
