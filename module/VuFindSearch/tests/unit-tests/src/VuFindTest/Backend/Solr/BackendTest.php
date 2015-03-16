<?php

/**
 * Unit tests for SOLR backend.
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
namespace VuFindTest\Backend\Solr;

use VuFindSearch\Backend\Exception\RemoteErrorException;
use VuFindSearch\Backend\Solr\Backend;
use VuFindSearch\Backend\Solr\HandlerMap;
use VuFindSearch\ParamBag;
use VuFindSearch\Query\Query;

use Zend\Http\Response;
use PHPUnit_Framework_TestCase;
use InvalidArgumentException;

/**
 * Unit tests for SOLR backend.
 *
 * @category VuFind2
 * @package  Search
 * @author   David Maus <maus@hab.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org
 */
class BackendTest extends PHPUnit_Framework_TestCase
{
    /**
     * Test retrieving a record.
     *
     * @return void
     */
    public function testRetrieve()
    {
        $resp = $this->loadResponse('single-record');
        $conn = $this->getConnectorMock(['retrieve']);
        $conn->expects($this->once())
            ->method('retrieve')
            ->will($this->returnValue($resp->getBody()));

        $back = new Backend($conn);
        $back->setIdentifier('test');
        $coll = $back->retrieve('foobar');
        $this->assertCount(1, $coll);
        $this->assertEquals('test', $coll->getSourceIdentifier());
        $rec  = $coll->first();
        $this->assertEquals('test', $rec->getSourceIdentifier());
        $this->assertEquals('690250223', $rec->id);
    }

    /**
     * Test retrieving a batch of records.
     *
     * @return void
     */
    public function testRetrieveBatch()
    {
        $resp = $this->loadResponse('multi-record');
        $conn = $this->getConnectorMock(['search']);
        $conn->expects($this->once())
            ->method('search')
            ->will($this->returnValue($resp->getBody()));

        $back = new Backend($conn);
        $back->setIdentifier('test');
        $coll = $back->retrieveBatch(['12345', '125456', '234547']);
        $this->assertCount(3, $coll);
        $this->assertEquals('test', $coll->getSourceIdentifier());
        $rec  = $coll->first();
        $this->assertEquals('test', $rec->getSourceIdentifier());
        $this->assertEquals('12345', $rec->id);
        $recs = $coll->getRecords();
        $this->assertEquals('test', $recs[1]->getSourceIdentifier());
        $this->assertEquals('125456', $recs[1]->id);
        $rec  = $coll->next();
        $this->assertEquals('test', $recs[2]->getSourceIdentifier());
        $this->assertEquals('234547', $recs[2]->id);
    }

    /**
     * Test retrieving similar records.
     *
     * @return void
     */
    public function testSimilar()
    {
        $resp = $this->loadResponse('morelikethis');
        $conn = $this->getConnectorMock(['similar']);
        $conn->expects($this->once())
            ->method('similar')
            ->will($this->returnValue($resp->getBody()));

        $back = new Backend($conn);
        $back->setIdentifier('test');
        $coll = $back->similar('704640');
        $this->assertCount(5, $coll);
        $this->assertEquals('test', $coll->getSourceIdentifier());
        $rec  = $coll->first();
        $this->assertEquals('test', $rec->getSourceIdentifier());
        $this->assertEquals('704635', $rec->id);
    }

    /**
     * Test terms component.
     *
     * @return void
     */
    public function testTerms()
    {
        $resp = $this->loadResponse('terms');
        $conn = $this->getConnectorMock(['query']);
        $conn->expects($this->once())
            ->method('query')
            ->will($this->returnValue($resp->getBody()));
        $back = new Backend($conn);
        $back->setIdentifier('test');
        $terms = $back->terms('author', '', -1);
        $this->assertTrue($terms->hasFieldTerms('author'));
        $this->assertCount(10, $terms->getFieldTerms('author'));
    }

    /**
     * Test handling of a bad JSON response.
     *
     * @return void
     *
     * @expectedException        VuFindSearch\Backend\Exception\BackendException
     * @expectedExceptionMessage JSON decoding error: 4 -- bad {
     */
    public function testBadJson()
    {
        $conn = $this->getConnectorMock(['query']);
        $conn->expects($this->once())
            ->method('query')
            ->will($this->returnValue('bad {'));
        $back = new Backend($conn);
        $terms = $back->terms('author', '', -1);
    }

    /**
     * Test injectResponseWriter throws on incompatible response writer.
     *
     * @return void
     *
     * @expectedException        VuFindSearch\Exception\InvalidArgumentException
     * @expectedExceptionMessage Invalid response writer type: xml
     */
    public function testInjectResponseWriterThrownOnIncompabileResponseWriter()
    {
        $conn = $this->getConnectorMock();
        $back = new Backend($conn);
        $back->retrieve('foobar', new ParamBag(['wt' => ['xml']]));
    }

