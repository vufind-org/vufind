<?php

/**
 * Yaml Config Test Class.
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
 * along with this program; if not, see
 * <https://www.gnu.org/licenses/>.
 *
 * @category VuFind
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */

namespace VuFindTest\Config;

use VuFind\Config\ConfigManagerInterface;
use VuFindTest\Feature\ConfigRelatedServicesTrait;
use VuFindTest\Feature\FixtureTrait;

/**
 * Yaml Config Test Class.
 *
 * @category VuFind
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */
class YamlConfigTest extends \PHPUnit\Framework\TestCase
{
    use FixtureTrait;
    use ConfigRelatedServicesTrait;

    /**
     * Test loading of a config.
     *
     * @return void
     */
    public function testSearchSpecsRead()
    {
        // The searchspecs config should define author dismax fields (among many
        // other things).
        $configManager = $this->getContainerWithConfigRelatedServices()->get(ConfigManagerInterface::class);
        $specs = $configManager->getConfigArray('searchspecs');
        $this->assertNotEmpty($specs['Author']['DismaxFields']);
    }

    /**
     * Test loading of a non-existent config.
     *
     * @return void
     */
    public function testMissingFileRead()
    {
        $configManager = $this->getContainerWithConfigRelatedServices()->get(ConfigManagerInterface::class);
        $specs = $configManager->getConfigArray('notreallyasearchspecs');
        $this->assertSame([], $specs);
    }

    /**
     * Test @parent_yaml directive.
     *
     * @return void
     */
    public function testParentYaml()
    {
        $configManager = $this->getContainerWithConfigRelatedServices(
            baseDir: $this->getFixtureDir() . 'configs/inheritance'
        )->get(ConfigManagerInterface::class);

        $this->assertSame(
            [
                'parent' => 'bar',
                'grandparent' => 'baz',
            ],
            $configManager->getConfigArray('parent')
        );

        $this->assertSame(
            [
                'child' => 'foo',
                'parent' => 'bar',
                'grandparent' => 'baz',
            ],
            $configManager->getConfigArray('child')
        );
    }

    /**
     * Test @parent_yaml and @merged_sections directives.
     *
     * @return void
     */
    public function testParentYamlAndMergedSections(): void
    {
        $configManager = $this->getContainerWithConfigRelatedServices(
            baseDir: $this->getFixtureDir() . 'configs/yaml'
        )->get(ConfigManagerInterface::class);
        $config = $configManager->getConfigArray('yamlreader-child.yaml');
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

    /**
     * Test @parent_yaml set to false.
     *
     * @return void
     */
    public function testParentYamlFalse(): void
    {
        $configManager = $this->getContainerWithConfigRelatedServices(
            baseDir: $this->getFixtureDir() . 'configs/yaml',
            localDir: $this->getFixtureDir() . 'configs/yaml/localDir',
        )->get(ConfigManagerInterface::class);
        $config = $configManager->getConfigArray('yamlreader-parent-yaml-false.yaml');
        $this->assertEquals(
            [
                'Child' => 'Will exist',
            ],
            $config
        );
    }

    /**
     * Data provider for testParentConfigName.
     *
     * @return \Iterator<(int | string), mixed>
     */
    public static function parentConfigNameProvider(): \Iterator
    {
        yield 'base-parent-base-child' => ['base', 'base'];
        yield 'base-parent-local-child' => ['base', 'local'];
        yield 'local-parent-base-child' => ['local', 'base'];
        yield 'local-parent-local-child' => ['local', 'local'];
    }

    /**
     * Test @parent_config_name.
     *
     * @param string $parentLocation Location of parent configuration to be loaded
     * @param string $childLocation  Location of child configuration to be loaded
     *
     * @return void
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('parentConfigNameProvider')]
    public function testParentConfigName(string $parentLocation, string $childLocation): void
    {
        $configManager = $this->getContainerWithConfigRelatedServices(
            baseDir: $this->getFixtureDir() . 'configs/yaml/baseDir',
            localDir: $this->getFixtureDir() . 'configs/yaml/localDir',
            baseSubDir: '',
            localSubDir: '',
        )->get(ConfigManagerInterface::class);
        $config = $configManager->getConfigArray($childLocation . '_child_' . $parentLocation . '_parent.yaml');
        $this->assertEquals(
            [
                'All' => $childLocation . '-child',
                'ChildOnly' => $childLocation . '-child',
                'ParentOnly' => $parentLocation . '-parent',
            ],
            $config
        );
    }

    /**
     * Test @parent_config_name set to false.
     *
     * @return void
     */
    public function testParentConfigNameFalse(): void
    {
        $configManager = $this->getContainerWithConfigRelatedServices(
            baseDir: $this->getFixtureDir() . 'configs/yaml',
            localDir: $this->getFixtureDir() . 'configs/yaml/localDir',
        )->get(ConfigManagerInterface::class);
        $config = $configManager->getConfigArray('yamlreader-parent-config-name-false.yaml');
        $this->assertEquals(
            [
                'Child' => 'Will exist',
            ],
            $config
        );
    }
}
