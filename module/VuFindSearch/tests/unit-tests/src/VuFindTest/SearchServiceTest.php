<?php

/**
 * Unit tests for search service.
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
namespace VuFindTest;

use VuFindSearch\Service;
use VuFindSearch\ParamBag;
use VuFindSearch\Backend\BackendInterface;
use VuFindSearch\Backend\Exception\BackendException;
use VuFindSearch\Feature\RetrieveBatchInterface;
use VuFindSearch\Feature\RandomInterface;
use VuFindSearch\Feature\SimilarInterface;
use VuFindSearch\Query\Query;
use VuFindSearch\Response\AbstractRecordCollection;

use PHPUnit_Framework_TestCase as TestCase;

/**
 * Unit tests for search service.
 *
 * @category VuFind2
 * @package  Search
 * @author   David Maus <maus@hab.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org
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
        $em->expects($this->at(0))->method('trigger')
            ->with($this->equalTo('pre'), $this->equalTo($backend));
        $em->expects($this->at(1))->method('trigger')
            ->with($this->equalTo('post'), $this->equalTo($response));
        $service->retrieve('foo', 'bar', $params);
    }

    /**
     * Test exception-throwing retrieve action.
     *
     * @return void
     *
     * @expectedException        VuFindSearch\Backend\Exception\BackendException
     * @expectedExceptionMessage test
     */
    public function testRetrieveException()
    {
        $service = $this->getService();
        $backend = $this->getBackend();
        $params = new ParamBag(['x' => 'y']);
        $exception = new BackendException('test');
        $backend->expects($this->once())->method('retrieve')
            ->with($this->equalTo('bar'), $this->equalTo($params))
            ->will($this->throwException($exception));
        $em = $service->getEventManager();
        $em->expects($this->at(0))->method('trigger')
            ->with($this->equalTo('pre'), $this->equalTo($backend));
        $em->expects($this->at(1))->method('trigger')
            ->with($this->equalTo('error'), $this->equalTo($exception));
        $service->retrieve('foo', 'bar', $params);
    }

    /**
     * Test exception-throwing search action.
     *
     * @return void
     *
     * @expectedException        VuFindSearch\Backend\Exception\BackendException
     * @expectedExceptionMessage test
     */
    public function testSearchException()
    {
        $service = $this->getService();
        $backend = $this->getBackend();
        $exception = new BackendException('test');
        $backend->expects($this->once())->method('search')
            ->will($this->throwException($exception));
        $em = $service->getEventManager();
        $em->expects($this->at(0))->method('trigger')
            ->with($this->equalTo('pre'), $this->equalTo($backend));
        $em->expects($this->at(1))->method('trigger')
            ->with($this->equalTo('error'), $this->equalTo($exception));
        $service->search('foo', new Query('test'));
    }

    /**
     * Test batch retrieve (with RetrieveBatchInterface).
     *
     * @return void
     */
    public function testRetrieveBatchInterface()
    {
        // Use a special backend for this test...
        $this->backend = $this->getMock('VuFindTest\TestClassForRetrieveBatchInterface');

        $service = $this->getService();
        $backend = $this->getBackend();
        $params = new ParamBag(['x' => 'y']);
        $ids = ['bar', 'baz'];
        $backend->expects($this->once(0))->method('retrieveBatch')
            ->with($this->equalTo($ids), $this->equalTo($params))
            ->will($this->returnValue('response'));
        $em = $service->getEventManager();
        $em->expects($this->at(0))->method('trigger')
            ->with($this->equalTo('pre'), $this->equalTo($backend));
        $em->expects($this->at(1))->method('trigger')
            ->with($this->equalTo('post'), $this->equalTo('response'));
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
        $mockRecord = $this->getMock('VuFindSearch\Response\RecordInterface');
        $response1 = $this->getRecordCollection();
        $response1->expects($this->once())->method('add')
            ->with($this->equalTo($mockRecord));
        $response2 = $this->getRecordCollection();
        $response2->expects($this->once())->method('first')
            ->will($this->returnValue($mockRecord));
        $params = new ParamBag(['x' => 'y']);
        $backend->expects($this->at(0))->method('retrieve')
            ->with($this->equalTo('bar'), $this->equalTo($params))
            ->will($this->returnValue($response1));
        $backend->expects($this->at(1))->method('retrieve')
            ->with($this->equalTo('baz'), $this->equalTo($params))
            ->will($this->returnValue($response2));
        $em = $service->getEventManager();
        $em->expects($this->at(0))->method('trigger')
            ->with($this->equalTo('pre'), $this->equalTo($backend));
        $em->expects($this->at(1))->method('trigger')
            ->with($this->equalTo('post'), $this->equalTo($response1));
        $service->retrieveBatch('foo', ['bar', 'baz'], $params);
    }

    /**
     * Test exception-throwing batch retrieve action (with RetrieveBatchInterface).
     *
     * @return void
     *
     * @expectedException        VuFindSearch\Backend\Exception\BackendException
     * @expectedExceptionMessage test
     */
    public function testRetrieveBatchInterfaceException()
    {
        // Use a special backend for this test...
        $this->backend = $this->getMock('VuFindTest\TestClassForRetrieveBatchInterface');

        $service = $this->getService();
        $backend = $this->getBackend();
        $params = new ParamBag(['x' => 'y']);
        $exception = new BackendException('test');
        $ids = ['bar', 'baz'];
        $backend->expects($this->once(0))->method('retrieveBatch')
            ->with($this->equalTo($ids), $this->equalTo($params))
            ->will($this->throwException($exception));
        $em = $service->getEventManager();
        $em->expects($this->at(0))->method('trigger')
            ->with($this->equalTo('pre'), $this->equalTo($backend));
        $em->expects($this->at(1))->method('trigger')
            ->with($this->equalTo('error'), $this->equalTo($exception));
        $service->retrieveBatch('foo', $ids, $params);

        // Put the backend back to the default:
        unset($this->backend);
    }

    /**
     * Test exception-throwing batch retrieve action (without
     * RetrieveBatchInterface).
     *
     * @return void
     *
     * @expectedException        VuFindSearch\Backend\Exception\BackendException
     * @expectedExceptionMessage test
     */
    public function testRetrieveBatchNoInterfaceException()
    {
        $service = $this->getService();
        $backend = $this->getBackend();
        $params = new ParamBag(['x' => 'y']);
        $exception = new BackendException('test');
        $backend->expects($this->once())->method('retrieve')
            ->with($this->equalTo('bar'), $this->equalTo($params))
            ->will($this->throwException($exception));
        $em = $service->getEventManager();
        $em->expects($this->at(0))->method('trigger')
            ->with($this->equalTo('pre'), $this->equalTo($backend));
        $em->expects($this->at(1))->method('trigger')
            ->with($this->equalTo('error'), $this->equalTo($exception));
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
        $this->backend = $this->getMock('VuFindTest\TestClassForRandomInterface');

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
        $em->expects($this->at(0))->method('trigger')
            ->with($this->equalTo('pre'), $this->equalTo($backend));
        $em->expects($this->at(1))->method('trigger')
            ->with($this->equalTo('post'), $this->equalTo($response));
        $service->random('foo', $query, "10", $params);

        // Put the backend back to the default:
        unset($this->backend);
    }

    /**
     * Test random (with RandomInterface) exception.
     *
     * @return void
     *
     * @expectedException        VuFindSearch\Backend\Exception\BackendException
     * @expectedExceptionMessage test
     */
    public function testRandomInterfaceWithException()
    {
        // Use a special backend for this test...
        $this->backend = $this->getMock('VuFindTest\TestClassForRandomInterface');

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
        $em->expects($this->at(0))->method('trigger')
            ->with($this->equalTo('pre'), $this->equalTo($backend));
        $em->expects($this->at(1))->method('trigger')
            ->with($this->equalTo('error'), $this->equalTo($exception));
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

        $params = new ParamBag(['x' => 'y']);
        $query = new Query('test');

        // First Search Grabs 0 records but uses get total method
        $backend->expects($this->at(0))->method('search')
            ->with(
                $this->equalTo($query),
                $this->equalTo("0"),
                $this->equalTo("0"),
                $this->equalTo($params)
            )->will($this->returnValue($responseForZero));

        $responseForZero->expects($this->once())->method('getTotal')
            ->will($this->returnValue($total));

        for ($i = 1; $i < $limit + 1; $i++) {
            $response = $this->getRecordCollection();
            $response->expects($this->any())->method('first')
                ->will($this->returnValue($this->getMock('VuFindSearch\Response\RecordInterface')));
            $backend->expects($this->at($i))->method('search')
                ->with(
                    $this->equalTo($query),
                    $this->anything(),
                    $this->equalTo("1"),
                    $this->equalTo($params)
                )->will(
                    $this->returnValue($response)
                );
        }

        $em = $service->getEventManager();
        $em->expects($this->at(0))->method('trigger')
            ->with($this->equalTo('pre'), $this->equalTo($backend));
        $em->expects($this->at(1))->method('trigger')
            ->with($this->equalTo('post'), $this->anything());
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
        $em->expects($this->at(0))->method('trigger')
            ->with($this->equalTo('pre'), $this->equalTo($backend));
        $em->expects($this->at(1))->method('trigger')
            ->with($this->equalTo('post'), $this->equalTo($responseForZero));
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
        $response = $this->getRecordCollection();

        $params = new ParamBag(['x' => 'y']);
        $query = new Query('test');

        // First Search Grabs 0 records but uses get total method
        $backend->expects($this->at(0))->method('search')
            ->with(
                $this->equalTo($query),
                $this->equalTo("0"),
                $this->equalTo("0"),
                $this->equalTo($params)
            )->will($this->returnValue($responseForZero));

        $responseForZero->expects($this->once())->method('getTotal')
            ->will($this->returnValue($total));

        // Second search grabs all the records and calls shuffle
        $backend->expects($this->at(1))->method('search')
            ->with(
                $this->equalTo($query),
                $this->equalTo("0"),
                $this->equalTo($limit),
                $this->equalTo($params)
            )->will($this->returnValue($response));
        $response->expects($this->once())->method('shuffle');

        $em = $service->getEventManager();
        $em->expects($this->at(0))->method('trigger')
            ->with($this->equalTo('pre'), $this->equalTo($backend));
        $em->expects($this->at(1))->method('trigger')
            ->with($this->equalTo('post'), $this->equalTo($responseForZero));
        $service->random('foo', $query, $limit, $params);
    }

    /**
     * Test random (without RandomInterface) exception.
     *
     * @return void
     *
     * @expectedException        VuFindSearch\Backend\Exception\BackendException
     * @expectedExceptionMessage test
     */
    public function testRandomNoInterfaceWithExceptionAtFirstSearch()
    {
        $service = $this->getService();
        $backend = $this->getBackend();
        $exception = new BackendException('test');
        $params = new ParamBag(['x' => 'y']);
        $query = new Query('test');

        // Exception at first search
        $backend->expects($this->once())->method('search')
            ->will($this->throwException($exception));
        $em = $service->getEventManager();
        $em->expects($this->at(0))->method('trigger')
            ->with($this->equalTo('pre'), $this->equalTo($backend));
        $em->expects($this->at(1))->method('trigger')
            ->with($this->equalTo('error'), $this->equalTo($exception));
        $service->random('foo', $query, "10", $params);
    }

    /**
     * Test random (without RandomInterface) exception at item retrieval search.
     *
     * @return void
     *
     * @expectedException        VuFindSearch\Backend\Exception\BackendException
     * @expectedExceptionMessage test
     */
    public function testRandomNoInterfaceWithExceptionAtItemSearch()
    {
        $limit = 10;
        $total = 20;
        $service = $this->getService();
        $backend = $this->getBackend();
        $responseForZero = $this->getRecordCollection();
        $exception = new BackendException('test');

        $params = new ParamBag(['x' => 'y']);
        $query = new Query('test');

        // First Search Grabs 0 records but uses get total method
        $backend->expects($this->at(0))->method('search')
            ->with(
                $this->equalTo($query),
                $this->equalTo("0"),
                $this->equalTo("0"),
                $this->equalTo($params)
            )->will($this->returnValue($responseForZero));

        $responseForZero->expects($this->once())->method('getTotal')
            ->will($this->returnValue($total));

        // Exception at item search
        $backend->expects($this->at(1))->method('search')
            ->will($this->throwException($exception));
        $em = $service->getEventManager();
        $em->expects($this->at(0))->method('trigger')
            ->with($this->equalTo('pre'), $this->equalTo($backend));
        $em->expects($this->at(1))->method('trigger')
            ->with($this->equalTo('error'), $this->equalTo($exception));
        $service->random('foo', $query, "10", $params);
    }

    /**
     * Test random (without RandomInterface) exception with less results than limit.
     *
     * @return void
     *
     * @expectedException        VuFindSearch\Backend\Exception\BackendException
     * @expectedExceptionMessage test
     */
    public function testRandomNoInterfaceExceptionWithLessResultsThanLimit()
    {
        $limit = 10;
        $total = 5;
        $service = $this->getService();
        $backend = $this->getBackend();
        $responseForZero = $this->getRecordCollection();
        $response = $this->getRecordCollection();
        $exception = new BackendException('test');

        $params = new ParamBag(['x' => 'y']);
        $query = new Query('test');

        // First Search Grabs 0 records but uses get total method
        $backend->expects($this->at(0))->method('search')
            ->with(
                $this->equalTo($query),
                $this->equalTo("0"),
                $this->equalTo("0"),
                $this->equalTo($params)
            )->will($this->returnValue($responseForZero));

        $responseForZero->expects($this->once())->method('getTotal')
            ->will($this->returnValue($total));

        // Second search grabs all the records
        $backend->expects($this->at(1))->method('search')
            ->will($this->throwException($exception));

        $em = $service->getEventManager();
        $em->expects($this->at(0))->method('trigger')
            ->with($this->equalTo('pre'), $this->equalTo($backend));
        $em->expects($this->at(1))->method('trigger')
            ->with($this->equalTo('error'), $this->equalTo($exception));
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
        $this->backend = $this->getMock('VuFindTest\TestBackendClassForSimilar');

        $service = $this->getService();
        $backend = $this->getBackend();
        $response = 'fake';
        $params = new ParamBag(['x' => 'y']);
        $backend->expects($this->once())->method('similar')
            ->with($this->equalTo('bar'), $this->equalTo($params))
            ->will($this->returnValue($response));
        $em = $service->getEventManager();
        $em->expects($this->at(0))->method('trigger')
            ->with($this->equalTo('pre'), $this->equalTo($backend));
        $em->expects($this->at(1))->method('trigger')
            ->with($this->equalTo('post'), $this->equalTo($response));
        $service->similar('foo', 'bar', $params);

        // Put the backend back to the default:
        unset($this->backend);
    }

    /**
     * Test similar action on bad backend.
     *
     * @return void
     *
     * @expectedException        VuFindSearch\Backend\Exception\BackendException
     * @expectedExceptionMessage foo does not support similar()
     */
    public function testSimilarOnNonSupportingBackend()
    {
        $service = $this->getService();
        $params = new ParamBag(['x' => 'y']);
        $service->similar('foo', 'bar', $params);
    }

    /**
     * Test exception-throwing similar action.
     *
     * @return void
     *
     * @expectedException        VuFindSearch\Backend\Exception\BackendException
     * @expectedExceptionMessage test
     */
    public function testSimilarException()
    {
        // Use a special backend for this test...
        $this->backend = $this->getMock('VuFindTest\TestBackendClassForSimilar');

        $service = $this->getService();
        $backend = $this->getBackend();
        $params = new ParamBag(['x' => 'y']);
        $exception = new BackendException('test');
        $backend->expects($this->once())->method('similar')
            ->with($this->equalTo('bar'), $this->equalTo($params))
            ->will($this->throwException($exception));
        $em = $service->getEventManager();
        $em->expects($this->at(0))->method('trigger')
            ->with($this->equalTo('pre'), $this->equalTo($backend));
        $em->expects($this->at(1))->method('trigger')
            ->with($this->equalTo('error'), $this->equalTo($exception));
        $service->similar('foo', 'bar', $params);

        // Put the backend back to the default:
        unset($this->backend);
    }

    /**
     * Test a failure to resolve.
     *
     * @return void
     *
     * @expectedException        VuFindSearch\Exception\RuntimeException
     * @expectedExceptionMessage Unable to resolve backend: retrieve, junk
     */
    public function testFailedResolve()
    {
        $mockResponse = $this->getMock('Zend\EventManager\ResponseCollection');
        $mockResponse->expects($this->any())->method('stopped')->will($this->returnValue(false));
        $em = $this->getMock('Zend\EventManager\EventManagerInterface');
        $service = new Service();
        $em->expects($this->any())->method('trigger')
            ->with($this->equalTo('resolve'), $this->equalTo($service))
            ->will($this->returnValue($mockResponse));
        $service->setEventManager($em);
        $service->retrieve('junk', 'foo');
    }

    // Internal API

    /**
     * Get a mock backend.
     *
     * @return BackendInterface
     */
    protected function getBackend()
    {
        if (!$this->backend) {
            $this->backend = $this->getMock('VuFindSearch\Backend\BackendInterface');
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
        $em = $this->getMock('Zend\EventManager\EventManagerInterface');
        $service = $this->getMock('VuFindSearch\Service', ['resolve']);
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
        return $this->getMock('VuFindSearch\Response\AbstractRecordCollection');
    }
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