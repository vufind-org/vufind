<?php

/**
 * Unit tests for the MCP firewall container.
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
use Mcp\Exception\ContainerException;
use Mcp\Exception\ServiceNotFoundException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use stdClass;
use VuFind\Config\YamlReader;
use VuFind\Http\RouteHelper;
use VuFind\Http\ServerUrlHelper;
use VuFind\Record\Loader;
use VuFind\Search\SearchRunner;
use VuFindApi\Formatter\RecordFormatter;
use VuFindApi\Mcp\Capabilities\SearchSolr;
use VuFindApi\Mcp\FirewallContainer;
use VuFindTest\Feature\ReflectionTrait;

/**
 * Unit tests for the MCP firewall container.
 *
 * @category VuFind
 * @package  Tests
 * @author   Maccabee Levine <msl321@lehigh.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */
class FirewallContainerTest extends TestCase
{
    use ReflectionTrait;

    /**
     * A namespace prefix list that does not match any real class, for tests that only care about
     * the allowlist behavior and want the capability-namespace check to always be irrelevant.
     */
    protected const NO_CAPABILITY_NAMESPACES = ['NoCapabilitiesUnderThisNamespace\\'];

    /**
     * Data provider for testHas().
     *
     * @return Generator
     */
    public static function getHasData(): Generator
    {
        yield 'allowed and in wrapped container' => ['Allowed', true, true];
        yield 'allowed but missing from wrapped container' => ['Allowed', false, false];
        yield 'not on the allowlist and not a real class' => ['NotAllowed', true, false];
        yield 'not on the allowlist, even though it is a real class' => [stdClass::class, true, false];
    }

    /**
     * Test that has() returns true for a service that is BOTH on the allowlist AND available from
     * the wrapped container, but false for anything else -- including a real class (stdClass),
     * proving that being real and reflectable is not by itself enough absent the allowlist or
     * capability-namespace match, and a disallowed identifier that is not even a real class
     * (which cannot fall back to being a capability class either).
     *
     * @param string $id                  Service id to check
     * @param bool   $wrappedContainerHas Whether the wrapped container reports having $id
     * @param bool   $expected            Expected result of has()
     *
     * @return void
     */
    #[DataProvider('getHasData')]
    public function testHas(string $id, bool $wrappedContainerHas, bool $expected): void
    {
        $wrapped = $this->createMock(ContainerInterface::class);
        $wrapped->method('has')->with($id)->willReturn($wrappedContainerHas);
        $container = new FirewallContainer($wrapped, ['Allowed'], self::NO_CAPABILITY_NAMESPACES);
        $this->assertSame($expected, $container->has($id));
    }

    /**
     * Test that get() delegates to the wrapped container for an allowed service.
     *
     * @return void
     */
    public function testGetDelegatesToWrappedContainerForAllowedService(): void
    {
        $service = new stdClass();
        $wrapped = $this->createMock(ContainerInterface::class);
        $wrapped->method('has')->with('Allowed')->willReturn(true);
        $wrapped->method('get')->with('Allowed')->willReturn($service);
        $container = new FirewallContainer($wrapped, ['Allowed'], self::NO_CAPABILITY_NAMESPACES);
        $this->assertSame($service, $container->get('Allowed'));
    }

    /**
     * Test that get() refuses a service that is neither on the allowlist nor a capability class,
     * without ever asking the wrapped container for it.
     *
     * @return void
     */
    public function testGetThrowsForServiceNotOnAllowlist(): void
    {
        $wrapped = $this->createMock(ContainerInterface::class);
        $wrapped->expects($this->never())->method('get');
        $container = new FirewallContainer($wrapped, ['Allowed'], self::NO_CAPABILITY_NAMESPACES);

        $this->expectException(ServiceNotFoundException::class);
        $container->get('NotAllowed');
    }

    /**
     * Test that has() returns true for a real class under the capability namespace (here, the
     * actual SearchSolr capability class), even though it is not itself on the service allowlist.
     *
     * @return void
     */
    public function testHasReturnsTrueForCapabilityClass(): void
    {
        $wrapped = $this->createMock(ContainerInterface::class);
        $wrapped->method('has')->willReturn(false);
        $container = new FirewallContainer($wrapped, [], ['VuFindApi\Mcp\Capabilities\\']);
        $this->assertTrue($container->has(SearchSolr::class));
    }

