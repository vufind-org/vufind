<?php

/**
 * ILS driver test
 *
 * PHP version 8
 *
 * Copyright (C) Hebis Verbundzentrale 2023.
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
 * @author   Thomas Wagener <wagener@hebis.uni-frankfurt.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */

namespace VuFindTest\ILS\Driver;

use Laminas\Config\Exception\RuntimeException;
use VuFind\ILS\Driver\ComposedDriver;
use VuFind\ILS\Logic\AvailabilityStatusInterface;

use function call_user_func_array;

/**
 * ILS driver test
 *
 * @category VuFind
 * @package  Tests
 * @author   Kyle McGrogan <km7717@ship.edu>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */
class ComposedDriverTest extends AbstractMultiDriverTestCase
{
    /**
     * Test that driver handles missing main ILS driver configuration properly.
     *
     * @return void
     */
    public function testMissingILSConfiguration()
    {
        $this->expectException(\VuFind\Exception\ILS::class);

        $driver = $this->getDriver(
            [
                'configLoader' => $this->getMockFailingConfigPluginManager(new RuntimeException()),
            ]
        );
        $driver->setConfig(['Drivers' => ['d1' => 'DAIA']]);
        $driver->init();

        $driver->getStatus('1');
    }

    /**
     * Testing method with defaultCall and only main driver
     *
     * @return void
     */
    public function testDefaultCallSingleMainDriver()
    {
        $expected = [
            '1' => [
                'success' => true,
                'status' => 'hold_cancel_success',
            ],
            '2' => [
                'success' => false,
                'status' => 'hold_cancel_fail',
            ],

        ];

        $composedDriver = $this->initSimpleMethodTest(
            'cancelHolds',
            [
                [
                    'patron' => $this->getPatron('username'),
                    'details' => ['1', '2'],
                ],
            ],
            [],
            [
                'd1' => [
                    'class' => 'Voyager',
                    'times' => $this->once(),
                    'return' => $expected,
                ],
            ]
        );

        $result = $composedDriver->cancelHolds(
            [
                'patron' => $this->getPatron('username'),
                'details' => ['1', '2'],
            ]
        );
        $this->assertEquals($expected, $result);
    }

    /**
     * Testing method with defaultCall and multiple drivers but using base main_driver
     *
     * @return void
     */
    public function testDefaultCallBaseMainDriver()
    {
        $expected = [
            '1' => [
                'success' => true,
                'status' => 'hold_cancel_success',
            ],
            '2' => [
                'success' => false,
                'status' => 'hold_cancel_fail',
            ],

        ];

        $composedDriver = $this->initSimpleMethodTest(
            'cancelHolds',
            [
                [
                    'patron' => $this->getPatron('username'),
                    'details' => ['1', '2'],
                ],
            ],
            [],
            [
                'd1' => [
                    'class' => 'Voyager',
                    'times' => $this->once(),
                    'return' => $expected,
                ],
                'd2' => [
                    'class' => 'Demo',
                    'times' => $this->never(),
                    'return' => null,
                ],
            ]
        );

        $result = $composedDriver->cancelHolds(
            [
                'patron' => $this->getPatron('username'),
                'details' => ['1', '2'],
            ]
        );
        $this->assertEquals($expected, $result);
    }

    /**
     * Testing method with defaultCall and multiple drivers and overwritten main_driver
     *
     * @return void
     */
    public function testDefaultCallOverwrittenMainDriver()
    {
        $expected = [
            '1' => [
                'success' => true,
                'status' => 'hold_cancel_success',
            ],
            '2' => [
                'success' => false,
                'status' => 'hold_cancel_fail',
            ],

        ];

        $composedDriver = $this->initSimpleMethodTest(
            'cancelHolds',
            [
                [
                    'patron' => $this->getPatron('username'),
                    'details' => ['1', '2'],
                ],
            ],
            [
                'main_driver' => 'd2',
            ],
            [
                'd1' => [
                    'class' => 'Demo',
                    'times' => $this->never(),
                    'return' => null,
                ],
                'd2' => [
                    'class' => 'Voyager',
                    'times' => $this->once(),
                    'return' => $expected,
                ],
            ]
        );

        $result = $composedDriver->cancelHolds(
            [
                'patron' => $this->getPatron('username'),
                'details' => ['1', '2'],
            ]
        );
        $this->assertEquals($expected, $result);
    }

