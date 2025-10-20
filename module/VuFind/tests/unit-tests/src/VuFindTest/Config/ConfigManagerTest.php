<?php

/**
 * Config Manager Test Class
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2025.
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
 * @author   Chris Hallberg <challber@villanova.edu>
 * @author   Thomas Wagener <wagener@hebis.uni-frankfurt.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */

namespace VuFindTest\Config;

use VuFind\Config\ConfigManagerInterface;
use VuFind\Config\Location\ConfigDirectory;
use VuFind\Config\Location\ConfigFile;
use VuFind\Exception\ConfigException;
use VuFindTest\Feature\ConfigRelatedServicesTrait;
use VuFindTest\Feature\FixtureTrait;

use function count;

/**
 * Config Manager Test Class
 *
 * @category VuFind
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Chris Hallberg <challber@villanova.edu>
 * @author   Thomas Wagener <wagener@hebis.uni-frankfurt.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */
class ConfigManagerTest extends \PHPUnit\Framework\TestCase
{
    use FixtureTrait;
    use ConfigRelatedServicesTrait;

    /**
     * Get config manager.
     *
     * @return ConfigManagerInterface
     */
    protected function getConfigManager(): ConfigManagerInterface
    {
        $container = new \VuFindTest\Container\MockContainer($this);
        $this->addConfigRelatedServicesToContainer($container);
        return $container->get(ConfigManagerInterface::class);
    }

    /**
     * Wrapper around loadConfigFromLocation method.
     *
     * @param string $name               Configuration to load
     * @param array  $subsection         Subsection
     * @param bool   $handleParentConfig If parent configuration should be handled
     *
     * @return mixed
     */
    protected function getConfig(string $name, array $subsection = [], bool $handleParentConfig = true): mixed
    {
        $fileMap = [
            'unit-test-parent'
                => new ConfigFile($this->getFixturePath('configs/inheritance/unit-test-parent.ini')),
            'unit-test-child'
                => new ConfigFile($this->getFixturePath('configs/inheritance/unit-test-child.ini')),
            'unit-test-child2'
                => new ConfigFile($this->getFixturePath('configs/inheritance/unit-test-child2.ini')),
            'generic-file' => new ConfigFile($this->getFixturePath('configs/generic-file/test')),
            'dir-config' => new ConfigDirectory($this->getFixtureDir() . 'configs/dir-config'),
            'dir-config-with-inheritance'
                => new ConfigDirectory($this->getFixtureDir() . 'configs/inheritance/dir-config'),
        ];
        $realResolver = $this->getPathResolver();
        $configLocation = $fileMap[$name]
            ?? $realResolver->getConfigLocation($name);
        $configLocation->setSubsection($subsection);
        return $this->getConfigManager()->loadConfigFromLocation(
            $configLocation,
            handleParentConfig: $handleParentConfig
        );
    }

    /**
     * Test get config by name.
     *
     * @return void
     */
    public function testGetConfigByName(): void
    {
        $config = $this->getConfigManager()->getConfig('config');
        $this->assertEquals('Library Catalog', $config['Site']['title']);
    }

    /**
     * Test get config with subsection.
     *
     * @return void
     */
    public function testGetConfigWithSubsection(): void
    {
        $config = $this->getConfigManager()->getConfig('config/Site');
        $this->assertEquals('Library Catalog', $config['title']);

        $config = $this->getConfigManager()->getConfig('config/Site/title');
        $this->assertEquals('Library Catalog', $config);
    }

    /**
     * Test get config array.
     *
     * @return void
     */
    public function testGetConfigArray(): void
    {
        $config = $this->getConfigManager()->getConfigArray('config');
        $this->assertEquals('Library Catalog', $config['Site']['title']);
    }

    /**
     * Test get config array exception.
     *
     * @return void
     */
    public function testGetConfigArrayException(): void
    {
        $this->expectException(ConfigException::class);
        $this->expectExceptionMessage('Configuration on path config/Site/title is not an array.');
        $this->getConfigManager()->getConfigArray('config/Site/title');
    }

