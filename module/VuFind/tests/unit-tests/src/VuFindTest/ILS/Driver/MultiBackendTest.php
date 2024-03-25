<?php

/**
 * ILS driver test
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2011.
 * Copyright (C) The National Library of Finland 2014-2021.
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
 * @author   Kyle McGrogan <km7717@ship.edu>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */

namespace VuFindTest\ILS\Driver;

use Laminas\Config\Exception\RuntimeException;
use VuFind\Exception\ILS as ILSException;
use VuFind\ILS\Driver\MultiBackend;

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
class MultiBackendTest extends AbstractMultiDriverTestCase
{
    /**
     * Test that driver handles missing ILS driver configuration properly.
     *
     * @return void
     */
    public function testMissingILSConfiguration()
    {
        $driver = new MultiBackend(
            $this->getMockFailingConfigPluginManager(new RuntimeException()),
            $this->getMockILSAuthenticator(),
            $this->getMockSM()
        );
        $driver->setConfig(['Drivers' => ['d1' => 'Voyager']]);
        $driver->init();

        $result = $driver->getStatus('d1.123');
        $this->assertEquals([], $result);
    }

    /**
     *  Tests that logging works correctly
     *
     * @return void
     */
    public function testLogging()
    {
        $objs = parent::testLogging();

        $this->callMethod($objs['driver'], 'getLocalId', ['bad']);
        $this->assertEquals(
            'VuFind\ILS\Driver\MultiBackend: '
            . "Could not find local id in 'bad'",
            $objs['writer']->events[1]['message']
        );
    }

    /**
     * Testing method for getSourceFromParams
     *
     * @return void
     */
    public function testGetSourceFromParams()
    {
        $driver = $this->initDriver();

        $drivers = ['d1' => 'Voyager', 'd2' => 'Demo'];
        $this->setProperty($driver, 'drivers', $drivers);

        $result = $this->callMethod($driver, 'getSourceFromParams', ['']);
        $this->assertEquals('', $result);

        $result = $this->callMethod($driver, 'getSourceFromParams', ['d1.record2']);
        $this->assertEquals('d1', $result);

        $data = [
            'id' => 'record1',
            'cat_username' => 'record2',
        ];
        $result = $this->callMethod($driver, 'getSourceFromParams', [$data]);
        $this->assertEquals('', $result);

        $data = [
            'id' => 'record1',
            'cat_username' => 'd1.record2',
        ];
        $result = $this->callMethod($driver, 'getSourceFromParams', [$data]);
        $this->assertEquals('d1', $result);

        $data = [
            'id' => 'd2.record1',
            'cat_username' => 'record2',
        ];
        $result = $this->callMethod($driver, 'getSourceFromParams', [$data]);
        $this->assertEquals('d2', $result);

        $data = [
            'test' => 'true',
            'patron' => [
                'id' => 'd2.record1',
                'cat_username' => 'record2',
            ],
        ];
        $result = $this->callMethod($driver, 'getSourceFromParams', [$data]);
        $this->assertEquals('d2', $result);
    }

    /**
     * Testing method for addIdPrefixes
     *
     * @return void
     */
    public function testAddIdPrefixes()
    {
        $driver = $this->initDriver();
        $source = 'source';
        $data = [];

        $result = $this->callMethod($driver, 'addIdPrefixes', [$data, $source]);
        $this->assertEquals($data, $result);

        $data = [
            'id' => 'record1',
            'cat_username' => 'record2',
        ];
        $expected = [
            'id' => "$source.record1",
            'cat_username' => "$source.record2",
        ];
        $result = $this->callMethod($driver, 'addIdPrefixes', [$data, $source]);
        $this->assertEquals($expected, $result);

        // Empty source must not add prefixes
        $expected = [
            'id' => 'record1',
            'cat_username' => 'record2',
        ];
        $result = $this->callMethod($driver, 'addIdPrefixes', [$data, '']);
        $this->assertEquals($expected, $result);

        $data = [
            'id' => 'record1',
            'cat_username' => [
                'id' => 'record2',
                'cat_username' => [
                    'id' => 'record3',
                    'cat_username' => 'record4',
                ],
                'cat_info' => 'record5',
                'other' => 'something',
            ],
            'cat_info' => 'record6',
        ];
        $expected = [
            'id' => "$source.record1",
            'cat_username' => [
                'id' => "$source.record2",
                'cat_username' => [
                    'id' => "$source.record3",
                    'cat_username' => "$source.record4",
                ],
                'cat_info' => "$source.record5",
                'other' => 'something',
            ],
            'cat_info' => "$source.record6",
        ];
        $modify = ['id', 'cat_username', 'cat_info'];
        $result = $this->callMethod(
            $driver,
            'addIdPrefixes',
            [$data, $source, $modify]
        );
        $this->assertEquals($expected, $result);

        // Numeric keys are not considered
        $data = [
            'id' => 'record1',
            'cat_username' => ['foo', 'bar'],
        ];
        $expected = [
            'id' => "$source.record1",
            'cat_username' => ['foo', 'bar'],
        ];
        $result = $this->callMethod(
            $driver,
            'addIdPrefixes',
            [$data, $source, $modify]
        );
        $this->assertEquals($expected, $result);
    }

    /**
     * Testing method for stripIdPrefixes
     *
     * @return void
     */
    public function testStripIdPrefixes()
    {
        $driver = $this->initDriver();
        $source = 'source';
        $data = [];

        $result
            = $this->callMethod($driver, 'stripIdPrefixes', [$data, $source]);
        $this->assertEquals($data, $result);

        $data = "$source.record";
        $result
            = $this->callMethod($driver, 'stripIdPrefixes', [$data, $source]);
        $this->assertEquals('record', $result);

        $expected = [
            'id' => 'record1',
            'cat_username' => 'record2',
        ];
        $data = [
            'id' => "$source.record1",
            'cat_username' => "$source.record2",
        ];
        $result
            = $this->callMethod($driver, 'stripIdPrefixes', [$data, $source]);
        $this->assertEquals($expected, $result);

        $expected = [
            'id' => 'record1',
            'cat_username' => [
                'id' => 'record2',
                'cat_username' => [
                    'id' => 'record3',
                    'cat_username' => 'record4',
                ],
                'cat_info' => 'record5',
                'other' => "$source.something",
            ],
            'cat_info' => 'record6',
        ];
        $data = [
            'id' => "$source.record1",
            'cat_username' => [
                'id' => "$source.record2",
                'cat_username' => [
                    'id' => "$source.record3",
                    'cat_username' => "$source.record4",
                ],
                'cat_info' => "$source.record5",
                'other' => "$source.something",
            ],
            'cat_info' => "$source.record6",
        ];
        $modify = ['id', 'cat_username', 'cat_info'];
        $result = $this->callMethod(
            $driver,
            'stripIdPrefixes',
            [$data, $source, $modify]
        );
        $this->assertEquals($expected, $result);

        // Numeric keys are not considered
        $data = [
            'id' => "$source.record1",
            'test' => ["$source.foo", "$source.bar"],
        ];
        $expected = [
            'id' => 'record1',
            'test' => ["$source.foo", "$source.bar"],
        ];
        $result = $this->callMethod(
            $driver,
            'stripIdPrefixes',
            [$data, $source]
        );
        $this->assertEquals($expected, $result);
    }

    /**
     * Testing method for driverSupportsMethod
     *
     * @return void
     */
    public function testDriverSupportsMethod()
    {
        $driver = $this->initDriver();
        $voyager = $this->getMockILS('Voyager', ['init']);

        $result = $this->callMethod(
            $driver,
            'driverSupportsMethod',
            [$voyager, 'getHolding']
        );
        $this->assertTrue($result);

        $result = $this->callMethod(
            $driver,
            'driverSupportsMethod',
            [$voyager, 'INVALIDMETHOD']
        );
        $this->assertFalse($result);

        $dummy = $this->getMockILS('Voyager', ['init', 'supportsMethod']);
        $dummy->expects($this->once())
            ->method('supportsMethod')
            ->with('getHolding')
            ->will($this->returnValue(false));

        $result = $this->callMethod(
            $driver,
            'driverSupportsMethod',
            [$dummy, 'getHolding']
        );
        $this->assertFalse($result);
    }