    /**
     * Test injectResponseWriter throws on incompatible named list setting.
     *
     * @return void
     *
     * @expectedException        VuFindSearch\Exception\InvalidArgumentException
     * @expectedExceptionMessage Invalid named list implementation type: bad
     */
    public function testInjectResponseWriterThrownOnIncompabileNamedListSetting()
    {
        $conn = $this->getConnectorMock();
        $back = new Backend($conn);
        $back->retrieve('foobar', new ParamBag(['json.nl' => ['bad']]));
    }

    /**
     * Test getting a connector.
     *
     * @return void
     */
    public function testGetConnector()
    {
        $conn = $this->getConnectorMock();
        $back = new Backend($conn);
        $this->assertEquals($conn, $back->getConnector());
    }

    /**
     * Test getting an identifier.
     *
     * @return void
     */
    public function testGetIdentifier()
    {
        $conn = $this->getConnectorMock();
        $back = new Backend($conn);
        $back->setIdentifier('foo');
        $this->assertEquals('foo', $back->getIdentifier());
    }

    /**
     * Test refining an alphabrowse exception (string 1).
     *
     * @return void
     *
     * @expectedException        VuFindSearch\Backend\Exception\RemoteErrorException
     * @expectedExceptionMessage Alphabetic Browse index missing.
     */
    public function testRefineAlphaBrowseException()
    {
        $this->runRefineExceptionCall('does not exist');
    }

    /**
     * Test refining an alphabrowse exception (string 2).
     *
     * @return void
     *
     * @expectedException        VuFindSearch\Backend\Exception\RemoteErrorException
     * @expectedExceptionMessage Alphabetic Browse index missing.
     */
    public function testRefineAlphaBrowseExceptionWithAltString()
    {
        $this->runRefineExceptionCall('couldn\'t find a browse index');
    }

    /**
     * Test that we don't refine a non-alphabrowse-related exception.
     *
     * @return void
     *
     * @expectedException        VuFindSearch\Backend\Exception\RemoteErrorException
     * @expectedExceptionMessage not a browse error
     */
    public function testRefineAlphaBrowseExceptionWithNonBrowseString()
    {
        $this->runRefineExceptionCall('not a browse error');
    }

    /**
     * Test random method
     *
     * @return void
     */
    public function testRandom()
    {
        // Test that random sort parameter is added:
        $params = $this->getMock('VuFindSearch\ParamBag', ['set']);
        $params->expects($this->once())->method('set')
            ->with($this->equalTo('sort'), $this->matchesRegularExpression('/[0-9]+_random asc/'));

        // Test that random proxies search; stub out injectResponseWriter() to prevent it
        // from injecting unwanted extra parameters into $params:
        $back = $this->getMock(
            'VuFindSearch\Backend\Solr\Backend', ['search', 'injectResponseWriter'],
            [$this->getConnectorMock()]
        );
        $back->expects($this->once())->method('injectResponseWriter');
        $back->expects($this->once())->method('search')
            ->will($this->returnValue('dummy'));
        $this->assertEquals('dummy', $back->random(new Query('foo'), 1, $params));
    }

    /// Internal API

    /**
     * Support method to run a "refine exception" test.
     *
     * @param string $msg Error message
     *
     * @return void
     */
    protected function runRefineExceptionCall($msg)
    {
        $conn = $this->getConnectorMock(['query']);
        $e = new RemoteErrorException($msg, 400, new \Zend\Http\Response());
        $conn->expects($this->once())->method('query')
            ->with($this->equalTo('browse'))
            ->will($this->throwException($e));
        $back = new Backend($conn);
        $back->alphabeticBrowse('foo', 'bar', 1);
    }

    /**
     * Load a SOLR response as fixture.
     *
     * @param string $fixture Fixture file
     *
     * @return Zend\Http\Response
     *
     * @throws InvalidArgumentException Fixture files does not exist
     */
    protected function loadResponse($fixture)
    {
        $file = realpath(sprintf('%s/solr/response/%s', PHPUNIT_SEARCH_FIXTURES, $fixture));
        if (!is_string($file) || !file_exists($file) || !is_readable($file)) {
            throw new InvalidArgumentException(sprintf('Unable to load fixture file: %s', $file));
        }
        return Response::fromString(file_get_contents($file));
    }

    /**
     * Return connector mock.
     *
     * @param array $mock Functions to mock
     *
     * @return Connector
     */
    protected function getConnectorMock(array $mock = [])
    {
        $map = new HandlerMap(['select' => ['fallback' => true]]);
        return $this->getMock('VuFindSearch\Backend\Solr\Connector', $mock, ['http://example.org/', $map]);
    }
}
