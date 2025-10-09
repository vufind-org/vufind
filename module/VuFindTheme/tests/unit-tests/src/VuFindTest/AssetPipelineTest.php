<?php

/**
 * AssetPipeline Test Class
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
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */

namespace VuFindTest;

use Laminas\View\Helper\Url;
use PHPUnit\Framework\MockObject\MockObject;
use VuFindTheme\AssetPipeline;
use VuFindTheme\ThemeInfo;

use function count;

/**
 * AssetPipeline Test Class
 *
 * @category VuFind
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */
class AssetPipelineTest extends \PHPUnit\Framework\TestCase
{
    /**
     * Get a partially mocked pipeline object
     *
     * @param string|bool $pipelineConfig Pipeline configuration
     * @param ?ThemeInfo  $themeInfo      ThemeInfo object (omit for default mock)
     * @param ?Url        $urlHelper      URL view helper (omit for default mock)
     * @param array       $methods        Pipeline methods to mock (omit for all)
     * @param ?int        $maxImportSize  Maximum imported file size (null for no limit)
     *
     * @return MockObject&AssetPipeline
     */
    protected function getMockPipeline(
        string|bool $pipelineConfig = '*',
        ?ThemeInfo $themeInfo = null,
        ?Url $urlHelper = null,
        array $methods = [],
        ?int $maxImportSize = null
    ): MockObject&AssetPipeline {
        $themeInfo ??= $this->createMock(ThemeInfo::class);
        $urlHelper ??= $this->createMock(Url::class);
        return $this->getMockBuilder(AssetPipeline::class)
            ->onlyMethods($methods)
            ->setConstructorArgs([$themeInfo, $urlHelper, $pipelineConfig, $maxImportSize])
            ->getMock();
    }

    /**
     * Data provider for testIsPipelineEnabledForType().
     *
     * @return array[]
     */
    public static function isPipelineEnabledForTypeProvider(): array
    {
        return [
            'globally enabled (wildcard), available, js' => ['*', 'js', true, true],
            'globally enabled (wildcard), available, css' => ['*', 'css', true, true],
            'globally enabled (wildcard), unavailable, js' => ['*', 'js', false, false],
            'globally enabled (wildcard), unavailable, css' => ['*', 'css', false, false],
            'globally enabled (on), available, js' => ['on', 'js', true, true],
            'globally enabled (on), available, css' => ['on', 'css', true, true],
            'globally enabled (on), unavailable, js' => ['on', 'js', false, false],
            'globally enabled (on), unavailable, css' => ['on', 'css', false, false],
            'globally enabled (boolean), available, js' => [true, 'js', true, true],
            'globally enabled (boolean), available, css' => [true, 'css', true, true],
            'globally enabled (boolean), unavailable, js' => [true, 'js', false, false],
            'globally enabled (boolean), unavailable, css' => [true, 'css', false, false],
            'globally enabled (boolean string), available, js' => ['true', 'js', true, true],
            'globally enabled (boolean string), available, css' => ['true', 'css', true, true],
            'globally enabled (boolean string), unavailable, js' => ['true', 'js', false, false],
            'globally enabled (boolean string), unavailable, css' => ['true', 'css', false, false],
            'globally enabled (1), available, js' => ['1', 'js', true, true],
            'globally enabled (1), available, css' => ['1', 'css', true, true],
            'globally enabled (1), unavailable, js' => ['1', 'js', false, false],
            'globally enabled (1), unavailable, css' => ['1', 'css', false, false],
            'globally enabled (delimited list), available, js' => ['js,css', 'js', true, true],
            'globally enabled (delimited list), available, css' => ['js,css', 'css', true, true],
            'globally enabled (delimited list), unavailable, js' => ['js,css', 'js', false, false],
            'globally enabled (delimited list), unavailable, css' => ['js,css', 'css', false, false],
            'globally enabled (delimited list with space), available, js' => ['css, js', 'js', true, true],
            'globally enabled (delimited list with space), available, css' => ['css, js', 'css', true, true],
            'globally enabled (delimited list with space), unavailable, js' => ['css, js', 'js', false, false],
            'globally enabled (delimited list with space), unavailable, css' => ['css, js', 'css', false, false],
            'globally disabled (boolean), available, js' => [false, 'js', true, false],
            'globally disabled (boolean), available, css' => [false, 'css', true, false],
            'globally disabled (boolean), unavailable, js' => [false, 'js', false, false],
            'globally disabled (boolean), unavailable, css' => [false, 'css', false, false],
            'globally disabled (off), available, js' => ['off', 'js', true, false],
            'globally disabled (off), available, css' => ['off', 'css', true, false],
            'globally disabled (off), unavailable, js' => ['off', 'js', false, false],
            'globally disabled (off), unavailable, css' => ['off', 'css', false, false],
            'globally disabled (boolean string), available, js' => ['false', 'js', true, false],
            'globally disabled (boolean string), available, css' => ['false', 'css', true, false],
            'globally disabled (boolean string), unavailable, js' => ['false', 'js', false, false],
            'globally disabled (boolean string), unavailable, css' => ['false', 'css', false, false],
            'globally disabled (0), available, js' => ['0', 'js', true, false],
            'globally disabled (0), available, css' => ['0', 'css', true, false],
            'globally disabled (0), unavailable, js' => ['0', 'js', false, false],
            'globally disabled (0), unavailable, css' => ['0', 'css', false, false],
            'css only, available, js' => ['css', 'js', true, false],
            'css only, available, css' => ['css', 'css', true, true],
            'css only, unavailable, js' => ['css', 'js', false, false],
            'css only, unavailable, css' => ['css', 'css', false, false],
            'js only, available, js' => ['js', 'js', true, true],
            'js only, available, css' => ['js', 'css', true, false],
            'js only, unavailable, js' => ['js', 'js', false, false],
            'js only, unavailable, css' => ['js', 'css', false, false],
        ];
    }