    /**
     * Testing method for getHolding
     *
     * @return void
     */
    public function testGetHolding()
    {
        $ils1 = $this->getMockILS('Voyager', ['init', 'getHolding']);
        $ils1->expects($this->exactly(2))
            ->method('getHolding')
            ->with(
                $this->logicalOr(
                    $this->equalTo('123456'),
                    $this->equalTo('654321')
                )
            )
            ->will(
                $this->returnCallback(
                    function ($param) {
                        if ($param == '123456') {
                            return ['id' => '123456', 'status' => 'in'];
                        }
                        return [];
                    }
                )
            );

        $ils2 = $this->getMockILS('Demo', ['init', 'getHolding']);
        $ils2->expects($this->once())
            ->method('getHolding')
            ->with(
                $this->equalTo('123456'),
                $this->equalTo(['cat_username' => 'test.patron'])
            )
            ->will(
                $this->returnValue(
                    [
                        [
                            'id' => '123456',
                            'status' => 'in',
                        ],
                    ]
                )
            );

        $driver = $this->getMultiDriverForDrivers(['d1' => $ils1, 'd2' => $ils2]);

        $expectedReturn = ['id' => 'd1.123456', 'status' => 'in'];
        $return = $driver
            ->getHolding('d1.123456', ['cat_username' => 'test.patron']);
        $this->assertEquals($expectedReturn, $return);

        $return = $driver->getHolding('fail.123456');
        $this->assertEquals([], $return);

        $return = $driver->getHolding('d1.654321');
        $this->assertEquals([], $return);

        $this->assertEquals(
            [['id' => 'd2.123456', 'status' => 'in']],
            $driver->getHolding('d2.123456', ['cat_username' => 'test.patron'])
        );
    }

    /**
     * Testing method for getPurchaseHistory
     *
     * @return void
     */
    public function testGetPurchaseHistory()
    {
        $driverReturn = ['purchases' => '123456'];
        $ILS = $this->getMockILS('Voyager', ['init', 'getPurchaseHistory']);
        $ILS->expects($this->once())
            ->method('getPurchaseHistory')
            ->with('123456')
            ->will($this->returnValue($driverReturn));

        $sm = $this->getMockSM($this->once(), 'Voyager', $ILS);
        $driver = $this->initDriver(['driverManager' => $sm]);
        $drivers = ['d1' => 'Voyager'];
        $this->setProperty($driver, 'drivers', $drivers);
        $id = 'd1.123456';

        $return = $driver->getPurchaseHistory($id);
        $this->assertEquals($driverReturn, $return);

        $return = $driver->getPurchaseHistory("fail.$id");
        $this->assertEquals([], $return);
    }

    /**
     * Testing method for getLoginDrivers
     *
     * @return void
     */
    public function testGetLoginDrivers()
    {
        $driver = $this->initDriver();

        $result = $driver->getLoginDrivers();
        $this->assertEquals(['d1', 'd2'], $result);
    }

    /**
     * Testing method for getDefaultLoginDriver
     *
     * @return void
     */
    public function testGetDefaultLoginDriver()
    {
        $driver = $this->initDriver();

        $result = $driver->getDefaultLoginDriver();
        $this->assertEquals('d1', $result);

        $driver->setConfig(
            [
                'Drivers' => [],
                'Login' => [
                    'drivers' => ['d2', 'd1'],
                ],
            ]
        );

        $result = $driver->getDefaultLoginDriver();
        $this->assertEquals('d2', $result);

        $driver->setConfig(
            [
                'Drivers' => [],
                'Login' => [],
            ]
        );
        $result = $driver->getDefaultLoginDriver();
        $this->assertEquals('', $result);
    }

    /**
     * Testing method for getStatus
     *
     * @return void
     */
    public function testGetStatus()
    {
        $ILS = $this->getMockILS('Voyager', ['init', 'getStatus']);
        $ILS->expects($this->exactly(2))
            ->method('getStatus')
            ->with(
                $this->logicalOr(
                    $this->equalTo('123456'),
                    $this->equalTo('654321')
                )
            )
            ->will(
                $this->returnCallback(
                    function ($param) {
                        $r_arr = ['id' => $param];
                        if ($param == '123456') {
                            $r_arr['status'] = 'in';
                        } elseif ($param == '654321') {
                            $r_arr['status'] = 'out';
                        } else {
                            $r_arr['status'] = 'out';
                        }
                        return [$r_arr];
                    }
                )
            );

        $sm = $this->getMockSM($this->once(), 'Voyager', $ILS);
        $driver = $this->initDriver(['driverManager' => $sm]);
        $drivers = ['d1' => 'Voyager'];
        $this->setProperty($driver, 'drivers', $drivers);

        $return = $driver->getStatus('d1.123456');
        $this->assertEquals([['id' => 'd1.123456', 'status' => 'in']], $return);

        $return = $driver->getStatus('d1.654321');
        $this->assertEquals([['id' => 'd1.654321', 'status' => 'out']], $return);

        $return = $driver->getStatus('invalid.654321');
        $this->assertEquals([], $return);
    }

    /**
     * Testing method for getStatuses
     *
     * @return void
     */
    public function testGetStatuses()
    {
        $ils1 = $this->getMockILS('Voyager', ['init', 'getStatuses']);
        $ils1->expects($this->exactly(2))
            ->method('getStatuses')
            ->with(
                $this->equalTo(['123456', '098765'])
            )
            ->will(
                $this->returnValue(
                    [
                        [
                            [
                                'id' => '123456',
                                'status' => 'in',
                            ],
                            [
                                'id' => '123456',
                                'status' => 'out',
                            ],
                        ],
                        [
                            [
                                'id' => '098765',
                                'status' => 'out',
                            ],
                        ],
                    ]
                )
            );

        $ils2 = $this->getMockILS('Unicorn', ['init', 'setConfig', 'getStatuses']);
        $ils2->expects($this->exactly(1))
            ->method('getStatuses')
            ->with(
                $this->equalTo(['654321', '567890'])
            )
            ->will(
                $this->returnValue(
                    [
                        [
                            [
                                'id' => '654321',
                                'status' => 'out',
                            ],
                        ],
                        [
                            [
                                'id' => '567890',
                                'status' => 'in',
                            ],
                        ],
                    ]
                )
            );

        $exception = new \VuFind\Exception\ILS('Simulated exception');
        $ils3 = $this->getMockILS('Demo', ['init', 'setConfig', 'getStatuses']);
        $ils3->expects($this->exactly(1))
            ->method('getStatuses')
            ->with(
                $this->equalTo(['654321', '567890'])
            )
            ->will(
                $this->throwException($exception)
            );

        $sm = $this->getMockBuilder(\VuFind\ILS\Driver\PluginManager::class)
            ->disableOriginalConstructor()->getMock();
        $sm->expects($this->exactly(2))
            ->method('get')
            ->with(
                $this->logicalOr('Voyager', 'Unicorn')
            )->will(
                $this->returnCallback(
                    function ($driver) use ($ils1, $ils2) {
                        return 'Voyager' === $driver ? $ils1 : $ils2;
                    }
                )
            );

        $driver = $this->initDriver(['driverManager' => $sm]);
        $drivers = [
            'd1' => 'Voyager',
            'd2' => 'Unicorn',
        ];
        $this->setProperty($driver, 'drivers', $drivers);

        $ids = [
            'd1.123456', 'd1.098765', 'd2.654321', 'd2.567890',
        ];
        $expectedReturn = [
            [
                ['id' => 'd1.123456', 'status' => 'in'],
                ['id' => 'd1.123456', 'status' => 'out'],
            ],
            [
                ['id' => 'd1.098765', 'status' => 'out'],
            ],
            [
                ['id' => 'd2.654321', 'status' => 'out'],
            ],
            [
                ['id' => 'd2.567890', 'status' => 'in'],
            ],
        ];
        $return = $driver->getStatuses($ids);
        $this->assertEquals($expectedReturn, $return);

        $sm = $this->getMockBuilder(\VuFind\ILS\Driver\PluginManager::class)
            ->disableOriginalConstructor()->getMock();
        $sm->expects($this->exactly(2))
            ->method('get')
            ->with(
                $this->logicalOr('Voyager', 'Demo')
            )->will(
                $this->returnCallback(
                    function ($driver) use ($ils1, $ils3) {
                        return 'Voyager' === $driver ? $ils1 : $ils3;
                    }
                )
            );

        $driver = $this->initDriver(['driverManager' => $sm]);
        $drivers = [
            'd1' => 'Voyager',
            'd3' => 'Demo',
        ];
        $this->setProperty($driver, 'drivers', $drivers);

        $ids = [
            'd1.123456', 'd1.098765', 'd3.654321', 'd3.567890',
        ];
        $expectedReturn = [
            [
                ['id' => 'd1.123456', 'status' => 'in'],
                ['id' => 'd1.123456', 'status' => 'out'],
            ],
            [
                ['id' => 'd1.098765', 'status' => 'out'],
            ],
            [
                ['id' => 'd3.654321', 'error' => 'An error has occurred'],
            ],
            [
                ['id' => 'd3.567890', 'error' => 'An error has occurred'],
            ],
        ];
        $return = $driver->getStatuses($ids);
        $this->assertEquals($expectedReturn, $return);

        $return = $driver->getStatuses([]);
        $this->assertEquals([], $return);
    }

