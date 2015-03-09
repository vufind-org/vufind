<?php
/**
 * SMS test
 *
 * PHP version 5
 *
 * Copyright (C) Villanova University 2011.
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
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://www.vufind.org  Main Page
 */
namespace VuFindTest\SMS;
use VuFind\SMS\Clickatell;

/**
 * SMS test
 *
 * @category VuFind2
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://www.vufind.org  Main Page
 */
class ClickatellTest extends \VuFindTest\Unit\TestCase
{
    /**
     * Setup method
     *
     * @return void
     */
    public function setUp()
    {
        // Without SOAP functionality, we can't proceed:
        if (!function_exists('iconv')) {
            $this->markTestSkipped('iconv extension not installed');
        }
    }

    /**
     * Test carrier list
     *
     * @return void
     */
    public function testCarriers()
    {
        $expected = [
            'Clickatell' => ['name' => 'Clickatell', 'domain' => null]
        ];
        $obj = $this->getClickatell();
        $this->assertEquals($expected, $obj->getCarriers());
    }

    /**
     * Test successful query
     *
     * @return void
     */
    public function testSuccessResponse()
    {
        $client = $this->getMockClient();
        $expectedUri = 'https://api.clickatell.com/http/sendmsg?api_id=api_id&user=user&password=password&to=1234567890&text=hello';
        $response = new \Zend\Http\Response();
        $response->setStatusCode(200);
        $response->setContent('ID:fake');
        $client->expects($this->once())->method('setMethod')->with($this->equalTo('GET'))->will($this->returnValue($client));
        $client->expects($this->once())->method('setUri')->with($this->equalTo($expectedUri))->will($this->returnValue($client));
        $client->expects($this->once())->method('send')->will($this->returnValue($response));
        $obj = $this->getClickatell($client);
        $this->assertTrue(
            $obj->text('Clickatell', '1234567890', 'test@example.com', 'hello')
        );
    }

    /**
     * Test unexpected response
     *
     * @return void
     *
     * @expectedException        VuFind\Exception\Mail
     * @expectedExceptionMessage badbadbad
     */
    public function testUnexpectedResponse()
    {
        $client = $this->getMockClient();
        $expectedUri = 'https://api.clickatell.com/http/sendmsg?api_id=api_id&user=user&password=password&to=1234567890&text=hello';
        $response = new \Zend\Http\Response();
        $response->setStatusCode(200);
        $response->setContent('badbadbad');
        $client->expects($this->once())->method('setMethod')->with($this->equalTo('GET'))->will($this->returnValue($client));
        $client->expects($this->once())->method('setUri')->with($this->equalTo($expectedUri))->will($this->returnValue($client));
        $client->expects($this->once())->method('send')->will($this->returnValue($response));
        $obj = $this->getClickatell($client);
        $obj->text('Clickatell', '1234567890', 'test@example.com', 'hello');
    }

    /**
     * Test unsuccessful query
     *
     * @return void
     *
     * @expectedException        VuFind\Exception\Mail
     * @expectedExceptionMessage Problem sending text.
     */
    public function testFailureResponse()
    {
        $client = $this->getMockClient();
        $expectedUri = 'https://api.clickatell.com/http/sendmsg?api_id=api_id&user=user&password=password&to=1234567890&text=hello';
        $response = new \Zend\Http\Response();
        $response->setStatusCode(404);
        $client->expects($this->once())->method('setMethod')->with($this->equalTo('GET'))->will($this->returnValue($client));
        $client->expects($this->once())->method('setUri')->with($this->equalTo($expectedUri))->will($this->returnValue($client));
        $client->expects($this->once())->method('send')->will($this->returnValue($response));
        $obj = $this->getClickatell($client);
        $obj->text('Clickatell', '1234567890', 'test@example.com', 'hello');
    }

    /**
     * Test an exception in the mail client
     *
     * @return void
     *
     * @expectedException        VuFind\Exception\Mail
     * @expectedExceptionMessage Foo
     */
    public function testClientException()
    {
        $client = $this->getMockClient();
        $expectedUri = 'https://api.clickatell.com/http/sendmsg?api_id=api_id&user=user&password=password&to=1234567890&text=hello';
        $client->expects($this->once())->method('setMethod')->with($this->equalTo('GET'))->will($this->returnValue($client));
        $client->expects($this->once())->method('setUri')->with($this->equalTo($expectedUri))->will($this->returnValue($client));
        $client->expects($this->once())->method('send')->will($this->throwException(new \Exception('Foo')));
        $obj = $this->getClickatell($client);
        $obj->text('Clickatell', '1234567890', 'test@example.com', 'hello');
    }

    /**
     * Build a test object
     *
     * @param \Zend\Http\Client $client HTTP client (null for default)
     * @param array             $config Configuration (null for default)
     *
     * @return Clickatell
     */
    protected function getClickatell($client = null, $config = null)
    {
        if (null === $config) {
            $config = $this->getDefaultConfig();
        }
        if (null === $client) {
            $client = $this->getMockClient();
        }
        return new Clickatell(
            new \Zend\Config\Config($config),
            ['client' => $client]
        );
    }

    /**
     * Get default configuration
     *
     * @return array
     */
    protected function getDefaultConfig()
    {
        return [
            'Clickatell' => [
                'user' => 'user',
                'password' => 'password',
                'api_id' => 'api_id',
            ],
        ];
    }

    /**
     * Get a mock HTTP client
     *
     * @return \Zend\Http\Client
     */
    protected function getMockClient()
    {
        return $this->getMock('Zend\Http\Client');
    }
}