    /**
     * Test that has() returns false for a real, trivially auto-wirable-if-allowed class that
     * simply lives outside the capability namespace -- proving that being a real, reflectable
     * class is not by itself enough to make something buildable.
     *
     * @return void
     */
    public function testHasReturnsFalseForRealClassOutsideCapabilityNamespace(): void
    {
        $wrapped = $this->createMock(ContainerInterface::class);
        $wrapped->method('has')->willReturn(false);
        $container = new FirewallContainer($wrapped, [], ['VuFindApi\Mcp\Capabilities\\']);
        $this->assertFalse($container->has(RecordFormatter::class));
    }

    /**
     * Test that get() auto-wires a capability class (here, the actual SearchSolr class) purely
     * from allowed services, resolving its constructor parameters through this same container --
     * this is how the MCP SDK builds a capability class to run a tool or resource, and is the
     * whole point of this container.
     *
     * @return void
     */
    public function testGetAutowiresCapabilityClassFromAllowedServices(): void
    {
        $services = [
            YamlReader::class => $this->createConfiguredMock(YamlReader::class, ['get' => []]),
            Loader::class => $this->createMock(Loader::class),
            RecordFormatter::class => $this->createMock(RecordFormatter::class),
            SearchRunner::class => $this->createMock(SearchRunner::class),
            RouteHelper::class => $this->createMock(RouteHelper::class),
            ServerUrlHelper::class => $this->createMock(ServerUrlHelper::class),
        ];
        $wrapped = $this->createMock(ContainerInterface::class);
        $wrapped->method('has')->willReturn(true);
        $wrapped->method('get')->willReturnCallback(fn (string $id) => $services[$id]);
        $container = new FirewallContainer($wrapped, array_keys($services), ['VuFindApi\Mcp\Capabilities\\']);

        $this->assertInstanceOf(SearchSolr::class, $container->get(SearchSolr::class));
    }

    /**
     * Test that has() checks EVERY configured namespace, not just the first, so a class under a
     * second (e.g. a site-added) namespace is still recognized as a capability class.
     *
     * @return void
     */
    public function testHasChecksAllConfiguredNamespaces(): void
    {
        $wrapped = $this->createMock(ContainerInterface::class);
        $wrapped->method('has')->willReturn(false);
        $container = new FirewallContainer(
            $wrapped,
            [],
            [self::NO_CAPABILITY_NAMESPACES[0], 'VuFindApi\Mcp\Capabilities\\']
        );
        $this->assertTrue($container->has(SearchSolr::class));
    }

    /**
     * Test that get() refuses a real, trivially auto-wirable-if-allowed class (here,
     * RecordFormatter, which VuFind's own API controllers build without issue) simply because it
     * lives outside the capability namespace -- this is the crux of the firewall: a class does
     * NOT become buildable just by being reflectable, no matter what the top-level request is.
     *
     * @return void
     */
    public function testGetThrowsForRealClassOutsideCapabilityNamespace(): void
    {
        $wrapped = $this->createMock(ContainerInterface::class);
        $wrapped->expects($this->never())->method('get');
        $container = new FirewallContainer($wrapped, [], ['VuFindApi\Mcp\Capabilities\\']);

        $this->expectException(ServiceNotFoundException::class);
        $container->get(RecordFormatter::class);
    }

    /**
     * Test that autowire() refuses to build a class with a constructor parameter that cannot be
     * resolved -- here, RecordFormatter's own required $recordFields parameter is a plain array,
     * not a class/interface type, so it can never be satisfied by this container. Exercised
     * directly (bypassing the capability-namespace check in get()) since this is the only way to
     * reach this branch for a class outside that namespace.
     *
     * @return void
     */
    public function testAutowireThrowsForUnresolvableParameter(): void
    {
        $wrapped = $this->createMock(ContainerInterface::class);
        $wrapped->expects($this->never())->method('get');
        $container = new FirewallContainer($wrapped, [], self::NO_CAPABILITY_NAMESPACES);

        $this->expectException(ContainerException::class);
        $this->callMethod($container, 'autowire', [RecordFormatter::class]);
    }
}