    /**
     * This method tests getLocalId.
     *
     * @return mixed A MultiBackend instance.
     */
    public function testGetLocalId()
    {
        $driver = $this->initDriver();
        $term = 'source.local';
        $return = $this->callMethod($driver, 'getLocalId', [$term]);
        $this->assertEquals('local', $return);
    }

    /**
     * Test that MultiBackend can find and use the default ILS driver if parameters
     * don't include a detectable source id
     *
     * @return void
     */
    public function testDefaultDriver()
    {
        //Case: The parameters let it know what driver to use
        //Result: return the function results for that driver
        $patron = $this->getPatron('username', 'institution');

        $ILS = $this->getMockILS('Voyager', ['getMyTransactions', 'init']);

        $sm = $this->getMockSM($this->once(), 'Voyager', $ILS);
        $driver = $this->initDriver(['driverManager' => $sm]);
        $drivers = [
            'otherinst' => 'Unicorn',
            'institution' => 'Voyager',
        ];
        $this->setProperty($driver, 'drivers', $drivers);

        $patronPrefixless = $this->callMethod(
            $driver,
            'stripIdPrefixes',
            [$patron, 'institution']
        );
        $ILS->expects($this->atLeastOnce())
            ->method('getMyTransactions')
            ->with($patronPrefixless)
            ->will($this->returnValue(true));

        $returnVal = $driver->getMyTransactions($patron);
        $this->assertTrue($returnVal);

        //Case: There is a default driver set in the configuration
        //Result: return the function results for that driver

        // We need to clear patron login information so that MultiBackend has to
        // fall back on the defaultDriver implementation
        $patron['cat_username'] = 'username';

        $ILS = $this->getMockILS('Unicorn', ['getMyTransactions', 'init']);
        $ILS->expects($this->atLeastOnce())
            ->method('getMyTransactions')
            ->with($patron)
            ->will($this->returnValue(true));

        $sm = $this->getMockSM($this->once(), 'Unicorn', $ILS);
        $driver = $this->initDriver(['driverManager' => $sm]);
        $this->setProperty($driver, 'drivers', $drivers);

        $this->setProperty($driver, 'defaultDriver', 'otherinst');
        $returnVal = $driver->getMyTransactions($patron);
        $this->assertTrue($returnVal);
    }

    /**
     * Testing method for getNewItems without a default driver
     *
     * @return void
     */
    public function testGetNewItemsNoDefault()
    {
        $driver = $this->initDriver();

        // getNewItems only works with a default driver, so this call fails
        $this->expectException(\VuFind\Exception\ILS::class);
        $driver->getNewItems(1, 10, 5, 0);
    }

    /**
     * Testing method for getNewItems with a default driver
     *
     * @return void
     */
    public function testGetNewItems()
    {
        $return = [
            'count' => 2,
            'results' => [['id' => '1'], ['id' => '2']],
        ];

        $ILS = $this->getMockILS('Voyager', ['getNewItems', 'init']);
        $ILS->expects($this->once())
            ->method('getNewItems')
            ->with($this->equalTo('1'), $this->equalTo('10'), $this->equalTo('5'), $this->equalTo('0'))
            ->will($this->returnValue($return));

        $sm = $this->getMockSM($this->once(), 'Voyager', $ILS);
        $driver = $this->initDriver(['driverManager' => $sm]);
        $drivers = ['d1' => 'Voyager'];
        $this->setProperty($driver, 'drivers', $drivers);

        $expected = [
            'count' => 2,
            'results' => [['id' => 'd1.1'], ['id' => 'd1.2']],
        ];
        $this->setProperty($driver, 'defaultDriver', 'd1');
        $result = $driver->getNewItems(1, 10, 5, 0);
        $this->assertEquals($expected, $result);
    }

    /**
     * Testing method for getCourses without a default driver
     *
     * @return void
     */
    public function testGetCoursesNoDefault()
    {
        $driver = $this->initDriver();

        // getCourses only works with a default driver, so this call fails
        $this->expectException(\VuFind\Exception\ILS::class);
        $driver->getCourses();
    }

    /**
     * Testing method for getCourses with a default driver
     *
     * @return void
     */
    public function testGetCourses()
    {
        $expected = ['test' => 'true'];
        $driver = $this->initSimpleMethodTest(
            $this->once(),
            $this->never(),
            'getCourses',
            [],
            $expected,
            $expected
        );

        $this->setProperty($driver, 'defaultDriver', 'd1');
        $result = $driver->getCourses();
        $this->assertEquals($expected, $result);
    }

    /**
     * Testing method for getDepartments without a default driver
     *
     * @return void
     */
    public function testGetDepartmentsNoDefault()
    {
        $driver = $this->initDriver();

        // getDepartments only works with a default driver, so this call fails
        $this->expectException(\VuFind\Exception\ILS::class);
        $driver->getDepartments();
    }

    /**
     * Testing method for getDepartments
     *
     * @return void
     */
    public function testGetDepartments()
    {
        $expected = ['test' => 'true'];
        $driver = $this->initSimpleMethodTest(
            $this->once(),
            $this->never(),
            'getDepartments',
            [],
            $expected,
            $expected
        );

        $this->setProperty($driver, 'defaultDriver', 'd1');
        $result = $driver->getDepartments();
        $this->assertEquals($expected, $result);
    }

    /**
     * Testing method for getInstructors without a default driver
     *
     * @return void
     */
    public function testGetInstructorsNoDefault()
    {
        $driver = $this->initDriver();

        // getInstructors only works with a default driver, so this call fails
        $this->expectException(\VuFind\Exception\ILS::class);
        $driver->getInstructors();
    }

    /**
     * Testing method for getInstructors
     *
     * @return void
     */
    public function testGetInstructors()
    {
        $expected = ['test' => 'true'];
        $driver = $this->initSimpleMethodTest(
            $this->once(),
            $this->never(),
            'getInstructors',
            [],
            $expected,
            $expected
        );

        $this->setProperty($driver, 'defaultDriver', 'd1');
        $result = $driver->getInstructors();
        $this->assertEquals($expected, $result);
    }

    /**
     * Testing method for findReserves without a default driver
     *
     * @return void
     */
    public function testFindReservesNoDefault()
    {
        $driver = $this->initDriver();

        // findReserves only works with a default driver, so this call fails
        $this->expectException(\VuFind\Exception\ILS::class);
        $driver->findReserves('course', 'inst', 'dept');
    }

    /**
     * Testing method for findReserves
     *
     * @return void
     */
    public function testFindReserves()
    {
        $reservesReturn = [
            [
                'BIB_ID' => '12345',
                'COURSE_ID' => 1,
                'DEPARTMENT_ID' => 2,
                'INSTRUCTOR_ID' => 3,
            ],
            [
                'BIB_ID' => '56789',
                'COURSE_ID' => 4,
                'DEPARTMENT_ID' => 5,
                'INSTRUCTOR_ID' => 6,
            ],
        ];

        $ILS = $this->getMockILS('Voyager', ['findReserves', 'init']);
        $ILS->expects($this->once())
            ->method('findReserves')
            ->with($this->equalTo('course'), $this->equalTo('inst'), $this->equalTo('dept'))
            ->will($this->returnValue($reservesReturn));

        $sm = $this->getMockSM($this->once(), 'Voyager', $ILS);
        $driver = $this->initDriver(['driverManager' => $sm]);
        $drivers = ['d1' => 'Voyager'];
        $this->setProperty($driver, 'drivers', $drivers);

        $this->setProperty($driver, 'defaultDriver', 'd1');
        $expected = $reservesReturn;
        $expected[0]['BIB_ID'] = 'd1.' . $expected[0]['BIB_ID'];
        $expected[1]['BIB_ID'] = 'd1.' . $expected[1]['BIB_ID'];
        $result = $driver->findReserves('course', 'inst', 'dept');
        $this->assertEquals($expected, $result);
    }

    /**
     * Testing method for getMyProfile
     *
     * @return void
     */
    public function testGetMyProfile()
    {
        $expected1 = ['test' => '1'];
        $expected2 = ['test' => '2'];
        $driver = $this->initSimpleMethodTest(
            $this->once(),
            $this->once(),
            'getMyProfile',
            [$this->getPatron('username')],
            $expected1,
            $expected2
        );

        $result = $driver->getMyProfile($this->getPatron('username', 'invalid'));
        $this->assertEquals([], $result);

        $result = $driver->getMyProfile($this->getPatron('username', 'd1'));
        $this->assertEquals($expected1, $result);

        $result = $driver->getMyProfile($this->getPatron('username', 'd2'));
        $this->assertEquals($expected2, $result);
    }

