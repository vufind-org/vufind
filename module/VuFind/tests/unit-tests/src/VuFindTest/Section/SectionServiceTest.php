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
     * Data provider for testSectionConfiguration.
     *
     * @return array
     */
    public static function sectionConfigurationProvider(): array
    {
        return [
            // Missing section key from default configuration file.
            [
                'MissingSectionKey',
                SectionServiceInterface::DEFAULT_CONFIG_FILE,
                BadConfig::class,
                'Section not found: MissingSectionKey',
            ],
            // Missing configuration file.
            [
                'MissingConfigurationFile',
                'MissingConfigurationFile.yaml',
                ConfigException::class,
                'Configuration file not found or empty: MissingConfigurationFile.yaml',
            ],
            // Missing section type.
            [
                'MissingSectionType',
                [],
                BadConfig::class,
                'Missing required setting: type',
            ],
            // Navigation plugin with a missing plugin setting.
            [
                'MissingNavigationPlugin',
                ['type' => 'navigation'],
                BadConfig::class,
                'Missing required setting: plugin',
            ],
            // Nonexistent navigation plugin.
            [
                'NonexistentNavigationPlugin',
                [
                    'type' => 'navigation',
                    'plugin' => 'nonexistentNavigationPlugin',
                ],
                ServiceNotFoundException::class,
            ],
        ];
    }

    /**
     * Test section configuration.
     *
     * @param string       $key                    Section key in configuration
     * @param array|string $config                 Section configuration or
     *                                             configuration file name
     * @param string       $expectedExceptionClass Expected exception class
     * @param ?string      $expectedExceptionMsg   Expected exception message
     *
     * @return void
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('sectionConfigurationProvider')]
    public function testSectionConfiguration(
        string $key,
        array|string $config,
        string $expectedExceptionClass,
        ?string $expectedExceptionMsg = null
    ) {
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
    public function testSettingsLocalization()
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
        $localizedConfig = $this->getAccountMenu($config)->getConfig();
        $this->assertEquals(
            'English language URL',
            $localizedConfig['Account']['MenuItems'][0]['url']
        );
    }
}
