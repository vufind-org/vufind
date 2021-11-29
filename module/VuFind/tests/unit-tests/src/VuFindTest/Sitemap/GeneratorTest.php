<?php
/**
 * Sitemap Generator Test Class
 *
 * PHP version 7
 *
 * Copyright (C) Villanova University 2021.
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
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301  USA
 *
 * @category VuFind
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */
namespace VuFindTest\Sitemap;

use Laminas\Config\Config;
use VuFind\Sitemap\Generator;
use VuFind\Sitemap\PluginManager;
use VuFindTest\Container\MockContainer;

/**
 * Sitemap Generator Test Class
 *
 * @category VuFind
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */
class GeneratorTest extends \PHPUnit\Framework\TestCase
{
    /**
     * Mock container
     *
     * @var MockContainer
     */
    protected $container = null;

    /**
     * Standard setup method.
     *
     * @return void
     */
    public function setUp(): void
    {
        $this->container = new MockContainer($this);
    }

    /**
     * Get a generator for testing purposes.
     *
     * @param array  $config  Sitemap configuration options
     * @param array  $objects Dependency objects to override
     * @param array  $locales Locales to activate
     * @param string $baseUrl Base URL of site
     *
     * @return Generator
     */
    protected function getGenerator(
        array $config = [],
        array $objects = [],
        array $locales = ['en', 'de'],
        string $baseUrl = 'http://foo'
    ) {
        return new Generator(
            $baseUrl,
            new Config($config),
            $locales,
            $objects[PluginManager::class]
                ?? $this->container->get(PluginManager::class)
        );
    }

    /**
     * Test that the warnings array is initialized correctly.
     *
     * @return void
     */
    public function testEmptyWarnings(): void
    {
        $this->assertEquals([], $this->getGenerator()->getWarnings());
    }
}