    /**
     * Test that pipelines are appropriately enabled/disabled based on configuration.
     *
     * @param string|bool $config        Configuration
     * @param string      $type          Asset type
     * @param bool        $available     Is the pipeline available?
     * @param bool        $expectGrouped Do we expect the pipeline to be applied (true) or not (false)?
     *
     * @return void
     *
     * @dataProvider isPipelineEnabledForTypeProvider
     */
    public function testIsPipelineEnabledForType(
        string|bool $config,
        string $type,
        bool $available,
        bool $expectGrouped
    ): void {
        $pipeline = $this->getMockPipeline(
            $config,
            methods: ['isPipelineAvailable', 'groupAssets', 'processGroupedAssets']
        );
        $pipeline->method('isPipelineAvailable')->willReturn($available);
        $raw = ['simulated raw assets'];
        $grouped = ['simulated grouped assets'];
        $pipeline->expects($this->any())->method('groupAssets')->with($raw, $type)->willReturn($grouped);
        $pipeline->expects($this->any())->method('processGroupedAssets')->with($grouped, $type)->willReturn($grouped);
        $this->assertEquals($expectGrouped ? $grouped : $raw, $pipeline->process($raw, $type));
    }

    /**
     * Data provider for testGroupAssets().
     *
     * @return array
     */
    public static function groupAssetsProvider(): array
    {
        return [
            'empty css array' => [[], 'css', []],
            'empty js array' => [[], 'js', []],
            'simple css links' => [
                [
                    ['href' => 'foo.css'],
                    ['href' => 'bar.css'],
                ],
                'css',
                [
                    [
                        'items' => [
                            ['href' => 'foo.css'],
                            ['href' => 'bar.css'],
                        ],
                        'key' => '/theme/css/foo.css/theme/css/bar.css',
                    ],
                ],
            ],
            'complex css links' => [
                [
                    ['href' => 'foo.css'],
                    ['href' => 'http://bar.css'],
                    ['href' => 'baz.css', 'options' => ['exclude_from_pipeline' => true]],
                ],
                'css',
                [
                    [
                        'items' => [
                            ['href' => 'foo.css'],
                        ],
                        'key' => '/theme/css/foo.css',
                    ],
                    [
                        'other' => true,
                        'item' => ['href' => 'http://bar.css'],
                    ],
                    [
                        'other' => true,
                        'item' => ['href' => 'baz.css', 'options' => ['exclude_from_pipeline' => true]],
                    ],
                ],
            ],
            'simple js links' => [
                [
                    ['src' => 'foo.js'],
                    ['src' => 'bar.js'],
                ],
                'js',
                [
                    [
                        'items' => [
                            ['src' => 'foo.js'],
                            ['src' => 'bar.js'],
                        ],
                        'key' => '/theme/js/foo.js/theme/js/bar.js',
                    ],
                ],
            ],
            'complex js links' => [
                [
                    ['src' => 'foo.js'],
                    ['src' => 'http://bar.js'],
                    ['src' => 'baz.js', 'options' => ['exclude_from_pipeline' => true]],
                    ['src' => 'xyzzy.js', 'attrs' => ['conditional' => 'foo']],
                ],
                'js',
                [
                    [
                        'items' => [
                            ['src' => 'foo.js'],
                        ],
                        'key' => '/theme/js/foo.js',
                    ],
                    [
                        'other' => true,
                        'item' => ['src' => 'http://bar.js'],
                    ],
                    [
                        'other' => true,
                        'item' => ['src' => 'baz.js', 'options' => ['exclude_from_pipeline' => true]],
                    ],
                    [
                        'other' => true,
                        'item' => ['src' => 'xyzzy.js', 'attrs' => ['conditional' => 'foo']],
                    ],
                ],
            ],
        ];
    }

