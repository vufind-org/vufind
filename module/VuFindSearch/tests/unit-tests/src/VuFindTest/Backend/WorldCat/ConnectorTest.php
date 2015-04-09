<?php

/**
 * Unit tests for WorldCat connector.
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
 * @category VuFind2
 * @package  Search
 * @author   David Maus <maus@hab.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org
 */
namespace VuFindTest\Backend\WorldCat;

use VuFindSearch\Backend\WorldCat\Connector;
use VuFindSearch\ParamBag;

/**
 * Unit tests for WorldCat backend.
 *
 * @category VuFind2
 * @package  Search
 * @author   David Maus <maus@hab.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org
 */
class ConnectorTest extends \PHPUnit_Framework_TestCase
{
    /**
     * Test "get holdings"
     *
     * @return void
     */
    public function testGetHoldings()
    {
        $client = $this->getMock('Zend\Http\Client');
        $connector = new Connector('key', $client);
        $client->expects($this->once())->method('setMethod')
            ->with($this->equalTo('POST'))
            ->will($this->returnValue($client));
        $client->expects($this->once())->method('setUri')
            ->with($this->equalTo('http://www.worldcat.org/webservices/catalog/content/libraries/baz?wskey=key&servicelevel=full'));
        $body = '<foo>bar</foo>';
        $response = $this->getMock('Zend\Http\Response');
        $response->expects($this->once())->method('getBody')
            ->will($this->returnValue($body));
        $response->expects($this->any())->method('isSuccess')
            ->will($this->returnValue(true));
        $client->expects($this->once())->method('send')
            ->will($this->returnValue($response));
        $final = $connector->getHoldings('baz');
        $this->assertEquals('bar', (string)$final);
    }

    /**
     * Test "get holdings" HTTP failure
     *
     * @return void
     *
     * @expectedException VuFindSearch\Backend\Exception\RequestErrorException
     */
    public function testGetHoldingsHttpFailure()
    {
        $client = $this->getMock('Zend\Http\Client');
        $connector = new Connector('key', $client);
        $client->expects($this->once())->method('setMethod')
            ->with($this->equalTo('POST'))
            ->will($this->returnValue($client));
        $response = $this->getMock('Zend\Http\Response');
        $response->expects($this->any())->method('isSuccess')
            ->will($this->returnValue(false));
        $client->expects($this->once())->method('send')
            ->will($this->returnValue($response));
        $connector->getHoldings('baz');
    }

    /**
     * Test "get record"
     *
     * @return void
     */
    public function testGetRecord()
    {
        $client = $this->getMock('Zend\Http\Client');
        $connector = new Connector('key', $client);
        $client->expects($this->once())->method('setMethod')
            ->with($this->equalTo('POST'))
            ->will($this->returnValue($client));
        $client->expects($this->once())->method('setUri')
            ->with($this->equalTo('http://www.worldcat.org/webservices/catalog/content/baz?servicelevel=full&wskey=key'));
        $body = '<foo>bar</foo>';
        $response = $this->getMock('Zend\Http\Response');
        $response->expects($this->once())->method('getBody')
            ->will($this->returnValue($body));
        $response->expects($this->any())->method('isSuccess')
            ->will($this->returnValue(true));
        $client->expects($this->once())->method('send')
            ->will($this->returnValue($response));
        $final = $connector->getRecord('baz');
        $this->assertEquals($body, $final['docs'][0]);
    }

    /**
     * Test "get record" with error
     *
     * @return void
     */
    public function testGetRecordWithError()
    {
        $client = $this->getMock('Zend\Http\Client');
        $connector = new Connector('key', $client);
        $client->expects($this->once())->method('setMethod')
            ->with($this->equalTo('POST'))
            ->will($this->returnValue($client));
        $client->expects($this->once())->method('setUri')
            ->with($this->equalTo('http://www.worldcat.org/webservices/catalog/content/baz?servicelevel=full&wskey=key'));
        $body = '<foo><diagnostic>bad</diagnostic></foo>';
        $response = $this->getMock('Zend\Http\Response');
        $response->expects($this->once())->method('getBody')
            ->will($this->returnValue($body));
        $response->expects($this->any())->method('isSuccess')
            ->will($this->returnValue(true));
        $client->expects($this->once())->method('send')
            ->will($this->returnValue($response));
        $final = $connector->getRecord('baz');
        $this->assertEquals([], $final['docs']);
    }

    /**
     * Test search
     *
     * @return void
     */
    public function testSearch()
    {
        $client = $this->getMock('Zend\Http\Client');
        $connector = new Connector('key', $client);
        $client->expects($this->once())->method('setMethod')
            ->with($this->equalTo('POST'))
            ->will($this->returnValue($client));
        $client->expects($this->once())->method('setUri')
            ->with($this->equalTo('http://www.worldcat.org/webservices/catalog/search/sru?version=1.1&x=y&startRecord=0&maximumRecords=20&servicelevel=full&wskey=key'));
        $body = '<foo>,<numberOfRecords>1</numberOfRecords><records><record><recordData>bar</recordData></record></records></foo>';
        $response = $this->getMock('Zend\Http\Response');
        $response->expects($this->once())->method('getBody')
            ->will($this->returnValue($body));
        $response->expects($this->any())->method('isSuccess')
            ->will($this->returnValue(true));
        $client->expects($this->once())->method('send')
            ->will($this->returnValue($response));
        $final = $connector->search(new ParamBag(['x' => 'y']), 0, 20);
        $this->assertEquals('<recordData>bar</recordData>', $final['docs'][0]);
        $this->assertEquals(1, $final['total']);
    }
}
