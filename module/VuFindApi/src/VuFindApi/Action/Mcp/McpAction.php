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
use Mcp\Server;
use Mcp\Server\Transport\StreamableHttpTransport;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use VuFind\Action\AbstractAction;
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
        // TODO: this is a single permission for all MCP tools/resources; when OAuth support is added, consider
        // granting scopes per tool/resource instead, so different capabilities can require different access.
        $this->accessPermission = 'access.mcp';
        // permissionBehavior.ini's default of 'promptLogin' would redirect an MCP client to an HTML login
        // form; MCP clients need an HTTP-level denial instead.
        $this->accessDeniedBehavior = 'exception';
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

        // laminas-psr7bridge's Psr7ServerRequest::fromLaminas() writes the body into the stream without
        // rewinding it afterward, leaving the pointer at EOF; rewind so the transport can read it.
        $request->getBody()->rewind();
        $transport = new StreamableHttpTransport($request);
        $response = $this->server->run($transport);
        return $response;
    }
}
