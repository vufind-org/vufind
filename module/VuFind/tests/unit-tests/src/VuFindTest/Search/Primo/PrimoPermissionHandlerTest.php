<?php

/**
 * Unit tests for Primo Permission Handler.
 *
 * PHP version 5
 *
 * Copyright (C) Villanova University 2015.
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
 * @author   Oliver Goldschmidt <o.goldschmidt@tuhh.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org   Main Site
 */
namespace VuFindTest\Search\Primo;

use VuFind\Search\Primo\PrimoPermissionHandler;
use VuFindTest\Unit\TestCase;

use ZfcRbac\Service\AuthorizationServiceAwareInterface,
    ZfcRbac\Service\AuthorizationServiceAwareTrait;

/**
 * Unit tests for Primo Permission Handler.
 *
 * @category VuFind2
 * @package  Search
 * @author   Oliver Goldschmidt <o.goldschmidt@tuhh.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org   Main Site
 */
class PrimoPermissionHandlerTest extends TestCase
{
    /**
     * Backend.
     *
     * @var BackendInterface
     */
    protected $backend;

    /**
     * Sample configuration.
     *
     * @var array
     */
    protected $primoConfig = [
        'onCampusRule' => [
            'MEMBER' => 'primo.MEMBER'
        ],
        'defaultCode' => 'DEFAULT',
        'defaultOnCampusRule' => 'primo.defaultRule'
    ];

    /**
     * Sample configuration without default.
     *
     * @var array
     */
    protected $primoConfigWithoutDefault = [
        'onCampusRule' => [
            'MEMBER' => 'primo.MEMBER'
        ]
    ];

    /**
     * Sample configuration.
     *
     * @var array
     */
    protected $primoConfigDefaultOnly = [
        'defaultCode' => 'DEFAULT',
        'defaultOnCampusRule' => 'primo.defaultRule'
    ];

    /**
     * Setup.
     *
     * @return void
     */
    protected function setup()
    {
    }

    /**
     * Test the handler without configuration.
     * This should throw an Exception.
     *
     * @return void
     */
    public function testWithoutConfig()
    {
$this->markTestSkipped();
        try {
            $handler = new PrimoPermissionHandler(null);
        } catch(Exception $e){
            $this->assertEquals(
                "The Primo Permission System has not been configured.
                Please configure section [InstitutionPermission] in Primo.ini.",
                $e->getMessage()
            );
        }
    }

    /**
     * Test the handler without setting an authorization service.
     * This should return false.
     *
     * @return void
     */
    public function testWithoutAuthorizationService()
    {
        $handler = new PrimoPermissionHandler($this->primoConfig);
        $this->assertEquals(false, $handler->hasPermission());
        $this->assertEquals(false, $handler->hasDefaultPermission());
    }

    /**
     * Test the handler if permission matches
     *
     * @return void
     */
    public function testHandlerAuthSuccessfull()
    {
        $handler = new PrimoPermissionHandler($this->primoConfig);
        $mockAuth = $this->getMockBuilder('ZfcRbac\Service\AuthorizationService')
            ->disableOriginalConstructor()
            ->getMock();
        $mockAuth->expects($this->any())->method('isGranted')
            ->with($this->equalTo('primo.MEMBER'))
            ->will($this->returnValue(true));
        $handler->setAuthorizationService($mockAuth);

        $this->assertEquals(true, $handler->hasPermission());
        $this->assertEquals(false, $handler->hasDefaultPermission());
    }

    /**
     * Test the handler if permission (member and deafult) does not match
     *
     * @return void
     */
    public function testHandlerAuthNotSuccessfull()
    {
        $handler = new PrimoPermissionHandler($this->primoConfig);
        $mockAuth = $this->getMockBuilder('ZfcRbac\Service\AuthorizationService')
            ->disableOriginalConstructor()
            ->getMock();
        $mockAuth->expects($this->any())->method('isGranted')
            ->with($this->logicalOr(
                $this->equalTo('primo.MEMBER'),
                $this->equalTo('primo.defaultRule')
            ))
            ->will($this->returnCallback([$this,
                'handlerAuthNotSuccessfullCallback']));
        $handler->setAuthorizationService($mockAuth);

        $this->assertEquals(false, $handler->hasPermission());
        $this->assertEquals(false, $handler->hasDefaultPermission());
    }