    /**
     * Test get config object.
     *
     * @return void
     */
    public function testGetConfigObject(): void
    {
        $config = $this->getConfigManager()->getConfigObject('config');
        $this->assertEquals('Library Catalog', $config->get('Site')->get('title'));
    }

    /**
     * Test get config object exception.
     *
     * @return void
     */
    public function testGetConfigObjectException(): void
    {
        $this->expectException(ConfigException::class);
        $this->expectExceptionMessage('Configuration on path config/Site/title is not an array.');
        $this->getConfigManager()->getConfigObject('config/Site/title');
    }

    /**
     * Data provider for testReadOnlyConfig().
     *
     * @return array
     */
    public static function readOnlyConfigProvider(): array
    {
        return [
            'empty config' => ['unset'],
            'override config' => ['title'],
        ];
    }

    /**
     * Test configuration is read-only.
     *
     * @param string $key Key to change
     *
     * @dataProvider readOnlyConfigProvider
     *
     * @return void
     */
    public function testReadOnlyConfig($key): void
    {
        $this->expectException(ConfigException::class);
        $this->expectExceptionMessage('Config is immutable; cannot set ' . $key . ' to bad');
        $config = $this->getConfigManager()->getConfigObject('config');
        $this->assertIsObject($config);
        $config->Site->$key = 'bad';
    }

    /**
     * Test basic config.ini loading.
     *
     * @return void
     */
    public function testBasicRead(): void
    {
        // This should retrieve config.ini, which should have "Library Catalog"
        // set as the default system title.
        $config = $this->getConfig('config');
        $this->assertEquals('Library Catalog', $config['Site']['title']);
    }

    /**
     * Test loading of a custom .ini file.
     *
     * @return void
     */
    public function testCustomRead(): void
    {
        // This should retrieve sms.ini, which should include a Carriers array.
        $config = $this->getConfig('sms');
        $this->assertTrue(count($config['Carriers'] ?? []) > 0);
    }

    /**
     * Test inheritance features.
     *
     * @return void
     */
    public function testInheritance(): void
    {
        // Make sure load succeeds:
        $config = $this->getConfig('unit-test-child');
        $this->assertIsArray($config);

        // Make sure Section 1 was overridden; values from parent should not be
        // present.
        $this->assertArrayNotHasKey('a', $config['Section1']);
        $this->assertEquals('10', $config['Section1']['j']);

        // Make sure Section 2 was merged; values from parent and child should
        // both be present.
        $this->assertEquals('4', $config['Section2']['d']);
        $this->assertEquals('13', $config['Section2']['m']);

        // Make sure Section 3 was inherited; values from parent should exist.
        $this->assertEquals('7', $config['Section3']['g']);

        // Make sure Section 4 arrays were overwritten.
        $this->assertEquals([3], $config['Section4']['j']);
        $this->assertEquals(['c' => 3], $config['Section4']['k']);

        // Make sure Section 5 arrays passed through as-is.
        $this->assertEquals(['a' => 1, 'b' => 2], $config['Section5']['l']);
    }

    /**
     * Test inheritance features with subsections.
     *
     * @return void
     */
    public function testInheritanceWithSubsections(): void
    {
        // Make sure Section 1 was overridden; values from parent should not be
        // present.
        $config = $this->getConfig('unit-test-child', ['Section1']);
        $this->assertArrayNotHasKey('a', $config);
        $this->assertEquals('10', $config['j']);

        // Make sure Section 2 was merged; values from parent and child should
        // both be present.
        $config = $this->getConfig('unit-test-child', ['Section2']);
        $this->assertEquals('4', $config['d']);
        $this->assertEquals('13', $config['m']);

        // Make sure Section 3 was inherited; values from parent should exist.
        $config = $this->getConfig('unit-test-child', ['Section3']);
        $this->assertEquals('7', $config['g']);

        // Make sure Section 4 arrays were overwritten.
        $config = $this->getConfig('unit-test-child', ['Section4']);
        $this->assertEquals([3], $config['j']);
        $this->assertEquals(['c' => 3], $config['k']);

        // Make sure Section 5 arrays passed through as-is.
        $config = $this->getConfig('unit-test-child', ['Section5']);
        $this->assertEquals(['a' => 1, 'b' => 2], $config['l']);
    }

