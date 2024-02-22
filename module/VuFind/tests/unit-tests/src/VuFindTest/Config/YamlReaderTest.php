<?php

/**
 * Config YamlReader Test Class
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2022.
 * Copyright (C) The National Library of Finland 2022.
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
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */

namespace VuFindTest\Config;

use Laminas\Cache\Storage\StorageInterface;
use VuFind\Config\YamlReader;
use VuFindTest\Feature\FixtureTrait;
use VuFindTest\Feature\PathResolverTrait;

/**
 * Config YamlReader Test Class
 *
 * @category VuFind
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */
class YamlReaderTest extends \PHPUnit\Framework\TestCase
{
    use FixtureTrait;
    use PathResolverTrait;

    /**
     * Test that the cache is updated as expected.
     *
     * @return void
     */
    public function testCacheWrite()
    {
        $yamlData = ['foo' => 'bar'];
        $cache = $this->createMock(StorageInterface::class);
        $cache->expects($this->once())->method('getItem')
            ->will($this->returnValue(null));
        $cache->expects($this->once())->method('setItem')
            ->with($this->matchesRegularExpression('/\d+/'), $this->equalTo($yamlData));
        $manager = $this->getMockBuilder(\VuFind\Cache\Manager::class)
            ->disableOriginalConstructor()
            ->getMock();
        $manager->expects($this->once())->method('getCache')
            ->with($this->equalTo('yaml'))
            ->will($this->returnValue($cache));
        $reader = $this->getMockBuilder(YamlReader::class)
            ->onlyMethods(['parseYaml'])
            ->setConstructorArgs([$manager])
            ->getMock();
        $reader->expects($this->once())
            ->method('parseYaml')
            ->with(
                $this->equalTo(null),
                $this->matchesRegularExpression('/.*searchspecs.yaml/')
            )->will($this->returnValue($yamlData));
        $this->assertEquals($yamlData, $reader->get('searchspecs.yaml'));
    }

    /**
     * Test that the cache can short-circuit data loading.
     *
     * @return void
     */
    public function testCacheRead()
    {
        $yamlData = ['foo' => 'bar'];
        $cache = $this->createMock(StorageInterface::class);
        $cache->expects($this->once())->method('getItem')
            ->will($this->returnValue($yamlData));
        $cache->expects($this->never())->method('setItem');
        $manager = $this->getMockBuilder(\VuFind\Cache\Manager::class)
            ->disableOriginalConstructor()
            ->getMock();
        $manager->expects($this->once())->method('getCache')
            ->with($this->equalTo('yaml'))
            ->will($this->returnValue($cache));
        $reader = $this->getMockBuilder(YamlReader::class)
            ->onlyMethods(['parseYaml'])
            ->setConstructorArgs([$manager])
            ->getMock();
        $reader->expects($this->never())->method('parseYaml');
        // Test twice to confirm that cache is only called once (due to secondary
        // cache inside the reader object):
        $this->assertEquals($yamlData, $reader->get('searchspecs.yaml'));
        $this->assertEquals($yamlData, $reader->get('searchspecs.yaml'));
    }

    /**
     * Test that we can force a reload from cache.
     *
     * @return void
     */
    public function testCacheForcedReload()
    {
        $yamlData = ['foo' => 'bar'];
        $cache = $this->createMock(StorageInterface::class);
        $cache->expects($this->exactly(2))->method('getItem')
            ->will($this->returnValue($yamlData));
        $cache->expects($this->never())->method('setItem');
        $manager = $this->getMockBuilder(\VuFind\Cache\Manager::class)
            ->disableOriginalConstructor()
            ->getMock();
        $manager->expects($this->exactly(2))->method('getCache')
            ->with($this->equalTo('yaml'))
            ->will($this->returnValue($cache));
        $reader = $this->getMockBuilder(YamlReader::class)
            ->onlyMethods(['parseYaml'])
            ->setConstructorArgs([$manager])
            ->getMock();
        $reader->expects($this->never())->method('parseYaml');
        // Test twice to confirm that cache is re-checked in response to third
        // get() parameter.
        $this->assertEquals($yamlData, $reader->get('searchspecs.yaml'));
        $this->assertEquals($yamlData, $reader->get('searchspecs.yaml', true, true));
    }

    /**
     * Test @parent_yaml and @merged_sections directives
     *
     * @return void
     */
    public function testParentConfig(): void
    {
        $reader = new YamlReader(
            null,
            $this->getPathResolver($this->getFixtureDir() . 'configs/yaml')
        );
        $config = $reader->get('yamlreader-child.yaml');
        $this->assertEquals(
            [
                'Overridden' => [
                    'Original' => 'Not so original',
                ],
                'Other' => [
                    'Merged' => [
                        'Foo' => ['Foo', 'Bar'],
                        'Baz' => ['Bar', 'Bar', 'ChildBaz'],
                        'Child' => ['Foo', 'Baz'],
                    ],
                    'Replaced' => [
                        'ParentOnly' => 'Will exist',
                        'Original' => 'Replaces parent',
                        'Boolean' => false,
                        'ChildOnly' => 'From child',
                    ],
                    'NonMerged' => [
                        'Original' => 'Not so original either',
                    ],
                    'ParentOnly' => [true],
                ],
                'ChildOnly' => [
                    'Child' => 'true',
                ],
            ],
            $config
        );
    }
}
