<?php

/**
 * GetItemStatuses test class.
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2024.
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
 * @author   MSUL Public Catalog Team <LIB.DL.pubcat@msu.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */

namespace VuFindTest\AjaxHandler;

use Generator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\Exception;
use VuFind\AjaxHandler\GetItemStatuses;
use VuFind\Config\Config;
use VuFind\GetThis\GetThisLoader;
use VuFind\Http\RouteHelper;
use VuFind\ILS\Connection;
use VuFind\ILS\Logic\AvailabilityStatus;
use VuFind\ILS\Logic\AvailabilityStatusManager;
use VuFind\ILS\Logic\Holds;
use VuFind\Search\Base\Params;
use VuFind\Search\Base\Results;
use VuFind\Search\Memory;
use VuFind\Session\Settings;
use VuFind\View\Renderer\TemplateRendererInterface;
use VuFindTest\Unit\AjaxHandlerTestCase;

use function count;

/**
 * SystemStatus test class.
 *
 * @category VuFind
 * @package  Tests
 * @author   MSUL Public Catalog Team <LIB.DL.pubcat@msu.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */
class GetItemStatusesTest extends AjaxHandlerTestCase
{
    /**
     * Get GetItemStatuses Ajax handler.
     *
     * @param array                      $config                    Config
     * @param ?Settings                  $settings                  Settings
     * @param ?Connection                $ils                       Ils
     * @param ?TemplateRendererInterface $templateRenderer          TemplateRenderer
     * @param ?Holds                     $holdLogic                 HoldLogic
     * @param ?AvailabilityStatusManager $availabilityStatusManager AvailabilityStatusManager
     * @param ?GetThisLoader             $getThis                   GetThisLoader
     * @param ?RouteHelper               $routeHelper               RouteHelper
     * @param ?Memory                    $searchMemory              SearchMemory
     *
     * @return GetItemStatuses
     * @throws Exception
     */
    protected function getHandler(
        array $config = [],
        ?Settings $settings = null,
        ?Connection $ils = null,
        ?TemplateRendererInterface $templateRenderer = null,
        ?Holds $holdLogic = null,
        ?AvailabilityStatusManager $availabilityStatusManager = null,
        ?GetThisLoader $getThis = null,
        ?RouteHelper $routeHelper = null,
        ?Memory $searchMemory = null,
    ): GetItemStatuses {
        return new GetItemStatuses(
            $settings ?? $this->createStub(Settings::class),
            new Config($config),
            $ils ?? $this->createStub(Connection::class),
            $templateRenderer ?? $this->createStub(TemplateRendererInterface::class),
            $holdLogic ?? $this->createStub(Holds::class),
            $availabilityStatusManager ?? $this->createStub(AvailabilityStatusManager::class),
            $getThis,
            $routeHelper ?? $this->createStub(RouteHelper::class),
            $searchMemory ?? $this->createStub(Memory::class)
        );
    }

