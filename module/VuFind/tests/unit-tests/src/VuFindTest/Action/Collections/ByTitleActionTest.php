<?php

/**
 * Collections ByTitleAction test class.
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2026.
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
 * along with this program; if not, see
 * <https://www.gnu.org/licenses/>.
 *
 * @category VuFind
 * @package  Tests
 * @author   Emmanuel Afuadajo <afuadajoe@gmail.com>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */

namespace VuFindTest\Action\Collections;

use Laminas\Diactoros\Response;
use Psr\Http\Message\ResponseInterface;
use VuFind\Action\Collections\ByTitleAction;
use VuFind\ActionHelper\RedirectHelper;
use VuFind\RecordDriver\AbstractBase as RecordDriver;
use VuFindSearch\Response\RecordCollectionInterface;

/**
 * Collections ByTitleAction test class.
 *
 * @category VuFind
 * @package  Tests
 * @author   Emmanuel Afuadajo <afuadajoe@gmail.com>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */
class ByTitleActionTest extends AbstractCollectionsActionTestCase
{
    /**
     * Get a mock search service whose title lookup returns the given record drivers.
     *
     * @param array $records Record drivers to return
     *
     * @return \VuFindSearch\Service
     */
    protected function getSearchServiceReturningRecords(array $records): \VuFindSearch\Service
    {
        $collection = $this->createMock(RecordCollectionInterface::class);
        $collection->method('getRecords')->willReturn($records);
        return $this->getMockSearchService($collection);
    }

    /**
     * Test that a single matching collection redirects straight to that collection.
     *
     * @return void
     */
    public function testSingleResultRedirectsToCollection(): void
    {
        $record = $this->createMock(RecordDriver::class);
        $record->method('getUniqueId')->willReturn('coll1');
        $searchService = $this->getSearchServiceReturningRecords([$record]);

        $expectedResponse = new Response();
        $redirectHelper = $this->createMock(RedirectHelper::class);
        $redirectHelper->expects($this->once())->method('redirectToRoute')
            ->with($this->isInstanceOf(ResponseInterface::class), 'collection', ['id' => 'coll1'])
            ->willReturn($expectedResponse);

        $action = $this->buildAction(
            ByTitleAction::class,
            [],
            $searchService,
            [RedirectHelper::class => $redirectHelper]
        );

        $result = $this->invokeAction($action, ['title' => 'Unique Collection']);
        $this->assertSame($expectedResponse, $result);
    }

    /**
     * Test that multiple matches render the list of collections.
     *
     * @return void
     */
    public function testMultipleResultsRenderList(): void
    {
        $records = [$this->createMock(RecordDriver::class), $this->createMock(RecordDriver::class)];
        $searchService = $this->getSearchServiceReturningRecords($records);

        $redirectHelper = $this->createMock(RedirectHelper::class);
        $redirectHelper->expects($this->never())->method('redirectToRoute');

        $action = $this->buildAction(
            ByTitleAction::class,
            [],
            $searchService,
            [RedirectHelper::class => $redirectHelper]
        );

        $this->invokeAction($action, ['title' => 'Common Title']);
        $this->assertSame($records, $this->capturedTemplateParams['collections']);
    }

    /**
     * Test that no matches render the empty list rather than redirecting.
     *
     * @return void
     */
    public function testNoResultsRenderList(): void
    {
        $searchService = $this->getSearchServiceReturningRecords([]);

        $redirectHelper = $this->createMock(RedirectHelper::class);
        $redirectHelper->expects($this->never())->method('redirectToRoute');

        $action = $this->buildAction(
            ByTitleAction::class,
            [],
            $searchService,
            [RedirectHelper::class => $redirectHelper]
        );

        $this->invokeAction($action, ['title' => 'Nonexistent']);
        $this->assertSame([], $this->capturedTemplateParams['collections']);
    }
}
