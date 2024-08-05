<?php

/**
 * Abstract multi driver test
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
 * @author   Thomas Wagener <wagener@hebis.uni-frankfurt.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */

namespace VuFindTest\ILS\Driver;

use Laminas\Config\Exception\RuntimeException;
use VuFind\ILS\Driver\AbstractMultiDriver;

use function call_user_func_array;
use function count;
use function in_array;

/**
 * Abstract multi driver test
 *
 * @category VuFind
 * @package  Tests
 * @author   Kyle McGrogan <km7717@ship.edu>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @author   Thomas Wagener <wagener@hebis.uni-frankfurt.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */
abstract class AbstractMultiDriverTestCase extends \PHPUnit\Framework\TestCase
{
    use \VuFindTest\Feature\ConfigPluginManagerTrait;
    use \VuFindTest\Feature\ReflectionTrait;

    /**
     * Test that the driver can be properly initialized.
     *
     * @return void
     */
    public function testInitialization()
    {
        $driver = $this->initDriver();
        $driverList = $this->getProperty($driver, 'drivers');
        $this->assertNotNull($driverList);
    }

    /**
     * Test that driver complains about missing configuration.
     *
     * @return void
     */
    public function testMissingConfiguration()
    {
        $this->expectException(\VuFind\Exception\ILS::class);

        $container = new \VuFindTest\Container\MockContainer($this);
        $test = $this->getDriver(
            [
                'configLoader' => new \VuFind\Config\PluginManager($container),
                'driverManager' =>  $this->getMockSM($this->never()),
            ]
        );
        $test->init();
    }

    /**
     * Test that driver handles ILS driver configuration loading properly when
     * drivers_config_path is not defined.
     *
     * @return void
     */
    public function testILSConfigurationPathWithoutDriverConfigPath()
    {
        $mockPM = $this->getMockConfigPluginManager(
            ['d1' => ['config' => 'values']],
            [],
            $this->once()
        );
        $ils = $this->getMockILS('Voyager');
        $driver = $this->initDriver(
            [
                'configLoader' => $mockPM,
                'driverManager' => $this->getMockSM(null, 'Voyager', $ils),
            ],
            ['d1' => 'Voyager']
        );

        $driver->getStatus('d1.123');
    }

    /**
     * Test that driver handles ILS driver configuration loading properly when
     * drivers_config_path is defined.
     *
     * @return void
     */
    public function testILSConfigurationPathWithDriverConfigPath()
    {
        $mockPM = $this->getMockConfigPluginManager(
            ['configpath/d1' => ['config' => 'values']],
            [],
            $this->once()
        );
        $ils = $this->getMockILS('Voyager');
        $driver = $this->initDriver(
            [
                'configLoader' => $mockPM,
                'driverManager' => $this->getMockSM(null, 'Voyager', $ils),
            ],
            ['d1' => 'Voyager'],
            'configpath'
        );

        $driver->getStatus('d1.123');
    }

    /**
     *  Tests that logging works correctly
     *
     * @return array
     */
    public function testLogging()
    {
        $logger = new \Laminas\Log\Logger();
        $writer = new \Laminas\Log\Writer\Mock();
        $logger->addWriter($writer);

        $driver = $this->initDriver(
            [
                'configLoader' => $this->getMockFailingConfigPluginManager(new RuntimeException()),
            ]
        );
        $driver->setLogger($logger);

        $this->callMethod($driver, 'getDriverConfig', ['bad']);
        $this->assertEquals(
            $driver::class . ': Could not load config for bad',
            $writer->events[0]['message']
        );

        return ['driver' => $driver, 'writer' => $writer];
    }

    /**
     * Test that MultiBackend can properly retrieve a new driver.
     *
     * @return void
     */
    public function testGetDriver()
    {
        //Set up the mock driver to be retrieved
        $ILS = $this->getMockILS('Voyager', ['init', 'setConfig']);
        $ILS->expects($this->once())
            ->method('init');
        $ILS->expects($this->once())
            ->method('setConfig')
            ->with(['config' => 'values']);

        //Set up the ServiceLocator so it returns our mock driver
        $driver = $this->initDriver(
            ['driverManager' => $this->getMockSM($this->once(), 'Voyager', $ILS)]
        );

        //Add an entry for our test driver to the array of drivers
        $drivers = ['testing2' => 'Voyager'];
        $this->setProperty($driver, 'drivers', $drivers);

        $returnDriver = $this->callMethod($driver, 'getDriver', ['testing2']);
        $this->assertEquals($ILS, $returnDriver);

        $returnDriver = $this->callMethod($driver, 'getDriver', ['nonexistent']);
        $this->assertNull($returnDriver);
    }