    /**
     * Testing method with mergeSingleArrayResults and base main driver without support
     *
     * @return void
     */
    public function testMergeSingleArrayResultsBaseMainDriverWithoutSupport()
    {
        $result1 = [
            'firstname' => 'John',
            'lastname' => 'Doe',
        ];

        $result2 = null;

        $result3 = null;

        $expected = [
            'firstname' => 'John',
            'lastname' => 'Doe',
        ];

        $composedDriver = $this->initSimpleMethodTest(
            'getMyProfile',
            [$this->getPatron('username')],
            [
            ],
            [
                'd1' => [
                    'class' => 'Voyager',
                    'times' => $this->once(),
                    'return' => $result1,
                ],
                'd2' => [
                    'class' => 'Demo',
                    'times' => $this->never(),
                    'return' => $result2,
                ],
                'd3' => [
                    'class' => 'Demo',
                    'times' => $this->never(),
                    'return' => $result3,
                ],
            ]
        );

        $result = $composedDriver->getMyProfile(
            $this->getPatron('username')
        );
        $this->assertEquals($expected, $result);
    }

    /**
     * Testing method with mergeSingleArrayResults and base main driver with support
     *
     * @return void
     */
    public function testMergeSingleArrayResultsBaseMainDriverWithSupport()
    {
        $result1 = [
            'firstname' => 'John',
            'lastname' => 'Doe',
        ];

        $result2 = null;

        $result3 = [
            'address1' => 'Main Street 1.',
            'city' => 'Springfield',
            'phone' => '123456',
        ];

        $expected = [
            'firstname' => 'John',
            'lastname' => 'Doe',
            'address1' => 'Main Street 1.',
            'city' => 'Springfield',
        ];

        $composedDriver = $this->initSimpleMethodTest(
            'getMyProfile',
            [$this->getPatron('username')],
            [
                'support_drivers' => [
                    'd3' => 'city,address1,address2',
                ],
            ],
            [
                'd1' => [
                    'class' => 'Voyager',
                    'times' => $this->once(),
                    'return' => $result1,
                ],
                'd2' => [
                    'class' => 'Demo',
                    'times' => $this->never(),
                    'return' => $result2,
                ],
                'd3' => [
                    'class' => 'Demo',
                    'times' => $this->once(),
                    'return' => $result3,
                ],
            ]
        );

        $result = $composedDriver->getMyProfile(
            $this->getPatron('username')
        );
        $this->assertEquals($expected, $result);
    }

    /**
     * Testing method with mergeSingleArrayResults and overwritten main driver without support
     *
     * @return void
     */
    public function testMergeSingleArrayResultsOverwrittenMainDriverWithoutSupport()
    {
        $result1 = null;

        $result2 = [
            'firstname' => 'John',
            'lastname' => 'Doe',
        ];

        $result3 = null;

        $expected = [
            'firstname' => 'John',
            'lastname' => 'Doe',
        ];

        $composedDriver = $this->initSimpleMethodTest(
            'getMyProfile',
            [$this->getPatron('username')],
            [
                'main_driver' => 'd2',
            ],
            [
                'd1' => [
                    'class' => 'Voyager',
                    'times' => $this->never(),
                    'return' => $result1,
                ],
                'd2' => [
                    'class' => 'Demo',
                    'times' => $this->once(),
                    'return' => $result2,
                ],
                'd3' => [
                    'class' => 'Demo',
                    'times' => $this->never(),
                    'return' => $result3,
                ],
            ]
        );

        $result = $composedDriver->getMyProfile(
            $this->getPatron('username')
        );
        $this->assertEquals($expected, $result);
    }

    /**
     * Testing method with mergeSingleArrayResults and overwritten main driver with support
     *
     * @return void
     */
    public function testMergeSingleArrayResultsOverwrittenMainDriverWithSupport()
    {
        $result1 = null;

        $result2 = [
            'firstname' => 'John',
            'lastname' => 'Doe',
        ];

        $result3 = [
            'address1' => 'Main Street 1.',
            'city' => 'Springfield',
            'phone' => '123456',
        ];

        $expected = [
            'firstname' => 'John',
            'lastname' => 'Doe',
            'address1' => 'Main Street 1.',
            'city' => 'Springfield',
        ];

        $composedDriver = $this->initSimpleMethodTest(
            'getMyProfile',
            [$this->getPatron('username')],
            [
                'main_driver' => 'd2',
                'support_drivers' => [
                    'd3' => 'city,address1,address2',
                ],
            ],
            [
                'd1' => [
                    'class' => 'Voyager',
                    'times' => $this->never(),
                    'return' => $result1,
                ],
                'd2' => [
                    'class' => 'Demo',
                    'times' => $this->once(),
                    'return' => $result2,
                ],
                'd3' => [
                    'class' => 'Demo',
                    'times' => $this->once(),
                    'return' => $result3,
                ],
            ]
        );

        $result = $composedDriver->getMyProfile(
            $this->getPatron('username')
        );
        $this->assertEquals($expected, $result);
    }