    /**
     * Provider for testStatusesSorting.
     *
     * @return Generator
     */
    public static function provideDataStatusesSorting(): Generator
    {
        $records = [
            [
                [
                    'id' => '4',
                    'availability' => new AvailabilityStatus(false, 'Damaged'),
                    'location' => '0/3rd Floor Main Library',
                ],
                [
                    'id' => '1',
                    'availability' => new AvailabilityStatus(false, 'Not available'),
                    'location' => '0/1st Floor Main Library',
                ],
                [
                    'id' => '2',
                    'availability' => new AvailabilityStatus(true, 'Available'),
                    'location' => '0/1st Floor Main Library',
                ],
                [
                    'id' => '3',
                    'availability' => new AvailabilityStatus(false, 'Not here'),
                    'location' => '0/2nd Floor Main Library',
                ],
                [
                    'id' => '5',
                    'availability' => new AvailabilityStatus(true, 'Available'),
                    'location' => '0/2nd Floor Main Library',
                ],
            ],
        ];

        yield 'no sorting' => [
            'records' => $records,
            'expected' => [
                4, 1, 2, 3, 5,
            ],
            'filters' => [
            ],
            'config' => [
                'Record' => [
                    'getStatusesSorting' => 'false',
                ],
            ],
        ];

        yield 'simple sorting on location' => [
            'records' => $records,
            'expected' => [
                1, 2, 3, 5, 4,
            ],
            'filters' => [
            ],
            'config' => [
                'Record' => [
                    'getStatusesSorting' => [
                        'location' => '',
                    ],
                ],
            ],
        ];

        yield 'simple reversed sorting on location' => [
            'records' => $records,
            'expected' => [
                4, 3, 5, 1, 2,
            ],
            'filters' => [
            ],
            'config' => [
                'Record' => [
                    'getStatusesSorting' => [
                        'location' => 'reversed',
                    ],
                ],
            ],
        ];

        yield 'sorting on availability (first) + location' => [
            'records' => $records,
            'expected' => [
                2, 5, 3, 1, 4,
            ],
            'filters' => [
            ],
            'config' => [
                'Record' => [
                    'getStatusesSorting' => [
                        'availability' => 'reversed',
                        'location' => '',
                    ],
                ],
            ],
        ];

        yield 'sorting on location (first) + availability' => [
            'records' => $records,
            'expected' => [
                2, 1, 5, 3, 4,
            ],
            'filters' => [
            ],
            'config' => [
                'Record' => [
                    'getStatusesSorting' => [
                        'location' => '',
                        'availability' => 'reversed',
                    ],
                ],
            ],
        ];

        yield 'sorting on location filter' => [
            'records' => $records,
            'expected' => [
                3, 5, 4, 1, 2,
            ],
            'filters' => [
                'Location' => [
                    ['displayText' => '0/2nd Floor Main Library'],
                ],
            ],
            'config' => [
                'Record' => [
                    'getStatusesSorting' => [
                        'compareLocationFilters' => '',
                    ],
                ],
            ],
        ];

        yield 'sorting on multi location filter + availability' => [
            'records' => $records,
            'expected' => [
                5, 3, 2, 1, 4,
            ],
            'filters' => [
                'Location' => [
                    ['displayText' => '0/2nd Floor Main Library'],
                    ['displayText' => '0/1st Floor Main Library'],
                ],
            ],
            'config' => [
                'Record' => [
                    'getStatusesSorting' => [
                        'compareLocationFilters' => '',
                        'availability' => 'reversed',
                    ],
                ],
            ],
        ];
    }

    /**
     * Test the AJAX handler's response if access is denied.
     *
     * @param array $records  List of records returned by the ILS
     * @param array $expected Expected list of ids
     * @param array $filters  Search filters
     * @param array $config   Config
     *
     * @return void
     * @throws Exception
     */
    #[DataProvider('provideDataStatusesSorting')]
    public function testStatusesSorting(
        array $records,
        array $expected,
        array $filters,
        array $config
    ): void {

        $params = $this->createMock(Params::class);
        $params->method('getFilterList')->willReturn($filters);
        $results = $this->createMock(Results::class);
        $results->method('getParams')->willReturn($params);
        $searchMemory = $this->createMock(Memory::class);
        $searchMemory->method('getCurrentSearch')->willReturn($results);
        $holdLogic = $this->createMock(Holds::class);
        $holdLogic->method('getSuppressedLocations')->willReturn([]);
        $availabilityStatusManager = $this->createMock(AvailabilityStatusManager::class);
        $availabilityStatusManager->method('combine')->willReturnCallback(
            function (array $record): array {
                return $record[0];
            }
        );

        $getItemStatuses = $this->getHandler(
            $config,
            holdLogic: $holdLogic,
            availabilityStatusManager: $availabilityStatusManager,
            searchMemory: $searchMemory
        );

        $ids = [];
        for ($i = 1; $i < count($records); $i++) {
            $ids[] = $i;
        }

        $recordsStatuses = $getItemStatuses->parseRecordsStatusesFromIlsData(
            $ids,
            $records,
            'all',
            'all',
            true
        );
        $holdings = current($recordsStatuses)['record'];

        $ids = [];
        foreach ($holdings as $holding) {
            $ids[] = $holding['id'];
        }

        $this->assertEquals($expected, $ids);
    }
}
