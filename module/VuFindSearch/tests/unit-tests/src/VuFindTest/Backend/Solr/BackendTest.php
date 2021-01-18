<?php

/**
 * Unit tests for SOLR backend.
 *
 * PHP version 7
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
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301  USA
 *
 * @category VuFind
 * @package  Search
 * @author   David Maus <maus@hab.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org
 */
namespace VuFindTest\Backend\Solr;

use InvalidArgumentException;
use Laminas\Http\Response;
use PHPUnit\Framework\TestCase;
use VuFindSearch\Backend\Exception\RemoteErrorException;
use VuFindSearch\Backend\Solr\Backend;
use VuFindSearch\Backend\Solr\HandlerMap;
use VuFindSearch\Backend\Solr\Response\Json\RecordCollection;
use VuFindSearch\ParamBag;
use VuFindSearch\Query\Query;

/**
 * Unit tests for SOLR backend.
 *
 * @category VuFind
 * @package  Search
 * @author   David Maus <maus@hab.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org
 */
class BackendTest extends TestCase
{
    use \VuFindTest\Unit\FixtureTrait;

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
        $this->runRetrieveBatchTests($back);
    }

    /**
     * Given a configured backend, run some standard tests (this allows us
     * to test two different versions of the same scenario.
     *
     * @param Backend $back Backend
     *
     * @return void
     */
    protected function runRetrieveBatchTests($back)
    {
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
     * Test retrieving a batch of records, using a non-default page size.
     *
     * @return void
     */
    public function testRetrieveBatchWithNonDefaultPageSize()
    {
        $resp1 = $this->loadResponse('multi-record-part1');
        $resp2 = $this->loadResponse('multi-record-part2');
        $resp3 = $this->loadResponse('multi-record-part3');
        $conn = $this->getConnectorMock(['search']);
        $conn->expects($this->at(0))
            ->method('search')
            ->will($this->returnValue($resp1->getBody()));
        $conn->expects($this->at(1))
            ->method('search')
            ->will($this->returnValue($resp2->getBody()));
        $conn->expects($this->at(2))
            ->method('search')
            ->will($this->returnValue($resp3->getBody()));

        $back = new Backend($conn);
        $back->setPageSize(1);
        $this->runRetrieveBatchTests($back);
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
     * Test terms component (using ParamBag as first param).
     *
     * @return void
     */
    public function testTermsWithParamBagAsFirstParameter()
    {
        $resp = $this->loadResponse('terms');
        $conn = $this->getConnectorMock(['query']);
        $conn->expects($this->once())
            ->method('query')
            ->will($this->returnValue($resp->getBody()));
        $back = new Backend($conn);
        $back->setIdentifier('test');
        $bag = new ParamBag();
        $bag->set('terms.fl', 'author');
        $bag->set('terms.lower', '');
        $bag->set('terms.limit', '-1');
        $terms = $back->terms($bag);
        $this->assertTrue($terms->hasFieldTerms('author'));
        $this->assertCount(10, $terms->getFieldTerms('author'));
    }

    /**
     * Test handling of a bad JSON response.
     *
     * @return void
     */
    public function testBadJson()
    {
        $this->expectException(\VuFindSearch\Backend\Exception\BackendException::class);
        $this->expectExceptionMessage('JSON decoding error: 4 -- bad {');

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
     */
    public function testInjectResponseWriterThrownOnIncompabileResponseWriter()
    {
        $this->expectException(\VuFindSearch\Exception\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid response writer type: xml');

        $conn = $this->getConnectorMock();
        $back = new Backend($conn);
        $back->retrieve('foobar', new ParamBag(['wt' => ['xml']]));
    }

    /**
     * Test injectResponseWriter throws on incompatible named list setting.
     *
     * @return void
     */
    public function testInjectResponseWriterThrownOnIncompabileNamedListSetting()
    {
        $this->expectException(\VuFindSearch\Exception\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid named list implementation type: bad');

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
     * Test getting multiple IDs.
     *
     * @return void
     */
    public function testGetIds()
    {
        $paramBagChecker = function (ParamBag $params) {
            $expected = [
                'wt' => ['json'],
                'json.nl' => ['arrarr'],
                'fl' => ['id'],
                'rows' => [10],
                'start' => [0],
                'q' => ['foo'],
            ];
            $paramsArr = $params->getArrayCopy();
            foreach ($expected as $key => $vals) {
                if (count(array_diff($vals, $paramsArr[$key] ?? [])) !== 0) {
                    return false;
                }
            }
            return true;
        };
        // TODO: currently this test is concerned with ensuring that the right
        // parameters are sent to Solr; it may be worth adding a more realistic
        // return value to better test processing of retrieved records.
        $conn = $this->getConnectorMock(['search']);
        $conn->expects($this->once())->method('search')
            ->with($this->callback($paramBagChecker))
            ->will($this->returnValue(json_encode([])));
        $back = new Backend($conn);
        $query = new Query('foo');
        $result = $back->getIds($query, 0, 10);
        $this->assertTrue($result instanceof RecordCollection);
        $this->assertEquals(0, count($result));
    }

    /**
     * Test refining an alphabrowse exception (string 1).
     *
     * @return void
     */
    public function testRefineAlphaBrowseException()
    {
        $this->expectException(\VuFindSearch\Backend\Exception\RemoteErrorException::class);
        $this->expectExceptionMessage('Alphabetic Browse index missing.');

        $this->runRefineExceptionCall('does not exist');
    }

    /**
     * Test refining an alphabrowse exception (string 2).
     *
     * @return void
     */
    public function testRefineAlphaBrowseExceptionWithAltString()
    {
        $this->expectException(\VuFindSearch\Backend\Exception\RemoteErrorException::class);
        $this->expectExceptionMessage('Alphabetic Browse index missing.');

        $this->runRefineExceptionCall('couldn\'t find a browse index');
    }

    /**
     * Test that we don't refine a non-alphabrowse-related exception.
     *
     * @return void
     */
    public function testRefineAlphaBrowseExceptionWithNonBrowseString()
    {
        $this->expectException(\VuFindSearch\Backend\Exception\RemoteErrorException::class);
        $this->expectExceptionMessage('not a browse error');

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
        $params = $this->getMockBuilder(\VuFindSearch\ParamBag::class)
            ->setMethods(['set'])->getMock();
        $params->expects($this->once())->method('set')
            ->with($this->equalTo('sort'), $this->matchesRegularExpression('/[0-9]+_random asc/'));

        // Test that random proxies search; stub out injectResponseWriter() to prevent it
        // from injecting unwanted extra parameters into $params:
        $back = $this->getMockBuilder(__NAMESPACE__ . '\BackendMock')
            ->setMethods(['search', 'injectResponseWriter'])
            ->setConstructorArgs([$this->getConnectorMock()])
            ->getMock();
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
        $e = new RemoteErrorException($msg, 400, new \Laminas\Http\Response());
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
     * @return Laminas\Http\Response
     *
     * @throws InvalidArgumentException Fixture files does not exist
     */
    protected function loadResponse($fixture)
    {
        return Response::fromString(
            $this->getFixture("solr/response/$fixture", 'VuFindSearch')
        );
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
        return $this->getMockBuilder(\VuFindSearch\Backend\Solr\Connector::class)
            ->setMethods($mock)
            ->setConstructorArgs(['http://example.org/', $map])
            ->getMock();
    }
}

class BackendMock extends \VuFindSearch\Backend\Solr\Backend
{
    public function injectResponseWriter(\VuFindSearch\ParamBag $params)
    {
    }
}
