<?php

/**
 * EDS Options Test.
 *
 * PHP version 8
 *
 * Copyright (C) Hebis Verbundzentrale 2026.
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
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */

namespace VuFindTest\Search\EDS;

use VuFind\Search\EDS\Options;
use VuFindTest\Feature\ConfigRelatedServicesTrait;

/**
 * EDS Options Test.
 *
 * @category VuFind
 * @package  Tests
 * @author   Thomas Wagener <wagener@hebis.uni-frankfurt.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */
class OptionsTest extends \PHPUnit\Framework\TestCase
{
    use ConfigRelatedServicesTrait;

    /**
     * Test that the API is not called on construction.
     *
     * @return void
     */
    public function testApiIsNotCalledOnConstruction(): void
    {
        $mockConfigManager = $this->getMockConfigManager();
        $apiInfoCallback = function (): array {
            throw new \Exception('API callback should not be called on construction');
        };
        new Options($mockConfigManager, $apiInfoCallback);
    }

    /**
     * Data provider for testViewOptions.
     *
     * @return \Iterator
     */
    public static function viewOptionsProvider(): \Iterator
    {
        yield 'all defaults' => [
            [],
            [],
            'brief',
            ['list_brief' => 'list_brief'],
        ];
        yield 'default set in EDS.ini' => [
            [
                'General' => [
                    'default_view' => 'list_title',
                ],
            ],
            [],
            'title',
            ['list_title' => 'list_title'],
        ];
        yield 'options set in EDS.ini' => [
            [
                'Views' => [
                    'list_title' => 'Title View',
                    'list_brief' => 'Brief View',
                    'list_detailed' => 'Detailed View',
                ],
            ],
            [],
            'brief',
            [
                'list_title' => 'Title View',
                'list_brief' => 'Brief View',
                'list_detailed' => 'Detailed View',
            ],
        ];
        yield 'options and default set in EDS.ini' => [
            [
                'General' => [
                    'default_view' => 'list_title',
                ],
                'Views' => [
                    'list_title' => 'Title View',
                    'list_brief' => 'Brief View',
                    'list_detailed' => 'Detailed View',
                ],
            ],
            [],
            'title',
            [
                'list_title' => 'Title View',
                'list_brief' => 'Brief View',
                'list_detailed' => 'Detailed View',
            ],
        ];
        yield 'default set in API response' => [
            [],
            ['ViewResultSettings' => ['ResultListView' => 'detailed']],
            'detailed',
            ['list_detailed' => 'list_detailed'],
        ];
        yield [
            [
                'General' => [
                    'default_view' => 'list_title',
                ],
            ],
            ['ViewResultSettings' => ['ResultListView' => 'detailed']],
            'detailed',
            ['list_detailed' => 'list_detailed'],
        ];
        yield [
            [
                'Views' => [
                    'list_title' => 'Title View',
                    'list_brief' => 'Brief View',
                    'list_detailed' => 'Detailed View',
                ],
            ],
            ['ViewResultSettings' => ['ResultListView' => 'detailed']],
            'detailed',
            [
                'list_title' => 'Title View',
                'list_brief' => 'Brief View',
                'list_detailed' => 'Detailed View',
            ],
        ];
        yield [
            [
                'General' => [
                    'default_view' => 'list_title',
                ],
                'Views' => [
                    'list_title' => 'Title View',
                    'list_brief' => 'Brief View',
                    'list_detailed' => 'Detailed View',
                ],
            ],
            ['ViewResultSettings' => ['ResultListView' => 'detailed']],
            'detailed',
            [
                'list_title' => 'Title View',
                'list_brief' => 'Brief View',
                'list_detailed' => 'Detailed View',
            ],
        ];
    }

    /**
     * Test that the Options handle view settings correctly.
     *
     * @param array  $config              EDS configuration
     * @param array  $apiInfo             Info from the EDS API
     * @param string $expectedEbscoView   Expected EBSCO view
     * @param string $expectedViewOptions Expected view options
     *
     * @return void
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('viewOptionsProvider')]
    public function testViewOptions(
        array $config,
        array $apiInfo,
        string $expectedEbscoView,
        array $expectedViewOptions
    ): void {
        $mockConfigManager = $this->getMockConfigManager(
            [
                'EDS' => $config,
            ]
        );
        $apiInfoCallback = function () use ($apiInfo): array {
            return $apiInfo;
        };
        $options = new Options($mockConfigManager, $apiInfoCallback);
        $this->assertSame($expectedEbscoView, $options->getEbscoView());
        $this->assertEquals($expectedViewOptions, $options->getViewOptions());
    }
}