    /**
     * Test that MultiBackend can properly find a driver and pass
     * log in credentials to it
     *
     * @return void
     */
    public function testPatronLogin()
    {
        $driver = $this->initDriver();
        $patronReturn = $this->getPatron('username');
        $instance = 'institution';

        //Set up the mock object and prepare its expectations
        $ILS = $this->getMockILS('Voyager', ['patronLogin']);
        $ILS->expects($this->once())
            ->method('patronLogin')
            ->with('username', 'password')
            ->will($this->returnValue($patronReturn));

        // Prep MultiBackend with values it will need
        $drivers = [$instance => 'Voyager'];
        $cache = [$instance => $ILS];
        $this->setProperty($driver, 'drivers', $drivers);
        $this->setProperty($driver, 'driverCache', $cache);

        //Call the method
        $patron = $driver->patronLogin("$instance.username", 'password');

        //Check that it added username info properly.
        $this->assertSame(
            $instance . '.' . $patronReturn['cat_username'],
            $patron['cat_username']
        );

        $this->expectException(\VuFind\Exception\ILS::class);
        $driver->patronLogin('bad', 'info');
    }

    /**
     * Testing method for getMyTransactions
     *
     * @return void
     */
    public function testGetMyTransactions()
    {
        $expected1 = [['id' => 'd1.1']];
        $expected2 = [['id' => 'd2.1']];
        $driver = $this->initSimpleMethodTest(
            $this->once(),
            $this->once(),
            'getMyTransactions',
            [$this->getPatron('username')],
            [['id' => '1']],
            [['id' => '1']]
        );

        $result = $driver->getMyTransactions($this->getPatron('username', 'd1'));
        $this->assertEquals($expected1, $result);

        $result = $driver->getMyTransactions($this->getPatron('username', 'd2'));
        $this->assertEquals($expected2, $result);

        $this->expectException(\VuFind\Exception\ILS::class);
        $this->expectExceptionMessage('No suitable backend driver found');
        $driver->getMyTransactions(
            $this->getPatron('username', 'invalid')
        );
    }

    /**
     * Testing method for getRenewDetails
     *
     * @return void
     */
    public function testGetRenewDetails()
    {
        $expected1 = [['id' => 'd1.1']];
        $expected2 = [['id' => 'd2.1']];
        $driver = $this->initSimpleMethodTest(
            $this->once(),
            $this->once(),
            'getRenewDetails',
            [['id' => 'loanid']],
            [['id' => '1']],
            [['id' => '1']]
        );

        $result = $driver->getRenewDetails(
            [
                'id' => 'd1.loanid',
            ]
        );
        $this->assertEquals($expected1, $result);

        $result = $driver->getRenewDetails(
            [
                'id' => 'd2.loanid',
            ]
        );
        $this->assertEquals($expected2, $result);

        $this->expectException(\VuFind\Exception\ILS::class);
        $this->expectExceptionMessage('No suitable backend driver found');
        $driver->getRenewDetails(
            [
                'id' => 'invalid.loanid',
            ]
        );
    }

    /**
     * Testing method for renewMyItems
     *
     * @return void
     */
    public function testRenewMyItems()
    {
        $expected1 = [
            ['id' => 'd1.1'],
            ['id' => 'd1.2'],
        ];
        $expected2 = [
            ['id' => 'd2.1'],
            ['id' => 'd2.2'],
        ];
        $driver = $this->initSimpleMethodTest(
            $this->once(),
            $this->once(),
            'renewMyItems',
            [['patron' => $this->getPatron('username')]],
            [['id' => '1'], ['id' => '2']],
            [['id' => '1'], ['id' => '2']]
        );

        $result = $driver->renewMyItems(
            ['patron' => $this->getPatron('username', 'd1')]
        );
        $this->assertEquals($expected1, $result);

        $result = $driver->renewMyItems(
            ['patron' => $this->getPatron('username', 'd2')]
        );
        $this->assertEquals($expected2, $result);

        $this->expectException(\VuFind\Exception\ILS::class);
        $this->expectExceptionMessage('No suitable backend driver found');
        $driver->renewMyItems(
            ['patron' => $this->getPatron('username', 'invalid')]
        );
    }

    /**
     * Testing method for getMyFines
     *
     * @return void
     */
    public function testGetMyFines()
    {
        $expected1 = [['id' => 'd1.1']];
        $expected2 = [['id' => 'd2.1']];
        $driver = $this->initSimpleMethodTest(
            $this->once(),
            $this->once(),
            'getMyFines',
            [$this->getPatron('username')],
            [['id' => '1']],
            [['id' => '1']]
        );

        $result = $driver->getMyFines($this->getPatron('username', 'd1'));
        $this->assertEquals($expected1, $result);

        $result = $driver->getMyFines($this->getPatron('username', 'd2'));
        $this->assertEquals($expected2, $result);

        $this->expectException(\VuFind\Exception\ILS::class);
        $this->expectExceptionMessage('No suitable backend driver found');
        $driver->getMyFines($this->getPatron('username', 'invalid'));
    }

    /**
     * Testing method for getHoldLink
     *
     * @return void
     */
    public function testGetHoldLink()
    {
        $expected1 = 'http://driver1/1';
        $expected2 = 'http://driver2/1';
        $driver = $this->initSimpleMethodTest(
            $this->once(),
            $this->once(),
            'getHoldLink',
            [1, []],
            $expected1,
            $expected2
        );

        $result1 = $driver->getHoldLink('d1.1', []);
        $this->assertEquals($expected1, $result1);

        $result2 = $driver->getHoldLink('d2.1', []);
        $this->assertEquals($expected2, $result2);

        $this->expectException(\VuFind\Exception\ILS::class);
        $this->expectExceptionMessage('No suitable backend driver found');
        $driver->getHoldLink('invalid.1', []);
    }

    /**
     * Testing method for getMyHolds
     *
     * @return void
     */
    public function testGetMyHolds()
    {
        $expected1 = [['id' => 'd1.1']];
        $expected2 = [['id' => 'd2.1']];
        $driver = $this->initSimpleMethodTest(
            $this->once(),
            $this->once(),
            'getMyHolds',
            [$this->getPatron('username')],
            [['id' => '1']],
            [['id' => '1']]
        );

        $result = $driver->getMyHolds($this->getPatron('username', 'd1'));
        $this->assertEquals($expected1, $result);

        $result = $driver->getMyHolds($this->getPatron('username', 'd2'));
        $this->assertEquals($expected2, $result);

        $this->expectException(\VuFind\Exception\ILS::class);
        $this->expectExceptionMessage('No suitable backend driver found');
        $driver->getMyHolds($this->getPatron('username', 'invalid'));
    }

    /**
     * Testing method for getAccountBlocks
     *
     * @return void
     */
    public function testGetAccountBlocks()
    {
        $driver = $this->initSimpleMethodTest(
            $this->once(),
            $this->once(),
            'getAccountBlocks',
            [$this->getPatron('username')],
            ['fine limit exceeded'],
            ['too many items checked out']
        );

        $result = $driver->getAccountBlocks($this->getPatron('username', 'd1'));
        $this->assertEquals(['fine limit exceeded'], $result);

        $result = $driver->getAccountBlocks($this->getPatron('username', 'd2'));
        $this->assertEquals(['too many items checked out'], $result);

        $result = $driver->getAccountBlocks($this->getPatron('username', 'd3'));
        $this->assertEquals(false, $result);

        $this->expectException(\VuFind\Exception\ILS::class);
        $this->expectExceptionMessage('No suitable backend driver found');
        $driver->getAccountBlocks($this->getPatron('username', 'invalid'));
    }

    /**
     * Testing method for getRequestBlocks
     *
     * @return void
     */
    public function testGetRequestBlocks()
    {
        $driver = $this->initSimpleMethodTest(
            $this->once(),
            $this->once(),
            'getRequestBlocks',
            [$this->getPatron('username')],
            ['too many holds'],
            ['too many items checked out']
        );

        $result = $driver->getRequestBlocks($this->getPatron('username', 'd1'));
        $this->assertEquals(['too many holds'], $result);

        $result = $driver->getRequestBlocks($this->getPatron('username', 'd2'));
        $this->assertEquals(['too many items checked out'], $result);

        $result = $driver->getRequestBlocks($this->getPatron('username', 'd3'));
        $this->assertEquals(false, $result);

        $this->expectException(\VuFind\Exception\ILS::class);
        $this->expectExceptionMessage('No suitable backend driver found');
        $driver->getRequestBlocks($this->getPatron('username', 'invalid'));
    }

