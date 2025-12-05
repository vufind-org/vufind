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
use Laminas\Psr7Bridge\Psr7Response;
use Laminas\ServiceManager\ServiceLocatorInterface;
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
    // protected $accessPermission = 'access.mcp';

    /**
     * Constructor
     *
     * @param ServiceLocatorInterface $sm     Service manager
     * @param Server                  $server MCP Server
     */
    public function __construct(ServiceLocatorInterface $sm, protected Server $server)
    {
        return parent::__construct($sm);
    }

    /**
     * MCP action
     *
     * @return \Laminas\Http\Response
     */
    public function mcpAction()
    {
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
}
