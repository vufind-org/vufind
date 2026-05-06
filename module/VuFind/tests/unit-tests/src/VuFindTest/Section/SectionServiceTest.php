<?php

/**
 * Section service tests.
 *
 * PHP version 8
 *
 * Copyright (C) The National Library of Finland 2025.
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
 * @author   Aleksi Peebles <aleksi.peebles@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */

namespace VuFindTest\Section;

use Laminas\ServiceManager\Exception\ServiceNotFoundException;
use VuFind\Exception\BadConfig;
use VuFind\Exception\ConfigException;
use VuFind\Section\SectionServiceInterface;
use VuFindTest\Unit\AbstractSectionTestCase;

/**
 * Section service tests.
 *
 * @category VuFind
 * @package  Tests
 * @author   Aleksi Peebles <aleksi.peebles@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */
class SectionServiceTest extends AbstractSectionTestCase
{
    /**
     * Data provider for testGetSectionConfiguration.
     *
     * @return \Iterator<string, array>
     */
    public static function getSectionConfigurationProvider(): \Iterator
    {
        yield 'Missing section key from default configuration' => [
            'MissingSectionKey',
            SectionServiceInterface::DEFAULT_CONFIG_PATH,
            BadConfig::class,
            'Section not found: MissingSectionKey',
        ];
        yield 'Missing configuration' => [
            'MissingConfiguration',
            'MissingConfiguration',
            ConfigException::class,
            'Configuration path not found or empty: MissingConfiguration',
        ];
    }

    /**
     * Test getting section configuration.
     *
     * @param string  $key                    Section key in configuration
     * @param string  $configPath             Configuration path
     * @param string  $expectedExceptionClass Expected exception class
     * @param ?string $expectedExceptionMsg   Expected exception message
     *
     * @return void
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('getSectionConfigurationProvider')]
    public function testGetSectionConfiguration(
        string $key,
        string $configPath,
        string $expectedExceptionClass,
        ?string $expectedExceptionMsg = null
    ): void {
        $this->expectException($expectedExceptionClass);
        if ($expectedExceptionMsg) {
            $this->expectExceptionMessage($expectedExceptionMsg);
        }
        $this->getSectionService()->getSectionConfig($key, $configPath);
    }

    /**
     * Data provider for testSectionConfiguration.
     *
     * @return \Iterator<string, array>
     */
    public static function sectionConfigurationProvider(): \Iterator
    {
        yield 'Missing plugin' => [
            'MissingPlugin',
            [],
            BadConfig::class,
            'Missing required setting: plugin',
        ];
        yield 'Nonexistent plugin' => [
            'NonexistentPlugin',
            ['plugin' => 'foobar'],
            ServiceNotFoundException::class,
        ];
    }

    /**
     * Test section configuration.
     *
     * @param string  $key                    Section key
     * @param array   $config                 Configuration
     * @param string  $expectedExceptionClass Expected exception class
     * @param ?string $expectedExceptionMsg   Expected exception message
     *
     * @return void
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('sectionConfigurationProvider')]
    public function testSectionConfiguration(
        string $key,
        array $config,
        string $expectedExceptionClass,
        ?string $expectedExceptionMsg = null
    ): void {
        $this->expectException($expectedExceptionClass);
        if ($expectedExceptionMsg) {
            $this->expectExceptionMessage($expectedExceptionMsg);
        }
        $this->getSectionService()->getSection($key, $config);
    }

    /**
     * Test settings localization.
     *
     * @return void
     */
    public function testSettingsLocalization(): void
    {
        $config = [
            'Account' => [
                'label' => 'Your Account',
                'MenuItems' => [
                    [
                        'label' => 'saved_items',
                        'url' => [
                            'en' => 'English language URL',
                            'fi' => 'Finnish language URL',
                        ],
                    ],
                ],
            ],
        ];
        $container = $this->getContainerWithSectionRelatedServices();
        $localizedConfig = $this->getAccountMenu($container, $config)->getSectionConfig();
        $this->assertEquals(
            'English language URL',
            $localizedConfig['Account']['MenuItems'][0]['url']
        );
        $container = $this->getContainerWithSectionRelatedServices('fi');
        $localizedConfig = $this->getAccountMenu($container, $config)->getSectionConfig();
        $this->assertEquals(
            'Finnish language URL',
            $localizedConfig['Account']['MenuItems'][0]['url']
        );
        $container = $this->getContainerWithSectionRelatedServices('sv', ['fi', 'en']);
        $localizedConfig = $this->getAccountMenu($container, $config)->getSectionConfig();
        $this->assertEquals(
            'Finnish language URL',
            $localizedConfig['Account']['MenuItems'][0]['url']
        );
    }

    /**
     * Data provider for testAlwaysFailCheckMethod.
     *
     * @return \Iterator<string, array>
     */
    public static function alwaysFailCheckMethodProvider(): \Iterator
    {
        yield 'Hide section' => [
            [
                'Header' => [
                    'checkMethod' => 'alwaysFail',
                    'MenuItems' => [
                        [
                            'label' => 'Item 1 label',
                            'url' => '#',
                        ],
                        [
                            'label' => 'Item 2 label',
                            'url' => '#',
                        ],
                    ],
                ],
            ],
            [],
        ];
        yield 'Hide item' => [
            [
                'Header' => [
                    'MenuItems' => [
                        [
                            'label' => 'Item 1 label',
                            'url' => '#',
                        ],
                        [
                            'label' => 'Item 2 label',
                            'url' => '#',
                            'checkMethod' => 'alwaysFail',
                        ],
                    ],
                ],
            ],
            [
                'Header' => [
                    'MenuItems' => [
                        [
                            'label' => 'Item 1 label',
                            'url' => '#',
                        ],
                    ],
                ],
            ],
        ];
    }

    /**
     * Test alwaysFail check method.
     *
     * @param array $config       Configuration
     * @param array $expectedMenu Expected menu
     *
     * @return void
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('alwaysFailCheckMethodProvider')]
    public function testAlwaysFailCheckMethod(array $config, array $expectedMenu): void
    {
        $container = $this->getContainerWithSectionRelatedServices();
        $this->assertEquals(
            $this->getHeaderBar($container, $config)->getMenu(),
            $expectedMenu
        );
    }
}
