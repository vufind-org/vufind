<?php

/**
 * SMS test
 *
 * PHP version 8
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
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301  USA
 *
 * @category VuFind
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */

namespace VuFindTest\SMS;

use VuFind\SMS\Clickatell;

use function function_exists;

/**
 * SMS test
 *
 * @category VuFind
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */
class ClickatellTest extends \PHPUnit\Framework\TestCase
{
    /**
     * Expected base URL in tests.
     *
     * @var string
     */
    protected $expectedBaseUri = 'https://api.clickatell.com/http/sendmsg?api_id=api_id&user=user&password=password';

    /**
     * Setup method
     *
     * @return void
     */
    public function setUp(): void
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
            'Clickatell' => ['name' => 'Clickatell', 'domain' => null],
        ];
        $obj = $this->getClickatell();
        $this->assertEquals($expected, $obj->getCarriers());
    }

    /**
     * Test unknown exception message error
     *
     * @return void
     */
    public function testUnknownException()
    {
        $client = $this->getMockClient();
        $expectedUri = $this->expectedBaseUri . '&to=1234567890&text=hello';
        $client->expects($this->once())
            ->method('setMethod')
            ->with($this->equalTo('GET'))
            ->will($this->returnValue($client));
        $client->expects($this->once())
            ->method('setUri')
            ->with($this->equalTo($expectedUri))
            ->will($this->returnValue($client));
        $client->expects($this->once())
            ->method('send')
            ->will(
                $this->throwException(
                    new \VuFind\Exception\SMS(
                        'Technical message',
                        \VuFind\Exception\SMS::ERROR_UNKNOWN
                    )
                )
            );
        $obj = $this->getClickatell($client);
        try {
            $obj->text('Clickatell', '1234567890', 'test@example.com', 'hello');
        } catch (\VuFind\Exception\SMS $e) {
            $this->assertEquals('sms_failure', $e->getDisplayMessage());
        }
    }

    /**
     * Test successful query
     *
     * @return void
     */
    public function testSuccessResponse()
    {
        $client = $this->getMockClient();
        $expectedUri = $this->expectedBaseUri . '&to=1234567890&text=hello';
        $response = new \Laminas\Http\Response();
        $response->setStatusCode(200);
        $response->setContent('ID:fake');
        $client->expects($this->once())
            ->method('setMethod')
            ->with($this->equalTo('GET'))
            ->will($this->returnValue($client));
        $client->expects($this->once())
            ->method('setUri')
            ->with($this->equalTo($expectedUri))
            ->will($this->returnValue($client));
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
     */
    public function testUnexpectedResponse()
    {
        $this->expectException(\VuFind\Exception\SMS::class);
        $this->expectExceptionMessage('badbadbad');

        $client = $this->getMockClient();
        $expectedUri = $this->expectedBaseUri . '&to=1234567890&text=hello';
        $response = new \Laminas\Http\Response();
        $response->setStatusCode(200);
        $response->setContent('badbadbad');
        $client->expects($this->once())
            ->method('setMethod')
            ->with($this->equalTo('GET'))
            ->will($this->returnValue($client));
        $client->expects($this->once())
            ->method('setUri')
            ->with($this->equalTo($expectedUri))
            ->will($this->returnValue($client));
        $client->expects($this->once())
            ->method('send')
            ->will($this->returnValue($response));
        $obj = $this->getClickatell($client);
        $obj->text('Clickatell', '1234567890', 'test@example.com', 'hello');
    }

    /**
     * Test unsuccessful query
     *
     * @return void
     */
    public function testFailureResponse()
    {
        $this->expectException(\VuFind\Exception\SMS::class);
        $this->expectExceptionMessage('Problem sending text.');

        $client = $this->getMockClient();
        $expectedUri = $this->expectedBaseUri . '&to=1234567890&text=hello';
        $response = new \Laminas\Http\Response();
        $response->setStatusCode(404);
        $client->expects($this->once())
            ->method('setMethod')
            ->with($this->equalTo('GET'))
            ->will($this->returnValue($client));
        $client->expects($this->once())
            ->method('setUri')
            ->with($this->equalTo($expectedUri))
            ->will($this->returnValue($client));
        $client->expects($this->once())
            ->method('send')
            ->will($this->returnValue($response));
        $obj = $this->getClickatell($client);
        $obj->text('Clickatell', '1234567890', 'test@example.com', 'hello');
    }

    /**
     * Test an exception in the mail client
     *
     * @return void
     */
    public function testClientException()
    {
        $this->expectException(\VuFind\Exception\SMS::class);
        $this->expectExceptionMessage('Foo');

        $client = $this->getMockClient();
        $expectedUri = $this->expectedBaseUri . '&to=1234567890&text=hello';
        $client->expects($this->once())
            ->method('setMethod')
            ->with($this->equalTo('GET'))
            ->will($this->returnValue($client));
        $client->expects($this->once())
            ->method('setUri')
            ->with($this->equalTo($expectedUri))
            ->will($this->returnValue($client));
        $client->expects($this->once())
            ->method('send')
            ->will($this->throwException(new \Exception('Foo')));
        $obj = $this->getClickatell($client);
        $obj->text('Clickatell', '1234567890', 'test@example.com', 'hello');
    }

    /**
     * Build a test object
     *
     * @param \Laminas\Http\Client $client HTTP client (null for default)
     * @param array                $config Configuration (null for default)
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
            new \Laminas\Config\Config($config),
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
     * @return \Laminas\Http\Client
     */
    protected function getMockClient()
    {
        return $this->createMock(\Laminas\Http\Client::class);
    }
}
