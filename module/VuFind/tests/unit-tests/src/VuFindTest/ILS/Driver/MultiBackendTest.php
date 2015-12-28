<?php
/**
 * ILS driver test
 *
 * PHP version 5
 *
 * Copyright (C) Villanova University 2011.
 * Copyright (C) The National Library of Finland 2014.
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
 * Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
 *
 * @category VuFind2
 * @package  Tests
 * @author   Kyle McGrogan <km7717@ship.edu>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://www.vufind.org  Main Page
 */
namespace VuFindTest\ILS\Driver;
use VuFind\ILS\Driver\MultiBackend, VuFind\Config\Reader as ConfigReader;
use Zend\Log\Writer\Mock;

/**
 * ILS driver test
 *
 * @category VuFind2
 * @package  Tests
 * @author   Kyle McGrogan <km7717@ship.edu>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://www.vufind.org  Main Page
 */
class MultiBackendTest extends \VuFindTest\Unit\TestCase
{
    /**
     * Test that driver complains about missing configuration.
     *
     * @return void
     */
    public function testMissingConfiguration()
    {
        $this->setExpectedException('VuFind\Exception\ILS');
        $test = new MultiBackend(
            new \VuFind\Config\PluginManager(), $this->getMockILSAuthenticator()
        );
        $test->init();
    }

    /**
     * Test that MultiBackend can be properly initialized.
     *
     * @return void
     */
    public function testInitialization()
    {
        $driver = $this->getDriver();
        $driverList = $this->getProperty($driver, 'drivers');
        $this->assertNotNull($driverList);
    }

    /**
     *  Tests that logging works correctly
     *
     *  @return void
     */
    public function testLogging()
    {
        $logger = new \Zend\Log\Logger();
        $writer = new \Zend\Log\Writer\Mock();
        $logger->addWriter($writer);

        $mockPM = $this->getMock('\VuFind\Config\PluginManager');
        $mockPM->expects($this->any())
            ->method('get')
            ->will(
                $this->throwException(new \Zend\Config\Exception\RuntimeException())
            );
        $driver = new MultiBackend($mockPM, $this->getMockILSAuthenticator());
        $driver->setConfig(['Drivers' => []]);
        $driver->setLogger($logger);
        $driver->init();
        $this->callMethod($driver, 'getDriverConfig', ['bad']);
        $this->assertEquals(
            'VuFind\ILS\Driver\MultiBackend: Could not load config for bad',
            $writer->events[0]['message']
        );

        $this->callMethod($driver, 'getLocalId', ['bad']);
        $this->assertEquals(
            'VuFind\ILS\Driver\MultiBackend: '
            . "Could not find local id in 'bad'",
            $writer->events[1]['message']
        );
    }

    /**
     * Testing method for getSourceFromParams
     *
     * @return void
     */
    public function testGetSourceFromParams()
    {
        $driver = $this->getDriver();

        $drivers = ['d1' => 'Voyager', 'd2' => 'Demo'];
        $this->setProperty($driver, 'drivers', $drivers);

        $result = $this->callMethod($driver, 'getSourceFromParams', ['']);
        $this->assertEquals('', $result);

        $data = [
            'id' => 'record1',
            'cat_username' => 'record2'
        ];
        $result = $this->callMethod($driver, 'getSourceFromParams', [$data]);
        $this->assertEquals('', $result);

        $data = [
            'id' => 'record1',
            'cat_username' => 'd1.record2'
        ];
        $result = $this->callMethod($driver, 'getSourceFromParams', [$data]);
        $this->assertEquals('d1', $result);

        $data = [
            'id' => 'd2.record1',
            'cat_username' => 'record2'
        ];
        $result = $this->callMethod($driver, 'getSourceFromParams', [$data]);
        $this->assertEquals('d2', $result);

        $data = [
            'test' => 'true',
            'patron' => [
                'id' => 'd2.record1',
                'cat_username' => 'record2'
            ]
        ];
        $result = $this->callMethod($driver, 'getSourceFromParams', [$data]);
        $this->assertEquals('d2', $result);
    }

    /**
     * Test that MultiBackend can properly retrieve a new driver
     * Almost the same as testing the Uninitialized driver, we just
     * have to expect it to get initialized.
     *
     * @return void
     */
    public function testGetDriver()
    {
        $driver = $this->getDriver();
        //Set up the mock driver to be retrieved
        $ILS = $this->getMockILS('Voyager', ['init', 'setConfig']);
        $ILS->expects($this->exactly(2))
            ->method('init');
        $ILS->expects($this->once())
            ->method('setConfig')
            ->with(['config' => 'values']);

        //Set up the ServiceLocator so it returns our mock driver
        $sm = $this->getMockSM($this->once(), 'Voyager', $ILS);
        $driver->setServiceLocator($sm);

        //Add an entry for our test driver to the array of drivers
        $drivers = ['testing2' => 'Voyager'];
        $this->setProperty($driver, 'drivers', $drivers);

        $returnDriver = $this->callMethod($driver, 'getDriver', ['testing2']);
        $this->assertEquals($ILS, $returnDriver);

        $this->setProperty($driver, 'isInitialized', []);
        $returnDriver = $this->callMethod($driver, 'getDriver', ['testing2']);
        $this->assertEquals($ILS, $returnDriver);

        $returnDriver
            = $this->callMethod($driver, 'getDriver', ['nonexistent']);
        $this->assertNull($returnDriver);
    }