    /**
     * Testing method for getMyStorageRetrievalRequests
     *
     * @return void
     */
    public function testGetMyStorageRetrievalRequests()
    {
        $expected1 = [['id' => 'd1.1']];
        $expected2 = [['id' => 'd2.1']];
        $driver = $this->initSimpleMethodTest(
            $this->once(),
            $this->once(),
            'getMyStorageRetrievalRequests',
            [$this->getPatron('username')],
            [['id' => '1']],
            [['id' => '1']]
        );

        $result = $driver->getMyStorageRetrievalRequests(
            $this->getPatron('username', 'd1')
        );
        $this->assertEquals($expected1, $result);

        $result = $driver->getMyStorageRetrievalRequests(
            $this->getPatron('username', 'd2')
        );
        $this->assertEquals($expected2, $result);

        // Try handling of unsupported request
        $result = $driver->getMyStorageRetrievalRequests(
            $this->getPatron('username', 'd3')
        );
        $this->assertEquals([], $result);

        $this->expectException(\VuFind\Exception\ILS::class);
        $this->expectExceptionMessage('No suitable backend driver found');
        $driver->getMyStorageRetrievalRequests(
            $this->getPatron('username', 'invalid')
        );
    }

    /**
     * Testing method for checkRequestIsValid
     *
     * @return void
     */
    public function testCheckRequestIsValid()
    {
        $ils1 = $this->getMockILS('Voyager', ['init', 'checkRequestIsValid']);
        $ils1->expects($this->once())
            ->method('checkRequestIsValid')
            ->with('bibid', ['id' => 'itemid'], $this->getPatron('username'))
            ->will(
                $this->returnValue(true)
            );

        $ils2 = $this->getMockILS('Demo', ['init', 'checkRequestIsValid']);
        $ils2->expects($this->once())
            ->method('checkRequestIsValid')
            ->with('bibid', ['id' => 'itemid'], $this->getPatron('username'))
            ->will(
                $this->returnValue(true)
            );

        $driver = $this->getMultiDriverForDrivers(['d1' => $ils1, 'd2' => $ils2]);

        $result = $driver->checkRequestIsValid(
            'd1.bibid',
            [
                'id' => 'd1.itemid',
            ],
            $this->getPatron('username', 'd1')
        );
        $this->assertTrue($result);

        $result = $driver->checkRequestIsValid(
            'd2.bibid',
            [
                'id' => 'd2.itemid',
            ],
            $this->getPatron('username', 'd2')
        );
        $this->assertTrue($result);

        // Cross-driver request must not be accepted for Voyager:
        $result = $driver->checkRequestIsValid(
            'd2.bibid',
            [
                'id' => 'd2.itemid',
            ],
            $this->getPatron('username', 'd1')
        );
        $this->assertFalse($result);

        // Request with a patron missing cat_username must not be accepted
        $result = $driver->checkRequestIsValid(
            'd1.bibid',
            [
                'id' => 'd1.itemid',
            ],
            ['bad patron']
        );
        $this->assertFalse($result);

        $result = $driver->checkRequestIsValid(
            'invalid.bibid',
            [
                'id' => 'invalid.itemid',
            ],
            $this->getPatron('username', 'invalid')
        );
        $this->assertFalse($result);

        // Cross-driver request must be accepted for Demo:
        $demo = $this->getMockILS('Demo', ['init', 'checkRequestIsValid']);
        $demo->expects($this->once())
            ->method('checkRequestIsValid')
            ->with('d1.bibid', ['id' => 'd1.itemid'], $this->getPatron('username'))
            ->will(
                $this->returnValue(true)
            );

        $driver = $this->getMultiDriverForDrivers(['d2' => $demo]);
        $result = $driver->checkRequestIsValid(
            'd1.bibid',
            [
                'id' => 'd1.itemid',
            ],
            $this->getPatron('username', 'd2')
        );
        $this->assertTrue($result);
    }

    /**
     * Testing method for checkStorageRetrievalRequestIsValid
     *
     * @return void
     */
    public function testCheckStorageRetrievalRequestIsValid()
    {
        $expected1 = true;
        $expected2 = false;
        $driver = $this->initSimpleMethodTest(
            $this->once(),
            $this->once(),
            'checkStorageRetrievalRequestIsValid',
            [
                'bibid',
                ['id' => 'itemid'],
                $this->getPatron('username'),
            ],
            true,
            false
        );

        $result = $driver->checkStorageRetrievalRequestIsValid(
            'd1.bibid',
            [
                'id' => 'd1.itemid',
            ],
            $this->getPatron('username', 'd1')
        );
        $this->assertEquals($expected1, $result);

        $result = $driver->checkStorageRetrievalRequestIsValid(
            'd2.bibid',
            [
                'id' => 'd2.itemid',
            ],
            $this->getPatron('username', 'd2')
        );
        $this->assertEquals($expected2, $result);

        // Cross-driver request must not be accepted
        $result = $driver->checkStorageRetrievalRequestIsValid(
            'd1.bibid',
            [
                'id' => 'd1.itemid',
            ],
            $this->getPatron('username', 'd2')
        );
        $this->assertFalse($result);

        $result = $driver->checkStorageRetrievalRequestIsValid(
            'invalid.bibid',
            [
                'id' => 'invalid.itemid',
            ],
            $this->getPatron('username', 'invalid')
        );
        $this->assertFalse($result);
    }

    /**
     * Testing method for getPickUpLocations
     *
     * @return void
     */
    public function testGetPickUpLocations()
    {
        $expected1 = [['locationID' => '1']];
        $expected2 = [['locationID' => '2']];
        $driver = $this->initSimpleMethodTest(
            $this->once(),
            $this->once(),
            'getPickUpLocations',
            [
                $this->getPatron('username'),
                ['id' => '1'],
            ],
            $expected1,
            $expected2
        );

        $result = $driver->getPickUpLocations(
            $this->getPatron('username', 'd1'),
            ['id' => 'd1.1']
        );
        $this->assertEquals($expected1, $result);

        $result = $driver->getPickUpLocations(
            $this->getPatron('username', 'd2'),
            ['id' => 'd2.1']
        );
        $this->assertEquals($expected2, $result);

        // Must return empty set if sources of patron id and bib id differ
        $result = $driver->getPickUpLocations(
            $this->getPatron('username', 'd2'),
            ['id' => 'd1.1']
        );
        $this->assertEquals([], $result);

        $this->expectException(\VuFind\Exception\ILS::class);
        $this->expectExceptionMessage('No suitable backend driver found');
        $driver->getPickUpLocations(
            $this->getPatron('username', 'invalid'),
            ['id' => '1']
        );
    }

    /**
     * Testing method for getDefaultPickUpLocation
     *
     * @return void
     */
    public function testGetDefaultPickUpLocation()
    {
        $expected1 = '1';
        $expected2 = '1';
        $driver = $this->initSimpleMethodTest(
            $this->once(),
            $this->once(),
            'getDefaultPickUpLocation',
            [
                $this->getPatron('username'),
                ['id' => '1'],
            ],
            $expected1,
            $expected2
        );

        $result = $driver->getDefaultPickUpLocation(
            $this->getPatron('username', 'd1'),
            ['id' => 'd1.1']
        );
        $this->assertEquals($expected1, $result);

        $result = $driver->getDefaultPickUpLocation(
            $this->getPatron('username', 'd2'),
            ['id' => 'd2.1']
        );
        $this->assertEquals($expected2, $result);

        // Must return false if sources of patron id and bib id differ
        $result = $driver->getDefaultPickUpLocation(
            $this->getPatron('username', 'd2'),
            ['id' => 'd1.1']
        );
        $this->assertFalse($result);

        $this->expectException(\VuFind\Exception\ILS::class);
        $this->expectExceptionMessage('No suitable backend driver found');
        $driver->getDefaultPickUpLocation(
            $this->getPatron('username', 'invalid'),
            ['id' => '1']
        );
    }

    /**
     * Testing method for getRequestGroups
     *
     * @return void
     */
    public function testGetRequestGroups()
    {
        $expected1 = [['locationID' => '1']];
        $expected2 = [['locationID' => '2']];
        $driver = $this->initSimpleMethodTest(
            $this->once(),
            $this->once(),
            'getRequestGroups',
            [
                '1',
                $this->getPatron('username'),
            ],
            $expected1,
            $expected2
        );

        $result = $driver->getRequestGroups(
            'd1.1',
            $this->getPatron('username', 'd1')
        );
        $this->assertEquals($expected1, $result);

        $result = $driver->getRequestGroups(
            'd2.1',
            $this->getPatron('username', 'd2')
        );
        $this->assertEquals($expected2, $result);

        // Must return empty set if sources of patron id and bib id differ
        $result = $driver->getRequestGroups(
            'd1.1',
            $this->getPatron('username', 'd2')
        );
        $this->assertEquals([], $result);

        $this->expectException(\VuFind\Exception\ILS::class);
        $this->expectExceptionMessage('No suitable backend driver found');
        $driver->getRequestGroups(
            '1',
            $this->getPatron('username', 'invalid')
        );
    }

