<?php

/**
 * Unit tests for the MCP server provider.
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

namespace VuFindTest\Mcp;

use Generator;
use Laminas\Diactoros\ServerRequest;
use Laminas\Diactoros\Stream;
use Mcp\Server;
use Mcp\Server\Transport\StreamableHttpTransport;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use VuFind\Config\Config;
use VuFind\Config\YamlReader;
use VuFind\Exception\ConfigException;
use VuFind\Http\RouteHelper;
use VuFind\Http\ServerUrlHelper;
use VuFind\Record\Loader;
use VuFind\Search\SearchRunner;
use VuFindApi\Formatter\RecordFormatter;
use VuFindApi\Mcp\Capabilities\SearchSolr;
use VuFindApi\Mcp\ServerProvider;
use VuFindTest\Feature\ReflectionTrait;

/**
 * Unit tests for the MCP server provider.
 *
 * @category VuFind
 * @package  Tests
 * @author   Maccabee Levine <msl321@lehigh.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */
class ServerProviderTest extends TestCase
{
    use ReflectionTrait;

    /**
     * Data provider for testGetServerReturnsNullWhenDisabled().
     *
     * @return Generator
     */
    public static function getDisabledConfigData(): Generator
    {
        yield 'no General section at all' => [[]];
        yield 'enabled explicitly false' => [['General' => ['enabled' => false]]];
    }

    /**
     * Test that the real constructor never touches the MCP SDK (and, in particular, never tries to
     * create the session directory) when the server is disabled -- the default configuration.
     *
     * @param array $mcpConfig MCP configuration
     *
     * @return void
     */
    #[DataProvider('getDisabledConfigData')]
    public function testGetServerReturnsNullWhenDisabled(array $mcpConfig): void
    {
        $provider = new ServerProvider(
            $mcpConfig,
            new Config([]),
            $this->createMock(YamlReader::class),
            $this->createMock(Loader::class),
            $this->createMock(RecordFormatter::class),
            $this->createMock(SearchRunner::class),
            $this->createMock(RouteHelper::class),
            $this->createMock(ServerUrlHelper::class),
            new NullLogger(),
        );
        $this->assertNull($provider->getServer());
    }

    /**
     * Data provider for testSetServerInfoUsesConfiguredOrDefaultValues().
     *
     * @return Generator
     */
    public static function getServerInfoData(): Generator
    {
        yield 'all defaults' => [
            [],
            ['Site' => ['generator' => 'VuFind 10.2', 'title' => 'My Library']],
            ['name' => 'VuFind® Server', 'version' => '10.2', 'description' => 'My Library'],
        ];
        yield 'custom name, version suffix, and description' => [
            ['name' => 'MyLibrary VuFind® Server', 'versionSuffix' => '-a', 'description' => 'Custom description'],
            ['Site' => ['generator' => 'VuFind 10.2.1', 'title' => 'My Library']],
            ['name' => 'MyLibrary VuFind® Server', 'version' => '10.2.1-a', 'description' => 'Custom description'],
        ];
    }

    /**
     * Test that setServerInfo() derives the version from config.ini's Site->generator (with the
     * "VuFind " prefix stripped) plus an optional suffix, and falls back to Site->title for the
     * description when none is configured.
     *
     * @param array $general      ModelContextProtocol.yaml's General section
     * @param array $siteConfig   config.ini's Site section
     * @param array $expectedInfo Expected serverInfo returned by a real initialize call
     *
     * @return void
     */
    #[DataProvider('getServerInfoData')]
    public function testSetServerInfoUsesConfiguredOrDefaultValues(
        array $general,
        array $siteConfig,
        array $expectedInfo
    ): void {
        $provider = $this->getProviderWithoutConstructor(['General' => $general], $siteConfig);
        $builder = Server::builder();
        $this->callMethod($provider, 'setServerInfo', [$builder]);
        $server = $builder->build();

        [$result] = $this->sendMcpRequest($server, $this->getInitializeRequest(), null);
        // assertEquals rather than assertSame: key order in the SDK's own response is an
        // implementation detail this test should not be coupled to.
        $this->assertEquals($expectedInfo, $result['result']['serverInfo']);
    }