    /**
     *  Tests that getDriverConfig works correctly
     *
     * @return void
     */
    public function testGetDriverConfig()
    {
        $configData = ['config' => 'values'];
        $driver = $this->initDriver();
        $val = $this->callMethod($driver, 'getDriverConfig', ['good']);
        $this->assertEquals($configData, $val);

        $driver = $this->initDriver(
            ['configLoader' => $this->getMockFailingConfigPluginManager(new RuntimeException())]
        );
        $val = $this->callMethod($driver, 'getDriverConfig', ['bad']);
        $this->assertEquals([], $val);
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
            'college' => '',
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
    protected function getMockSM($times = null, $driver = 'Voyager', $return = null)
    {
        $sm = $this->getMockBuilder(\VuFind\ILS\Driver\PluginManager::class)
            ->disableOriginalConstructor()->getMock();
        $sm->expects($times ?? $this->any())
            ->method('get')
            ->with($driver)
            ->will($this->returnValue($return));
        return $sm;
    }

    /**
     * Get a mock Demo driver
     *
     * @return \VuFind\ILS\Driver\Demo
     */
    protected function getMockDemoDriver()
    {
        $session = $this->getMockBuilder(\Laminas\Session\Container::class)
            ->disableOriginalConstructor()->getMock();
        return $this->getMockBuilder(__NAMESPACE__ . '\MultiDriverTest\DemoMock')
            ->setConstructorArgs(
                [
                    new \VuFind\Date\Converter(),
                    $this->createMock(\VuFindSearch\Service::class),
                    function () use ($session) {
                        return $session;
                    },
                ]
            )->getMock();
    }

    /**
     * Get a mock driver
     *
     * @param string $type    Type of driver to make
     * @param array  $methods Array of methods to stub
     *
     * @return \VuFind\ILS\Driver\AbstractBase
     */
    protected function getMockILS($type, $methods = null)
    {
        if ($methods && in_array('supportsMethod', $methods)) {
            $mock = $this
                ->getMockBuilder(__NAMESPACE__ . '\\MultiDriverTest\\' . $type . 'NoSupportMock')
                ->onlyMethods($methods)
                ->setConstructorArgs([new \VuFind\Date\Converter()])
                ->getMock();
        } elseif ($type == 'Demo') {
            $mock = $this->getMockDemoDriver();
        } else {
            $class = __NAMESPACE__ . '\\MultiDriverTest\\' . $type . 'Mock';
            $mock = $this->getMockBuilder($class)
                ->setConstructorArgs([new \VuFind\Date\Converter()])
                ->getMock();
        }
        if ($methods && in_array('init', $methods)) {
            $mock->expects($this->any())
                ->method('init')
                ->will($this->returnValue(null));
        }
        $mock->setConfig(['dummy_config' => true]);
        return $mock;
    }

    /**
     * Method to get an initialized Multi Driver.
     *
     * @param array   $constructorArgs   Optional constructor arguments
     * @param array   $drivers           List of used drivers
     * @param ?string $driversConfigPath Optional driver config path
     *
     * @return mixed A MultiBackend instance.
     */
    abstract protected function initDriver($constructorArgs = [], $drivers = [], $driversConfigPath = null);

    /**
     * Method to get a raw Multi Driver instance.
     *
     * @param array $constructorArgs Optional constructor arguments
     *
     * @return mixed A Multi Driver instance.
     */
    abstract protected function getDriver($constructorArgs = []);

    /**
     * Create a Multi Driver for the given ILS drivers
     *
     * @param array $drivers Array of drivers with prefix as key and driver instance
     * as value
     * @param mixed $count   How many drivers are expected to be used. Default is
     * that all defined drivers are to be used.
     *
     * @return AbstractMultiDriver
     */
    protected function getMultiDriverForDrivers($drivers, $count = null)
    {
        $driverMap = [];
        $driverNameMap = [];
        $i = 0;
        foreach ($drivers as $name => $driver) {
            $i++;
            $driverName = "Driver$i";
            $driverMap[$driverName] = $driver;
            $driverNameMap[$name] = $driverName;
        }
        $sm = $this->getMockBuilder(\VuFind\ILS\Driver\PluginManager::class)
            ->disableOriginalConstructor()->getMock();
        // MultiBackend should always ask for a driver just once, so exactly can be
        // used here:
        $sm->expects(null !== $count ? $count : $this->exactly(count($driverMap)))
            ->method('get')
            ->with(
                call_user_func_array([$this, 'logicalOr'], array_keys($driverMap))
            )
            ->will(
                $this->returnCallback(
                    function ($driver) use ($driverMap) {
                        return $driverMap[$driver];
                    }
                )
            );

        $driver = $this->initDriver(['driverManager' => $sm]);
        $this->setProperty($driver, 'drivers', $driverNameMap);

        return $driver;
    }
}