    /**
     * Testing method for getDefaultRequestGroup
     *
     * @return void
     */
    public function testGetDefaultRequestGroup()
    {
        $expected1 = '1';
        $expected2 = '1';
        $driver = $this->initSimpleMethodTest(
            $this->once(),
            $this->once(),
            'getDefaultRequestGroup',
            [
                $this->getPatron('username'),
                ['id' => '1'],
            ],
            $expected1,
            $expected2
        );

        $result = $driver->getDefaultRequestGroup(
            $this->getPatron('username', 'd1'),
            ['id' => 'd1.1']
        );
        $this->assertEquals($expected1, $result);

        $result = $driver->getDefaultRequestGroup(
            $this->getPatron('username', 'd2'),
            ['id' => 'd2.1']
        );
        $this->assertEquals($expected2, $result);

        // Must return false if sources of patron id and bib id differ
        $result = $driver->getDefaultRequestGroup(
            $this->getPatron('username', 'd2'),
            ['id' => 'd1.1']
        );
        $this->assertFalse($result);

        $this->expectException(\VuFind\Exception\ILS::class);
        $this->expectExceptionMessage('No suitable backend driver found');
        $driver->getDefaultRequestGroup(
            $this->getPatron('username', 'invalid'),
            ['id' => '1']
        );
    }

    /**
     * Testing method for placeHold
     *
     * @return void
     */
    public function testPlaceHold()
    {
        $expected1 = [
            'success' => true,
            'status' => '',
        ];
        $expected2 = [
            'success' => false,
            'status' => 'hold_error_fail',
        ];
        $driver = $this->initSimpleMethodTest(
            $this->once(),
            $this->once(),
            'placeHold',
            [['patron' => $this->getPatron('username'), 'id' => 1]],
            $expected1,
            $expected2
        );

        $result = $driver->placeHold(
            [
                'patron' => $this->getPatron('username', 'd1'),
                'id' => 'd1.1',
            ]
        );
        $this->assertEquals($expected1, $result);

        $result = $driver->placeHold(
            [
                'patron' => $this->getPatron('username', 'd2'),
                'id' => 'd2.1',
            ]
        );
        $this->assertEquals($expected2, $result);

        // Patron/item source mismatch
        $result = $driver->placeHold(
            [
                'patron' => $this->getPatron('username', 'd2'),
                'id' => 'd1.1',
            ]
        );
        $this->assertEquals(
            [
                'success' => false,
                'sysMessage' => 'ILSMessages::hold_wrong_user_institution',
            ],
            $result
        );

        $this->expectException(\VuFind\Exception\ILS::class);
        $this->expectExceptionMessage('No suitable backend driver found');
        $driver->placeHold(
            [
                'patron' => $this->getPatron('username', 'invalid'),
                'id' => 'invalid.1',
            ]
        );
    }

    /**
     * Testing method for cancelHolds
     *
     * @return void
     */
    public function testCancelHolds()
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
        $driver = $this->initSimpleMethodTest(
            $this->once(),
            $this->once(),
            'cancelHolds',
            [
                [
                    'patron' => $this->getPatron('username'),
                    'details' => ['1', '2'],
                ],
            ],
            $expected,
            $expected
        );

        $result = $driver->cancelHolds(
            [
                'patron' => $this->getPatron('username', 'd1'),
                'details' => ['1', '2'],
            ]
        );
        $this->assertEquals($expected, $result);

        $result = $driver->cancelHolds(
            [
                'patron' => $this->getPatron('username', 'd2'),
                'details' => ['1', '2'],
            ]
        );
        $this->assertEquals($expected, $result);

