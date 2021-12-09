<?php

/**
 * Unit tests for search service.
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
namespace VuFindTest;

use PHPUnit\Framework\TestCase;
use VuFindSearch\Backend\BackendInterface;
use VuFindSearch\Backend\Exception\BackendException;
use VuFindSearch\Feature\GetIdsInterface;
use VuFindSearch\Feature\RandomInterface;
use VuFindSearch\Feature\RetrieveBatchInterface;
use VuFindSearch\Feature\SimilarInterface;
use VuFindSearch\ParamBag;
use VuFindSearch\Query\Query;
use VuFindSearch\Response\AbstractRecordCollection;

use VuFindSearch\Service;

/**
 * Unit tests for search service.
 *
 * @category VuFind
 * @package  Search
 * @author   David Maus <maus@hab.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org
 */
class SearchServiceTest extends TestCase
{
    /**
     * Mock backend
     *
     * @var BackendInterface
     */
    protected $backend = false;

    /**
     * Test retrieve action.
     *
     * @return void
     */
    public function testRetrieve()
    {
        $service = $this->getService();
        $backend = $this->getBackend();
        $response = 'fake';
        $params = new ParamBag(['x' => 'y']);
        $backend->expects($this->once())->method('retrieve')
            ->with($this->equalTo('bar'), $this->equalTo($params))
            ->will($this->returnValue($response));
        $em = $service->getEventManager();
        $em->expects($this->exactly(2))->method('trigger')
            ->withConsecutive(['pre', $backend], ['post', $response]);
        $service->retrieve('foo', 'bar', $params);
    }

    /**
     * Test exception-throwing retrieve action.
     *
     * @return void
     */
    public function testRetrieveException()
    {
        $this->expectException(\VuFindSearch\Backend\Exception\BackendException::class);
        $this->expectExceptionMessage('test');

        $service = $this->getService();
        $backend = $this->getBackend();
        $params = new ParamBag(['x' => 'y']);
        $exception = new BackendException('test');
        $backend->expects($this->once())->method('retrieve')
            ->with($this->equalTo('bar'), $this->equalTo($params))
            ->will($this->throwException($exception));
        $em = $service->getEventManager();
        $em->expects($this->exactly(2))->method('trigger')
            ->withConsecutive(['pre', $backend], ['error', $exception]);
        $service->retrieve('foo', 'bar', $params);
    }

    /**
     * Test exception-throwing search action.
     *
     * @return void
     */
    public function testSearchException()
    {
        $this->expectException(\VuFindSearch\Backend\Exception\BackendException::class);
        $this->expectExceptionMessage('test');

        $service = $this->getService();
        $backend = $this->getBackend();
        $exception = new BackendException('test');
        $backend->expects($this->once())->method('search')
            ->will($this->throwException($exception));
        $em = $service->getEventManager();
        $em->expects($this->exactly(2))->method('trigger')
            ->withConsecutive(['pre', $backend], ['error', $exception]);
        $service->search('foo', new Query('test'));
    }

    /**
     * Test that when a backend doesn't implement the "get IDs" feature
     * interface, the getIds method of the search service simply proxies search.
     * We'll test this by mimicing the testSearchException test above.
     *
     * @return void
     */
    public function testGetIdsProxyingSearchException()
    {
        $this->expectException(\VuFindSearch\Backend\Exception\BackendException::class);
        $this->expectExceptionMessage('test');

        $service = $this->getService();
        $backend = $this->getBackend();
        $exception = new BackendException('test');
        $backend->expects($this->once())->method('search')
            ->will($this->throwException($exception));
        $em = $service->getEventManager();
        $em->expects($this->exactly(2))->method('trigger')
            ->withConsecutive(['pre', $backend], ['error', $exception]);
        $service->getIds('foo', new Query('test'));
    }

