<?php

/**
 * UserIpReaderFactory Test Class
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2020.
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
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */

namespace VuFindTest\Net;

use Laminas\Stdlib\Parameters;
use VuFind\Net\UserIpReaderFactory;

use function func_get_args;

/**
 * UserIpReaderFactory Test Class
 *
 * @category VuFind
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */
class UserIpReaderFactoryTest extends \PHPUnit\Framework\TestCase
{
    use \VuFindTest\Feature\ConfigPluginManagerTrait;

    /**
     * Get a container set up for the factory.
     *
     * @param array $config Configuration (simulated config.ini)
     * @param array $server Simulated $_SERVER superglobal data
     *
     * @return \VuFindTest\Container\MockContainer
     */
    protected function getContainer($config = [], $server = ['server' => true]): \VuFindTest\Container\MockContainer
    {
        $container = new \VuFindTest\Container\MockContainer($this);
        $container->set(
            \VuFind\Config\PluginManager::class,
            $this->getMockConfigPluginManager(compact('config'), [], $this->once())
        );
        $mockRequest = $this
            ->getMockBuilder(\Laminas\Http\PhpEnvironment\Request::class)
            ->disableOriginalConstructor()->getMock();
        $mockRequest->expects($this->once())->method('getServer')
            ->will($this->returnValue(new Parameters($server)));
        $container->set('Request', $mockRequest);
        return $container;
    }

    /**
     * Extend UserIpReader to capture constructor parameters
     *
     * @return \VuFind\Net\UserIpReader
     */
    protected function getReaderClass()
    {
        $readerClass = new class () extends \VuFind\Net\UserIpReader {
            /**
             * Property for storing constructor arguments for testing.
             *
             * @var array
             */
            public $args;

            /**
             * Constructor
             */
            public function __construct()
            {
                $args = func_get_args();
                $this->args = $args;
                parent::__construct(
                    ...(empty($args) ? [new Parameters([])] : $args)
                );
            }
        };
        return $readerClass::class;
    }

    /**
     * Test the factory's defaults
     *
     * @return void
     */
    public function testDefaults()
    {
        $factory = new UserIpReaderFactory();
        $container = $this->getContainer();
        $reader = $factory($container, $this->getReaderClass());
        [$server, $allowForwardedIps, $ipFilter] = $reader->args;
        $this->assertEquals(['server' => true], $server->toArray());
        $this->assertFalse($allowForwardedIps);
        $this->assertEquals([], $ipFilter);
    }

    /**
     * Test non-default values, with a single filtered IP
     *
     * @return void
     */
    public function testNonDefaultsWithSingleFilteredIP()
    {
        $factory = new UserIpReaderFactory();
        $container = $this->getContainer(
            [
                'Proxy' => [
                    'allow_forwarded_ips' => true,
                    'forwarded_ip_filter' => '1.2.3.4',
                ],
            ]
        );
        $reader = $factory($container, $this->getReaderClass());
        [$server, $allowForwardedIps, $ipFilter] = $reader->args;
        $this->assertEquals(['server' => true], $server->toArray());
        $this->assertTrue($allowForwardedIps);
        $this->assertEquals(['1.2.3.4'], $ipFilter);
    }

    /**
     * Test non-default values, with multiple filtered IPs
     *
     * @return void
     */
    public function testNonDefaultsWithMultipleFilteredIPs()
    {
        $factory = new UserIpReaderFactory();
        $container = $this->getContainer(
            [
                'Proxy' => [
                    'allow_forwarded_ips' => true,
                    'forwarded_ip_filter' => ['1.2.3.4', '5.6.7.8'],
                ],
            ]
        );
        $reader = $factory($container, $this->getReaderClass());
        [$server, $allowForwardedIps, $ipFilter] = $reader->args;
        $this->assertEquals(['server' => true], $server->toArray());
        $this->assertTrue($allowForwardedIps);
        $this->assertEquals(['1.2.3.4', '5.6.7.8'], $ipFilter);
    }
}