    /**
     * Test that addResourceTemplates() registers a configured resource template, including passing
     * its config heading through as the template's name.
     *
     * @return void
     */
    public function testAddResourceTemplatesRegistersConfiguredTemplateWithNameAndMetadata(): void
    {
        $mcpConfig = [
            'ResourceTemplates' => [
                'getRecord' => [
                    'class' => SearchSolr::class,
                    'function' => 'getRecord',
                    'uriTemplate' => 'catalog://record/{recordId}',
                    'title' => 'Get Record by ID',
                    'description' => 'Retrieve a single record by its bibliographic id.',
                ],
            ],
        ];
        $provider = $this->getProviderWithoutConstructor($mcpConfig, []);
        $builder = Server::builder()->setServerInfo(name: 'Test', version: '1.0');
        $this->callMethod($provider, 'addResourceTemplates', [$builder]);
        $server = $builder->build();

        $sessionId = $this->initializeSession($server);
        [$result] = $this->sendMcpRequest(
            $server,
            ['jsonrpc' => '2.0', 'id' => 2, 'method' => 'resources/templates/list', 'params' => []],
            $sessionId
        );

        // assertEquals rather than assertSame: key order in the SDK's own response is an
        // implementation detail this test should not be coupled to.
        $this->assertEquals(
            [
                'uriTemplate' => 'catalog://record/{recordId}',
                'name' => 'getRecord',
                'title' => 'Get Record by ID',
                'description' => 'Retrieve a single record by its bibliographic id.',
            ],
            $result['result']['resourceTemplates'][0] ?? null
        );
    }

    /**
     * Data provider for testAddResourceTemplatesThrowsWhenRequiredKeyMissing().
     *
     * @return Generator
     */
    public static function getResourceTemplateRequiredKeys(): Generator
    {
        yield 'class' => ['class'];
        yield 'function' => ['function'];
        yield 'uriTemplate' => ['uriTemplate'];
    }

    /**
     * Test that addResourceTemplates() validates every one of its required config keys via
     * getRequiredSetting(), not just some of them.
     *
     * @param string $missingKey Required key to omit from an otherwise-valid config entry
     *
     * @return void
     */
    #[DataProvider('getResourceTemplateRequiredKeys')]
    public function testAddResourceTemplatesThrowsWhenRequiredKeyMissing(string $missingKey): void
    {
        $resourceTemplate = [
            'class' => SearchSolr::class,
            'function' => 'getRecord',
            'uriTemplate' => 'catalog://record/{recordId}',
        ];
        unset($resourceTemplate[$missingKey]);
        $provider = $this->getProviderWithoutConstructor(
            ['ResourceTemplates' => ['getRecord' => $resourceTemplate]],
            []
        );

        $this->expectException(ConfigException::class);
        $this->callMethod($provider, 'addResourceTemplates', [Server::builder()]);
    }

    /**
     * Test that addTools() registers a configured tool, including its input schema.
     *
     * @return void
     */
    public function testAddToolsRegistersConfiguredToolWithNameAndInputSchema(): void
    {
        $inputSchema = [
            'type' => 'object',
            'properties' => [
                'keywords' => ['type' => 'string', 'description' => 'Keywords to search for'],
            ],
            'required' => ['keywords'],
        ];
        $mcpConfig = [
            'Tools' => [
                'searchRecordsAnyType' => [
                    'class' => SearchSolr::class,
                    'function' => 'searchRecords',
                    'title' => 'Search Records',
                    'description' => 'Search library catalog records of all content types by keywords.',
                    'inputSchema' => $inputSchema,
                ],
            ],
        ];
        $provider = $this->getProviderWithoutConstructor($mcpConfig, []);
        $builder = Server::builder()->setServerInfo(name: 'Test', version: '1.0');
        $this->callMethod($provider, 'addTools', [$builder]);
        $server = $builder->build();

        $sessionId = $this->initializeSession($server);
        [$result] = $this->sendMcpRequest(
            $server,
            ['jsonrpc' => '2.0', 'id' => 2, 'method' => 'tools/list', 'params' => []],
            $sessionId
        );

        // assertEquals rather than assertSame: key order in the SDK's own response is an
        // implementation detail this test should not be coupled to.
        $this->assertEquals(
            [
                'name' => 'searchRecordsAnyType',
                'title' => 'Search Records',
                'description' => 'Search library catalog records of all content types by keywords.',
                'inputSchema' => $inputSchema,
            ],
            $result['result']['tools'][0] ?? null
        );
    }

