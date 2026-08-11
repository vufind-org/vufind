<?php

/**
 * Unit tests for the MCP action.
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2026.
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
 * @package  Tests
 * @author   Maccabee Levine <msl321@lehigh.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */

namespace VuFindTest\Action\Mcp;

use Laminas\Diactoros\Response;
use Laminas\Diactoros\ServerRequest;
use Laminas\Diactoros\Stream;
use Mcp\Exception\ServiceNotFoundException;
use Mcp\Server;
use PHPUnit\Framework\TestCase;
use VuFindApi\Action\Mcp\McpAction;
use VuFindApi\Mcp\ServerProvider;
use VuFindTest\Feature\ReflectionTrait;

use function strlen;

/**
 * Unit tests for the MCP action.
 *
 * @category VuFind
 * @package  Tests
 * @author   Maccabee Levine <msl321@lehigh.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */
class McpActionTest extends TestCase
{
    use ReflectionTrait;

    /**
     * Build an action wired to a stubbed ServerProvider.
     *
     * @param ?Server $server Server for the provider to return (null simulates a disabled MCP server)
     *
     * @return McpAction
     */
    protected function getAction(?Server $server): McpAction
    {
        $serverProvider = $this->createMock(ServerProvider::class);
        $serverProvider->method('getServer')->willReturn($server);
        return new McpAction($serverProvider);
    }

    /**
     * Test that the constructor wires up the framework's own access-control mechanism
     * (AbstractAction::$accessPermission / $accessDeniedBehavior), rather than defining its own.
     *
     * @return void
     */
    public function testConstructorSetsAccessControlProperties(): void
    {
        $action = $this->getAction(null);

        $this->assertSame('access.mcp', $this->getProperty($action, 'accessPermission'));

        // The default 'promptLogin' behavior would redirect an MCP client to an HTML login form,
        // so this must be overridden to 'exception' for an API-style endpoint.
        $this->assertSame('exception', $this->getProperty($action, 'accessDeniedBehavior'));
    }

    /**
     * Test that action() refuses to run when the MCP server is disabled (ServerProvider::getServer()
     * returns null).
     *
     * @return void
     */
    public function testActionThrowsWhenServerIsNotEnabled(): void
    {
        $action = $this->getAction(null);

        $this->expectException(ServiceNotFoundException::class);
        $action->action(new ServerRequest(), new Response());
    }

    /**
     * Test that action() rewinds a request body left at EOF (as laminas-psr7bridge's
     * Psr7ServerRequest::fromLaminas() leaves it) and correctly passes it through to a real MCP
     * server, returning its response. Uses a real Server rather than a mock/stub because Mcp\Server
     * is declared final and cannot be doubled.
     *
     * @return void
     */
    public function testActionRewindsConsumedRequestBodyAndReturnsServerResult(): void
    {
        $server = Server::builder()->setServerInfo(name: 'Test Server', version: '1.2.3')->build();
        $action = $this->getAction($server);

        $payload = json_encode([
            'jsonrpc' => '2.0',
            'id' => 1,
            'method' => 'initialize',
            'params' => [
                'protocolVersion' => '2025-06-18',
                'capabilities' => [],
                'clientInfo' => ['name' => 'test', 'version' => '1.0'],
            ],
        ]);
        $body = new Stream('php://temp', 'r+');
        $body->write($payload);
        // Writing leaves the pointer at the end of the content without rewinding, exactly as described
        // in the comment in McpAction::action() that this test guards against regressing. (Note: the
        // stream's eof() flag itself is only set by a subsequent failed read, not by this alone.)
        $this->assertSame(strlen($payload), $body->tell());

        $request = (new ServerRequest())
            ->withMethod('POST')
            ->withHeader('Host', 'localhost')
            ->withHeader('Content-Type', 'application/json')
            ->withBody($body);

        $result = $action->action($request, new Response());

        $this->assertSame(200, $result->getStatusCode());
        $decoded = json_decode((string)$result->getBody(), true);
        $this->assertSame('2.0', $decoded['jsonrpc'] ?? null);
        $this->assertSame('Test Server', $decoded['result']['serverInfo']['name'] ?? null);
        $this->assertSame('1.2.3', $decoded['result']['serverInfo']['version'] ?? null);
    }
}
