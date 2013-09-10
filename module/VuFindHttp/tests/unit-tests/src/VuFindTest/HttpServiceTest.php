<?php

/**
 * Proxy service unit test.
 *
 * PHP version 5
 *
 * Copyright (C) Villanova University 2010.
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
 * @category Search
 * @package  Service
 * @author   David Maus <maus@hab.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://github.com/dmj/vf2-proxy
 */

namespace VuFindTest;

use VuFindHttp\HttpService as Service;

/**
 * Proxy service unit test.
 *
 * PHP version 5
 *
 * Copyright (C) Villanova University 2010.
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
 * @category Search
 * @package  Service
 * @author   David Maus <maus@hab.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://github.com/dmj/vf2-proxy
 */

class ProxyServiceTest extends Unit\TestCase
{

    protected $local = array('ipv4 localhost' => 'http://localhost',
                             'ipv4 loopback'  => 'http://127.0.0.1',
                             'ipv6 loopback'  => 'http://[::1]');

    /**
     * Test GET request with associative array.
     *
     * @return void
     */
    public function testGetWithAssocParams()
    {
        $service = new Service();
        $adapter = $this->getMock('Zend\Http\Client\Adapter\Test', array('write'));
        $adapter->expects($this->once())
            ->method('write')
            ->with(
                $this->equalTo('GET'),
                $this->equalTo(
                    new \Zend\Uri\Http('http://example.tld?foo=bar&bar%5B0%5D=baz')
                )
            );
        $service->setDefaultAdapter($adapter);
        $service->get('http://example.tld', array('foo' => 'bar', 'bar' => array('baz')));
    }

    /**
     * Test GET request with parameter pairs.
     *
     * @return void
     */
    public function testGetWithParamPairs()
    {
        $service = new Service();
        $adapter = $this->getMock('Zend\Http\Client\Adapter\Test', array('write'));
        $adapter->expects($this->once())
            ->method('write')
            ->with(
                $this->equalTo('GET'),
                $this->equalTo(
                    new \Zend\Uri\Http('http://example.tld?foo=bar&bar=baz&bar=bar')
                )
            );
        $service->setDefaultAdapter($adapter);
        $service->get('http://example.tld', array('foo=bar', 'bar=baz', 'bar=bar'));
    }

    /**
     * Test GET request appends query part.
     *
     * @return void
     */
    public function testGetAppendsQueryPart()
    {
        $service = new Service();
        $adapter = $this->getMock('Zend\Http\Client\Adapter\Test', array('write'));
        $adapter->expects($this->once())
            ->method('write')
            ->with(
                $this->equalTo('GET'),
                $this->equalTo(
                    new \Zend\Uri\Http('http://example.tld?foo=bar&bar=baz')
                )
            );
        $service->setDefaultAdapter($adapter);
        $service->get('http://example.tld?foo=bar', array('bar=baz'));
    }

    /**
     * Test POST request of form data.
     *
     * @return void
     */
    public function testPostForm()
    {
        $service = new Service();
        $adapter = new \Zend\Http\Client\Adapter\Test();
        $service->setDefaultAdapter($adapter);
        $service->postForm('http://example.tld', array('foo=bar'));
    }

    /**
     * Test POST request.with empty body
     *
     * @return void
     */
    public function testSendPostRequestEmptyBody()
    {
        $service = new Service();
        $adapter = $this->getMock('Zend\Http\Client\Adapter\Test', array('write'));
        $adapter->expects($this->once())
            ->method('write')
            ->with(
                $this->equalTo('POST'),
                $this->equalTo(
                    new \Zend\Uri\Http('http://example.tld')
                )
            );
        $service->setDefaultAdapter($adapter);
        $service->post('http://example.tld');
    }

    /**
     * Test proxify.
     *
     * @return void
     */
    public function testProxify()
    {
        $service = new Service(
            array(
                'proxy_host' => 'localhost',
                'proxy_port' => '666'
            )
        );
        $client = new \Zend\Http\Client('http://example.tld:8080');
        $client = $service->proxify($client);
        $adapter = $client->getAdapter();
        $this->assertInstanceOf('Zend\Http\Client\Adapter\Proxy', $adapter);
        $config = $adapter->getConfig();
        $this->assertEquals('localhost', $config['proxy_host']);
        $this->assertEquals('666', $config['proxy_port']);
    }

    /**
     * Test no proxify with local address.
     *
     * @return void
     */
    public function testNoProxifyLocal()
    {
        $service = new Service(
            array(
                'proxy_host' => 'localhost',
                'proxy_port' => '666'
            )
        );
        foreach ($this->local as $name => $address) {
            $client = new \Zend\Http\Client($address);
            $client->setAdapter(new \Zend\Http\Client\Adapter\Test());
            $client = $service->proxify($client);
            $this->assertInstanceOf(
                'Zend\Http\Client\Adapter\Test',
                $client->getAdapter(),
                sprintf('Failed to proxify %s: %s', $name, $address)
            );
        }
    }

    /**
     * Test for runtime exception.
     *
     * @expectedException \VuFindHttp\Exception\RuntimeException
     *
     * @return void
     */
    public function testRuntimeException()
    {
        $service = new Service();
        $service->get('http://example.tld');
    }

    /**
     * Test isAssocArray with mixed keys.
     *
     * @return void
     */
    public function testIsAssocArrayWithMixedKeys()
    {
        $arr = array();
        $arr[] = 'foo';
        $arr['foo'] = 'bar';
        $this->assertTrue(Service::isAssocParams($arr));
    }

    /**
     * Test default settings.
     *
     * @return void
     */
    public function testDefaults()
    {
        $service = new Service(array(), array('foo' => 'bar'));
        $client = $service->createClient();
        $clientConfig = $this->getProperty($client, 'config');
        $this->assertEquals($clientConfig['foo'], 'bar');
    }

    /**
     * Test timeout setting.
     *
     * @return void
     */
    public function testTimeout()
    {
        $service = new Service();
        $client = $service->createClient(null, \Zend\Http\Request::METHOD_GET, 67);
        $clientConfig = $this->getProperty($client, 'config');
        $this->assertEquals($clientConfig['timeout'], 67);
    }
}