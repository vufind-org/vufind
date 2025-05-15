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
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301  USA
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

use VuFind\Config\ConfigManager;
use VuFind\Config\Handler\PluginManager as HandlerPluginManager;
use VuFind\Config\Location\ConfigDirectory;
use VuFind\Config\Location\ConfigFile;
use VuFindTest\Feature\FixtureTrait;
use VuFindTest\Feature\PathResolverTrait;

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
    use PathResolverTrait;

    /**
     * Get config manager.
     *
     * @return ConfigManager
     */
    protected function getConfigManager(): ConfigManager
    {
        $realResolver = $this->getPathResolver();
        $container = new \VuFindTest\Container\MockContainer($this);
        $mockConfigHandler = new HandlerPluginManager($container);
        $configManager = new ConfigManager($mockConfigHandler, $realResolver);
        $container->set(ConfigManager::class, $configManager);
        return $configManager;
    }

    /**
     * Wrapper around loadConfig method.
     *
     * @param string $name Configuration to load
     *
     * @return array
     */
    protected function getConfig(string $name): array
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
        ];
        $realResolver = $this->getPathResolver();
        $configLocation = $fileMap[$name]
            ?? $realResolver->getConfigLocation($name);
        return $this->getConfigManager()->loadConfig($configLocation);
    }

    /**
     * Test get config by name.
     *
     * @return void
     */
    public function testGetConfigByName(): void
    {
        $config = $this->getConfigManager()->get('config');
        $this->assertEquals('Library Catalog', $config['Site']['title']);
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
            ['some config'],
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
                'generic' => ['some config'],
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
}