    /**
     * Test that MultiBackend can properly retrieve an uninitialized
     * driver.
     *
     * @return void
     */
    public function testGetUninitializedDriver()
    {
        $driver = $this->getDriver();
        //Set up the mock driver to be retrieved
        $ILS = $this->getMockILS('Voyager', ['setConfig']);
        $ILS->expects($this->once())
            ->method('setConfig')
            ->with(['config' => 'values']);

        //Set up the ServiceLocator so it returns our mock driver
        $sm = $this->getMockSM($this->once(), 'Voyager', $ILS);
        $driver->setServiceLocator($sm);
        //Add an entry for our test driver to the array of drivers
        $drivers = ['testing' => 'Voyager'];
        $this->setProperty($driver, 'drivers', $drivers);

        //Case: A driver is associated with the given name
            //Result: Return that driver. Cached, but not initialized.
        $unInitDriver = $this->callMethod(
            $driver,
            'getUninitializedDriver',
            ['testing']
        );
        $this->assertEquals($ILS, $unInitDriver);

        //Check the cache arrays to make sure they get set correctly
        $isInit = $this->getProperty($driver, 'isInitialized');
        $cache = $this->getproperty($driver, 'cache');
        $this->assertEquals($ILS, $cache['testing']);
        $this->assertFalse($isInit['testing']);

        //Verify that the cached driver is returned properly
        $unInitDriver = $this->callMethod(
            $driver,
            'getUninitializedDriver',
            ['testing']
        );
        $this->assertEquals($ILS, $unInitDriver);

        //Case: No driver associated with that name exists
            //Result: Return of null
        $unInitDriver = $this->callMethod(
            $driver,
            'getUninitializedDriver',
            ['noDriverWithThisName']
        );
        $this->assertNull($unInitDriver);

        //Case: No configuration for the driver
            //Result: Return of null
        $mockPM = $this->getMock('\VuFind\Config\PluginManager');
        $mockPM->expects($this->any())
            ->method('get')
            ->will(
                $this->throwException(new \Zend\Config\Exception\RuntimeException())
            );
        $driver = new MultiBackend($mockPM, $this->getMockILSAuthenticator());
        $driver->setConfig(['Drivers' => ['d1' => 'Voyager']]);
        $driver->init();
        $unInitDriver = $this->callMethod(
            $driver,
            'getUninitializedDriver',
            ['d1']
        );
        $this->assertNull($unInitDriver);
    }

    /**
     * Test that MultiBackend can properly initialize a driver it
     * is given, and cache it.
     *
     * @return void
     */
    public function testInitializeDriver()
    {
        $driver = $this->getDriver();
        //Set up the mock driver to be initialized.
        $ILS = $this->getMockILS('Voyager', ['init']);
        $ILS->expects($this->once())
            ->method('init');

        //Run the test method
        $this->callMethod($driver, 'initializeDriver', [$ILS, 'test']);

        //Check the cache arrays
        $isInit = $this->getProperty($driver, 'isInitialized');
        $cache = $this->getproperty($driver, 'cache');
        $this->assertSame($ILS, $cache['test']);
        $this->assertTrue($isInit['test']);

        $this->setProperty($driver, 'isInitialized', []);
        $d = new \VuFind\ILS\Driver\Voyager(new \VuFind\Date\Converter());
        $this->setExpectedException('VuFind\Exception\ILS');
        $this->callMethod($driver, 'initializeDriver', [$d, 'fail']);
    }

    /**
     *  Tests that getDriverConfig works correctly
     *
     *  @return void
     */
    public function testGetDriverConfig()
    {
        $configData = ['config' => 'values'];
        $driver = $this->getDriver();
        $val = $this->callMethod($driver, 'getDriverConfig', ['good']);
        $this->assertEquals($configData, $val);

        $config = new \Zend\Config\Config($configData);
        $mockPM = $this->getMock('\VuFind\Config\PluginManager');
        $mockPM->expects($this->any())
            ->method('get')
            ->will(
                $this->throwException(new \Zend\Config\Exception\RuntimeException())
            );
        $driver = new MultiBackend($mockPM, $this->getMockILSAuthenticator());
        $driver->setConfig(['Drivers' => []]);
        $driver->init();
        $val = $this->callMethod($driver, 'getDriverConfig', ['bad']);
        $this->assertEquals([], $val);
    }