    /**
     * Helper function (Callback) to inject different return values
     * for the mock object with different parameters
     *
     * @return void
     */
    public function handlerAuthNotSuccessfullCallback($param) {
        if ($param == 'primo.MEMBER') {
            return false;
        }
        return false;
    }

    /**
     * Test the handler code if permission matches
     *
     * @return void
     */
    public function testHandlerCodeSuccessfull()
    {
        $handler = new PrimoPermissionHandler($this->primoConfig);
        $mockAuth = $this->getMockBuilder('ZfcRbac\Service\AuthorizationService')
            ->disableOriginalConstructor()
            ->getMock();
        $mockAuth->expects($this->any())->method('isGranted')
            ->with($this->equalTo('primo.MEMBER'))
            ->will($this->returnValue(true));
        $handler->setAuthorizationService($mockAuth);

        $this->assertEquals('MEMBER', $handler->getInstCode());
    }

    /**
     * Test the handler if permission does not match
     * This should return the default PrimoInstance code
     * (if this is configured, for this test it is configured)
     *
     * @return void
     */
    public function testHandlerCodeAuthNotSuccessfull()
    {
        $handler = new PrimoPermissionHandler($this->primoConfig);
        $mockAuth = $this->getMockBuilder('ZfcRbac\Service\AuthorizationService')
            ->disableOriginalConstructor()
            ->getMock();
        $mockAuth->expects($this->any())->method('isGranted')
            ->with($this->equalTo('primo.MEMBER'))
            ->will($this->returnValue(false));
        $handler->setAuthorizationService($mockAuth);

        $this->assertEquals('DEFAULT', $handler->getInstCode());
    }

    /**
     * Test the handler if permission does not match
     *
     * @return void
     */
    public function testHandlerDefaultAuthSuccessfull()
    {
        $handler = new PrimoPermissionHandler($this->primoConfig);
        $mockAuth = $this->getMockBuilder('ZfcRbac\Service\AuthorizationService')
            ->disableOriginalConstructor()
            ->getMock();
        $mockAuth->expects($this->any())->method('isGranted')
            ->with($this->logicalOr(
                $this->equalTo('primo.MEMBER'),
                $this->equalTo('primo.defaultRule')
            ))
            ->will($this->returnCallback([$this,
                'handlerDefaultAuthSuccessfullCallback']));
        $handler->setAuthorizationService($mockAuth);

        $this->assertEquals(true, $handler->hasPermission());
        $this->assertEquals(true, $handler->hasDefaultPermission());
    }

    /**
     * Helper function (Callback) to inject different return values
     * for the mock object with different parameters
     *
     * @return void
     */
    public function handlerDefaultAuthSuccessfullCallback($param) {
        if ($param == 'primo.MEMBER') {
            return false;
        }
        return true;
    }

    /**
     * Test the handler if permission does not match
     *
     * @return void
     */
    public function testHandlerDefaultAuthNotSuccessfull()
    {
        $handler = new PrimoPermissionHandler($this->primoConfig);
        $mockAuth = $this->getMockBuilder('ZfcRbac\Service\AuthorizationService')
            ->disableOriginalConstructor()
            ->getMock();
        $mockAuth->expects($this->any())->method('isGranted')
            ->with($this->logicalOr(
                $this->equalTo('primo.MEMBER'),
                $this->equalTo('primo.defaultRule')
            ))
            ->will($this->returnCallback([$this,
                'handlerDefaultAuthNotSuccessfullCallback']));
        $handler->setAuthorizationService($mockAuth);

        $this->assertEquals(false, $handler->hasPermission());
        $this->assertEquals(false, $handler->hasDefaultPermission());
    }

    /**
     * Helper function (Callback) to inject different return values
     * for the mock object with different parameters
     *
     * @return void
     */
    public function handlerDefaultAuthNotSuccessfullCallback($param) {
        if ($param == 'primo.MEMBER') {
            return false;
        }
        return false;
    }

    /**
     * Test the handler if permission does not exist
     *
     * @return void
     */
    public function testAuthNotExisting()
    {
        $handler = new PrimoPermissionHandler($this->primoConfig);
        $mockAuth = $this->getMockBuilder('ZfcRbac\Service\AuthorizationService')
            ->disableOriginalConstructor()
            ->getMock();
        $mockAuth->expects($this->any())->method('isGranted')
            ->will($this->returnValue(false));
        $handler->setAuthorizationService($mockAuth);

        $this->assertEquals('DEFAULT', $handler->getInstCode());
        $this->assertEquals(false, $handler->hasPermission());
        $this->assertEquals(false, $handler->hasDefaultPermission());
    }

