<?php

/**
 * Action for Model Context Protocol (MCP).
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

namespace VuFindApi\Action\Mcp;

use Mcp\Exception\ServiceNotFoundException;
use Mcp\Schema\JsonRpc\Error;
use Mcp\Server;
use Mcp\Server\Transport\StreamableHttpTransport;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use VuFind\Action\AbstractAction;
use VuFind\ActionHelper\PermissionHelper;
use VuFind\ServiceManager\Factory\Autowire;
use VuFindApi\Mcp\ServerProvider;

/**
 * Action for Model Context Protocol (MCP).
 *
 * @category VuFind
 * @package  Controller
 * @author   Maccabee Levine <msl321@lehigh.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
class McpAction extends AbstractAction
{
    /**
     * Permission required for all MCP endpoints.
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

    protected ?Server $server;

    /**
     * Constructor.
     *
     * @param ServerProvider $serverProvider MCP server provider
     */
    #[Autowire]
    public function __construct(
        ServerProvider $serverProvider
    ) {
        parent::__construct();
        $this->server = $serverProvider->getServer();
    }

    /**
     * Process an MCP request.
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
        if (!$this->server) {
            throw new ServiceNotFoundException('This MCP server is not enabled.');
        }

        // $content = json_decode($this->params()->getController()->getRequest()->getContent(), true);
        // $mcpMethod = $content['method'] ?? '';
        $mcpMethod = null;
        if ($this->isAccessDenied($mcpMethod)) {
            // $messageId = $content['messageId'] ?? '';
            $messageId = '';
            return $this->outputAuthError($messageId);
        }

        // laminas-psr7bridge's Psr7ServerRequest::fromLaminas() writes the body into the stream without
        // rewinding it afterward, leaving the pointer at EOF; rewind so the transport can read it.
        $request->getBody()->rewind();
        $transport = new StreamableHttpTransport($request);
        $response = $this->server->run($transport);
        return $response;
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
        $permissions = $this->getHelper(PermissionHelper::class);
        return $mcpMethod
            ? !$permissions->isAuthorized($this->baseAccessPermission . '.' . $mcpMethod)
            : !$permissions->isAuthorized($this->baseAccessPermission);
    }

    /**
     * Output an authorization error.
     *
     * @param string $messageId The MCP message Id.
     *
     * @return ResponseInterface
     */
    protected function outputAuthError(string $messageId): ResponseInterface
    {
        $error = new Error($messageId, $this->AUTH_ERROR, 'Access denied');
        $response = $this->response
            ->withStatus(403)
            ->withHeader('Content-Type', 'application/json');
        return $response;
    }
}