    /**
     * Test asset grouping.
     *
     * @param array  $assets                Assets to process
     * @param string $type                  Asset type
     * @param array  $expectedGroupedAssets Expected processed assets
     *
     * @return void
     *
     * @dataProvider groupAssetsProvider
     */
    public function testGroupAssets(array $assets, string $type, array $expectedGroupedAssets): void
    {
        $themeInfo = $this->createMock(ThemeInfo::class);
        $themeInfo->method('findContainingTheme')->willReturnCallback(
            function ($path) {
                return ['path' => "/theme/$path"];
            }
        );
        $pipeline = $this->getMockPipeline(
            themeInfo: $themeInfo,
            methods: ['getKeyForFile', 'isPipelineAvailable', 'processGroupedAssets']
        );
        $pipeline->method('isPipelineAvailable')->willReturn(true);
        $pipeline->method('getKeyForFile')->willReturnCallback(fn ($file) => $file);
        $pipeline->expects($this->once())->method('processGroupedAssets')->with($expectedGroupedAssets, $type)
            ->willReturn([]);
        $pipeline->process($assets, $type);
    }

    /**
     * Data provider for testProcessGroupedAssets().
     *
     * @return array[]
     */
    public static function processGroupedAssetsProvider(): array
    {
        return [
            'simple css links' => [
                [
                    [
                        'items' => [
                            ['href' => 'foo.css'],
                            ['href' => 'bar.css'],
                        ],
                        'key' => '/theme/css/foo.css/theme/css/bar.css',
                    ],
                ],
                'css',
                [
                    ['href' => '2-css'],
                ],
            ],
            'complex css links' => [
                [
                    [
                        'items' => [
                            ['href' => 'foo.css'],
                        ],
                        'key' => '/theme/css/foo.css',
                    ],
                    [
                        'other' => true,
                        'item' => ['href' => 'http://bar.css'],
                    ],
                    [
                        'other' => true,
                        'item' => ['href' => 'baz.css', 'options' => ['exclude_from_pipeline' => true]],
                    ],
                ],
                'css',
                [
                    ['href' => '1-css'],
                    ['href' => 'http://bar.css'],
                    ['href' => 'baz.css', 'options' => ['exclude_from_pipeline' => true]],
                ],
            ],
            'simple js links' => [
                [
                    [
                        'items' => [
                            ['src' => 'foo.js'],
                            ['src' => 'bar.js'],
                        ],
                        'key' => '/theme/js/foo.js/theme/js/bar.js',
                    ],
                ],
                'js',
                [
                    ['src' => '2-js'],
                ],
            ],
            'complex js links' => [
                [
                    [
                        'items' => [
                            ['src' => 'foo.js'],
                        ],
                        'key' => '/theme/js/foo.js',
                    ],
                    [
                        'other' => true,
                        'item' => ['src' => 'http://bar.js'],
                    ],
                    [
                        'other' => true,
                        'item' => ['src' => 'baz.js', 'options' => ['exclude_from_pipeline' => true]],
                    ],
                    [
                        'other' => true,
                        'item' => ['src' => 'xyzzy.js', 'attrs' => ['conditional' => 'foo']],
                    ],
                ],
                'js',
                [
                    ['src' => '1-js'],
                    ['src' => 'http://bar.js'],
                    ['src' => 'baz.js', 'options' => ['exclude_from_pipeline' => true]],
                    ['src' => 'xyzzy.js', 'attrs' => ['conditional' => 'foo']],
                ],
            ],

        ];
    }

    /**
     * Test processing of grouped assets.
     *
     * @param array  $groupedAssets  Grouped assets to process
     * @param string $type           Asset type
     * @param array  $expectedResult Expected final result
     *
     * @return void
     *
     * @dataProvider processGroupedAssetsProvider
     */
    public function testProcessGroupedAssets(array $groupedAssets, string $type, array $expectedResult): void
    {
        $pipeline = $this->getMockPipeline(
            methods: ['getConcatenatedFilePath', 'groupAssets', 'isPipelineAvailable']
        );
        $pipeline->method('isPipelineAvailable')->willReturn(true);
        $pipeline->method('getConcatenatedFilePath')->willReturnCallback(
            function ($group, $type) {
                return count($group['items'] ?? []) . '-' . $type;
            }
        );
        $fakeAssets = [];
        $pipeline->expects($this->once())->method('groupAssets')->with($fakeAssets, $type)->willReturn($groupedAssets);
        $this->assertEquals($expectedResult, $pipeline->process($fakeAssets, $type));
    }
}
