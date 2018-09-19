<?php
/**
 * CheckRequestIsValid test class.
 *
 * PHP version 7
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
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301  USA
 *
 * @category VuFind
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */
namespace VuFindTest\AjaxHandler;

use VuFind\AjaxHandler\AbstractIlsAndUserActionFactory;
use VuFind\AjaxHandler\CheckRequestIsValid;
use VuFind\Auth\ILSAuthenticator;
use VuFind\ILS\Connection;
use VuFind\Session\Settings;
use Zend\ServiceManager\ServiceManager;

/**
 * CheckRequestIsValid test class.
 *
 * @category VuFind
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */
class CheckRequestIsValidTest extends \VuFindTest\Unit\AjaxHandlerTest
{
    /**
     * Set up a CheckRequestIsValid handler for testing.
     *
     * @param Settings         $ss      Session settings (or null for default)
     * @param Connection       $ils     ILS connection (or null for default)
     * @param ILSAuthenticator $ilsAuth ILS authenticator (or null for default)
     * @param User|bool        $user    Return value for isLoggedIn() in auth manager
     *
     * @return CheckRequestIsValid
     */
    protected function getHandler($ss = null, $ils = null, $ilsAuth = null,
        $user = false
    ) {
        // Create container
        $container = new ServiceManager();

        // Install or mock up services:
        $this->addServiceToContainer($container, 'VuFind\Session\Settings', $ss);
        $this->addServiceToContainer($container, 'VuFind\ILS\Connection', $ils);
        $this->addServiceToContainer(
            $container, 'VuFind\Auth\ILSAuthenticator', $ilsAuth
        );

        // Set up auth manager with user:
        $authManager = $this->getMockAuthManager($user);
        $container->setService('VuFind\Auth\Manager', $authManager);

        // Build the handler:
        $factory = new AbstractIlsAndUserActionFactory();
        return $factory($container, CheckRequestIsValid::class);
    }

    /**
     * Test the AJAX handler's response when no one is logged in.
     *
     * @return void
     */
    public function testLoggedOutUser()
    {
        $handler = $this->getHandler();
        $this->assertEquals(
            ['You must be logged in first', 401],
            $handler->handleRequest($this->getParamsHelper(['id' => 1, 'data' => 1]))
        );
    }

    /**
     * Test the AJAX handler's response when the query is empty.
     *
     * @return void
     */
    public function testEmptyQuery()
    {
        $handler = $this->getHandler(null, null, null, $this->getMockUser());
        $this->assertEquals(
            ['bulk_error_missing', 400],
            $handler->handleRequest($this->getParamsHelper())
        );
    }

    /**
     * Generic support function for successful request tests.
     *
     * @return void
     */
    protected function runSuccessfulTest($ilsMethod, $requestType = null)
    {
        $ilsAuth = $this->getMockService(
            'VuFind\Auth\ILSAuthenticator', ['storedCatalogLogin']
        );
        $ilsAuth->expects($this->once())->method('storedCatalogLogin')
            ->will($this->returnValue([3]));
        $ils = $this
            ->getMockService('VuFind\ILS\Connection', [$ilsMethod]);
        $ils->expects($this->once())->method($ilsMethod)
            ->with($this->equalTo(1), $this->equalTo(2), $this->equalTo([3]))
            ->will($this->returnValue(true));
        $handler = $this->getHandler(null, $ils, $ilsAuth, $this->getMockUser());
        $params = ['id' => 1, 'data' => 2, 'requestType' => $requestType];
        return $handler->handleRequest($this->getParamsHelper($params));
    }

    /**
     * Test a successful hold response.
     *
     * @return void
     */
    public function testHoldResponse()
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
    public function testILLResponse()
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
    public function testStorageResponse()
    {
        $this->assertEquals(
            [
                ['status' => true, 'msg' => 'storage_retrieval_request_place_text']
            ], $this->runSuccessfulTest(
                'checkStorageRetrievalRequestIsValid', 'StorageRetrievalRequest'
            )
        );
    }
}
