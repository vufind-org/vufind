<?php

/**
 * Unit tests for SOLR backend.
 *
 * PHP version 8
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
use Laminas\Uri\Http;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use VuFindSearch\Backend\Exception\RemoteErrorException;
use VuFindSearch\Backend\Solr\Backend;
use VuFindSearch\Backend\Solr\Connector;
use VuFindSearch\Backend\Solr\Document\CommitDocument;
use VuFindSearch\Backend\Solr\HandlerMap;
use VuFindSearch\Backend\Solr\Response\Json\RecordCollection;
use VuFindSearch\ParamBag;
use VuFindSearch\Query\Query;

use function count;

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
    use \VuFindTest\Feature\FixtureTrait;
    use \VuFindTest\Feature\ReflectionTrait;

    /**
     * Test retrieving a record.
     *
     * @return void
     */
    public function testRetrieve(): void
    {
        $resp = $this->loadResponse('single-record');
        $conn = $this->getConnectorMock(['retrieve']);
        $conn->expects($this->once())
            ->method('retrieve')
            ->willReturn($resp->getBody());

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
    public function testRetrieveBatch(): void
    {
        $resp = $this->loadResponse('multi-record');
        $conn = $this->getConnectorMock(['search']);
        $conn->expects($this->once())
            ->method('search')
            ->willReturn($resp->getBody());
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
    protected function runRetrieveBatchTests(Backend $back): void
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
        $this->assertEquals('test', $recs[2]->getSourceIdentifier());
        $this->assertEquals('234547', $recs[2]->id);
    }

    /**
     * Test retrieving a batch of records, using a non-default page size.
     *
     * @return void
     */
    public function testRetrieveBatchWithNonDefaultPageSize(): void
    {
        $resp1 = $this->loadResponse('multi-record-part1');
        $resp2 = $this->loadResponse('multi-record-part2');
        $resp3 = $this->loadResponse('multi-record-part3');
        $conn = $this->getConnectorMock(['search']);
        $conn->expects($this->exactly(3))
            ->method('search')
            ->willReturnOnConsecutiveCalls($resp1->getBody(), $resp2->getBody(), $resp3->getBody());

        $back = new Backend($conn);
        $back->setPageSize(1);
        $this->runRetrieveBatchTests($back);
    }

    /**
     * Test retrieving similar records.
     *
     * @return void
     */
    public function testSimilar(): void
    {
        $resp = $this->loadResponse('morelikethis');
        $conn = $this->getConnectorMock(['similar']);
        $conn->expects($this->once())
            ->method('similar')
            ->willReturn($resp->getBody());

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
    public function testTerms(): void
    {
        $resp = $this->loadResponse('terms');
        $conn = $this->getConnectorMock(['query']);
        $conn->expects($this->once())
            ->method('query')
            ->willReturn($resp->getBody());
        $back = new Backend($conn);
        $back->setIdentifier('test');
        $terms = $back->terms('author', '', -1);
        $this->assertTrue($terms->hasFieldTerms('author'));
        $this->assertCount(10, $terms->getFieldTerms('author'));
    }

    /**
     * Test facets.
     *
     * @return void
     */
    public function testFacets(): void
    {
        $resp = $this->loadResponse('facet');
        $conn = $this->getConnectorMock(['query']);
        $conn->expects($this->once())
            ->method('query')
            ->willReturn($resp->getBody());
        $back = new Backend($conn);
        $response = $back->search(new Query(), 0, 0);
        $facets = $response->getFacets();
        $this->assertIsArray($facets);
        $this->assertEquals(
            [
                'topic_facet' => [
                    'Research' => 16,
                    'Psychotherapy' => 8,
                    'Adult children of aging parents' => 7,
                    'Automobile drivers\' tests' => 7,
                    'Fathers and daughters' => 7,
                ],
            ],
            $facets
        );
    }

    /**
     * Test pivot facets.
     *
     * @return void
     */
    public function testPivotFacets(): void
    {
        $resp = $this->loadResponse('pivot-facet');
        $conn = $this->getConnectorMock(['query']);
        $conn->expects($this->once())
            ->method('query')
            ->willReturn($resp->getBody());
        $back = new Backend($conn);
        $response = $back->search(new Query(), 0, 0);
        $facets = $response->getPivotFacets();
        $this->assertIsArray($facets);

        $this->assertEquals(
            [
                'A - General Works' => [
                    'field' => 'callnumber-first',
                    'value' => 'A - General Works',
                    'count' => 40,
                    'pivot' => [
                        [
                            'field' => 'topic_facet',
                            'value' => 'Research',
                            'count' => 16,
                        ],
                        [
                            'field' => 'topic_facet',
                            'value' => 'Psychotherapy',
                            'count' => 8,
                        ],
                        [
                            'field' => 'topic_facet',
                            'value' => 'Cognitive therapy',
                            'count' => 4,
                        ],
                        [
                            'field' => 'topic_facet',
                            'value' => 'Crime',
                            'count' => 4,
                        ],
                        [
                            'field' => 'topic_facet',
                            'value' => 'Criminal justice, Administration of',
                            'count' => 4,
                        ],
                    ],
                ],
                'P - Language and Literature' => [
                    'field' => 'callnumber-first',
                    'value' => 'P - Language and Literature',
                    'count' => 7,
                    'pivot' => [
                        [
                            'field' => 'topic_facet',
                            'value' => 'Adult children of aging parents',
                            'count' => 7,
                        ],
                        [
                            'field' => 'topic_facet',
                            'value' => 'Automobile drivers\' tests',
                            'count' => 7,
                        ],
                        [
                            'field' => 'topic_facet',
                            'value' => 'Fathers and daughters',
                            'count' => 7,
                        ],
                        [
                            'field' => 'topic_facet',
                            'value' => 'Middle aged women',
                            'count' => 7,
                        ],
                        [
                            'field' => 'topic_facet',
                            'value' => 'Older men',
                            'count' => 7,
                        ],
                    ],
                ],
                'D - World History' => [
                    'field' => 'callnumber-first',
                    'value' => 'D - World History',
                    'count' => 3,
                    'pivot' => [
                        [
                            'field' => 'topic_facet',
                            'value' => 'History',
                            'count' => 2,
                        ],
                    ],
                ],
                'B - Philosophy, Psychology, Religion' => [
                    'field' => 'callnumber-first',
                    'value' => 'B - Philosophy, Psychology, Religion',
                    'count' => 2,
                ],
                'H - Social Science' => [
                    'field' => 'callnumber-first',
                    'value' => 'H - Social Science',
                    'count' => 1,
                    'pivot' => [
                        [
                            'field' => 'topic_facet',
                            'value' => 'Bank employees',
                            'count' => 1,
                        ],
                        [
                            'field' => 'topic_facet',
                            'value' => 'Bank management',
                            'count' => 1,
                        ],
                        [
                            'field' => 'topic_facet',
                            'value' => 'Globalization',
                            'count' => 1,
                        ],
                        [
                            'field' => 'topic_facet',
                            'value' => 'Industrial relations',
                            'count' => 1,
                        ],
                        [
                            'field' => 'topic_facet',
                            'value' => 'Labor unions',
                            'count' => 1,
                        ],
                    ],
                ],
            ],
            $facets
        );
    }

    /**
     * Test query facets.
     *
     * @return void
     */
    public function testQueryFacets(): void
    {
        $resp = $this->loadResponse('query-facet');
        $conn = $this->getConnectorMock(['query']);
        $conn->expects($this->once())
            ->method('query')
            ->willReturn($resp->getBody());
        $back = new Backend($conn);
        $response = $back->search(new Query(), 0, 0);
        $facets = $response->getQueryFacets();
        $this->assertIsArray($facets);
        $this->assertEquals(
            [
                'publishDate:[* TO 2000]' => 45,
                'publishDate:[2001 TO 2010]' => 11,
            ],
            $facets
        );
    }

    /**
     * Test terms component (using ParamBag as first param).
     *
     * @return void
     */
    public function testTermsWithParamBagAsFirstParameter(): void
    {
        $resp = $this->loadResponse('terms');
        $conn = $this->getConnectorMock(['query']);
        $conn->expects($this->once())
            ->method('query')
            ->willReturn($resp->getBody());
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
    public function testBadJson(): void
    {
        $this->expectException(\VuFindSearch\Backend\Exception\BackendException::class);
        $this->expectExceptionMessage('JSON decoding error: 4 -- bad {');

        $conn = $this->getConnectorMock(['query']);
        $conn->expects($this->once())
            ->method('query')
            ->willReturn('bad {');
        $back = new Backend($conn);
        $back->terms('author', '', -1);
    }

    /**
     * Test injectResponseWriter throws on incompatible response writer.
     *
     * @return void
     */
    public function testInjectResponseWriterThrownOnIncompabileResponseWriter(): void
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
    public function testInjectResponseWriterThrownOnIncompabileNamedListSetting(): void
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
    public function testGetConnector(): void
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
    public function testGetIdentifier(): void
    {
        $conn = $this->getConnectorMock();
        $back = new Backend($conn);
        $back->setIdentifier('foo');
        $this->assertEquals('foo', $back->getIdentifier());
    }

    /**
     * Data provider for testGetIds
     *
     * @return array
     */
    public static function getIdsProvider(): array
    {
        return [
            'default field list' => [null, 'id'],
            'customized field list' => ['last_indexed', 'id,last_indexed'],
        ];
    }

    /**
     * Test getting multiple IDs.
     *
     * @param ?string $flIn          Additional field list in input (null = none)
     * @param string  $expectedFlOut Expected field list in output
     *
     * @return void
     *
     * @dataProvider getIdsProvider
     */
    public function testGetIds(?string $flIn, string $expectedFlOut): void
    {
        $paramBagChecker = function (ParamBag $params) use ($expectedFlOut) {
            $expected = [
                'wt' => ['json'],
                'json.nl' => ['arrarr'],
                'fl' => [$expectedFlOut],
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
            ->willReturn(json_encode([]));
        $back = new Backend($conn);
        $query = new Query('foo');
        $params = new ParamBag();
        if ($flIn) {
            $params->set('fl', $flIn);
        }
        $result = $back->getIds($query, 0, 10, $params);
        $this->assertInstanceOf(RecordCollection::class, $result);
        $this->assertCount(0, $result);
    }

    /**
     * Test refining an alphabrowse exception (string 1).
     *
     * @return void
     */
    public function testRefineAlphaBrowseException(): void
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
    public function testRefineAlphaBrowseExceptionWithAltString(): void
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
    public function testRefineAlphaBrowseExceptionWithNonBrowseString(): void
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
    public function testRandom(): void
    {
        // Test that random sort parameter is added:
        $params = $this->getMockBuilder(\VuFindSearch\ParamBag::class)
            ->onlyMethods(['set'])->getMock();
        $params->expects($this->once())->method('set')
            ->with($this->equalTo('sort'), $this->matchesRegularExpression('/[0-9]+_random asc/'));

        // Test that random proxies search; stub out injectResponseWriter() to prevent it
        // from injecting unwanted extra parameters into $params:
        $back = $this->getMockBuilder(Backend::class)
            ->onlyMethods(['search', 'injectResponseWriter'])
            ->setConstructorArgs([$this->getConnectorMock()])
            ->getMock();
        $back->expects($this->once())->method('injectResponseWriter');
        $back->expects($this->once())->method('search')
            ->willReturn('dummy');
        $this->assertEquals('dummy', $back->random(new Query('foo'), 1, $params));
    }

    /**
     * Test writeDocument
     *
     * @return void
     */
    public function testWriteDocument(): void
    {
        $doc = new CommitDocument();
        $client = $this->getMockBuilder(\Laminas\Http\Client::class)
            ->onlyMethods(['setOptions'])
            ->getMock();
        $client->expects($this->exactly(1))->method('setOptions')
            ->with(['timeout' => 60]);
        $connector = $this->getConnectorMock(['getUrl', 'write'], $client);
        $connector->expects($this->once())->method('write')
            ->with(
                $this->equalTo($doc),
                $this->equalTo('update'),
                $this->isNull()
            )
            ->willReturnCallback(
                function () use ($connector) {
                    // Call client factory for expectations to be met:
                    $factory = $this->getProperty($connector, 'clientFactory');
                    $factory('');
                    return true;
                }
            );
        $connector->expects($this->once())->method('getUrl')
            ->willReturn('http://localhost:8983/solr/core/biblio');
        $backend = new Backend($connector);
        $this->assertEquals(
            ['core' => 'biblio'],
            $backend->writeDocument($doc, 60)
        );
    }

    /**
     * Test extra request details
     *
     * @return void
     */
    public function testExtraRequestDetails(): void
    {
        $solrUri = new Http('https://www.someExampleSolr.com');
        $connector = $this->getConnectorMock(['getLastUrl']);
        $connector->expects($this->once())->method('getLastUrl')->willReturn($solrUri);
        $backend = new Backend($connector);
        $this->assertEquals(
            ['solrRequestUrl' => $solrUri],
            $backend->getExtraRequestDetails()
        );
    }

    /**
     * Test reset extra request details
     *
     * @return void
     */
    public function testResetExtraRequestDetails(): void
    {
        $solrUri = new Http('https://www.someExampleSolr.com');
        $connector = $this->getConnectorMock(['getLastUrl', 'resetLastUrl']);
        $connector->expects($this->once())->method('resetLastUrl');
        $connector->expects($this->exactly(2))->method('getLastUrl')
            ->willReturnOnConsecutiveCalls($solrUri, null);
        $backend = new Backend($connector);
        $this->assertEquals(
            ['solrRequestUrl' => $solrUri],
            $backend->getExtraRequestDetails()
        );
        $backend->resetExtraRequestDetails();
        $this->assertEquals(
            ['solrRequestUrl' => null],
            $backend->getExtraRequestDetails()
        );
    }

    /// Internal API

    /**
     * Support method to run a "refine exception" test.
     *
     * @param string $msg Error message
     *
     * @return void
     */
    protected function runRefineExceptionCall($msg): void
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
     * @return Response
     *
     * @throws InvalidArgumentException Fixture files does not exist
     */
    protected function loadResponse($fixture): Response
    {
        return Response::fromString(
            $this->getFixture("solr/response/$fixture", 'VuFindSearch')
        );
    }

    /**
     * Return connector mock.
     *
     * @param array      $mock   Functions to mock
     * @param HttpClient $client HTTP Client (optional)
     *
     * @return MockObject&Connector
     */
    protected function getConnectorMock(array $mock = [], $client = null): MockObject&Connector
    {
        $map = new HandlerMap(['select' => ['fallback' => true]]);
        return $this->getMockBuilder(\VuFindSearch\Backend\Solr\Connector::class)
            ->onlyMethods($mock)
            ->setConstructorArgs(
                [
                    'http://localhost/',
                    $map,
                    function () use ($client) {
                        // If client is provided, return it since it may have test
                        // expectations:
                        return $client ?? new \Laminas\Http\Client();
                    },
                ]
            )
            ->getMock();
    }
}