    /**
     * Data provider for testAddToolsThrowsWhenRequiredKeyMissing().
     *
     * @return Generator
     */
    public static function getToolRequiredKeys(): Generator
    {
        yield 'class' => ['class'];
        yield 'function' => ['function'];
    }

    /**
     * Test that addTools() validates every one of its required config keys via getRequiredSetting(),
     * not just some of them.
     *
     * @param string $missingKey Required key to omit from an otherwise-valid config entry
     *
     * @return void
     */
    #[DataProvider('getToolRequiredKeys')]
    public function testAddToolsThrowsWhenRequiredKeyMissing(string $missingKey): void
    {
        $tool = [
            'class' => SearchSolr::class,
            'function' => 'searchRecords',
        ];
        unset($tool[$missingKey]);
        $provider = $this->getProviderWithoutConstructor(['Tools' => ['searchRecordsAnyType' => $tool]], []);

        $this->expectException(ConfigException::class);
        $this->callMethod($provider, 'addTools', [Server::builder()]);
    }

    /**
     * Data provider for testGetRequiredSettingThrowsForMissingValue().
     *
     * @return Generator
     */
    public static function getMissingSettingData(): Generator
    {
        yield 'key entirely absent' => [['class' => 'Foo']];
        yield 'value is null' => [['class' => 'Foo', 'function' => null]];
        yield 'value is an empty string' => [['class' => 'Foo', 'function' => '']];
    }

    /**
     * Test that getRequiredSetting() throws a clear ConfigException -- naming the entry and the
     * missing key -- rather than allowing a missing config value to reach the MCP SDK.
     *
     * @param array $entry A ResourceTemplate/Tool config entry missing its 'function' setting
     *
     * @return void
     */
    #[DataProvider('getMissingSettingData')]
    public function testGetRequiredSettingThrowsForMissingValue(array $entry): void
    {
        $provider = $this->getProviderWithoutConstructor([], []);

        $this->expectException(ConfigException::class);
        $this->expectExceptionMessage(
            "ModelContextProtocol.yaml: 'someTool' is missing required setting 'function'."
        );
        $this->callMethod($provider, 'getRequiredSetting', [$entry, 'function', 'someTool']);
    }

    /**
     * Test that getRequiredSetting() returns the value when present.
     *
     * @return void
     */
    public function testGetRequiredSettingReturnsValueWhenPresent(): void
    {
        $provider = $this->getProviderWithoutConstructor([], []);
        $this->assertSame(
            'bar',
            $this->callMethod($provider, 'getRequiredSetting', [['foo' => 'bar'], 'foo', 'someTool'])
        );
    }

    /**
     * Test that addAutoDiscovery() registers no additional tools when no AutoDiscovery config is
     * present.
     *
     * @return void
     */
    public function testAddAutoDiscoveryRegistersNoToolsWhenNotConfigured(): void
    {
        $provider = $this->getProviderWithoutConstructor([], []);
        $builder = Server::builder()->setServerInfo(name: 'Test', version: '1.0');
        $this->callMethod($provider, 'addAutoDiscovery', [$builder]);
        $server = $builder->build();

        $sessionId = $this->initializeSession($server);
        [$result] = $this->sendMcpRequest(
            $server,
            ['jsonrpc' => '2.0', 'id' => 2, 'method' => 'tools/list', 'params' => []],
            $sessionId
        );
        $this->assertSame([], $result['result']['tools'] ?? null);
    }

