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

use VuFind\Config\PathResolver;
use VuFindTest\Feature\FixtureTrait;
use VuFindTest\Feature\PathResolverTrait;

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
    use PathResolverTrait;

    /**
     * Stacked path resolver
     *
     * @var PathResolver
     */
    protected $stackedResolver;

    /**
     * Setup method.
     *
     * @return void
     */
    public function setUp(): void
    {
        $fixtureDir = $this->getStackedFixtureDir();
        $this->stackedResolver = new PathResolver(
            [
                'directory' => APPLICATION_PATH,
                'defaultConfigSubdir' => PathResolver::DEFAULT_CONFIG_SUBDIR,
            ],
            [
                [
                    'directory' => $fixtureDir . 'secondary',
                    'defaultConfigSubdir' => 'config/custom',
                ],
                [
                    'directory' => $fixtureDir . 'primary',
                    'defaultConfigSubdir' => PathResolver::DEFAULT_CONFIG_SUBDIR,
                ],
            ]
        );
    }

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
     * Data provider for testPathStack
     *
     * @return array
     */
    public static function getTestPathStackData(): array
    {
        return [
            [
                // A file that exists only in the primary path:
                'only-primary.ini',
                'primary/config/vufind/only-primary.ini',
            ],
            [
                // A file that exists both in the primary and secondary paths:
                'both.ini',
                'primary/config/vufind/both.ini',
            ],
            [
                // A file that exists in the secondary path as well as base path:
                'facets.ini',
                'secondary/config/custom/facets.ini',
            ],
            [
                // A file that exists only in the base path:
                'config.ini',
                'config/vufind/config.ini',
                APPLICATION_PATH . '/',
            ],
        ];
    }

    /**
     * Test stacked path resolution
     *
     * @param string  $filename         Filename to check
     * @param string  $expectedFilePath Expected result (minus base path)
     * @param ?string $expectedBasePath Expected base path in result (null = use default fixture path)
     *
     * @dataProvider getTestPathStackData
     *
     * @return void
     */
    public function testPathStack(string $filename, string $expectedFilePath, ?string $expectedBasePath = null): void
    {
        $this->assertEquals(
            ($expectedBasePath ?? $this->getStackedFixtureDir()) . $expectedFilePath,
            $this->stackedResolver->getConfigPath($filename)
        );
    }

    /**
     * Get path to stacked config fixtures
     *
     * @return string
     */
    public function getStackedFixtureDir(): string
    {
        return realpath($this->getFixtureDir() . 'configs/pathstack') . '/';
    }
}