    /**
     * Testing method with combineArraysOfAssociativeArrays and only base main driver
     *
     * @return void
     */
    public function testCombineArraysOfAssociativeArraysBaseMainDriverNoSupportNoSubfields()
    {
        $expected = [
            [
                'id' => '123456',
                'status' => 'in',
            ],
        ];

        $composedDriver = $this->initSimpleMethodTest(
            'getHolding',
            [
                '123456',
            ],
            [],
            [
                'd1' => [
                    'class' => 'Voyager',
                    'times' => $this->once(),
                    'return' => $expected,
                ],
                'd2' => [
                    'class' => 'Demo',
                    'times' => $this->never(),
                    'return' => null,
                ],
            ]
        );

        $result = $composedDriver->getHolding(
            '123456'
        );
        $this->assertEquals($expected, $result);
    }

    /**
     * Testing method with combineArraysOfAssociativeArrays and only base main driver with results in subfields
     *
     * @return void
     */
    public function testCombineArraysOfAssociativeArraysBaseMainDriverNoSupportWithSubfields()
    {
        $expected = [
            'count' => 2,
            'holdings' => [
                'id' => '123456',
                'item_id' => '111111',
                'status' => 'in',
            ],
            'electronic_holdings' => [
                'id' => '123456',
                'item_id' => '222222',
                'status' => 'out',
            ],
        ];

        $composedDriver = $this->initSimpleMethodTest(
            'getHolding',
            [
                '123456',
            ],
            [],
            [
                'd1' => [
                    'class' => 'Voyager',
                    'times' => $this->once(),
                    'return' => $expected,
                ],
                'd2' => [
                    'class' => 'Demo',
                    'times' => $this->never(),
                    'return' => null,
                ],
            ]
        );

        $result = $composedDriver->getHolding(
            '123456'
        );
        $this->assertEquals($expected, $result);
    }

    /**
     * Testing method with combineArraysOfAssociativeArrays and overwritten main driver with results in subfields
     *
     * @return void
     */
    public function testCombineArraysOfAssociativeArraysOverwrittenMainDriverNoSupportNoSubfields()
    {
        $expected = [
            [
                'id' => '123456',
                'status' => 'in',
            ],
        ];

        $composedDriver = $this->initSimpleMethodTest(
            'getHolding',
            [
                '123456',
            ],
            [
                'main_driver' => 'd2',
            ],
            [
                'd1' => [
                    'class' => 'Demo',
                    'times' => $this->never(),
                    'return' => null,
                ],
                'd2' => [
                    'class' => 'Voyager',
                    'times' => $this->once(),
                    'return' => $expected,
                ],
            ]
        );

        $result = $composedDriver->getHolding(
            '123456'
        );
        $this->assertEquals($expected, $result);
    }

    /**
     * Testing method with combineArraysOfAssociativeArrays and overwritten main driver with support drivers
     *
     * @return void
     */
    public function testCombineArraysOfAssociativeArraysOverwrittenMainDriverNoSupportWithSubfields()
    {
        $expected = [
            'count' => 2,
            'holdings' => [
                'id' => '123456',
                'item_id' => '111111',
                'status' => 'in',
            ],
            'electronic_holdings' => [
                'id' => '123456',
                'item_id' => '222222',
                'status' => 'out',
            ],
        ];

        $composedDriver = $this->initSimpleMethodTest(
            'getHolding',
            [
                '123456',
            ],
            [
                'main_driver' => 'd2',
            ],
            [
                'd1' => [
                    'class' => 'Demo',
                    'times' => $this->never(),
                    'return' => null,
                ],
                'd2' => [
                    'class' => 'Voyager',
                    'times' => $this->once(),
                    'return' => $expected,
                ],
            ]
        );

        $result = $composedDriver->getHolding(
            '123456'
        );
        $this->assertEquals($expected, $result);
    }