        $this->expectException(\VuFind\Exception\ILS::class);
        $this->expectExceptionMessage('No suitable backend driver found');
        $driver->cancelHolds(
            [
                'patron' => $this->getPatron('username', 'invalid'),
                'details' => ['1', '2'],
            ]
        );
    }

    /**
     * Testing method for getCancelHoldDetails
     *
     * @return void
     */
    public function testGetCancelHoldDetails()
    {
        $expected = ['1', '2'];
        $driver = $this->initSimpleMethodTest(
            $this->once(),
            $this->exactly(2),
            'getCancelHoldDetails',
            [['id' => '1', 'item_id' => '2']],
            $expected,
            $expected
        );

        $result = $driver->getCancelHoldDetails(
            ['id' => 'd1.1', 'item_id' => 2],
            $this->getPatron('user', 'd1')
        );
        $this->assertEquals($expected, $result);

        $result = $driver->getCancelHoldDetails(
            ['id' => 'd2.1', 'item_id' => 2],
            $this->getPatron('user', 'd2')
        );
        $this->assertEquals($expected, $result);

        $result = $driver->getCancelHoldDetails(
            ['id' => 'd2.1', 'item_id' => 2]
        );
        $this->assertEquals($expected, $result);

        $this->expectException(\VuFind\Exception\ILS::class);
        $this->expectExceptionMessage('No suitable backend driver found');
        $driver->getCancelHoldDetails(
            ['id' => 'd1.1', 'item_id' => 2],
            $this->getPatron('user', 'invalid')
        );
    }

    /**
     * Testing method for placeStorageRetrievalRequest
     *
     * @return void
     */
    public function testPlaceStorageRetrievalRequest()
    {
        $expected1 = [
            'success' => true,
            'status' => '',
        ];
        $expected2 = [
            'success' => false,
            'status' => 'storage_retrieval_request_error_blocked',
        ];
        $driver = $this->initSimpleMethodTest(
            $this->once(),
            $this->once(),
            'placeStorageRetrievalRequest',
            [['patron' => $this->getPatron('username'), 'id' => 1]],
            $expected1,
            $expected2
        );

        $result = $driver->placeStorageRetrievalRequest(
            [
                'patron' => $this->getPatron('username', 'd1'),
                'id' => 'd1.1',
            ]
        );
        $this->assertEquals($expected1, $result);

        $result = $driver->placeStorageRetrievalRequest(
            [
                'patron' => $this->getPatron('username', 'd2'),
                'id' => 'd2.1',
            ]
        );
        $this->assertEquals($expected2, $result);

        // Patron/item source mismatch
        $result = $driver->placeStorageRetrievalRequest(
            [
                'patron' => $this->getPatron('username', 'd2'),
                'id' => 'd1.1',
            ]
        );
        $this->assertEquals(
            [
                'success' => false,
                'sysMessage' => 'ILSMessages::storage_wrong_user_institution',
            ],
            $result
        );

        $this->expectException(\VuFind\Exception\ILS::class);
        $this->expectExceptionMessage('No suitable backend driver found');
        $driver->placeStorageRetrievalRequest(
            [
                'patron' => $this->getPatron('username', 'invalid'),
                'id' => 'invalid.1',
            ]
        );
    }

    /**
     * Testing method for cancelStorageRetrievalRequests
     *
     * @return void
     */
    public function testCancelStorageRetrievalRequests()
    {
        $expected = [
            '1' => [
                'success' => true,
                'status' => 'storage_retrieval_request_cancel_success',
            ],
            '2' => [
                'success' => false,
                'status' => 'storage_retrieval_request_cancel_fail',
            ],

        ];
        $driver = $this->initSimpleMethodTest(
            $this->once(),
            $this->once(),
            'cancelStorageRetrievalRequests',
            [
                [
                    'patron' => $this->getPatron('username'),
                    'details' => ['1', '2'],
                ],
            ],
            $expected,
            $expected
        );

        $result = $driver->cancelStorageRetrievalRequests(
            [
                'patron' => $this->getPatron('username', 'd1'),
                'details' => ['1', '2'],
            ]
        );
        $this->assertEquals($expected, $result);

        $result = $driver->cancelStorageRetrievalRequests(
            [
                'patron' => $this->getPatron('username', 'd2'),
                'details' => ['1', '2'],
            ]
        );
        $this->assertEquals($expected, $result);

        $this->expectException(\VuFind\Exception\ILS::class);
        $this->expectExceptionMessage('No suitable backend driver found');
        $driver->cancelStorageRetrievalRequests(
            [
                'patron' => $this->getPatron('username', 'invalid'),
                'details' => ['1', '2'],
            ]
        );
    }

    /**
     * Testing method for getCancelStorageRetrievalRequestDetails
     *
     * @return void
     */
    public function testGetCancelStorageRetrievalRequestDetails()
    {
        $expected = ['1', '2'];
        $driver = $this->initSimpleMethodTest(
            $this->once(),
            $this->once(),
            'getCancelStorageRetrievalRequestDetails',
            [['id' => '1','item_id' => '2']],
            $expected,
            $expected
        );

        $result = $driver->getCancelStorageRetrievalRequestDetails(
            ['id' => 'd1.1', 'item_id' => 2],
            $this->getPatron('user', 'd1')
        );
        $this->assertEquals($expected, $result);

        $result = $driver->getCancelStorageRetrievalRequestDetails(
            ['id' => 'd2.1', 'item_id' => 2],
            $this->getPatron('user', 'd2')
        );
        $this->assertEquals($expected, $result);

        $this->expectException(\VuFind\Exception\ILS::class);
        $this->expectExceptionMessage('No suitable backend driver found');
        $driver->getCancelStorageRetrievalRequestDetails(
            ['id' => 'd1.1', 'item_id' => 2],
            $this->getPatron('user', 'invalid')
        );
    }

    /**
     * Testing method for checkILLRequestIsValid
     *
     * @return void
     */
    public function testCheckILLRequestIsValid()
    {
        $expected1 = true;
        $expected2 = false;
        $driver = $this->initSimpleMethodTest(
            $this->exactly(2),
            $this->once(),
            'checkILLRequestIsValid',
            [
                'bibid',
                ['id' => 'itemid'],
                $this->logicalOr(
                    $this->getPatron('username', 'd1'),
                    $this->getPatron('username', 'd2')
                ),
            ],
            true,
            false
        );

        $result = $driver->checkILLRequestIsValid(
            'd1.bibid',
            ['id' => 'd1.itemid'],
            $this->getPatron('username', 'd1')
        );
        $this->assertEquals($expected1, $result);

        $result = $driver->checkILLRequestIsValid(
            'd2.bibid',
            ['id' => 'd2.itemid'],
            $this->getPatron('username', 'd2')
        );
        $this->assertEquals($expected2, $result);

        // Cross-driver request must be accepted
        $result = $driver->checkILLRequestIsValid(
            'd1.bibid',
            ['id' => 'd1.itemid'],
            $this->getPatron('username', 'd2')
        );
        $this->assertEquals(true, $result);

        $this->expectException(\VuFind\Exception\ILS::class);
        $this->expectExceptionMessage('No suitable backend driver found');
        $result = $driver->checkILLRequestIsValid(
            'invalid.bibid',
            ['id' => 'invalid.itemid'],
            $this->getPatron('username', 'invalid')
        );
        $this->assertFalse($result);
    }

    /**
     * Testing method for getILLPickupLibraries
     *
     * @return void
     */
    public function testGetILLPickupLibraries()
    {
        $expected1 = [['locationID' => '1']];
        $expected2 = [['locationID' => '2']];
        $driver = $this->initSimpleMethodTest(
            $this->once(),
            $this->once(),
            'getILLPickupLibraries',
            [
                '1',
                $this->logicalOr(
                    $this->getPatron('username', 'd1'),
                    $this->getPatron('username', 'd2')
                ),
            ],
            $expected1,
            $expected2
        );

        $result = $driver->getILLPickupLibraries(
            'd1.1',
            $this->getPatron('username', 'd1')
        );
        $this->assertEquals($expected1, $result);

        $result = $driver->getILLPickupLibraries(
            'd2.1',
            $this->getPatron('username', 'd2')
        );
        $this->assertEquals($expected2, $result);

        $this->expectException(\VuFind\Exception\ILS::class);
        $this->expectExceptionMessage('No suitable backend driver found');
        $driver->getILLPickupLibraries(
            '1',
            $this->getPatron('username', 'invalid')
        );
    }

    /**
     * Testing method for getILLPickupLocations
     *
     * @return void
     */
    public function testGetILLPickupLocations()
    {
        $expected1 = [['locationID' => '1']];
        $expected2 = [['locationID' => '2']];
        $driver = $this->initSimpleMethodTest(
            $this->once(),
            $this->once(),
            'getILLPickupLocations',
            [
                '1',
                '2',
                $this->logicalOr(
                    $this->getPatron('username', 'd1'),
                    $this->getPatron('username', 'd2')
                ),
            ],
            $expected1,
            $expected2
        );

        $result = $driver->getILLPickupLocations(
            'd1.1',
            '2',
            $this->getPatron('username', 'd1')
        );
        $this->assertEquals($expected1, $result);

        $result = $driver->getILLPickupLocations(
            'd2.1',
            '2',
            $this->getPatron('username', 'd2')
        );
        $this->assertEquals($expected2, $result);

        $this->expectException(\VuFind\Exception\ILS::class);
        $this->expectExceptionMessage('No suitable backend driver found');
        $driver->getILLPickupLocations(
            '1',
            '2',
            $this->getPatron('username', 'invalid')
        );
    }

    /**
     * Testing method for placeILLRequest
     *
     * @return void
     */
    public function testPlaceILLRequest()
    {
        $expected1 = [
            'success' => true,
            'status' => '',
        ];
        $expected2 = [
            'success' => false,
            'status' => 'ill_request_error_fail',
        ];
        $driver = $this->initSimpleMethodTest(
            $this->once(),
            $this->once(),
            'placeILLRequest',
            [
                $this->logicalOr(
                    [
                        'patron' => $this->getPatron('username', 'd1'),
                        'id' => 1,
                    ],
                    [
                        'patron' => $this->getPatron('username', 'd2'),
                        'id' => 1,
                    ]
                ),
            ],
            $expected1,
            $expected2
        );

        $result = $driver->placeILLRequest(
            [
                'patron' => $this->getPatron('username', 'd1'),
                'id' => 'd1.1',
            ]
        );
        $this->assertEquals($expected1, $result);

        $result = $driver->placeILLRequest(
            [
                'patron' => $this->getPatron('username', 'd2'),
                'id' => 'd2.1',
            ]
        );
        $this->assertEquals($expected2, $result);

        $this->expectException(\VuFind\Exception\ILS::class);
        $this->expectExceptionMessage('No suitable backend driver found');
        $driver->placeILLRequest(
            [
                'patron' => $this->getPatron('username', 'invalid'),
                'id' => 'invalid.1',
            ]
        );
    }

    /**
     * Testing method for getMyILLRequests
     *
     * @return void
     */
    public function testGetMyILLRequests()
    {
        $expected1 = [['id' => 'd1.1']];
        $expected2 = [['id' => 'd2.1']];
        $driver = $this->initSimpleMethodTest(
            $this->once(),
            $this->once(),
            'getMyILLRequests',
            [$this->getPatron('username')],
            [['id' => '1']],
            [['id' => '1']]
        );

        $result = $driver->getMyILLRequests($this->getPatron('username', 'd1'));
        $this->assertEquals($expected1, $result);

        $result = $driver->getMyILLRequests($this->getPatron('username', 'd2'));
        $this->assertEquals($expected2, $result);

        // Try handling of unsupported request
        $result = $driver->getMyILLRequests($this->getPatron('username', 'd3'));
        $this->assertEquals([], $result);

        $this->expectException(\VuFind\Exception\ILS::class);
        $this->expectExceptionMessage('No suitable backend driver found');
        $driver->getMyILLRequests(
            $this->getPatron('username', 'invalid')
        );
    }

    /**
     * Testing method for cancelILLRequests
     *
     * @return void
     */
    public function testCancelILLRequests()
    {
        $expected = [
            '1' => [
                'success' => true,
                'status' => 'ill_request_cancel_success',
            ],
            '2' => [
                'success' => false,
                'status' => 'storage_retrieval_request_cancel_fail',
            ],

        ];
        $driver = $this->initSimpleMethodTest(
            $this->once(),
            $this->once(),
            'cancelILLRequests',
            [
                [
                    'patron' => $this->getPatron('username'),
                    'details' => ['1', '2'],
                ],
            ],
            $expected,
            $expected
        );

        $result = $driver->cancelILLRequests(
            [
                'patron' => $this->getPatron('username', 'd1'),
                'details' => ['1', '2'],
            ]
        );
        $this->assertEquals($expected, $result);

        $result = $driver->cancelILLRequests(
            [
                'patron' => $this->getPatron('username', 'd2'),
                'details' => ['1', '2'],
            ]
        );
        $this->assertEquals($expected, $result);

        $this->expectException(\VuFind\Exception\ILS::class);
        $this->expectExceptionMessage('No suitable backend driver found');
        $driver->cancelILLRequests(
            [
                'patron' => $this->getPatron('username', 'invalid'),
                'details' => ['1', '2'],
            ]
        );
    }

    /**
     * Testing method for getCancelILLRequestDetails
     *
     * @return void
     */
    public function testGetCancelILLRequestDetails()
    {
        $expected = ['1', '2'];
        $driver = $this->initSimpleMethodTest(
            $this->once(),
            $this->once(),
            'getCancelILLRequestDetails',
            [['id' => '1','item_id' => '2']],
            $expected,
            $expected
        );

        $result = $driver->getCancelILLRequestDetails(
            ['id' => 'd1.1', 'item_id' => 2],
            $this->getPatron('user', 'd1')
        );
        $this->assertEquals($expected, $result);

        $result = $driver->getCancelILLRequestDetails(
            ['id' => 'd2.1', 'item_id' => 2],
            $this->getPatron('user', 'd2')
        );
        $this->assertEquals($expected, $result);

        $this->expectException(\VuFind\Exception\ILS::class);
        $this->expectExceptionMessage('No suitable backend driver found');
        $driver->getCancelILLRequestDetails(
            ['id' => 'd1.1', 'item_id' => 2],
            $this->getPatron('user', 'invalid')
        );
    }

    /**
     * Testing method for getConfig
     *
     * @return void
     */
    public function testGetConfig()
    {
        $expected1 = ['config' => 'ok1'];
        $expected2 = ['config' => 'ok2'];
        $driver = $this->initSimpleMethodTest(
            $this->exactly(3),
            $this->once(),
            'getConfig',
            [
                $this->logicalOr(
                    'Holds',
                    ['Holds', ['id' => '123456']],
                    [
                        'Holds',
                        ['patron' => $this->getPatron('123456')],
                    ]
                ),
            ],
            $expected1,
            $expected2
        );

        $result = $driver->getConfig('Holds', ['id' => 'd1.123456']);
        $this->assertEquals($expected1, $result);

        $result = $driver->getConfig(
            'Holds',
            ['patron' => $this->getPatron('123456', 'd1')]
        );
        $this->assertEquals($expected1, $result);

        $return = $driver->getConfig('Holds', ['id' => 'fail.123456']);
        $this->assertEquals([], $return);

        $this->setProperty($driver, 'defaultDriver', 'd2');
        $return = $driver->getConfig('Holds', []);
        $this->assertEquals($expected2, $return);

        $mockAuth = $this->getMockILSAuthenticator('d1');
        $this->setProperty($driver, 'ilsAuth', $mockAuth);
        $result = $driver->getConfig('Holds');
        $this->assertEquals($expected1, $result);

        $mockAuth = $this->getMockILSAuthenticator(null);
        $this->setProperty($driver, 'ilsAuth', $mockAuth);
        $result = $driver->getConfig('Holds');
        $this->assertEquals([], $result);
    }

    /**
     * Test that MultiBackend can properly tell whether or not
     * a driver is has contains a specified method.
     *
     * @return void
     */
    public function testSupportsMethod()
    {
        //Set up the mock driver to be retrieved
        $ILS = $this->getMockILS('Voyager', ['setConfig', 'init']);
        $ILS->expects($this->once())
            ->method('setConfig')
            ->with(['config' => 'values']);
        $ILS->expects($this->once())
            ->method('init');

        $driver = $this->getMultiDriverForDrivers(['testing3' => $ILS]);

        $this->setProperty($driver, 'defaultDriver', 'testing3');
        $methodReturn = $driver->supportsMethod('fail', []);
        $this->assertFalse($methodReturn);

        //Case: No driver info in params, though default driver has method
        //Result: A return of true

        $methodReturn = $driver->supportsMethod('getStatus', []);
        $this->assertTrue($methodReturn);
        $this->setProperty($driver, 'defaultDriver', null);

        //Case: Instance to use is in parameters but does not have method
        //Result: A return of false

        $patron = [$this->getPatron('username', 'testing3')];
        $methodReturn = $driver->supportsMethod('fail', $patron);
        $this->assertFalse($methodReturn);

        //Case: Instance to use is in parameters and has method
        //Result: A return of true

        $methodReturn = $driver->supportsMethod('getStatus', $patron);
        $this->assertTrue($methodReturn);

        //Case: No parameters are given
        //Result: A return of true

        $methodReturn = $driver->supportsMethod('getStatus', []);
        $this->assertTrue($methodReturn);

        //Case: getLoginDrivers and getDefaultLoginDriver are always supported
        //Result: A return of true

        $methodReturn = $driver->supportsMethod('getLoginDrivers', []);
        $this->assertTrue($methodReturn);
        $methodReturn = $driver->supportsMethod('getDefaultLoginDriver', []);
        $this->assertTrue($methodReturn);

        //Case: loginIsHidden is supported when default driver is set and supports
        //it
        //Result: A return of true
        $this->setProperty($driver, 'defaultDriver', 'testing3');
        $methodReturn = $driver->supportsMethod('loginIsHidden', []);
        $this->assertTrue($methodReturn);

        //Case: loginIsHidden is not supported without a default driver
        //Result: A return of false
        $this->setProperty($driver, 'defaultDriver', null);
        $methodReturn = $driver->supportsMethod('loginIsHidden', []);
        $this->assertFalse($methodReturn);
    }

    /**
     * Initialize a MultiBackend driver for a simple method test with three drivers:
     * Voyager, Demo and Dummy that doesn't handle anything
     *
     * @param object $times1   The number of times first driver is expected to be
     * called
     * @param object $times2   The number of times second driver is expected to be
     * called
     * @param string $function Function name
     * @param array  $params   Function parameters
     * @param mixed  $return1  What the function should return with first driver
     * @param mixed  $return2  What the function should return with second driver
     *
     * @return object MultiBackend driver
     */
    protected function initSimpleMethodTest(
        $times1,
        $times2,
        $function,
        $params,
        $return1,
        $return2
    ) {
        $voyager = $this->getMockILS('Voyager', ['init', $function]);
        call_user_func_array(
            [$voyager->expects($times1)->method($function), 'with'],
            $params
        )->will($this->returnValue($return1));

        $voyager2 = $this->getMockILS('Voyager2', ['init', $function]);
        call_user_func_array(
            [$voyager2->expects($times2)->method($function), 'with'],
            $params
        )->will($this->returnValue($return2));

        $dummyILS = new MultiDriverTest\DummyILS();

        return $this->getMultiDriverForDrivers(
            [
                'd1' => $voyager,
                'd2' => $voyager2,
                'd3' => $dummyILS,
            ],
            $this->any()
        );
    }

    /**
     * Method to get an initialized MultiBackend Driver.
     *
     * @param array   $constructorArgs   Optional constructor arguments
     * @param array   $drivers           List of used drivers
     * @param ?string $driversConfigPath Optional driver config path
     *
     * @return mixed A MultiBackend instance.
     */
    protected function initDriver($constructorArgs = [], $drivers = [], $driversConfigPath = null)
    {
        $driver = $this->getDriver($constructorArgs);
        $driver->setConfig(
            [
                'General' => [
                    'drivers_config_path' => $driversConfigPath,
                ],
                'Drivers' => $drivers,
                'Login' => [
                    'drivers' => ['d1', 'd2'],
                    'default_driver' => 'd1',
                ],
            ]
        );
        $driver->init();
        return $driver;
    }

    /**
     * Method to get a raw MultiBackend Driver instance.
     *
     * @param array $constructorArgs Optional constructor arguments
     *
     * @return mixed A MultiBackend instance.
     */
    protected function getDriver($constructorArgs = [])
    {
        $driver = new MultiBackend(
            $constructorArgs['configLoader']
                ?? $this->getMockConfigPluginManager([], ['config' => 'values']),
            $constructorArgs['ilsAuth'] ?? $this->getMockILSAuthenticator(),
            $constructorArgs['driverManager'] ?? $this->getMockSM()
        );
        return $driver;
    }

    /**
     * Get a mock ILS authenticator
     *
     * @param string $userSource Source id, if the authenticator should emulate a
     * situation where a user has logged in. Set to null for the attempt to cause an
     * exception.
     *
     * @return \VuFind\Auth\ILSAuthenticator
     */
    protected function getMockILSAuthenticator($userSource = '')
    {
        $mockAuth = $this->getMockBuilder(\VuFind\Auth\ILSAuthenticator::class)
            ->disableOriginalConstructor()
            ->getMock();
        if ($userSource) {
            $mockAuth->expects($this->any())
                ->method('storedCatalogLogin')
                ->will(
                    $this->returnValue($this->getPatron('username', $userSource))
                );
            $mockAuth->expects($this->any())
                ->method('getStoredCatalogCredentials')
                ->will(
                    $this->returnValue($this->getPatron('username', $userSource))
                );
        } elseif (null === $userSource) {
            $e = new ILSException('Simulated exception from ILSAuthenticator');
            $mockAuth->expects($this->any())
                ->method('storedCatalogLogin')
                ->will(
                    $this->throwException($e)
                );
            $mockAuth->expects($this->any())
                ->method('getStoredCatalogCredentials')
                ->will(
                    $this->throwException($e)
                );
        }
        return $mockAuth;
    }
}