    /**
     * Testing method for addIdPrefixes
     *
     * @return void
     */
    public function testAddIdPrefixes()
    {
        $driver = $this->getDriver();
        $source = 'source';
        $data = [];

        $result = $this->callMethod($driver, 'addIdPrefixes', [$data, $source]);
        $this->assertEquals($data, $result);

        $data = [
            'id' => 'record1',
            'cat_username' => 'record2'
        ];
        $expected = [
            'id' => "$source.record1",
            'cat_username' => "$source.record2"
        ];
        $result = $this->callMethod($driver, 'addIdPrefixes', [$data, $source]);
        $this->assertEquals($expected, $result);

        $data = [
            'id' => 'record1',
            'cat_username' => [
                'id' => 'record2',
                'cat_username' => [
                    'id' => 'record3',
                    'cat_username' => 'record4'
                ],
                'cat_info' => 'record5',
                'other' => 'something'
            ],
            'cat_info' => 'record6'
        ];
        $expected = [
            'id' => "$source.record1",
            'cat_username' => [
                'id' => "$source.record2",
                'cat_username' => [
                    'id' => "$source.record3",
                    'cat_username' => "$source.record4"
                ],
                'cat_info' => "$source.record5",
                'other' => 'something'
            ],
            'cat_info' => "$source.record6"
        ];
        $modify = ['id', 'cat_username', 'cat_info'];
        $result = $this->callMethod(
            $driver, 'addIdPrefixes', [$data, $source, $modify]
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
        $driver = $this->getDriver();
        $source = 'source';
        $data = [];

        $result
            = $this->callMethod($driver, 'stripIdPrefixes', [$data, $source]);
        $this->assertEquals($data, $result);

        $data = "$source.record";
        $result
            = $this->callMethod($driver, 'stripIdPrefixes', [$data, $source]);
        $this->assertEquals("record", $result);

        $expected = [
            'id' => 'record1',
            'cat_username' => 'record2'
        ];
        $data = [
            'id' => "$source.record1",
            'cat_username' => "$source.record2"
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
                    'cat_username' => 'record4'
                ],
                'cat_info' => 'record5',
                'other' => "$source.something"
            ],
            'cat_info' => 'record6'
        ];
        $data = [
            'id' => "$source.record1",
            'cat_username' => [
                'id' => "$source.record2",
                'cat_username' => [
                    'id' => "$source.record3",
                    'cat_username' => "$source.record4"
                ],
                'cat_info' => "$source.record5",
                'other' => "$source.something"
            ],
            'cat_info' => "$source.record6"
        ];
        $modify = ['id', 'cat_username', 'cat_info'];
        $result = $this->callMethod(
            $driver, 'stripIdPrefixes', [$data, $source, $modify]
        );
        $this->assertEquals($expected, $result);
    }

    /**
     * Testing method for methodSupported
     *
     * @return void
     */
    public function testMethodSupported()
    {
        $driver = $this->getDriver();
        $voyager = $this->getMockILS('Voyager', ['init']);

        $result = $this->callMethod(
            $driver, 'methodSupported', [$voyager, 'getHolding']
        );
        $this->assertTrue($result);

        $result = $this->callMethod(
            $driver, 'methodSupported', [$voyager, 'INVALIDMETHOD']
        );
        $this->assertFalse($result);

        $dummy = $this->getMockILS('Voyager', ['init', 'supportsMethod']);
        $dummy->expects($this->once())
            ->method('supportsMethod')
            ->with('getHolding')
            ->will($this->returnValue(false));

        $result = $this->callMethod(
            $driver, 'methodSupported', [$dummy, 'getHolding']
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
        $driver = $this->getDriver();
        $drivers = ['d1' => 'Voyager'];
        $this->setProperty($driver, 'drivers', $drivers);
        $id = '123456';

        $ILS = $this->getMockILS('Voyager', ['init', 'getHolding']);
        $ILS->expects($this->exactly(2))
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

        $sm = $this->getMockSM($this->any(), 'Voyager', $ILS);
        $driver->setServiceLocator($sm);

        $expectedReturn = ['id' => 'd1.123456', 'status' => 'in'];
        $return = $driver->getHolding("d1.$id");
        $this->assertEquals($expectedReturn, $return);

        $return = $driver->getHolding("fail.$id");
        $this->assertEquals([], $return);

        $return = $driver->getHolding('d1.654321');
        $this->assertEquals([], $return);
    }

    /**
     * Testing method for getPurchaseHistory
     *
     * @return void
     */
    public function testGetPurchaseHistory()
    {
        $driver = $this->getDriver();
        $drivers = ['d1' => 'Voyager'];
        $this->setProperty($driver, 'drivers', $drivers);
        $id = 'd1.123456';

        $driverReturn = ['purchases' => '123456'];
        $ILS = $this->getMockILS('Voyager', ['init', 'getPurchaseHistory']);
        $ILS->expects($this->once())
            ->method('getPurchaseHistory')
            ->with('123456')
            ->will($this->returnValue($driverReturn));

        $sm = $this->getMockSM($this->any(), 'Voyager', $ILS);
        $driver->setServiceLocator($sm);

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
        $driver = $this->getDriver();

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
        $driver = $this->getDriver();

        $result = $driver->getDefaultLoginDriver();
        $this->assertEquals('d1', $result);

        $driver->setConfig(
            [
                'Drivers' => [],
                'Login' => [
                    'drivers' => ['d2', 'd1']
                ]
            ]
        );

        $result = $driver->getDefaultLoginDriver();
        $this->assertEquals('d2', $result);

        $driver->setConfig(
            [
                'Drivers' => [],
                'Login' => []
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
        $driver = $this->getDriver();
        $drivers = ['d1' => 'Voyager'];
        $this->setProperty($driver, 'drivers', $drivers);

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
                        return $r_arr;
                    }
                )
            );

        $sm = $this->getMockSM($this->any(), 'Voyager', $ILS);
        $driver->setServiceLocator($sm);

        $return = $driver->getStatus('d1.123456');
        $this->assertEquals(['id' => 'd1.123456', 'status' => 'in'], $return);

        $return = $driver->getStatus('d1.654321');
        $this->assertEquals(['id' => 'd1.654321', 'status' => 'out'], $return);

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
        $driver = $this->getDriver();
        $drivers = ['d1' => 'Voyager'];
        $this->setProperty($driver, 'drivers', $drivers);

        $ILS = $this->getMockILS('Voyager', ['init', 'getStatus']);
        $ILS->expects($this->exactly(4))
            ->method('getStatus')
            ->with(
                $this->logicalOr(
                    $this->equalTo('123456'),
                    $this->equalTo('654321'),
                    $this->equalTo('567890'),
                    $this->equalTo('098765')
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
                        } elseif ($param == '567890') {
                            $r_arr['status'] = 'in';
                        } else {
                            $r_arr['status'] = 'out';
                        }
                        return $r_arr;
                    }
                )
            );

        $sm = $this->getMockSM($this->any(), 'Voyager', $ILS);
        $driver->setServiceLocator($sm);

        $ids = [
            'd1.123456', 'd1.098765', 'd1.654321', 'd1.567890'
        ];
        $expectedReturn = [
            ['id' => "d1.123456", 'status' => 'in'],
            ['id' => "d1.098765", 'status' => 'out'],
            ['id' => "d1.654321", 'status' => 'out'],
            ['id' => "d1.567890", 'status' => 'in']
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
        $driver = $this->getDriver();
        $term = "source.local";
        $return = $this->callMethod($driver, 'getLocalId', [$term]);
        $this->assertEquals("local", $return);
    }

    /**
     * Test that MultiBackend can find and use the default ILS driver if parameters
     * don't include a detectable source id
     *
     * @return void
     */
    public function testDefaultDriver()
    {
        $driver = $this->getDriver();
        //Case: The parameters let it know what driver to use
            //Result: return the function results for that driver
        $patron = $this->getPatron('username', 'institution');

        $drivers = [
            'otherinst' => 'Unicorn',
            'institution' => 'Voyager'
        ];
        $this->setProperty($driver, 'drivers', $drivers);

        $patronPrefixless = $this->callMethod(
            $driver, 'stripIdPrefixes', [$patron, 'institution']
        );

        $ILS = $this->getMockILS('Voyager', ['getMyTransactions', 'init']);
        $ILS->expects($this->atLeastOnce())
            ->method('getMyTransactions')
            ->with($patronPrefixless)
            ->will($this->returnValue(true));

        $sm = $this->getMockSM($this->any(), 'Voyager', $ILS);
        $driver->setServiceLocator($sm);

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

        $sm = $this->getMockSM($this->any(), 'Unicorn', $ILS);
        $driver->setServiceLocator($sm);

        $this->setProperty($driver, 'defaultDriver', 'otherinst');
        $returnVal = $driver->getMyTransactions($patron);
        $this->assertTrue($returnVal);
    }

    /**
     * Testing method for getNewItems
     *
     * @return void
     */
    public function testGetNewItems()
    {
        $driver = $this->getDriver();
        $drivers = ['d1' => 'Voyager'];
        $this->setProperty($driver, 'drivers', $drivers);

        $return = [
            'count' => 2,
            'results' => ['id' => '1', 'id' => '2']
        ];

        $ILS = $this->getMockILS('Voyager', ['getNewItems', 'init']);
        $ILS->expects($this->once())
            ->method('getNewItems')
            ->with($this->equalTo('1'), $this->equalTo('10'), $this->equalTo('5'), $this->equalTo('0'))
            ->will($this->returnValue($return));

        $sm = $this->getMockSM($this->any(), 'Voyager', $ILS);
        $driver->setServiceLocator($sm);

        // getNewItems only works with a default driver, so the first calls fails
        $result = $driver->getNewItems(1, 10, 5, 0);
        $this->assertEquals([], $result);

        $expected = [
            'count' => 2,
            'results' => ['id' => 'd1.1', 'id' => 'd1.2']
        ];
        $this->setProperty($driver, 'defaultDriver', 'd1');
        $result = $driver->getNewItems(1, 10, 5, 0);
        $this->assertEquals($expected, $result);
    }

    /**
     * Testing method for getCourses
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

        // getCourses  only works with a default driver, so the first calls fails
        $result = $driver->getCourses();
        $this->assertEquals([], $result);

        $this->setProperty($driver, 'defaultDriver', 'd1');
        $result = $driver->getCourses();
        $this->assertEquals($expected, $result);
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

        // getCourses  only works with a default driver, so the first calls fails
        $result = $driver->getDepartments();
        $this->assertEquals([], $result);

        $this->setProperty($driver, 'defaultDriver', 'd1');
        $result = $driver->getDepartments();
        $this->assertEquals($expected, $result);
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

        // getCourses  only works with a default driver, so the first calls fails
        $result = $driver->getInstructors();
        $this->assertEquals([], $result);

        $this->setProperty($driver, 'defaultDriver', 'd1');
        $result = $driver->getInstructors();
        $this->assertEquals($expected, $result);
    }

    /**
     * Testing method for findReserves
     *
     * @return void
     */
    public function testFindReserves()
    {
        $driver = $this->getDriver();
        $drivers = ['d1' => 'Voyager'];
        $this->setProperty($driver, 'drivers', $drivers);
        $id = '123456';

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
            ]
        ];

        $ILS = $this->getMockILS('Voyager', ['findReserves', 'init']);
        $ILS->expects($this->once())
            ->method('findReserves')
            ->with($this->equalTo('course'), $this->equalTo('inst'), $this->equalTo('dept'))
            ->will($this->returnValue($reservesReturn));

        $sm = $this->getMockSM($this->any(), 'Voyager', $ILS);
        $driver->setServiceLocator($sm);

        // findReserves only works with a default driver, so the first calls fails
        $result = $driver->findReserves('course', 'inst', 'dept');
        $this->assertEquals([], $result);

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
        $driver = $this->getDriver();
        $patronReturn = $this->getPatron('username');
        $instance = 'institution';

        //Set up the mock object and prepare its expectations
        $ILS = $this->getMockILS('Voyager', ['patronLogin']);
        $ILS->expects($this->at(0))
            ->method('patronLogin')
            ->with('username', 'password')
            ->will($this->returnValue($patronReturn));

        //Prep MultiBackend with values it will need
        $drivers = [$instance => 'Voyager'];
        $isInit = [$instance => true];
        $cache = [$instance => $ILS];
        $this->setProperty($driver, 'drivers', $drivers);
        $this->setProperty($driver, 'isInitialized', $isInit);
        $this->setProperty($driver, 'cache', $cache);

        //Call the method
        $patron = $driver->patronLogin("$instance.username", 'password');

        //Check that it added username info properly.
        $this->assertSame(
            $instance . '.' . $patronReturn['cat_username'],
            $patron['cat_username']
        );

        $this->setExpectedException('VuFind\Exception\ILS');
        $driver->patronLogin("bad", "info");
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

        $this->setExpectedException(
            'VuFind\Exception\ILS', 'No suitable backend driver found'
        );
        $result = $driver->getMyTransactions(
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
                'id' => 'd1.loanid'
            ]
        );
        $this->assertEquals($expected1, $result);

        $result = $driver->getRenewDetails(
            [
                'id' => 'd2.loanid'
            ]
        );
        $this->assertEquals($expected2, $result);

        $this->setExpectedException(
            'VuFind\Exception\ILS', 'No suitable backend driver found'
        );
        $result = $driver->getRenewDetails(
            [
                'id' => 'invalid.loanid'
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
            ['id' => 'd1.2']
        ];
        $expected2 = [
            ['id' => 'd2.1'],
            ['id' => 'd2.2']
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

        $this->setExpectedException(
            'VuFind\Exception\ILS', 'No suitable backend driver found'
        );
        $result = $driver->renewMyItems(
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

        $this->setExpectedException(
            'VuFind\Exception\ILS', 'No suitable backend driver found'
        );
        $result = $driver->getMyFines($this->getPatron('username', 'invalid'));
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

        $this->setExpectedException(
            'VuFind\Exception\ILS', 'No suitable backend driver found'
        );
        $result = $driver->getMyHolds($this->getPatron('username', 'invalid'));
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

        $this->setExpectedException(
            'VuFind\Exception\ILS', 'No suitable backend driver found'
        );
        $result = $driver->getMyStorageRetrievalRequests(
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
        $expected1 = true;
        $expected2 = false;
        $driver = $this->initSimpleMethodTest(
            $this->once(),
            $this->once(),
            'checkRequestIsValid',
            [
                'bibid',
                ['id' => 'itemid'],
                $this->getPatron('username')
            ],
            true,
            false
        );

        $result = $driver->checkRequestIsValid(
            'd1.bibid',
            [
                'id' => 'd1.itemid'
            ],
            $this->getPatron('username', 'd1')
        );
        $this->assertEquals($expected1, $result);

        $result = $driver->checkRequestIsValid(
            'd2.bibid',
            [
                'id' => 'd2.itemid'
            ],
            $this->getPatron('username', 'd2')
        );
        $this->assertEquals($expected2, $result);

        // Cross-driver request must not be accepted
        $result = $driver->checkRequestIsValid(
            'd1.bibid',
            [
                'id' => 'd1.itemid'
            ],
            $this->getPatron('username', 'd2')
        );
        $this->assertFalse($result);

        // Request with a patron missing cat_username must not be accepted
        $result = $driver->checkRequestIsValid(
            'd1.bibid',
            [
                'id' => 'd1.itemid'
            ],
            ['bad patron']
        );
        $this->assertFalse($result);

        $result = $driver->checkRequestIsValid(
            'invalid.bibid',
            [
                'id' => 'invalid.itemid'
            ],
            $this->getPatron('username', 'invalid')
        );
        $this->assertFalse($result);
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
                $this->getPatron('username')
            ],
            true,
            false
        );

        $result = $driver->checkStorageRetrievalRequestIsValid(
            'd1.bibid',
            [
                'id' => 'd1.itemid'
            ],
            $this->getPatron('username', 'd1')
        );
        $this->assertEquals($expected1, $result);

        $result = $driver->checkStorageRetrievalRequestIsValid(
            'd2.bibid',
            [
                'id' => 'd2.itemid'
            ],
            $this->getPatron('username', 'd2')
        );
        $this->assertEquals($expected2, $result);

        // Cross-driver request must not be accepted
        $result = $driver->checkStorageRetrievalRequestIsValid(
            'd1.bibid',
            [
                'id' => 'd1.itemid'
            ],
            $this->getPatron('username', 'd2')
        );
        $this->assertFalse($result);

        $result = $driver->checkStorageRetrievalRequestIsValid(
            'invalid.bibid',
            [
                'id' => 'invalid.itemid'
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
                ['id' => '1']
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

        $this->setExpectedException(
            'VuFind\Exception\ILS', 'No suitable backend driver found'
        );
        $result = $driver->getPickUpLocations(
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
                ['id' => '1']
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

        $this->setExpectedException(
            'VuFind\Exception\ILS', 'No suitable backend driver found'
        );
        $result = $driver->getDefaultPickUpLocation(
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
                $this->getPatron('username')
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

        $this->setExpectedException(
            'VuFind\Exception\ILS', 'No suitable backend driver found'
        );
        $result = $driver->getRequestGroups(
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
                ['id' => '1']
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

        $this->setExpectedException(
            'VuFind\Exception\ILS', 'No suitable backend driver found'
        );
        $result = $driver->getDefaultRequestGroup(
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
            'status' => ''
        ];
        $expected2 = [
            'success' => false,
            'status' => 'hold_error_fail'
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
                'id' => 'd1.1'
            ]
        );
        $this->assertEquals($expected1, $result);

        $result = $driver->placeHold(
            [
                'patron' => $this->getPatron('username', 'd2'),
                'id' => 'd2.1'
            ]
        );
        $this->assertEquals($expected2, $result);

        // Patron/item source mismatch
        $result = $driver->placeHold(
            [
                'patron' => $this->getPatron('username', 'd2'),
                'id' => 'd1.1'
            ]
        );
        $this->assertEquals(
            ['success' => false, 'sysMessage' => 'hold_wrong_user_institution'],
            $result
        );

        $this->setExpectedException(
            'VuFind\Exception\ILS', 'No suitable backend driver found'
        );
        $result = $driver->placeHold(
            [
                'patron' => $this->getPatron('username', 'invalid'),
                'id' => 'invalid.1'
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
                'status' => 'hold_cancel_success'
            ],
            '2' => [
                'success' => false,
                'status' => 'hold_cancel_fail'
            ]

        ];
        $driver = $this->initSimpleMethodTest(
            $this->once(),
            $this->once(),
            'cancelHolds',
            [
                [
                    'patron' => $this->getPatron('username'),
                    'details' => ['1', '2']
                ]
            ],
            $expected,
            $expected
        );

        $result = $driver->cancelHolds(
            [
                'patron' => $this->getPatron('username', 'd1'),
                'details' => ['1', '2']
            ]
        );
        $this->assertEquals($expected, $result);

        $result = $driver->cancelHolds(
            [
                'patron' => $this->getPatron('username', 'd2'),
                'details' => ['1', '2']
            ]
        );
        $this->assertEquals($expected, $result);

        $this->setExpectedException(
            'VuFind\Exception\ILS', 'No suitable backend driver found'
        );
        $result = $driver->cancelHolds(
            [
                'patron' => $this->getPatron('username', 'invalid'),
                'details' => ['1', '2']
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
            $this->once(),
            'getCancelHoldDetails',
            [['id' => '1', 'item_id' => '2']],
            $expected,
            $expected
        );

        $result = $driver->getCancelHoldDetails(
            ['id' => 'd1.1', 'item_id' => 2]
        );
        $this->assertEquals($expected, $result);

        $result = $driver->getCancelHoldDetails(
            ['id' => 'd2.1', 'item_id' => 2]
        );
        $this->assertEquals($expected, $result);

        $this->setExpectedException(
            'VuFind\Exception\ILS', 'No suitable backend driver found'
        );
        $result = $driver->getCancelHoldDetails(
            ['id' => 'invalid.1', 'item_id' => 2]
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
            'status' => ''
        ];
        $expected2 = [
            'success' => false,
            'status' => 'storage_retrieval_request_error_blocked'
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
                'id' => 'd1.1'
            ]
        );
        $this->assertEquals($expected1, $result);

        $result = $driver->placeStorageRetrievalRequest(
            [
                'patron' => $this->getPatron('username', 'd2'),
                'id' => 'd2.1'
            ]
        );
        $this->assertEquals($expected2, $result);

        // Patron/item source mismatch
        $result = $driver->placeStorageRetrievalRequest(
            [
                'patron' => $this->getPatron('username', 'd2'),
                'id' => 'd1.1'
            ]
        );
        $this->assertEquals(
            ['success' => false, 'sysMessage' => 'hold_wrong_user_institution'],
            $result
        );

        $this->setExpectedException(
            'VuFind\Exception\ILS', 'No suitable backend driver found'
        );
        $result = $driver->placeStorageRetrievalRequest(
            [
                'patron' => $this->getPatron('username', 'invalid'),
                'id' => 'invalid.1'
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
                'status' => 'storage_retrieval_request_cancel_success'
            ],
            '2' => [
                'success' => false,
                'status' => 'storage_retrieval_request_cancel_fail'
            ]

        ];
        $driver = $this->initSimpleMethodTest(
            $this->once(),
            $this->once(),
            'cancelStorageRetrievalRequests',
            [
                [
                    'patron' => $this->getPatron('username'),
                    'details' => ['1', '2']
                ]
            ],
            $expected,
            $expected
        );

        $result = $driver->cancelStorageRetrievalRequests(
            [
                'patron' => $this->getPatron('username', 'd1'),
                'details' => ['1', '2']
            ]
        );
        $this->assertEquals($expected, $result);

        $result = $driver->cancelStorageRetrievalRequests(
            [
                'patron' => $this->getPatron('username', 'd2'),
                'details' => ['1', '2']
            ]
        );
        $this->assertEquals($expected, $result);

        $this->setExpectedException(
            'VuFind\Exception\ILS', 'No suitable backend driver found'
        );
        $result = $driver->cancelStorageRetrievalRequests(
            [
                'patron' => $this->getPatron('username', 'invalid'),
                'details' => ['1', '2']
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
            ['id' => 'd1.1', 'item_id' => 2]
        );
        $this->assertEquals($expected, $result);

        $result = $driver->getCancelStorageRetrievalRequestDetails(
            ['id' => 'd2.1', 'item_id' => 2]
        );
        $this->assertEquals($expected, $result);

        $this->setExpectedException(
            'VuFind\Exception\ILS', 'No suitable backend driver found'
        );
        $result = $driver->getCancelStorageRetrievalRequestDetails(
            ['id' => 'invalid.1', 'item_id' => 2]
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
                )
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

        $this->setExpectedException(
            'VuFind\Exception\ILS', 'No suitable backend driver found'
        );
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
                )
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

        $this->setExpectedException(
            'VuFind\Exception\ILS', 'No suitable backend driver found'
        );
        $result = $driver->getILLPickupLibraries(
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
                )
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

        $this->setExpectedException(
            'VuFind\Exception\ILS', 'No suitable backend driver found'
        );
        $result = $driver->getILLPickupLocations(
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
            'status' => ''
        ];
        $expected2 = [
            'success' => false,
            'status' => 'ill_request_error_fail'
        ];
        $driver = $this->initSimpleMethodTest(
            $this->once(),
            $this->once(),
            'placeILLRequest',
            [
                $this->logicalOr(
                    [
                        'patron' => $this->getPatron('username', 'd1'),
                        'id' => 1
                    ],
                    [
                        'patron' => $this->getPatron('username', 'd2'),
                        'id' => 1
                    ]
                )
            ],
            $expected1,
            $expected2
        );

        $result = $driver->placeILLRequest(
            [
                'patron' => $this->getPatron('username', 'd1'),
                'id' => 'd1.1'
            ]
        );
        $this->assertEquals($expected1, $result);

        $result = $driver->placeILLRequest(
            [
                'patron' => $this->getPatron('username', 'd2'),
                'id' => 'd2.1'
            ]
        );
        $this->assertEquals($expected2, $result);

        $this->setExpectedException(
            'VuFind\Exception\ILS', 'No suitable backend driver found'
        );
        $result = $driver->placeILLRequest(
            [
                'patron' => $this->getPatron('username', 'invalid'),
                'id' => 'invalid.1'
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

        $this->setExpectedException(
            'VuFind\Exception\ILS', 'No suitable backend driver found'
        );
        $result = $driver->getMyILLRequests(
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
                'status' => 'ill_request_cancel_success'
            ],
            '2' => [
                'success' => false,
                'status' => 'storage_retrieval_request_cancel_fail'
            ]

        ];
        $driver = $this->initSimpleMethodTest(
            $this->once(),
            $this->once(),
            'cancelILLRequests',
            [
                [
                    'patron' => $this->getPatron('username'),
                    'details' => ['1', '2']
                ]
            ],
            $expected,
            $expected
        );

        $result = $driver->cancelILLRequests(
            [
                'patron' => $this->getPatron('username', 'd1'),
                'details' => ['1', '2']
            ]
        );
        $this->assertEquals($expected, $result);

        $result = $driver->cancelILLRequests(
            [
                'patron' => $this->getPatron('username', 'd2'),
                'details' => ['1', '2']
            ]
        );
        $this->assertEquals($expected, $result);

        $this->setExpectedException(
            'VuFind\Exception\ILS', 'No suitable backend driver found'
        );
        $result = $driver->cancelILLRequests(
            [
                'patron' => $this->getPatron('username', 'invalid'),
                'details' => ['1', '2']
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
            ['id' => 'd1.1', 'item_id' => 2]
        );
        $this->assertEquals($expected, $result);

        $result = $driver->getCancelILLRequestDetails(
            ['id' => 'd2.1', 'item_id' => 2]
        );
        $this->assertEquals($expected, $result);

        $this->setExpectedException(
            'VuFind\Exception\ILS', 'No suitable backend driver found'
        );
        $result = $driver->getCancelILLRequestDetails(
            ['id' => 'invalid.1', 'item_id' => 2]
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
                        ['patron' => $this->getPatron('123456')]
                    ]
                )
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

    }

    /**
     * Test that MultiBackend can properly tell whether or not
     * a driver is has contains a specified method.
     *
     * @return void
     */
    public function testSupportsMethod()
    {
        $driver = $this->getDriver();
        //Set up the mock driver to be retrieved
        $ILS = $this->getMockILS('Voyager', ['setConfig', 'init']);
        $ILS->expects($this->once())
            ->method('setConfig')
            ->with(['config' => 'values']);
        $ILS->expects($this->once())
            ->method('init');

        //Set up the ServiceLocator so it returns our mock driver
        $sm = $this->getMockSM($this->once(), 'Voyager', $ILS);
        $driver->setServiceLocator($sm);

        //Add an entry for our test driver to the array of drivers
        $drivers = ['testing3' => 'Voyager'];
        $this->setProperty($driver, 'drivers', $drivers);

        //Case: No driver info in params, but default driver has method
            //Result: A return of false

        $this->setProperty($driver, 'defaultDriver', 'testing3');
        $methodReturn = $driver->supportsMethod('fail', null);
        $this->assertFalse($methodReturn);

        //Case: No driver info in params, though default driver has method
            //Result: A return of true

        $methodReturn = $driver->supportsMethod('getStatus', null);
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

        $methodReturn = $driver->supportsMethod('getStatus', null);
        $this->assertTrue($methodReturn);

        //Case: getLoginDrivers and getDefaultLoginDriver are always supported
            //Result: A return of true

        $methodReturn = $driver->supportsMethod('getLoginDrivers', null);
        $this->assertTrue($methodReturn);
        $methodReturn = $driver->supportsMethod('getDefaultLoginDriver', null);
        $this->assertTrue($methodReturn);
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
        $times1, $times2, $function, $params, $return1, $return2
    ) {
        $driver = $this->getDriver();
        $drivers = ['d1' => 'Voyager', 'd2' => 'Demo', 'd3' => 'DummyILS'];
        $this->setProperty($driver, 'drivers', $drivers);

        $voyager = $this->getMockILS('Voyager', ['init', $function]);
        call_user_func_array(
            [$voyager->expects($times1)->method($function), 'with'], $params
        )->will($this->returnValue($return1));

        $demo = $this->getMockILS('Demo', ['init', $function]);
        call_user_func_array(
            [$demo->expects($times2)->method($function), 'with'], $params
        )->will($this->returnValue($return2));

        $dummyILS = new DummyILS();

        $sm = $this->getMockForAbstractClass(
            'Zend\ServiceManager\ServiceLocatorInterface'
        );
        $sm->expects($this->any())
            ->method('get')
            ->with($this->logicalOr('Voyager', 'Demo', 'DummyILS'))
            ->will(
                $this->returnCallback(
                    function ($param) use ($voyager, $demo, $dummyILS) {
                        if ($param == 'Voyager') {
                            return $voyager;
                        } else if ($param == 'Demo') {
                            return $demo;
                        } else if ($param == 'DummyILS') {
                            return $dummyILS;
                        }
                        return null;
                    }
                )
            );
        $driver->setServiceLocator($sm);

        return $driver;
    }

    /**
     * Method to get a fresh MultiBackend Driver.
     *
     * @return mixed A MultiBackend instance.
     */
    protected function getDriver()
    {
        $driver = new MultiBackend(
            $this->getPluginManager(), $this->getMockILSAuthenticator()
        );
        $driver->setConfig(
            [
                'Drivers' => [],
                'Login' => [
                    'drivers' => ['d1', 'd2'],
                    'default_driver' => 'd1'
                ],
            ]
        );
        $driver->init();
        return $driver;
    }

    /**
     * Get a mock ILS authenticator
     *
     * @param bool $userSource Source id, if the authenticator should emulate a
     * situation where a user has logged in
     *
     * @return \VuFind\Auth\ILSAuthenticator
     */
    protected function getMockILSAuthenticator($userSource = '')
    {
        $mockAuth = $this->getMockBuilder('VuFind\Auth\ILSAuthenticator')
            ->disableOriginalConstructor()
            ->getMock();
        if ($userSource) {
            $mockAuth->expects($this->any())
                ->method('storedCatalogLogin')
                ->will(
                    $this->returnValue($this->getPatron('username', $userSource))
                );
        }
        return $mockAuth;
    }

    /**
     * Method to get a fresh Plugin Manager.
     *
     * @return mixed A MultiBackend instance.
     */
    protected function getPluginManager()
    {
        $configData = ['config' => 'values'];
        $config = new \Zend\Config\Config($configData);
        $mockPM = $this->getMock('\VuFind\Config\PluginManager');
        $mockPM->expects($this->any())
            ->method('get')
            ->will($this->returnValue($config));
        return $mockPM;
    }

    /**
     * Method to get a patron with the given username
     *
     * @param string $username The username to use
     * @param string $instance The instance to append before the username
     *
     * @return array A patron array.
     */
    protected function getPatron($username, $instance = null)
    {
        $cat_username = $instance ? $instance . '.' . $username : $username;
        return [
                    'id' => 1,
                    'firstname' => 'JANE',
                    'lastname' => 'DOE',
                    'cat_username' => $cat_username,
                    'cat_password' => 'password',
                    'email' => '',
                    'major' => '',
                    'college' => ''
        ];
    }

    /**
     * This function returns a mock service manager with the given parameters
     * For examples of what is to be passed, see:
     * http://www.phpunit.de/manual/3.0/en/mock-objects.html
     *
     * @param object $times  The number of times it is expected to be called.
     * @param object $driver The driver type this SM will expect to be called with.
     * @param mixed  $return What that get function should return.
     *
     * @return object The Mock Service Manager created.
     */
    protected function getMockSM($times, $driver, $return)
    {
        $sm = $this->getMockForAbstractClass(
            'Zend\ServiceManager\ServiceLocatorInterface'
        );
        $sm->expects($times)
            ->method('get')
            ->with($driver)
            ->will($this->returnValue($return));
        return $sm;
    }

    /**
     * Get a mock driver
     *
     * @param string $type    Type of driver to make
     * @param array  $methods Array of methods to stub
     *
     * @return \VuFind\ILS\Driver\$type
     */
    protected function getMockILS($type, $methods = null)
    {
        $mock = null;
        try {
            if ($type == 'Demo') {
                $mock = $this->getMock(
                    "VuFind\ILS\Driver\\$type", $methods,
                    [
                        new \VuFind\Date\Converter(),
                        $this->getMock('VuFindSearch\Service')
                    ]
                );
            } else {
                $mock = $this->getMock(
                    "VuFind\ILS\Driver\\$type", $methods,
                    [new \VuFind\Date\Converter()]
                );
            }
        } catch(\Exception $e) {
            $mock = $this->getMock(
                "VuFind\ILS\Driver\\$type", $methods
            );
        }
        if ($methods && in_array('init', $methods)) {
            $mock->expects($this->any())
                ->method('init')
                ->will($this->returnValue(null));
        }
        $mock->setConfig(['dummy_config' => true]);
        return $mock;
    }
}

/**
 * A dummy ILS driver used for testing a driver with unsupported features
 *
 * @category VuFind2
 * @package  Tests
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://www.vufind.org  Main Page
 */
class DummyILS extends \VuFind\ILS\Driver\AbstractBase
{
    /**
     * Initialize the driver.
     *
     * Validate configuration and perform all resource-intensive tasks needed to
     * make the driver active.
     *
     * @return void
     */
    public function init()
    {
        return;
    }

    /**
     * Get Status
     *
     * This is responsible for retrieving the status information of a certain
     * record.
     *
     * @param string $id The record id to retrieve the holdings for
     *
     * @throws \VuFind\Exception\ILS
     * @return mixed     On success, an associative array with the following keys:
     * id, availability (boolean), status, location, reserve, callnumber.
     */
    public function getStatus($id)
    {
        return [];
    }

    /**
     * Get Statuses
     *
     * This is responsible for retrieving the status information for a
     * collection of records.
     *
     * @param array $ids The array of record ids to retrieve the status for
     *
     * @throws \VuFind\Exception\ILS
     * @return array     An array of getStatus() return values on success.
     */
    public function getStatuses($ids)
    {
        return [];
    }

    /**
     * Get Holding
     *
     * This is responsible for retrieving the holding information of a certain
     * record.
     *
     * @param string $id     The record id to retrieve the holdings for
     * @param array  $patron Patron data
     *
     * @throws \VuFind\Exception\ILS
     * @return array         On success, an associative array with the following
     * keys: id, availability (boolean), status, location, reserve, callnumber,
     * duedate, number, barcode.
     */
    public function getHolding($id, array $patron = null)
    {
        return [];
    }

    /**
     * Get Purchase History
     *
     * This is responsible for retrieving the acquisitions history data for the
     * specific record (usually recently received issues of a serial).
     *
     * @param string $id The record id to retrieve the info for
     *
     * @throws \VuFind\Exception\ILS
     * @return array     An array with the acquisitions data on success.
     */
    public function getPurchaseHistory($id)
    {
        return [];
    }
}