    /**
     * Testing method with combineArraysOfAssociativeArrays and base main driver and
     * with support drivers
     *
     * @return void
     */
    public function testCombineArraysOfAssociativeArraysBaseMainDriverWithSupportNoSubfields()
    {
        $result1 = [
            [
                'id' => '123456',
                'item_id' => '1111',
                'status' => 'in',
            ],
            [
                'id' => '123456',
                'item_id' => '2222',
                'status' => 'out',
            ],
            [
                'id' => '123456',
                'item_id' => '3333',
                'status' => 'unknown',
            ],
        ];

        $result2 = null;

        $result3 = [
            [
                'id' => '123456',
                'item_id' => '1111',
                'location' => 'some location',
            ],
            [
                'id' => '123456',
                'item_id' => '2222',
            ],
        ];

        $result4 = [
            'count' => 2,
            'holdings' => [
                [
                    'id' => '123456',
                    'item_id' => '1111',
                    'summary' => 'some summary',
                    'item_notes' => 'some notes',
                ],
            ],
            'electronic_holdings' => [
                [
                    'id' => '123456',
                    'item_id' => '3333',
                    'summary' => 'other summary',
                ],
            ],
        ];

        $expected = [
            [
                'id' => '123456',
                'item_id' => '1111',
                'status' => 'in',
                'location' => 'some location',
                'summary' => 'some summary',
                'item_notes' => 'some notes',
            ],
            [
                'id' => '123456',
                'item_id' => '2222',
                'status' => 'out',
            ],
            [
                'id' => '123456',
                'item_id' => '3333',
                'status' => 'unknown',
                'summary' => 'other summary',
            ],
        ];

        $composedDriver = $this->initSimpleMethodTest(
            'getHolding',
            [
                '123456',
            ],
            [
                'merge_keys' => [
                    'd3' => 'item_id',
                    'd4' => 'item_id',
                ],
                'support_drivers' => [
                    'd3' => 'location',
                    'd4' => 'summary,item_notes',
                ],
            ],
            [
                'd1' => [
                    'class' => 'Voyager',
                    'times' => $this->once(),
                    'return' => $result1,
                ],
                'd2' => [
                    'class' => 'Demo',
                    'times' => $this->never(),
                    'return' => $result2,
                ],
                'd3' => [
                    'class' => 'Demo',
                    'times' => $this->once(),
                    'return' => $result3,
                ],
                'd4' => [
                    'class' => 'Demo',
                    'times' => $this->once(),
                    'return' => $result4,
                ],
            ]
        );

        $result = $composedDriver->getHolding(
            '123456'
        );
        $this->assertEquals($expected, $result);
    }

    /**
     * Testing method with combineArraysOfAssociativeArrays and base main driver with results in subfields and
     * with support drivers
     *
     * @return void
     */
    public function testCombineArraysOfAssociativeArraysBaseMainDriverWithSupportWithSubfields()
    {
        $result1 = [
            'count' => 3,
            'holdings' => [
                [
                    'id' => '123456',
                    'item_id' => '1111',
                    'status' => 'in',
                ],
                [
                    'id' => '123456',
                    'item_id' => '2222',
                    'status' => 'out',
                ],
            ],
            'electronic_holdings' => [
                [
                    'id' => '123456',
                    'item_id' => '3333',
                    'status' => 'unknown',
                ],
            ],
        ];

        $result2 = null;

        $result3 = [
            [
                'id' => '123456',
                'item_id' => '1111',
                'location' => 'some location',
            ],
            [
                'id' => '123456',
                'item_id' => '2222',
            ],
        ];

        $result4 = [
            'count' => 2,
            'holdings' => [
                [
                    'id' => '123456',
                    'item_id' => '1111',
                    'summary' => 'some summary',
                    'item_notes' => 'some notes',
                ],
            ],
            'electronic_holdings' => [
                [
                    'id' => '123456',
                    'item_id' => '3333',
                    'summary' => 'other summary',
                ],
            ],
        ];

        $expected = [
            'count' => 3,
            'holdings' => [
                [
                    'id' => '123456',
                    'item_id' => '1111',
                    'status' => 'in',
                    'location' => 'some location',
                    'summary' => 'some summary',
                    'item_notes' => 'some notes',
                ],
                [
                    'id' => '123456',
                    'item_id' => '2222',
                    'status' => 'out',
                ],
            ],
            'electronic_holdings' => [
                [
                    'id' => '123456',
                    'item_id' => '3333',
                    'status' => 'unknown',
                    'summary' => 'other summary',
                ],
            ],
        ];

        $composedDriver = $this->initSimpleMethodTest(
            'getHolding',
            [
                '123456',
            ],
            [
                'merge_keys' => [
                    'd3' => 'item_id',
                    'd4' => 'item_id',
                ],
                'support_drivers' => [
                    'd3' => 'location',
                    'd4' => 'summary,item_notes',
                ],
            ],
            [
                'd1' => [
                    'class' => 'Voyager',
                    'times' => $this->once(),
                    'return' => $result1,
                ],
                'd2' => [
                    'class' => 'Demo',
                    'times' => $this->never(),
                    'return' => $result2,
                ],
                'd3' => [
                    'class' => 'Demo',
                    'times' => $this->once(),
                    'return' => $result3,
                ],
                'd4' => [
                    'class' => 'Demo',
                    'times' => $this->once(),
                    'return' => $result4,
                ],
            ]
        );

        $result = $composedDriver->getHolding(
            '123456'
        );
        $this->assertEquals($expected, $result);
    }

