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
use Mcp\Exception\ServiceNotFoundException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use stdClass;
use VuFindApi\Mcp\FirewallContainer;

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
    /**
     * Data provider for testHas().
     *
     * @return Generator
     */
    public static function getHasData(): Generator
    {
        yield 'allowed and in wrapped container' => ['Allowed', true, true];
        yield 'allowed but missing from wrapped container' => ['Allowed', false, false];
        yield 'not on the allowlist, even though the wrapped container has it' => ['NotAllowed', true, false];
    }

    /**
     * Test that has() only returns true for a service that is BOTH on the allowlist AND available
     * from the wrapped container.
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
        $container = new FirewallContainer($wrapped, ['Allowed']);
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
        $container = new FirewallContainer($wrapped, ['Allowed']);
        $this->assertSame($service, $container->get('Allowed'));
    }

    /**
     * Test that get() refuses a service that is not on the allowlist, without ever asking the
     * wrapped container for it.
     *
     * @return void
     */
    public function testGetThrowsForServiceNotOnAllowlist(): void
    {
        $wrapped = $this->createMock(ContainerInterface::class);
        $wrapped->expects($this->never())->method('get');
        $container = new FirewallContainer($wrapped, ['Allowed']);

        $this->expectException(ServiceNotFoundException::class);
        $container->get('NotAllowed');
    }
}
