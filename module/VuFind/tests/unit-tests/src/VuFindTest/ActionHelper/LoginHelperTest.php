<?php

/**
 * LoginHelper test class.
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
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */

namespace VuFindTest\AjaxHandler;

use Generator;
use PHPUnit\Framework\TestCase;
use VuFind\ActionHelper\LoginHelper as ActionHelperLoginHelper;
use VuFind\ILS\Connection;
use VuFindTest\Feature\AutowireTrait;

/**
 * ForwardHelper test class.
 *
 * @category VuFind
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */
class LoginHelperTest extends TestCase
{
    use AutowireTrait;

    /**
     * Data provider for testGetILSLoginMethod().
     *
     * @return Generator<string, array>
     */
    public static function getILSLoginMethodProvider(): Generator
    {
        yield 'defaults (null configuration)' => ['password', null, ''];
        yield 'defaults (empty array configuration)' => ['password', [], ''];
        yield 'non-default with empty target' => ['foo', ['loginMethod' => 'foo'], ''];
        yield 'non-default with non-empty target' => ['foo', ['loginMethod' => 'foo'], 'bar'];
    }

    /**
     * Test getting the ILS login method.
     *
     * @param string $expectedMethod    Expected return value of getILSLoginMethod()
     * @param ?array $patronLoginConfig Configuration for patronLogin method
     * @param string $target            Target to pass to getILSLoginMethod()
     *
     * @return void
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('getILSLoginMethodProvider')]
    public function testGetILSLoginMethod(string $expectedMethod, ?array $patronLoginConfig, string $target): void
    {
        $ils = $this->createMock(Connection::class);
        $ils->expects($this->once())
            ->method('checkFunction')
            ->with('patronLogin', ['patron' => ['cat_username' => "$target.login"]])
            ->willReturn($patronLoginConfig);
        $helper = $this->getAutowiredObject(ActionHelperLoginHelper::class, [Connection::class => $ils]);
        $this->assertEquals($expectedMethod, $helper->getILSLoginMethod($target));
    }
}