    /**
     * Testing method with combineArraysOfAssociativeArrays and overwritten main driver with support drivers
     *
     * @return void
     */
    public function testCombineArraysOfAssociativeArraysOverwrittenMainDriverWithSupportNoSubfields()
    {
        $result1 = null;

        $result2 = [
            [
                'id' => '123456',
                'item_id' => '1111',
                'status' => 'in',
            ],
            [
                'id' => '123456',
                'item_id' => '2222',
                'status' => 'out',
            ],
            [
                'id' => '123456',
                'item_id' => '3333',
                'status' => 'unknown',
            ],
        ];

        $result3 = [
            [
                'id' => '123456',
                'item_id' => '1111',
                'location' => 'some location',
            ],
            [
                'id' => '123456',
                'item_id' => '2222',
            ],
        ];

        $result4 = [
            'count' => 2,
            'holdings' => [
                [
                    'id' => '123456',
                    'item_id' => '1111',
                    'summary' => 'some summary',
                    'item_notes' => 'some notes',
                ],
            ],
            'electronic_holdings' => [
                [
                    'id' => '123456',
                    'item_id' => '3333',
                    'summary' => 'other summary',
                ],
            ],
        ];

        $expected = [
            [
                'id' => '123456',
                'item_id' => '1111',
                'status' => 'in',
                'location' => 'some location',
                'summary' => 'some summary',
                'item_notes' => 'some notes',
            ],
            [
                'id' => '123456',
                'item_id' => '2222',
                'status' => 'out',
            ],
            [
                'id' => '123456',
                'item_id' => '3333',
                'status' => 'unknown',
                'summary' => 'other summary',
            ],
        ];

        $composedDriver = $this->initSimpleMethodTest(
            'getHolding',
            [
                '123456',
            ],
            [
                'main_driver' => 'd2',
                'merge_keys' => [
                    'd3' => 'item_id',
                    'd4' => 'item_id',
                ],
                'support_drivers' => [
                    'd3' => 'location',
                    'd4' => 'summary,item_notes',
                ],
            ],
            [
                'd1' => [
                    'class' => 'Voyager',
                    'times' => $this->never(),
                    'return' => $result1,
                ],
                'd2' => [
                    'class' => 'Demo',
                    'times' => $this->once(),
                    'return' => $result2,
                ],
                'd3' => [
                    'class' => 'Demo',
                    'times' => $this->once(),
                    'return' => $result3,
                ],
                'd4' => [
                    'class' => 'Demo',
                    'times' => $this->once(),
                    'return' => $result4,
                ],
            ]
        );

        $result = $composedDriver->getHolding(
            '123456'
        );
        $this->assertEquals($expected, $result);
    }

    /**
     * Testing method with combineArraysOfAssociativeArrays and overwritten main driver with results in subfields and
     * with support drivers
     *
     * @return void
     */
    public function testCombineArraysOfAssociativeArraysOverwrittenMainDriverWithSupportWithSubfields()
    {
        $result1 = null;

        $result2 = [
            'count' => 3,
            'holdings' => [
                [
                    'id' => '123456',
                    'item_id' => '1111',
                    'status' => 'in',
                ],
            ],
            'electronic_holdings' => [
                [
                    'id' => '123456',
                    'item_id' => '2222',
                    'status' => 'out',
                ],
                [
                    'id' => '123456',
                    'item_id' => '3333',
                    'status' => 'unknown',
                ],
            ],
        ];

        $result3 = [
            [
                'id' => '123456',
                'item_id' => '1111',
                'location' => 'some location',
            ],
            [
                'id' => '123456',
                'item_id' => '2222',
            ],
        ];

        $result4 = [
            'count' => 2,
            'holdings' => [
                [
                    'id' => '123456',
                    'item_id' => '1111',
                    'summary' => 'some summary',
                    'item_notes' => 'some notes',
                ],
            ],
            'electronic_holdings' => [
                [
                    'id' => '123456',
                    'item_id' => '3333',
                    'summary' => 'other summary',
                ],
            ],
        ];

        $expected = [
            'count' => 3,
            'holdings' => [
                [
                    'id' => '123456',
                    'item_id' => '1111',
                    'status' => 'in',
                    'location' => 'some location',
                    'summary' => 'some summary',
                    'item_notes' => 'some notes',
                ],
            ],
            'electronic_holdings' => [
                [
                    'id' => '123456',
                    'item_id' => '2222',
                    'status' => 'out',
                ],
                [
                    'id' => '123456',
                    'item_id' => '3333',
                    'status' => 'unknown',
                    'summary' => 'other summary',
                ],
            ],
        ];

        $composedDriver = $this->initSimpleMethodTest(
            'getHolding',
            [
                '123456',
            ],
            [
                'main_driver' => 'd2',
                'merge_keys' => [
                    'd3' => 'item_id',
                    'd4' => 'item_id',
                ],
                'support_drivers' => [
                    'd3' => 'location',
                    'd4' => 'summary,item_notes',
                ],
            ],
            [
                'd1' => [
                    'class' => 'Voyager',
                    'times' => $this->never(),
                    'return' => $result1,
                ],
                'd2' => [
                    'class' => 'Demo',
                    'times' => $this->once(),
                    'return' => $result2,
                ],
                'd3' => [
                    'class' => 'Demo',
                    'times' => $this->once(),
                    'return' => $result3,
                ],
                'd4' => [
                    'class' => 'Demo',
                    'times' => $this->once(),
                    'return' => $result4,
                ],
            ]
        );

        $result = $composedDriver->getHolding(
            '123456'
        );
        $this->assertEquals($expected, $result);
    }