    /**
     * Test that when a backend DOES implement the "get IDs" feature
     * interface, the appropriate method gets called.
     * We'll test this by mimicing the testSearchException test above.
     *
     * @return void
     */
    public function testGetIdsException()
    {
        $this->expectException(\VuFindSearch\Backend\Exception\BackendException::class);
        $this->expectExceptionMessage('test');

        // Use a special backend for this test...
        $this->backend = $this->createMockBackend(\VuFindTest\TestClassForGetIdsInterface::class);

        $service = $this->getService();
        $backend = $this->getBackend();
        $exception = new BackendException('test');
        $backend->expects($this->once())->method('getIds')
            ->will($this->throwException($exception));
        $em = $service->getEventManager();
        $em->expects($this->exactly(2))->method('trigger')
            ->withConsecutive(['pre', $backend], ['error', $exception]);
        $service->getIds('foo', new Query('test'));
    }

    /**
     * Test batch retrieve (with RetrieveBatchInterface).
     *
     * @return void
     */
    public function testRetrieveBatchInterface()
    {
        // Use a special backend for this test...
        $this->backend = $this->createMockBackend(
            \VuFindTest\TestClassForRetrieveBatchInterface::class
        );

        $service = $this->getService();
        $backend = $this->getBackend();
        $params = new ParamBag(['x' => 'y']);
        $ids = ['bar', 'baz'];
        $backend->expects($this->once())->method('retrieveBatch')
            ->with($this->equalTo($ids), $this->equalTo($params))
            ->will($this->returnValue('response'));
        $em = $service->getEventManager();
        $em->expects($this->exactly(2))->method('trigger')
            ->withConsecutive(['pre', $backend], ['post', 'response']);
        $service->retrieveBatch('foo', $ids, $params);

        // Put the backend back to the default:
        unset($this->backend);
    }

    /**
     * Test batch retrieve (without RetrieveBatchInterface).
     *
     * @return void
     */
    public function testRetrieveBatchNoInterface()
    {
        $service = $this->getService();
        $backend = $this->getBackend();
        $mockRecord = $this->createMock(\VuFindSearch\Response\RecordInterface::class);
        $response1 = $this->getRecordCollection();
        $response1->expects($this->once())->method('add')
            ->with($this->equalTo($mockRecord));
        $response2 = $this->getRecordCollection();
        $response2->expects($this->once())->method('first')
            ->will($this->returnValue($mockRecord));
        $params = new ParamBag(['x' => 'y']);
        $backend->expects($this->exactly(2))->method('retrieve')
            ->withConsecutive(['bar', $params], ['baz', $params])
            ->willReturnOnConsecutiveCalls($response1, $response2);
        $em = $service->getEventManager();
        $em->expects($this->exactly(2))->method('trigger')
            ->withConsecutive(['pre', $backend], ['post', $response1]);
        $service->retrieveBatch('foo', ['bar', 'baz'], $params);
    }

    /**
     * Test exception-throwing batch retrieve action (with RetrieveBatchInterface).
     *
     * @return void
     */
    public function testRetrieveBatchInterfaceException()
    {
        $this->expectException(\VuFindSearch\Backend\Exception\BackendException::class);
        $this->expectExceptionMessage('test');

        // Use a special backend for this test...
        $this->backend = $this->createMockBackend(\VuFindTest\TestClassForRetrieveBatchInterface::class);

        $service = $this->getService();
        $backend = $this->getBackend();
        $params = new ParamBag(['x' => 'y']);
        $exception = new BackendException('test');
        $ids = ['bar', 'baz'];
        $backend->expects($this->once())->method('retrieveBatch')
            ->with($this->equalTo($ids), $this->equalTo($params))
            ->will($this->throwException($exception));
        $em = $service->getEventManager();
        $em->expects($this->exactly(2))->method('trigger')
            ->withConsecutive(['pre', $backend], ['error', $exception]);
        $service->retrieveBatch('foo', $ids, $params);

        // Put the backend back to the default:
        unset($this->backend);
    }