    /**
     * Test inheritance features with array merging turned on.
     *
     * @return void
     */
    public function testInheritanceWithArrayMerging(): void
    {
        // Make sure load succeeds:
        $config = $this->getConfig('unit-test-child2');
        $this->assertIsArray($config);

        // Make sure Section 1 was overridden; values from parent should not be
        // present.
        $this->assertArrayNotHasKey('a', $config['Section1']);
        $this->assertEquals('10', $config['Section1']['j']);

        // Make sure Section 2 was merged; values from parent and child should
        // both be present.
        $this->assertEquals('4', $config['Section2']['d']);
        $this->assertEquals('13', $config['Section2']['m']);

        // Make sure Section 3 was inherited; values from parent should exist.
        $this->assertEquals('7', $config['Section3']['g']);

        // Make sure Section 4 arrays were merged.
        $this->assertEquals([1, 2, 3], $config['Section4']['j']);
        $this->assertEquals(
            ['a' => 1, 'b' => 2, 'c' => 3],
            $config['Section4']['k']
        );

        // Make sure Section 5 arrays passed through as-is.
        $this->assertEquals(['a' => 1, 'b' => 2], $config['Section5']['l']);
    }

    /**
     * Test that the plugin factory omits the Parent_Config section from the
     * merged configuration.
     *
     * @return void
     */
    public function testParentConfigOmission(): void
    {
        $config = $this->getConfig('unit-test-child');
        $this->assertArrayNotHasKey('Parent_Config', $config);
    }

    /**
     * Test loading of configs in subdirectories.
     *
     * @return void
     */
    public function testGenericFileConfig(): void
    {
        $config = $this->getConfig('generic-file');
        $this->assertEquals(
            'some config',
            $config
        );
    }

    /**
     * Test loading of configs in subdirectories.
     *
     * @return void
     */
    public function testDirConfig(): void
    {
        $config = $this->getConfig('dir-config');
        $this->assertEquals(
            [
                'subdir' => [
                    'testsubdir' => [
                        'SectionTestSubdir' => [
                            'a' => 0,
                            'b' => 1,
                        ],
                    ],
                ],
                'generic' => 'some config',
                'test1' => [
                    'Section1' => [
                        'c' => 2,
                        'd' => 3,
                    ],
                ],
                'test2' => [
                    'Section2' => [
                        'e' => 4,
                        'f' => 5,
                    ],
                ],
            ],
            $config
        );
    }

    /**
     * Test loading of configs in subdirectories with subsection.
     *
     * @return void
     */
    public function testDirConfigWithSubsection(): void
    {
        $config = $this->getConfig('dir-config', ['subdir']);
        $this->assertEquals(
            [
                'testsubdir' => [
                    'SectionTestSubdir' => [
                        'a' => 0,
                        'b' => 1,
                    ],
                ],
            ],
            $config
        );

        $config = $this->getConfig('dir-config', ['subdir', 'testsubdir']);
        $this->assertEquals(
            [
                'SectionTestSubdir' => [
                    'a' => 0,
                    'b' => 1,
                ],
            ],
            $config
        );
    }