    public function multiAuthCallback($multiAuth) {
        var_dump($multiAuth);
        return $multiAuth;
    }

    /***************** Tests with configuration without default ************/

    /**
     * Test the handler if permission matches
     *
     * @return void
     */
    public function testHandlerWithoutDefaultAuthSuccessfull()
    {
        $handler = new PrimoPermissionHandler($this->primoConfigWithoutDefault);
        $mockAuth = $this->getMockBuilder('ZfcRbac\Service\AuthorizationService')
            ->disableOriginalConstructor()
            ->getMock();
        $mockAuth->expects($this->any())->method('isGranted')
            ->with($this->equalTo('primo.MEMBER'))
            ->will($this->returnValue(true));
        $handler->setAuthorizationService($mockAuth);

        $this->assertEquals(true, $handler->hasPermission());
        $this->assertEquals(false, $handler->hasDefaultPermission());
    }

    /**
     * Test the handler if permission (member and deafult) does not match
     *
     * @return void
     */
    public function testHandlerWithoutDefaultAuthNotSuccessfull()
    {
        $handler = new PrimoPermissionHandler($this->primoConfigWithoutDefault);
        $mockAuth = $this->getMockBuilder('ZfcRbac\Service\AuthorizationService')
            ->disableOriginalConstructor()
            ->getMock();
        $mockAuth->expects($this->any())->method('isGranted')
            ->with($this->logicalOr(
                $this->equalTo('primo.MEMBER'),
                $this->equalTo('primo.defaultRule')
            ))
            ->will($this->returnCallback([$this,
                'handlerAuthNotSuccessfullCallback']));
        $handler->setAuthorizationService($mockAuth);

        $this->assertEquals(false, $handler->hasPermission());
        $this->assertEquals(false, $handler->hasDefaultPermission());
    }

    /**
     * Test the handler code if permission matches
     *
     * @return void
     */
    public function testHandlerWithoutDefaultCodeSuccessfull()
    {
        $handler = new PrimoPermissionHandler($this->primoConfigWithoutDefault);
        $mockAuth = $this->getMockBuilder('ZfcRbac\Service\AuthorizationService')
            ->disableOriginalConstructor()
            ->getMock();
        $mockAuth->expects($this->any())->method('isGranted')
            ->with($this->equalTo('primo.MEMBER'))
            ->will($this->returnValue(true));
        $handler->setAuthorizationService($mockAuth);

        $this->assertEquals('MEMBER', $handler->getInstCode());
    }

    /**
     * Test the handler if permission does not match
     * This should return the default PrimoInstance code
     * (if this is configured, for this test it is configured)
     *
     * @return void
     */
    public function testHandlerWithoutDefaultCodeAuthNotSuccessfull()
    {
        $handler = new PrimoPermissionHandler($this->primoConfigWithoutDefault);
        $mockAuth = $this->getMockBuilder('ZfcRbac\Service\AuthorizationService')
            ->disableOriginalConstructor()
            ->getMock();
        $mockAuth->expects($this->any())->method('isGranted')
            ->with($this->equalTo('primo.MEMBER'))
            ->will($this->returnValue(false));
        $handler->setAuthorizationService($mockAuth);

        $this->assertEquals(false, $handler->getInstCode());
    }

    /**
     * Test the handler if permission does not match
     *
     * @return void
     */
    public function testHandlerWithoutDefaultDefaultAuthSuccessfull()
    {
        $handler = new PrimoPermissionHandler($this->primoConfigWithoutDefault);
        $mockAuth = $this->getMockBuilder('ZfcRbac\Service\AuthorizationService')
            ->disableOriginalConstructor()
            ->getMock();
        $mockAuth->expects($this->any())->method('isGranted')
            ->with($this->logicalOr(
                $this->equalTo('primo.MEMBER'),
                $this->equalTo('primo.defaultRule')
            ))
            ->will($this->returnCallback([$this,
                'handlerDefaultAuthSuccessfullCallback']));
        $handler->setAuthorizationService($mockAuth);

        $this->assertEquals(false, $handler->hasPermission());
        $this->assertEquals(false, $handler->hasDefaultPermission());
    }