    /**
     * Test exception-throwing batch retrieve action (without
     * RetrieveBatchInterface).
     *
     * @return void
     */
    public function testRetrieveBatchNoInterfaceException()
    {
        $this->expectException(\VuFindSearch\Backend\Exception\BackendException::class);
        $this->expectExceptionMessage('test');

        $service = $this->getService();
        $backend = $this->getBackend();
        $params = new ParamBag(['x' => 'y']);
        $exception = new BackendException('test');
        $backend->expects($this->once())->method('retrieve')
            ->with($this->equalTo('bar'), $this->equalTo($params))
            ->will($this->throwException($exception));
        $em = $service->getEventManager();
        $em->expects($this->exactly(2))->method('trigger')
            ->withConsecutive(['pre', $backend], ['error', $exception]);
        $service->retrieveBatch('foo', ['bar'], $params);
    }

    /**
     * Test random (with RandomInterface).
     *
     * @return void
     */
    public function testRandomInterface()
    {
        // Use a special backend for this test...
        $this->backend = $this->createMockBackend(\VuFindTest\TestClassForRandomInterface::class);

        $service = $this->getService();
        $backend = $this->getBackend();
        $response = $this->getRecordCollection();
        $params = new ParamBag(['x' => 'y']);
        $query = new Query('test');

        $backend->expects($this->once())->method('random')
            ->with(
                $this->equalTo($query),
                $this->equalTo("10"),
                $this->equalTo($params)
            )->will(
                $this->returnValue($response)
            );
        $em = $service->getEventManager();
        $em->expects($this->exactly(2))->method('trigger')
            ->withConsecutive(['pre', $backend], ['post', $response]);
        $service->random('foo', $query, "10", $params);

        // Put the backend back to the default:
        unset($this->backend);
    }

    /**
     * Test random (with RandomInterface) exception.
     *
     * @return void
     */
    public function testRandomInterfaceWithException()
    {
        $this->expectException(\VuFindSearch\Backend\Exception\BackendException::class);
        $this->expectExceptionMessage('test');

        // Use a special backend for this test...
        $this->backend = $this->createMockBackend(\VuFindTest\TestClassForRandomInterface::class);

        $service = $this->getService();
        $backend = $this->getBackend();
        $exception = new BackendException('test');
        $params = new ParamBag(['x' => 'y']);
        $query = new Query('test');

        $backend->expects($this->once())->method('random')
            ->with(
                $this->equalTo($query),
                $this->equalTo("10"),
                $this->equalTo($params)
            )->will($this->throwException($exception));

        $em = $service->getEventManager();
        $em->expects($this->exactly(2))->method('trigger')
            ->withConsecutive(['pre', $backend], ['error', $exception]);
        $service->random('foo', $query, "10", $params);

        // Put the backend back to the default:
        unset($this->backend);
    }

    /**
     * Test random (without RandomInterface).
     *
     * @return void
     */
    public function testRandomNoInterface()
    {
        $limit = 10;
        $total = 20;
        $service = $this->getService();
        $backend = $this->getBackend();
        $responseForZero = $this->getRecordCollection();
        $responseForZero->expects($this->once())->method('getTotal')
            ->will($this->returnValue($total));

        $params = new ParamBag(['x' => 'y']);
        $query = new Query('test');

        // First Search Grabs 0 records but uses get total method
        $inputs = [[$query, "0", "0", $params]];
        $outputs = [$responseForZero];

        for ($i = 1; $i < $limit + 1; $i++) {
            $response = $this->getRecordCollection();
            $response->expects($this->any())->method('first')
                ->will($this->returnValue($this->createMock(\VuFindSearch\Response\RecordInterface::class)));
            $inputs[] = [$query, $this->anything(), "1", $params];
            $outputs[] = $response;
        }

        $backend->expects($this->exactly(count($inputs)))->method('search')
            ->withConsecutive(...$inputs)
            ->willReturnOnConsecutiveCalls(...$outputs);

        $em = $service->getEventManager();
        $em->expects($this->exactly(2))->method('trigger')
            ->withConsecutive(['pre', $backend], ['post', $this->anything()]);
        $service->random('foo', $query, $limit, $params);
    }

