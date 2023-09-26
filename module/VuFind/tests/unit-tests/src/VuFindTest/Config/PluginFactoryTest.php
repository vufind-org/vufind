<?php

/**
 * Config Factory Test Class
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2010.
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

namespace VuFindTest\Config;

use VuFind\Config\PathResolver;
use VuFindTest\Feature\FixtureTrait;
use VuFindTest\Feature\PathResolverTrait;

use function count;

/**
 * Config Factory Test Class
 *
 * @category VuFind
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Chris Hallberg <challber@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */
class PluginFactoryTest extends \PHPUnit\Framework\TestCase
{
    use FixtureTrait;
    use PathResolverTrait;

    /**
     * Plugin factory instance.
     *
     * @var \VuFind\Config\PluginFactory
     */
    protected $factory;

    /**
     * Standard setup method.
     *
     * @return void
     */
    public function setUp(): void
    {
        $this->factory = new \VuFind\Config\PluginFactory();
    }

    /**
     * Wrapper around factory
     *
     * @param string $name Configuration to load
     *
     * @return \Laminas\Config\Config
     */
    protected function getConfig($name)
    {
        $fileMap = [
            'unit-test-parent.ini'
                => $this->getFixturePath('configs/inheritance/unit-test-parent.ini'),
            'unit-test-child.ini'
                => $this->getFixturePath('configs/inheritance/unit-test-child.ini'),
            'unit-test-child2.ini'
                => $this->getFixturePath('configs/inheritance/unit-test-child2.ini'),
        ];
        $realResolver = $this->getPathResolver();
        $mockResolver = $this->getMockBuilder(PathResolver::class)
            ->disableOriginalConstructor()
            ->getMock();
        $mockResolver->expects($this->any())
            ->method('getConfigPath')
            ->willReturnCallback(
                function ($filename, $path) use ($fileMap, $realResolver) {
                    return $fileMap[$filename]
                        ?? $realResolver->getConfigPath($filename, $path);
                }
            );
        $container = new \VuFindTest\Container\MockContainer($this);
        $container->set(PathResolver::class, $mockResolver);
        return ($this->factory)($container, $name);
    }

    /**
     * Test basic config.ini loading.
     *
     * @return void
     */
    public function testBasicRead()
    {
        // This should retrieve config.ini, which should have "Library Catalog"
        // set as the default system title.
        $config = $this->getConfig('config');
        $this->assertEquals('Library Catalog', $config->Site->title);
    }

    /**
     * Test loading of a custom .ini file.
     *
     * @return void
     */
    public function testCustomRead()
    {
        // This should retrieve sms.ini, which should include a Carriers array.
        $config = $this->getConfig('sms');
        $this->assertTrue(isset($config->Carriers) && count($config->Carriers) > 0);
    }

    /**
     * Test inheritance features.
     *
     * @return void
     */
    public function testInheritance()
    {
        // Make sure load succeeds:
        $config = $this->getConfig('unit-test-child');
        $this->assertIsObject($config);

        // Make sure Section 1 was overridden; values from parent should not be
        // present.
        $this->assertTrue(!isset($config->Section1->a));
        $this->assertEquals('10', $config->Section1->j);

        // Make sure Section 2 was merged; values from parent and child should
        // both be present.
        $this->assertEquals('4', $config->Section2->d);
        $this->assertEquals('13', $config->Section2->m);

        // Make sure Section 3 was inherited; values from parent should exist.
        $this->assertEquals('7', $config->Section3->g);

        // Make sure Section 4 arrays were overwritten.
        $this->assertEquals([3], $config->Section4->j->toArray());
        $this->assertEquals(['c' => 3], $config->Section4->k->toArray());
    }

    /**
     * Test inheritance features with array merging turned on.
     *
     * @return void
     */
    public function testInheritanceWithArrayMerging()
    {
        // Make sure load succeeds:
        $config = $this->getConfig('unit-test-child2');
        $this->assertIsObject($config);

        // Make sure Section 1 was overridden; values from parent should not be
        // present.
        $this->assertTrue(!isset($config->Section1->a));
        $this->assertEquals('10', $config->Section1->j);

        // Make sure Section 2 was merged; values from parent and child should
        // both be present.
        $this->assertEquals('4', $config->Section2->d);
        $this->assertEquals('13', $config->Section2->m);

        // Make sure Section 3 was inherited; values from parent should exist.
        $this->assertEquals('7', $config->Section3->g);

        // Make sure Section 4 arrays were overwritten.
        $this->assertEquals([1, 2, 3], $config->Section4->j->toArray());
        $this->assertEquals(
            ['a' => 1, 'b' => 2, 'c' => 3],
            $config->Section4->k->toArray()
        );
    }

    /**
     * Test that the plugin factory omits the Parent_Config section from the
     * merged configuration.
     *
     * @return void
     */
    public function testParentConfigOmission()
    {
        $config = $this->getConfig('unit-test-child');
        $this->assertFalse(isset($config->Parent_Config));
    }

    /**
     * Test configuration is read-only.
     *
     * @return void
     */
    public function testReadOnlyConfig()
    {
        $this->expectException(\Laminas\Config\Exception\RuntimeException::class);

        $config = $this->getConfig('unit-test-parent');
        $config->Section1->z = 'bad';
    }
}
