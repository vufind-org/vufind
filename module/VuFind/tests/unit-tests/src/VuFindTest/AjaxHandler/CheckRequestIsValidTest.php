<?php

/**
 * CheckRequestIsValid test class.
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2018.
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
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */

namespace VuFindTest\AjaxHandler;

use VuFind\AjaxHandler\CheckRequestIsValid;
use VuFind\Application\CurrentContext;
use VuFind\Auth\ILSAuthenticator;
use VuFind\Auth\Manager;
use VuFind\Db\Entity\UserEntityInterface;
use VuFind\ILS\Connection;
use VuFindTest\Feature\AutowireTrait;

/**
 * CheckRequestIsValid test class.
 *
 * @category VuFind
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */
class CheckRequestIsValidTest extends \VuFindTest\Unit\AjaxHandlerTestCase
{
    use AutowireTrait;

    /**
     * Set up a CheckRequestIsValid handler for testing.
     *
     * @param ?UserEntityInterface $user Return value for getUserObject() in auth manager
     *
     * @return CheckRequestIsValid
     */
    protected function getHandler(?UserEntityInterface $user = null): CheckRequestIsValid
    {
        // Set up current context with user:
        $currentContext = $this->createMock(CurrentContext::class);
        $currentContext->method('get')
            ->with('CurrentUser')
            ->willReturn($user);
        $this->container->set(CurrentContext::class, $currentContext);

        // Build the handler:
        return $this->getAutowiredObject(CheckRequestIsValid::class, $this->container);
    }

    /**
     * Test the AJAX handler's response when no one is logged in.
     *
     * @return void
     */
    public function testLoggedOutUser(): void
    {
        $handler = $this->getHandler();
        $this->assertSame(
            ['You must be logged in first', 401],
            $handler->handleRequest($this->getRequest(['id' => 1, 'data' => 1]))
        );
    }

    /**
     * Test the AJAX handler's response when the query is empty.
     *
     * @return void
     */
    public function testEmptyQuery(): void
    {
        $handler = $this->getHandler($this->getMockUser());
        $this->assertSame(
            ['bulk_error_missing', 400],
            $handler->handleRequest($this->getRequest())
        );
    }

    /**
     * Generic support function for successful request tests.
     *
     * @param string $ilsMethod   ILS method to mock
     * @param string $requestType Request type for params helper
     *
     * @return array
     */
    protected function runSuccessfulTest($ilsMethod, $requestType = ''): array
    {
        $ilsAuth = $this->container
            ->createMock(ILSAuthenticator::class, ['storedCatalogLogin']);
        $ilsAuth->expects($this->once())->method('storedCatalogLogin')->willReturn([3]);
        $ils = $this->container->createMock(Connection::class, [$ilsMethod]);
        $ils->expects($this->once())->method($ilsMethod)
            ->with(1, 2, [3])
            ->willReturn(true);
        $this->container->set(Connection::class, $ils);
        $this->container->set(ILSAuthenticator::class, $ilsAuth);
        $handler = $this->getHandler($this->getMockUser());
        $params = ['id' => 1, 'data' => 2, 'requestType' => $requestType];
        return $handler->handleRequest($this->getRequest($params));
    }

    /**
     * Test a successful hold response.
     *
     * @return void
     */
    public function testHoldResponse(): void
    {
        $this->assertEquals(
            [['status' => true, 'msg' => 'request_place_text']],
            $this->runSuccessfulTest('checkRequestIsValid')
        );
    }

    /**
     * Test a successful ILL response.
     *
     * @return void
     */
    public function testILLResponse(): void
    {
        $this->assertEquals(
            [['status' => true, 'msg' => 'ill_request_place_text']],
            $this->runSuccessfulTest('checkILLRequestIsValid', 'ILLRequest')
        );
    }

    /**
     * Test a successful storage retrieval response.
     *
     * @return void
     */
    public function testStorageResponse(): void
    {
        $this->assertEquals(
            [
                ['status' => true, 'msg' => 'storage_retrieval_request_place_text'],
            ],
            $this->runSuccessfulTest(
                'checkStorageRetrievalRequestIsValid',
                'StorageRetrievalRequest'
            )
        );
    }
}