    /**
     * Test random (without RandomInterface).
     *
     * @return void
     */
    public function testRandomNoInterfaceWithNoResults()
    {
        $limit = 10;
        $total = 0;
        $service = $this->getService();
        $backend = $this->getBackend();
        $responseForZero = $this->getRecordCollection();

        $params = new ParamBag(['x' => 'y']);
        $query = new Query('test');

        // First Search Grabs 0 records but uses get total method
        // This should only be called once as the total results returned is 0
        $backend->expects($this->once())->method('search')
            ->with(
                $this->equalTo($query),
                $this->equalTo("0"),
                $this->equalTo("0"),
                $this->equalTo($params)
            )->will($this->returnValue($responseForZero));

        $responseForZero->expects($this->once())->method('getTotal')
            ->will($this->returnValue($total));

        $em = $service->getEventManager();
        $em->expects($this->exactly(2))->method('trigger')
            ->withConsecutive(['pre', $backend], ['post', $responseForZero]);
        $service->random('foo', $query, $limit, $params);
    }

    /**
     * Test random (without RandomInterface).
     *
     * @return void
     */
    public function testRandomNoInterfaceWithLessResultsThanLimit()
    {
        $limit = 10;
        $total = 5;
        $service = $this->getService();
        $backend = $this->getBackend();
        $responseForZero = $this->getRecordCollection();
        $responseForZero->expects($this->once())->method('getTotal')
            ->will($this->returnValue($total));
        $response = $this->getRecordCollection();
        $response->expects($this->once())->method('shuffle');

        $params = new ParamBag(['x' => 'y']);
        $query = new Query('test');

        // First Search Grabs 0 records but uses get total method
        // Second search grabs all the records and calls shuffle
        $backend->expects($this->exactly(2))->method('search')
            ->withConsecutive([$query, "0", "0", $params], [$query, "0", $limit, $params])
            ->willReturnOnConsecutiveCalls($responseForZero, $response);

        $em = $service->getEventManager();
        $em->expects($this->exactly(2))->method('trigger')
            ->withConsecutive(['pre', $backend], ['post', $responseForZero]);
        $service->random('foo', $query, $limit, $params);
    }

    /**
     * Test random (without RandomInterface) exception.
     *
     * @return void
     */
    public function testRandomNoInterfaceWithExceptionAtFirstSearch()
    {
        $this->expectException(\VuFindSearch\Backend\Exception\BackendException::class);
        $this->expectExceptionMessage('test');

        $service = $this->getService();
        $backend = $this->getBackend();
        $exception = new BackendException('test');
        $params = new ParamBag(['x' => 'y']);
        $query = new Query('test');

        // Exception at first search
        $backend->expects($this->once())->method('search')
            ->will($this->throwException($exception));
        $em = $service->getEventManager();
        $em->expects($this->exactly(2))->method('trigger')
            ->withConsecutive(['pre', $backend], ['error', $exception]);
        $service->random('foo', $query, "10", $params);
    }