    /**
     * Testing method with combineArraysOfAssociativeArrays and overwritten main driver without optional result
     * subfields
     *
     * @return void
     */
    public function testCombineArraysOfAssociativeArraysOverwrittenMainDriverWithoutOptionResultSubfields()
    {
        $result1 = null;

        $result2 = [
            [
                'id' => '123456',
                'amount' => '1000',
            ],
            [
                'id' => '654321',
                'amount' => '500',
            ],

        ];

        $result3 = [
            [
                'id' => '123456',
                'title' => 'some title',
            ],
        ];

        $expected = [
            [
                'id' => '123456',
                'amount' => '1000',
                'title' => 'some title',
            ],
            [
                'id' => '654321',
                'amount' => '500',
            ],
        ];

        $composedDriver = $this->initSimpleMethodTest(
            'getMyFines',
            [$this->getPatron('username')],
            [
                'main_driver' => 'd2',
                'merge_keys' => [
                    'd3' => 'id',
                ],
                'support_drivers' => [
                    'd3' => 'title',
                ],
            ],
            [
                'd1' => [
                    'class' => 'Voyager',
                    'times' => $this->never(),
                    'return' => $result1,
                ],
                'd2' => [
                    'class' => 'Demo',
                    'times' => $this->once(),
                    'return' => $result2,
                ],
                'd3' => [
                    'class' => 'Demo',
                    'times' => $this->once(),
                    'return' => $result3,
                ],
            ]
        );

        $result = $composedDriver->getMyFines(
            $this->getPatron('username')
        );
        $this->assertEquals($expected, $result);
    }

    /**
     * Testing method with combineMultipleArraysOfAssociativeArrays and base main driver without support
     *
     * @return void
     */
    public function testCombineMultipleArraysOfAssociativeArraysBaseMainDriverWithoutSupport()
    {
        $result1 = [
            [
                [
                    'id' => '123456',
                    'callnumber' => '1111',
                    'availability' => true,
                ],
                [
                    'id' => '123456',
                    'callnumber' => '2222',
                    'availability' => false,
                ],
            ],
            [
                [
                    'id' => '654321',
                    'callnumber' => '3333',
                    'availability' => AvailabilityStatusInterface::STATUS_AVAILABLE,
                ],
            ],
        ];

        $result2 = null;

        $result3 = null;

        $result4 = null;

        $expected = [
            [
                [
                    'id' => '123456',
                    'callnumber' => '1111',
                    'availability' => true,
                ],
                [
                    'id' => '123456',
                    'callnumber' => '2222',
                    'availability' => false,
                ],
            ],
            [
                [
                    'id' => '654321',
                    'callnumber' => '3333',
                    'availability' => AvailabilityStatusInterface::STATUS_AVAILABLE,
                ],
            ],
        ];

        $composedDriver = $this->initSimpleMethodTest(
            'getStatuses',
            [['123456', '654321']],
            [],
            [
                'd1' => [
                    'class' => 'Voyager',
                    'times' => $this->once(),
                    'return' => $result1,
                ],
                'd2' => [
                    'class' => 'Demo',
                    'times' => $this->never(),
                    'return' => $result2,
                ],
                'd3' => [
                    'class' => 'Demo',
                    'times' => $this->never(),
                    'return' => $result3,
                ],
                'd4' => [
                    'class' => 'Demo',
                    'times' => $this->never(),
                    'return' => $result4,
                ],
            ]
        );

        $result = $composedDriver->getStatuses(
            ['123456', '654321']
        );
        $this->assertEquals($expected, $result);
    }