    /**
     * Test the handler if permission does not match
     *
     * @return void
     */
    public function testHandlerWithoutDefaultDefaultAuthNotSuccessfull()
    {
        $handler = new PrimoPermissionHandler($this->primoConfigWithoutDefault);
        $mockAuth = $this->getMockBuilder('ZfcRbac\Service\AuthorizationService')
            ->disableOriginalConstructor()
            ->getMock();
        $mockAuth->expects($this->any())->method('isGranted')
            ->with($this->logicalOr(
                $this->equalTo('primo.MEMBER'),
                $this->equalTo('primo.defaultRule')
            ))
            ->will($this->returnCallback([$this,
                'handlerDefaultAuthNotSuccessfullCallback']));
        $handler->setAuthorizationService($mockAuth);

        $this->assertEquals(false, $handler->hasPermission());
        $this->assertEquals(false, $handler->hasDefaultPermission());
    }

    /***************** Tests with configuration with default only ************/

    /**
     * Test the handler if permission (member and deafult) does not match
     *
     * @return void
     */
    public function testHandlerDefaultOnlyAuthNotSuccessfull()
    {
        $handler = new PrimoPermissionHandler($this->primoConfigDefaultOnly);
        $mockAuth = $this->getMockBuilder('ZfcRbac\Service\AuthorizationService')
            ->disableOriginalConstructor()
            ->getMock();
        $mockAuth->expects($this->any())->method('isGranted')
            ->with($this->logicalOr(
                $this->equalTo('primo.MEMBER'),
                $this->equalTo('primo.defaultRule')
            ))
            ->will($this->returnCallback([$this,
                'handlerAuthNotSuccessfullCallback']));
        $handler->setAuthorizationService($mockAuth);

        $this->assertEquals(false, $handler->hasPermission());
        $this->assertEquals(false, $handler->hasDefaultPermission());
    }

    /**
     * Test the handler if permission does not match
     * This should return the default PrimoInstance code
     * (if this is configured, for this test it is configured)
     *
     * @return void
     */
    public function testHandlerDefaultOnlyCodeAuthNotSuccessfull()
    {
        $handler = new PrimoPermissionHandler($this->primoConfigDefaultOnly);
        $mockAuth = $this->getMockBuilder('ZfcRbac\Service\AuthorizationService')
            ->disableOriginalConstructor()
            ->getMock();
        $mockAuth->expects($this->any())->method('isGranted')
            ->with($this->equalTo('primo.MEMBER'))
            ->will($this->returnValue(false));
        $handler->setAuthorizationService($mockAuth);

        $this->assertEquals('DEFAULT', $handler->getInstCode());
    }

    /**
     * Test the handler if permission does not match
     *
     * @return void
     */
    public function testHandlerDefaultOnlyDefaultAuthSuccessfull()
    {
        $handler = new PrimoPermissionHandler($this->primoConfigDefaultOnly);
        $mockAuth = $this->getMockBuilder('ZfcRbac\Service\AuthorizationService')
            ->disableOriginalConstructor()
            ->getMock();
        $mockAuth->expects($this->any())->method('isGranted')
            ->with($this->logicalOr(
                $this->equalTo('primo.MEMBER'),
                $this->equalTo('primo.defaultRule')
            ))
            ->will($this->returnCallback([$this,
                'handlerDefaultAuthSuccessfullCallback']));
        $handler->setAuthorizationService($mockAuth);

        $this->assertEquals(true, $handler->hasPermission());
        $this->assertEquals(true, $handler->hasDefaultPermission());
    }

    /**
     * Test the handler if permission does not match
     *
     * @return void
     */
    public function testHandlerDefaultOnlyDefaultAuthNotSuccessfull()
    {
        $handler = new PrimoPermissionHandler($this->primoConfigDefaultOnly);
        $mockAuth = $this->getMockBuilder('ZfcRbac\Service\AuthorizationService')
            ->disableOriginalConstructor()
            ->getMock();
        $mockAuth->expects($this->any())->method('isGranted')
            ->with($this->logicalOr(
                $this->equalTo('primo.MEMBER'),
                $this->equalTo('primo.defaultRule')
            ))
            ->will($this->returnCallback([$this,
                'handlerDefaultAuthNotSuccessfullCallback']));
        $handler->setAuthorizationService($mockAuth);

        $this->assertEquals(false, $handler->hasPermission());
        $this->assertEquals(false, $handler->hasDefaultPermission());
    }
}
