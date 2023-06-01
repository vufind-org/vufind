<?php

/**
 * Config Locator Test Class
 *
 * PHP version 8
 *
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
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */

namespace VuFindTest\Config;

use VuFind\Config\Locator;
use VuFind\Config\PathResolver;

/**
 * Config Locator Test Class
 *
 * @category VuFind
 * @package  Tests
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */
class LocatorTest extends \PHPUnit\Framework\TestCase
{
    /**
     * Test Locator
     *
     * @return void
     */
    public function testLocator()
    {
        $baseConfig = APPLICATION_PATH . '/' . PathResolver::DEFAULT_CONFIG_SUBDIR
            . '/config.ini';
        $localConfig = LOCAL_OVERRIDE_DIR . '/' . PathResolver::DEFAULT_CONFIG_SUBDIR
            . '/config.ini';

        $this->assertEquals(
            $baseConfig,
            Locator::getBaseConfigPath('config.ini')
        );
        $this->assertEquals(
            $localConfig,
            Locator::getLocalConfigPath('config.ini', null, true)
        );
        $this->assertEquals(
            null,
            Locator::getLocalConfigPath('non-existent-config.ini')
        );
        $this->assertEquals(
            file_exists($localConfig) ? $localConfig : $baseConfig,
            Locator::getConfigPath('config.ini')
        );
    }
}
