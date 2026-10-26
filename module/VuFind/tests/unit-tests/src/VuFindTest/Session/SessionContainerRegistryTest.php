<?php

/**
 * Session Container Registry Test Class.
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
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */

namespace VuFindTest\Session;

use Generator;
use Laminas\Session\Container;
use Laminas\Session\SessionManager;
use PHPUnit\Framework\Attributes\DataProvider;
use VuFind\Session\SessionContainerRegistry;

/**
 * Session Container Registry Test Class.
 *
 * @category VuFind
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */
class SessionContainerRegistryTest extends \PHPUnit\Framework\TestCase
{
    use \VuFindTest\Feature\ReflectionTrait;

    /**
     * Get a registry object.
     *
     * @param ?SessionManager $sessionManager Session manager (or null to create default stub)
     *
     * @return SessionContainerRegistry
     */
    protected function getRegistry(?SessionManager $sessionManager = null): SessionContainerRegistry
    {
        return new SessionContainerRegistry($sessionManager ?? $this->createStub(SessionManager::class));
    }

    /**
     * Data provider for testHas().
     *
     * @return Generator<string, array>
     */
    public static function hasProvider(): Generator
    {
        yield 'legal name' => ['this\is\legal', true];
        yield 'illegal name' => ['0starting with digit is illegal', false];
    }

    /**
     * Test the has method.
     *
     * @param string $id             Container ID to request
     * @param bool   $expectedResult Expected result
     *
     * @return void
     */
    #[DataProvider('hasProvider')]
    public function testHas(string $id, bool $expectedResult): void
    {
        $this->assertSame($expectedResult, $this->getRegistry()->has($id));
    }

    /**
     * Test the get method.
     *
     * @return void
     */
    public function testGet(): void
    {
        $this->assertInstanceOf(Container::class, $this->getRegistry()->get('this\is\legal'));
    }
}
