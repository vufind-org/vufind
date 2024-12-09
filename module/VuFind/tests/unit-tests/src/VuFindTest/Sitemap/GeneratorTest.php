<?php

/**
 * Sitemap Generator Test Class
 *
 * PHP version 8
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
use PHPUnit\Framework\MockObject\MockObject;
use VuFind\Sitemap\Generator;
use VuFind\Sitemap\PluginManager;
use VuFind\Sitemap\SitemapIndex;
use VuFindTest\Container\MockContainer;
use VuFindTest\Feature\FixtureTrait;

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
    use FixtureTrait;

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
     * Data provider for testBuildIndex().
     *
     * @return array
     */
    public static function buildIndexProvider(): array
    {
        return [
            'empty configuration' => [[], 'setEmptyConfigIndexExpectations'],
            'relative file' => [
                [
                    'SitemapIndex' => [
                        'indexFileName' => 'sitemapIndex',
                        'baseSitemapFileName' => 'staticIndex',
                    ],
                ],
                'setRelativeFileIndexExpectations',
            ],
            'absolute URL' => [
                [
                    'SitemapIndex' => [
                        'indexFileName' => 'sitemapIndex',
                        'baseSitemapFileName' => 'http://foo/my-url.xml',
                    ],
                ],
                'setAbsoluteUrlIndexExpectations',
            ],
            'multiple index files' => [
                [
                    'SitemapIndex' => [
                        'indexFileName' => 'sitemapIndex',
                        'baseSitemapFileName' => ['staticIndex', 'http://foo/my-url.xml'],
                    ],
                ],
                'setMultipleIndexExpectations',
            ],
        ];
    }

    /**
     * Configure a mock object for the "empty configuration" buildIndex test.
     *
     * @param MockObject&SitemapIndex $mockIndex Mock to configure
     *
     * @return void
     */
    protected function setEmptyConfigIndexExpectations(MockObject&SitemapIndex $mockIndex): void
    {
        $mockIndex->expects($this->never())->method('addUrl');
        $mockIndex->expects($this->never())->method('write');
    }

    /**
     * Configure a mock object for the "relative file" buildIndex test.
     *
     * @param MockObject&SitemapIndex $mockIndex Mock to configure
     *
     * @return void
     */
    protected function setRelativeFileIndexExpectations(MockObject&SitemapIndex $mockIndex): void
    {
        $mockIndex->expects($this->once())->method('addUrl')->with($this->equalTo('http://foo/staticIndex.xml'));
        $mockIndex->expects($this->once())->method('write')
            ->with($this->equalTo($this->getFixturePath('sitemap') . '/sitemapIndex.xml'));
    }

    /**
     * Configure a mock object for the "absolute URL" buildIndex test.
     *
     * @param MockObject&SitemapIndex $mockIndex Mock to configure
     *
     * @return void
     */
    protected function setAbsoluteUrlIndexExpectations(MockObject&SitemapIndex $mockIndex): void
    {
        $mockIndex->expects($this->once())->method('addUrl')->with($this->equalTo('http://foo/my-url.xml'));
        $mockIndex->expects($this->once())->method('write')
            ->with($this->equalTo($this->getFixturePath('sitemap') . '/sitemapIndex.xml'));
    }

    /**
     * Configure a mock object for the "multiple index files" buildIndex test.
     *
     * @param MockObject&SitemapIndex $mockIndex Mock to configure
     *
     * @return void
     */
    protected function setMultipleIndexExpectations(MockObject&SitemapIndex $mockIndex): void
    {
        $checkCallback = function ($url) {
            static $lastUrl = null;
            if ($url === $lastUrl) {
                // we don't expect the same URL twice in a row
                return false;
            }
            $lastUrl = $url;
            return $url === 'http://foo/staticIndex.xml' || $url === 'http://foo/my-url.xml';
        };
        $mockIndex->expects($this->exactly(2))->method('addUrl')->with($this->callback($checkCallback));
        $mockIndex->expects($this->once())->method('write')
            ->with($this->equalTo($this->getFixturePath('sitemap') . '/sitemapIndex.xml'));
    }

    /**
     * Test building the sitemap index.
     *
     * @param array    $config            Configuration settings
     * @param callable $expectationMethod Name of method to set up expectations for mock index object
     *
     * @return void
     *
     * @dataProvider buildIndexProvider
     */
    public function testBuildIndex(array $config, string $expectationMethod): void
    {
        if (!empty($config)) {
            // This value needs to be dynamically determined, so we'll add it to
            // all non-empty configuration sets.
            $config['Sitemap']['fileLocation'] = $this->getFixturePath('sitemap');
        }
        $config = new Config($config);
        $pluginManager = $this->createMock(PluginManager::class);
        $mockIndex = $this->createMock(SitemapIndex::class);
        $this->$expectationMethod($mockIndex);
        $generator = new class ($config, $pluginManager, $mockIndex) extends Generator {
            /**
             * Constructor
             *
             * @param Config        $config        Sitemap configuration settings
             * @param PluginManager $pluginManager Generator plugin manager
             * @param SitemapIndex  $mockIndex     Mock sitemap index to use
             */
            public function __construct(
                Config $config,
                PluginManager $pluginManager,
                protected $mockIndex
            ) {
                parent::__construct('http://foo', $config, [], $pluginManager);
            }

            /**
             * Generate sitemaps from all mandatory and configured plugins
             *
             * @return array
             */
            protected function generateWithPlugins(): array
            {
                return []; // stub this out
            }

            /**
             * Get a fresh SitemapIndex object.
             *
             * @return SitemapIndex
             */
            protected function getNewSitemapIndex()
            {
                return $this->mockIndex;
            }
        };
        $generator->generate();
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