    /**
     * Test that addAutoDiscovery() picks up capabilities in the configured scanDirs, using the real
     * example capability shipped in Capabilities/AutoDiscovery/ExampleCapabilities.php as the fixture.
     *
     * @return void
     */
    public function testAddAutoDiscoveryRegistersToolsFoundInConfiguredScanDirs(): void
    {
        $mcpConfig = [
            'AutoDiscovery' => [
                'scanDirs' => ['Capabilities/AutoDiscovery'],
            ],
        ];
        $provider = $this->getProviderWithoutConstructor($mcpConfig, []);
        $builder = Server::builder()->setServerInfo(name: 'Test', version: '1.0');
        $this->callMethod($provider, 'addAutoDiscovery', [$builder]);
        $server = $builder->build();

        $sessionId = $this->initializeSession($server);
        [$result] = $this->sendMcpRequest(
            $server,
            ['jsonrpc' => '2.0', 'id' => 2, 'method' => 'tools/list', 'params' => []],
            $sessionId
        );
        $toolNames = array_column($result['result']['tools'] ?? [], 'name');
        $this->assertContains('add', $toolNames);
    }

    /**
     * Build a ServerProvider without running its constructor (which would otherwise try to create a
     * FileSessionStore under LOCAL_CACHE_DIR -- a real, shared directory this test should not depend
     * on being writable). Used by every test that exercises an individual protected method directly.
     *
     * @param array $mcpConfig  MCP configuration (ModelContextProtocol.yaml)
     * @param array $siteConfig config.ini's Site section
     *
     * @return ServerProvider
     */
    protected function getProviderWithoutConstructor(array $mcpConfig, array $siteConfig): ServerProvider
    {
        $provider = $this->getInstanceWithoutConstructor(ServerProvider::class);
        $this->setProperty($provider, 'mcpConfig', $mcpConfig);
        $this->setProperty($provider, 'topConfig', new Config(['Site' => $siteConfig['Site'] ?? []]));
        return $provider;
    }

    /**
     * Build a minimal JSON-RPC "initialize" request payload.
     *
     * @return array
     */
    protected function getInitializeRequest(): array
    {
        return [
            'jsonrpc' => '2.0',
            'id' => 1,
            'method' => 'initialize',
            'params' => [
                'protocolVersion' => '2025-06-18',
                'capabilities' => [],
                'clientInfo' => ['name' => 'test', 'version' => '1.0'],
            ],
        ];
    }

    /**
     * Initialize a session against a real MCP server and return the resulting session id.
     *
     * @param Server $server Server to initialize a session against
     *
     * @return string
     */
    protected function initializeSession(Server $server): string
    {
        [, $sessionId] = $this->sendMcpRequest($server, $this->getInitializeRequest(), null);
        return $sessionId;
    }

    /**
     * Send a JSON-RPC request to a real MCP server via the Streamable HTTP transport (the same
     * transport McpAction uses), and return the decoded response plus the session id to reuse for any
     * follow-up request.
     *
     * @param Server  $server    Server to send the request to
     * @param array   $payload   JSON-RPC request payload
     * @param ?string $sessionId Session id from a prior initialize call, if any
     *
     * @return array{0: array, 1: ?string}
     */
    protected function sendMcpRequest(Server $server, array $payload, ?string $sessionId): array
    {
        $body = new Stream('php://temp', 'r+');
        $body->write(json_encode($payload));
        $body->rewind();

        $request = (new ServerRequest())
            ->withMethod('POST')
            ->withHeader('Host', 'localhost')
            ->withHeader('Content-Type', 'application/json')
            ->withBody($body);
        if ($sessionId) {
            $request = $request->withHeader('Mcp-Session-Id', $sessionId);
        }

        $response = $server->run(new StreamableHttpTransport($request));
        $decoded = json_decode((string)$response->getBody(), true) ?? [];
        $newSessionId = $response->getHeaderLine('Mcp-Session-Id') ?: $sessionId;
        return [$decoded, $newSessionId];
    }
}
