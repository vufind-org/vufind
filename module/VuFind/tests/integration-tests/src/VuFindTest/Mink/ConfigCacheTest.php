<?php

/**
 * Mink config cache test class.
 *
 * PHP version 8
 *
 * Copyright (C) Hebis Verbundzentrale 2025.
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
 * @author   Thomas Wagener <wagener@hebis.uni-frankfurt.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */

namespace VuFindTest\Mink;

use VuFindTest\Feature\CacheManagementTrait;

/**
 * Mink config cache test class.
 *
 * @category VuFind
 * @package  Tests
 * @author   Thomas Wagener <wagener@hebis.uni-frankfurt.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */
class ConfigCacheTest extends \VuFindTest\Integration\MinkTestCase
{
    use CacheManagementTrait;

    /**
     * Data provider for configuration caching tests
     *
     * @return array
     */
    public static function cacheSettingsProvider(): array
    {
        return [
            'no caching' => [
                false,
                false,
                false,
            ],
            'override no caching' => [
                false,
                true,
                true,
            ],
            'override caching' => [
                true,
                false,
                false,
            ],
            'all caching' => [
                true,
                true,
                true,
            ],
        ];
    }

    /**
     * Test configuration caching.
     *
     * @param bool $cacheDefault     Cache enabled by default
     * @param bool $cacheIni         Ini cache enabled
     * @param bool $cacheSearchspecs Searchspecs cache enabled
     *
     * @dataProvider cacheSettingsProvider
     *
     * @return void
     */
    public function testConfigurationCaching(bool $cacheDefault, bool $cacheIni, bool $cacheSearchspecs): void
    {
        $this->changeConfigs(
            [
                'config' => [
                    'ConfigCache' => [
                        'disabled' => !$cacheDefault,
                        'reloadOnFileChange' => false,
                    ],
                    'CacheConfigHandler_ini' => ['disabled' => !$cacheIni],
                    'CacheConfigName_searchspecs' => ['disabled' => !$cacheSearchspecs],
                ],
            ] + $this->getCacheClearPermissionConfig()
        );
        $this->clearCache('config');
        $this->clearCache('searchspecs');
        // setup local config files to be cached.
        $this->changeConfigs(['searchbox' => []]);
        $this->changeYamlConfigs(['searchspecs' => []]);
        $page = $this->performSearch('Author, Primary 1795 - 1881');
        $this->unFindCss($page, '.keyboard-selection');
        $expected = '/Showing 1 - \d+ results of \d+/';
        $this->assertMatchesRegularExpression(
            $expected,
            $this->findCssAndGetText($page, '.search-stats')
        );
        $this->changeConfigs(
            [
                'searchbox' => [
                    'VirtualKeyboard' => ['layouts' => ['english']],
                ],
            ]
        );
        $this->changeYamlConfigs(
            [
                'searchspecs' => [
                    'AllFields' => [
                        'DismaxFields' => [
                            'title^500',
                        ],
                    ],
                ],
             ],
            ['searchspecs']
        );
        $page = $this->performSearch('Author, Primary 1795 - 1881');
        if ($cacheIni) {
            $this->unFindCss($page, '.keyboard-selection');
        } else {
            $this->findCss($page, '.keyboard-selection');
        }
        $expected = $cacheSearchspecs ? '/Showing 1 - \d+ results of \d+/' : '/No Results!/';
        $this->assertMatchesRegularExpression(
            $expected,
            $this->findCssAndGetText($page, '.search-stats')
        );
    }
}