    /**
     * Testing method with combineMultipleArraysOfAssociativeArrays and base main driver with support
     *
     * @return void
     */
    public function testCombineMultipleArraysOfAssociativeArraysBaseMainDriverWithSupport()
    {
        $result1 = [
            [
                [
                    'id' => '123456',
                    'callnumber' => '1111',
                    'availability' => true,
                ],
                [
                    'id' => '123456',
                    'callnumber' => '2222',
                    'availability' => false,
                ],
            ],
            [
                [
                    'id' => '654321',
                    'callnumber' => '3333',
                    'availability' => AvailabilityStatusInterface::STATUS_AVAILABLE,
                ],
            ],
        ];

        $result2 = null;

        $result3 = [
            [
                [
                    'id' => '654321',
                    'callnumber' => '3333',
                    'location' => 'location_3',
                ],
            ],
            [
                [
                    'id' => '123456',
                    'callnumber' => '1111',
                    'location' => 'location_1',
                ],
                [
                    'id' => '123456',
                    'callnumber' => '2222',
                    'location' => 'location_2',
                ],
            ],
        ];

        $result4 = [
            [
                [
                    'id' => '123456',
                    'callnumber' => '1111',
                    'reserve' => 'N',
                ],
            ],
        ];

        $expected = [
            [
                [
                    'id' => '123456',
                    'callnumber' => '1111',
                    'availability' => true,
                    'location' => 'location_1',
                    'reserve' => 'N',
                ],
                [
                    'id' => '123456',
                    'callnumber' => '2222',
                    'availability' => false,
                    'location' => 'location_2',
                ],
            ],
            [
                [
                    'id' => '654321',
                    'callnumber' => '3333',
                    'availability' => AvailabilityStatusInterface::STATUS_AVAILABLE,
                    'location' => 'location_3',
                ],
            ],
        ];

        $composedDriver = $this->initSimpleMethodTest(
            'getStatuses',
            [['123456', '654321']],
            [
                'merge_keys' => [
                    'd3' => 'callnumber',
                    'd4' => 'callnumber',
                ],
                'support_drivers' => [
                    'd3' => 'location',
                    'd4' => 'reserve',
                ],
            ],
            [
                'd1' => [
                    'class' => 'Voyager',
                    'times' => $this->once(),
                    'return' => $result1,
                ],
                'd2' => [
                    'class' => 'Demo',
                    'times' => $this->never(),
                    'return' => $result2,
                ],
                'd3' => [
                    'class' => 'Demo',
                    'times' => $this->once(),
                    'return' => $result3,
                ],
                'd4' => [
                    'class' => 'Demo',
                    'times' => $this->once(),
                    'return' => $result4,
                ],
            ]
        );

        $result = $composedDriver->getStatuses(
            ['123456', '654321']
        );
        $this->assertEquals($expected, $result);
    }

    /**
     * Testing method with combineMultipleArraysOfAssociativeArrays and overwritten main driver without support
     *
     * @return void
     */
    public function testCombineMultipleArraysOfAssociativeArraysOverwrittenMainDriverWithoutSupport()
    {
        $result1 = null;

        $result2 = [
            [
                [
                    'id' => '123456',
                    'callnumber' => '1111',
                    'availability' => true,
                ],
                [
                    'id' => '123456',
                    'callnumber' => '2222',
                    'availability' => false,
                ],
            ],
            [
                [
                    'id' => '654321',
                    'callnumber' => '3333',
                    'availability' => AvailabilityStatusInterface::STATUS_AVAILABLE,
                ],
            ],
        ];

        $result3 = null;

        $result4 = null;

        $expected = [
            [
                [
                    'id' => '123456',
                    'callnumber' => '1111',
                    'availability' => true,
                ],
                [
                    'id' => '123456',
                    'callnumber' => '2222',
                    'availability' => false,
                ],
            ],
            [
                [
                    'id' => '654321',
                    'callnumber' => '3333',
                    'availability' => AvailabilityStatusInterface::STATUS_AVAILABLE,
                ],
            ],
        ];

        $composedDriver = $this->initSimpleMethodTest(
            'getStatuses',
            [['123456', '654321']],
            [
                'main_driver' => 'd2',
            ],
            [
                'd1' => [
                    'class' => 'Voyager',
                    'times' => $this->never(),
                    'return' => $result1,
                ],
                'd2' => [
                    'class' => 'Demo',
                    'times' => $this->once(),
                    'return' => $result2,
                ],
                'd3' => [
                    'class' => 'Demo',
                    'times' => $this->never(),
                    'return' => $result3,
                ],
                'd4' => [
                    'class' => 'Demo',
                    'times' => $this->never(),
                    'return' => $result4,
                ],
            ]
        );

        $result = $composedDriver->getStatuses(
            ['123456', '654321']
        );
        $this->assertEquals($expected, $result);
    }

