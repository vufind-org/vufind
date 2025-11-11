<?php

/**
 * Config Path Resolver Test Class
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
 * along with this program; if not, see
 * <https://www.gnu.org/licenses/>.
 *
 * @category VuFind
 * @package  Tests
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */

namespace VuFindTest\Config;

use VuFind\Config\PathResolver;
use VuFindTest\Feature\ConfigRelatedServicesTrait;
use VuFindTest\Feature\FixtureTrait;

/**
 * Config Path Resolver Test Class
 *
 * @category VuFind
 * @package  Tests
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */
class PathResolverTest extends \PHPUnit\Framework\TestCase
{
    use FixtureTrait;
    use ConfigRelatedServicesTrait;

    /**
     * Test PathResolver
     *
     * @return void
     */
    public function testPathResolver(): void
    {
        $baseConfig = APPLICATION_PATH . '/' . PathResolver::DEFAULT_CONFIG_SUBDIR
            . '/config.ini';
        $localConfig = LOCAL_OVERRIDE_DIR . '/' . PathResolver::DEFAULT_CONFIG_SUBDIR
            . '/config.ini';

        $pathResolver = $this->getPathResolver();

        $this->assertEquals(
            $baseConfig,
            $pathResolver->getBaseConfigPath('config.ini')
        );
        $this->assertEquals(
            $localConfig,
            $pathResolver->getLocalConfigPath('config.ini', null, true)
        );
        $this->assertEquals(
            null,
            $pathResolver->getLocalConfigPath('non-existent-config.ini')
        );
        $this->assertEquals(
            file_exists($localConfig) ? $localConfig : $baseConfig,
            $pathResolver->getConfigPath('config.ini')
        );
    }

    /**
     * Data provider for testPathStack.
     *
     * @return array
     */
    public static function getTestPathStackData(): array
    {
        return [
            [
                // A file that exists only in the primary path:
                'primary.ini',
                'primary/config/vufind/primary.ini',
            ],
            [
                // A file that exists in all paths:
                'all.ini',
                'primary/config/vufind/all.ini',
            ],
            [
                // A file that exists in the secondary path as well as base path:
                'base-secondary.ini',
                'primary/../secondary/config/custom/base-secondary.ini',
            ],
            [
                // A file that exists only in the base path:
                'base.ini',
                'base/config/vufind/base.ini',
            ],
        ];
    }

    /**
     * Test stacked path resolution.
     *
     * @param string $filename         Filename to check
     * @param string $expectedFilePath Expected result (minus base path)
     *
     * @return void
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('getTestPathStackData')]
    public function testPathStack(string $filename, string $expectedFilePath): void
    {
        $fixtureDir = realpath($this->getFixtureDir() . 'configs/pathstack') . '/';
        $pathResolver = $this->getPathResolver(baseDir: $fixtureDir . 'base', localDir: $fixtureDir . 'primary');
        $this->assertEquals(
            $fixtureDir . $expectedFilePath,
            $pathResolver->getConfigPath($filename)
        );
    }
}