    /**
     * Test random (without RandomInterface) exception at item retrieval search.
     *
     * @return void
     */
    public function testRandomNoInterfaceWithExceptionAtItemSearch()
    {
        $this->expectException(\VuFindSearch\Backend\Exception\BackendException::class);
        $this->expectExceptionMessage('test');

        $limit = 10;
        $total = 20;
        $service = $this->getService();
        $backend = $this->getBackend();
        $responseForZero = $this->getRecordCollection();
        $responseForZero->expects($this->once())->method('getTotal')
            ->will($this->returnValue($total));
        $exception = new BackendException('test');

        $params = new ParamBag(['x' => 'y']);
        $query = new Query('test');

        // First Search Grabs 0 records but uses get total method
        // Exception at item search
        $backend->expects($this->exactly(2))->method('search')
            ->withConsecutive([$query, "0", "0", $params], [$this->anything()])
            ->willReturnOnConsecutiveCalls($responseForZero, $this->throwException($exception));

        $em = $service->getEventManager();
        $em->expects($this->exactly(2))->method('trigger')
            ->withConsecutive(['pre', $backend], ['error', $exception]);
        $service->random('foo', $query, "10", $params);
    }

    /**
     * Test random (without RandomInterface) exception with less results than limit.
     *
     * @return void
     */
    public function testRandomNoInterfaceExceptionWithLessResultsThanLimit()
    {
        $this->expectException(\VuFindSearch\Backend\Exception\BackendException::class);
        $this->expectExceptionMessage('test');

        $limit = 10;
        $total = 5;
        $service = $this->getService();
        $backend = $this->getBackend();
        $responseForZero = $this->getRecordCollection();
        $responseForZero->expects($this->once())->method('getTotal')
            ->will($this->returnValue($total));
        $response = $this->getRecordCollection();
        $exception = new BackendException('test');

        $params = new ParamBag(['x' => 'y']);
        $query = new Query('test');

        // First Search Grabs 0 records but uses get total method
        // Second search grabs all the records
        $backend->expects($this->exactly(2))->method('search')
            ->withConsecutive([$query, "0", "0", $params], [$this->anything()])
            ->willReturnOnConsecutiveCalls($responseForZero, $this->throwException($exception));

        $em = $service->getEventManager();
        $em->expects($this->exactly(2))->method('trigger')
            ->withConsecutive(['pre', $backend], ['error', $exception]);
        $service->random('foo', $query, $limit, $params);
    }

    /**
     * Test similar action.
     *
     * @return void
     */
    public function testSimilar()
    {
        // Use a special backend for this test...
        $this->backend = $this->createMockBackend(\VuFindTest\TestBackendClassForSimilar::class);

        $service = $this->getService();
        $backend = $this->getBackend();
        $response = 'fake';
        $params = new ParamBag(['x' => 'y']);
        $backend->expects($this->once())->method('similar')
            ->with($this->equalTo('bar'), $this->equalTo($params))
            ->will($this->returnValue($response));
        $em = $service->getEventManager();
        $em->expects($this->exactly(2))->method('trigger')
            ->withConsecutive(['pre', $backend], ['post', $response]);
        $service->similar('foo', 'bar', $params);

        // Put the backend back to the default:
        unset($this->backend);
    }

    /**
     * Test similar action on bad backend.
     *
     * @return void
     */
    public function testSimilarOnNonSupportingBackend()
    {
        $this->expectException(\VuFindSearch\Backend\Exception\BackendException::class);
        $this->expectExceptionMessage('foo does not support similar()');

        $service = $this->getService();
        $params = new ParamBag(['x' => 'y']);
        $service->similar('foo', 'bar', $params);
    }

    /**
     * Test exception-throwing similar action.
     *
     * @return void
     */
    public function testSimilarException()
    {
        $this->expectException(\VuFindSearch\Backend\Exception\BackendException::class);
        $this->expectExceptionMessage('test');

        // Use a special backend for this test...
        $this->backend = $this->createMockBackend(\VuFindTest\TestBackendClassForSimilar::class);

        $service = $this->getService();
        $backend = $this->getBackend();
        $params = new ParamBag(['x' => 'y']);
        $exception = new BackendException('test');
        $backend->expects($this->once())->method('similar')
            ->with($this->equalTo('bar'), $this->equalTo($params))
            ->will($this->throwException($exception));
        $em = $service->getEventManager();
        $em->expects($this->exactly(2))->method('trigger')
            ->withConsecutive(['pre', $backend], ['error', $exception]);
        $service->similar('foo', 'bar', $params);

        // Put the backend back to the default:
        unset($this->backend);
    }