    /**
     * Testing method with combineMultipleArraysOfAssociativeArrays and overwritten main driver with support
     *
     * @return void
     */
    public function testeCombineMultipleArraysOfAssociativeArraysOverwrittenMainDriverWithSupport()
    {
        $result1 = null;

        $result2 = [
            [
                [
                    'id' => '123456',
                    'callnumber' => '1111',
                    'availability' => true,
                ],
                [
                    'id' => '123456',
                    'callnumber' => '2222',
                    'availability' => false,
                ],
            ],
            [
                [
                    'id' => '654321',
                    'callnumber' => '3333',
                    'availability' => AvailabilityStatusInterface::STATUS_AVAILABLE,
                ],
            ],
        ];

        $result3 = [
            [
                [
                    'id' => '654321',
                    'callnumber' => '3333',
                    'location' => 'location_3',
                ],
            ],
            [
                [
                    'id' => '123456',
                    'callnumber' => '1111',
                    'location' => 'location_1',
                ],
                [
                    'id' => '123456',
                    'callnumber' => '2222',
                    'location' => 'location_2',
                ],
            ],
        ];

        $result4 = [
            [
                [
                    'id' => '123456',
                    'callnumber' => '1111',
                    'reserve' => 'N',
                ],
            ],
        ];

        $expected = [
            [
                [
                    'id' => '123456',
                    'callnumber' => '1111',
                    'availability' => true,
                    'location' => 'location_1',
                    'reserve' => 'N',
                ],
                [
                    'id' => '123456',
                    'callnumber' => '2222',
                    'availability' => false,
                    'location' => 'location_2',
                ],
            ],
            [
                [
                    'id' => '654321',
                    'callnumber' => '3333',
                    'availability' => AvailabilityStatusInterface::STATUS_AVAILABLE,
                    'location' => 'location_3',
                ],
            ],
        ];

        $composedDriver = $this->initSimpleMethodTest(
            'getStatuses',
            [['123456', '654321']],
            [
                'main_driver' => 'd2',
                'merge_keys' => [
                    'd3' => 'callnumber',
                    'd4' => 'callnumber',
                ],
                'support_drivers' => [
                    'd3' => 'location',
                    'd4' => 'reserve',
                ],
            ],
            [
                'd1' => [
                    'class' => 'Voyager',
                    'times' => $this->never(),
                    'return' => $result1,
                ],
                'd2' => [
                    'class' => 'Demo',
                    'times' => $this->once(),
                    'return' => $result2,
                ],
                'd3' => [
                    'class' => 'Demo',
                    'times' => $this->once(),
                    'return' => $result3,
                ],
                'd4' => [
                    'class' => 'Demo',
                    'times' => $this->once(),
                    'return' => $result4,
                ],
            ]
        );

        $result = $composedDriver->getStatuses(
            ['123456', '654321']
        );
        $this->assertEquals($expected, $result);
    }

    /**
     * Method to get an initialized Composed Driver.
     *
     * @param array   $constructorArgs   Optional constructor arguments
     * @param array   $drivers           List of used drivers
     * @param ?string $driversConfigPath Optional driver config path
     *
     * @return mixed A ComposedDriver instance.
     */
    protected function initDriver($constructorArgs = [], $drivers = [], $driversConfigPath = null)
    {
        $driver = $this->getDriver($constructorArgs);
        $driver->setConfig(
            [
                'General' => ['main_driver' => 'd1', 'drivers_config_path' => $driversConfigPath],
                'Drivers' => $drivers,
            ]
        );
        $driver->init();
        return $driver;
    }

    /**
     * Method to get a raw ComposedDriver instance.
     *
     * @param array $constructorArgs Optional constructor arguments
     *
     * @return mixed A ComposedDriver instance.
     */
    protected function getDriver($constructorArgs = [])
    {
        $driver = new ComposedDriver(
            $constructorArgs['configLoader'] ?? $this->getMockConfigPluginManager([], ['config' => 'values']),
            $constructorArgs['driverManager'] ?? $this->getMockSM()
        );
        return $driver;
    }

    /**
     * Initialize a ComposedDriver driver for a simple method test
     *
     * @param string $function       Function name
     * @param array  $params         Function parameters
     * @param array  $functionConfig Config for the given function
     * @param array  $driverConfigs  Associative array which maps driver names to class, number of calls and result
     *
     * @return object MultiBackend driver
     */
    protected function initSimpleMethodTest(
        $function,
        $params,
        $functionConfig,
        $driverConfigs
    ) {
        $drivers = [];
        foreach ($driverConfigs as $diverName => $driverConfig) {
            $driver = $this->getMockILS($driverConfig['class'], ['init', $function]);
            call_user_func_array(
                [$driver->expects($driverConfig['times'])->method($function), 'with'],
                $params
            )->will($this->returnValue($driverConfig['return']));
            $drivers[$diverName] = $driver;
        }
        $composedDriver = $this->getMultiDriverForDrivers(
            $drivers,
            $this->any()
        );
        $config = $this->getProperty($composedDriver, 'config');
        $config[$function] = $functionConfig;
        $this->setProperty($composedDriver, 'config', $config);
        return $composedDriver;
    }
}