    /**
     * Test loading of INI config with handling of parent configuration disabled.
     *
     * @return void
     */
    public function testIniConfigWithHandleParentConfigDisabled(): void
    {
        $config = $this->getConfig('unit-test-child', handleParentConfig: false);
        $this->assertEquals(
            [
                'relative_path' => 'unit-test-parent.ini',
                'override_full_sections' => 'Section1',
            ],
            $config['Parent_Config']
        );
        $this->assertEquals(10, $config['Section1']['j']);
        $this->assertArrayNotHasKey('Section3', $config);
    }

    /**
     * Test loading of directory config with handling of parent configuration disabled.
     *
     * @return void
     */
    public function testDirConfigWithHandleParentConfigDisabled(): void
    {
        $config = $this->getConfig('dir-config-with-inheritance', handleParentConfig: false);
        $subdirConfig = $config['subdir-child'];
        $this->assertEquals(
            [
                'relative_path' => '../unit-test-parent.ini',
                'override_full_sections' => 'Section1',
            ],
            $subdirConfig['Parent_Config']
        );
        $this->assertEquals(10, $subdirConfig['Section1']['j']);
        $this->assertArrayNotHasKey('Section3', $subdirConfig);
    }

    /**
     * Data provider for testConfigsInLocalDirStack().
     *
     * @return array
     */
    public static function localDirStackTestProvider(): array
    {
        return [
            'all' => [
                'all',
                [
                    'Section' => [
                        'value' => 'primary',
                        'value2' => 'secondary',
                    ],
                ],
            ],
            'primary' => [
                'primary',
                [
                    'Section' => [
                        'value' => 'primary',
                    ],
                ],
            ],
            'base-secondary' => [
                'base-secondary',
                [
                    'Section' => [
                        'value' => 'secondary',
                        'value2' => 'secondary',
                    ],
                ],
            ],
            'base' => [
                'base',
                [
                    'Section' => [
                        'value' => 'base',
                        'value2' => 'base',
                    ],
                ],
            ],
            'dir_config' => [
                'dir_config',
                [
                    'all-sub' => [
                        'Section' => [
                            'value' => 'primary',
                            'value2' => 'secondary',
                        ],
                    ],
                    'primary-sub' => [
                        'Section' => [
                            'value' => 'primary',
                        ],
                    ],
                    'base-secondary-sub' => [
                        'Section' => [
                            'value' => 'secondary',
                            'value2' => 'secondary',
                        ],
                    ],
                    'base-sub' => [
                        'Section' => [
                            'value' => 'base',
                            'value2' => 'base',
                        ],
                    ],
                ],
            ],
            'all-sub' => [
                'dir_config/all-sub',
                [
                    'Section' => [
                        'value' => 'primary',
                        'value2' => 'secondary',
                    ],
                ],
            ],
            'primary-sub' => [
                'dir_config/primary-sub',
                [
                    'Section' => [
                        'value' => 'primary',
                    ],
                ],
            ],
            'base-secondary-sub' => [
                'dir_config/base-secondary-sub',
                [
                    'Section' => [
                        'value' => 'secondary',
                        'value2' => 'secondary',
                    ],
                ],
            ],
            'base-sub' => [
                'dir_config/base-sub',
                [
                    'Section' => [
                        'value' => 'base',
                        'value2' => 'base',
                    ],
                ],
            ],
        ];
    }

    /**
     * Test loading of configs with inheritance and a local dir stack.
     *
     * @param string $configPath     Config path
     * @param array  $expectedConfig Expected config
     *
     * @return void
     *
     * @dataProvider localDirStackTestProvider
     */
    public function testConfigsInLocalDirStack(
        $configPath,
        $expectedConfig
    ): void {
        $fixtureDir = realpath($this->getFixtureDir() . 'configs/pathstack') . '/';
        $configManager = $this->getContainerWithConfigRelatedServices(
            baseDir: $fixtureDir . 'base',
            localDir: $fixtureDir . 'primary'
        )->get(ConfigManagerInterface::class);

        $config = $configManager->getConfigArray($configPath);
        $this->assertEquals(
            $expectedConfig,
            $config
        );
    }
}