    /**
     * Test a failure to resolve.
     *
     * @return void
     */
    public function testFailedResolve()
    {
        $this->expectException(\VuFindSearch\Exception\RuntimeException::class);
        $this->expectExceptionMessage('Unable to resolve backend: retrieve, junk');

        $mockResponse = $this->createMock(\Laminas\EventManager\ResponseCollection::class);
        $mockResponse->expects($this->any())->method('stopped')->will($this->returnValue(false));
        $em = $this->createMock(\Laminas\EventManager\EventManagerInterface::class);
        $service = new Service();
        $em->expects($this->any())->method('triggerUntil')
            ->with(
                $this->anything(),
                $this->equalTo('resolve'),
                $this->equalTo($service)
            )->will($this->returnValue($mockResponse));
        $service->setEventManager($em);
        $service->retrieve('junk', 'foo');
    }

    /**
     * Test a failure to resolve using a command object.
     *
     * @return void
     */
    public function testFailedResolveWithCommand()
    {
        $this->expectException(\VuFindSearch\Exception\RuntimeException::class);
        $this->expectExceptionMessage('Unable to resolve backend: getInfo, EDS');

        $mockResponse = $this->createMock(\Laminas\EventManager\ResponseCollection::class);
        $mockResponse->expects($this->any())->method('stopped')->will($this->returnValue(false));
        $em = $this->createMock(\Laminas\EventManager\EventManagerInterface::class);
        $service = new Service();
        $em->expects($this->any())->method('triggerUntil')
            ->with(
                $this->anything(),
                $this->equalTo('resolve'),
                $this->equalTo($service)
            )->will($this->returnValue($mockResponse));
        $service->setEventManager($em);
        $service->invoke(new \VuFindSearch\Backend\EDS\Command\GetInfoCommand());
    }

    // Internal API

    /**
     * Create a mock backend.
     */
    protected function createMockBackend(
        $class = \VuFindSearch\Backend\BackendInterface::class,
        $identifier = 'foo'
    ) {
        $backend = $this->createMock($class);
        $backend->method('getIdentifier')->will($this->returnValue($identifier));
        return $backend;
    }

    /**
     * Get a mock backend.
     *
     * @return BackendInterface
     */
    protected function getBackend()
    {
        if (!$this->backend) {
            $this->backend = $this->createMockBackend();
        }
        return $this->backend;
    }

    /**
     * Generate a fake service.
     *
     * @return Service
     */
    protected function getService()
    {
        $em = $this->createMock(\Laminas\EventManager\EventManagerInterface::class);
        $service = $this->getMockBuilder(Service::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['resolve'])
            ->getMock();
        $service->expects($this->any())->method('resolve')
            ->will($this->returnValue($this->getBackend()));
        $service->setEventManager($em);
        return $service;
    }

    /**
     * Generate a fake record collection.
     *
     * @param string $id ID of record to include in collection.
     *
     * @return AbstractRecordCollection
     */
    protected function getRecordCollection()
    {
        return $this->createMock(\VuFindSearch\Response\AbstractRecordCollection::class);
    }
}

/**
 * Stub class to test multiple interfaces.
 */
abstract class TestClassForGetIdsInterface
    implements BackendInterface, GetIdsInterface
{
}

/**
 * Stub class to test multiple interfaces.
 */
abstract class TestClassForRetrieveBatchInterface
    implements BackendInterface, RetrieveBatchInterface
{
}

/**
 * Stub class to test similar.
 */
abstract class TestBackendClassForSimilar
    implements BackendInterface, SimilarInterface
{
}

/**
 * Stub Class to test random interfaces.
 */
abstract class TestClassForRandomInterface
implements BackendInterface, RandomInterface
{
}
