<?php

/**
 * Unit tests for Primo Permission Handler.
 *
 * PHP version 8
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
 * along with this program; if not, see
 * <https://www.gnu.org/licenses/>.
 *
 * @category VuFind
 * @package  Search
 * @author   Oliver Goldschmidt <o.goldschmidt@tuhh.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */

namespace VuFindTest\Search\Primo;

use Lmc\Rbac\Mvc\Service\AuthorizationService;
use VuFind\Search\Primo\PrimoPermissionHandler;

/**
 * Unit tests for Primo Permission Handler.
 *
 * @category VuFind
 * @package  Search
 * @author   Oliver Goldschmidt <o.goldschmidt@tuhh.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */
class PrimoPermissionHandlerTest extends \PHPUnit\Framework\TestCase
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
            'MEMBER' => 'primo.MEMBER',
            'DEFAULT' => 'primo.defaultRule',
        ],
        'defaultCode' => 'DEFAULT',
    ];

    /**
     * Sample configuration without default.
     *
     * @var array
     */
    protected $primoConfigWithoutDefault = [
        'onCampusRule' => [
            'MEMBER' => 'primo.MEMBER',
        ],
    ];

    /**
     * Sample configuration with default with onCampusRule.
     *
     * @var array
     */
    protected $primoConfigDefaultOnly = [
        'defaultCode' => 'DEFAULT',
        'onCampusRule' => [
            'DEFAULT' => 'primo.defaultRule',
        ],
    ];

    /**
     * Sample configuration with institution code.
     *
     * @var array
     */
    protected $primoConfigInstitutionCode = [
        'defaultCode' => 'DEFAULT',
        'onCampusRule' => [
            'DEFAULT' => 'primo.defaultRule',
            'MEMBER' => 'primo.isOnCampusAtMEMBER',
        ],
        'institutionCode' => [
            'MEMBER' => 'primo.isAtMEMBER',
        ],
    ];

    /**
     * Sample configuration without default, but with institutionCode setting.
     *
     * @var array
     */
    protected $primoConfigWithoutDefaultWithInstCode = [
        'onCampusRule' => [
            'MEMBER' => 'primo.isOnCampusAtMEMBER',
        ],
        'institutionCode' => [
            'MEMBER' => 'primo.isAtMEMBER',
        ],
    ];

    /**
     * Sample configuration with default only.
     *
     * @var array
     */
    protected $primoConfigDefaultOnlyNoOnCampusRule = [
        'defaultCode' => 'DEFAULT',
    ];

    /**
     * Setup.
     *
     * @return void
     */
    protected function setUp(): void
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
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('No institutionCode found.');

        new PrimoPermissionHandler(null);
    }

    /**
     * Test the handler without setting an authorization service.
     * This should always return false.
     *
     * @return void
     */
    public function testWithoutAuthorizationService()
    {
        $handler = new PrimoPermissionHandler($this->primoConfig);
        $this->assertEquals(false, $handler->hasPermission());
    }

    /**
     * Test the handler without setting an authorization service.
     * This should always return false.
     *
     * @return void
     */
    public function testWithoutAuthorizationServiceWithLaminasConfigObject()
    {
        $handler = new PrimoPermissionHandler(
            new \VuFind\Config\Config($this->primoConfig)
        );
        $this->assertEquals(false, $handler->hasPermission());
    }

    /**
     * Test the handler code if permission matches
     * This should return the actual institution code (depending on config)
     *
     * @return void
     */
    public function testHandlerCodeSuccessfull()
    {
        $handler = new PrimoPermissionHandler($this->primoConfig);
        $mockAuth = $this->getMockBuilder(AuthorizationService::class)
            ->disableOriginalConstructor()
            ->getMock();
        $mockAuth->expects($this->any())->method('isGranted')
            ->with($this->equalTo('primo.MEMBER'))
            ->willReturn(true);
        $handler->setAuthorizationService($mockAuth);

        $this->assertEquals('MEMBER', $handler->getInstCode());
    }

    /**
     * Test the handler if permission does not match
     * This should return the default institution code
     * (if this is configured, for this test it is configured)
     *
     * @return void
     */
    public function testHandlerDefaultCode()
    {
        $handler = new PrimoPermissionHandler($this->primoConfig);
        $mockAuth = $this->getMockBuilder(AuthorizationService::class)
            ->disableOriginalConstructor()
            ->getMock();
        $mockAuth->expects($this->any())->method('isGranted')
            ->with(
                $this->logicalOr(
                    $this->equalTo('primo.MEMBER'),
                    $this->equalTo('primo.defaultRule')
                )
            )
            ->willReturnCallback(
                [$this,
                'handlerMemberAuthNotSuccessfullCallback']
            );
        $handler->setAuthorizationService($mockAuth);

        $this->assertEquals('DEFAULT', $handler->getInstCode());
    }

    /**
     * Test the institution code setter
     *
     * @return void
     */
    public function testSetInstCode()
    {
        $handler = new PrimoPermissionHandler($this->primoConfig);
        $handler->setInstCode('MEMBER');
        $this->assertEquals('MEMBER', $handler->getInstCode());
    }

    /**
     * Test the handler if permission via member code matches
     *
     * @return void
     */
    public function testHandlerMemberAuthSuccessfull()
    {
        $handler = new PrimoPermissionHandler($this->primoConfig);
        $mockAuth = $this->getMockBuilder(AuthorizationService::class)
            ->disableOriginalConstructor()
            ->getMock();
        $mockAuth->expects($this->any())->method('isGranted')
            ->with(
                $this->logicalOr(
                    $this->equalTo('primo.MEMBER'),
                    $this->equalTo('primo.defaultRule')
                )
            )
            ->willReturnCallback(
                [$this,
                'handlerMemberAuthSuccessfullCallback']
            );
        $handler->setAuthorizationService($mockAuth);

        $this->assertEquals(true, $handler->hasPermission());
    }

    /**
     * Test the handler if permission (member and default) does not match
     *
     * @return void
     */
    public function testHandlerMemberAuthNotSuccessfull()
    {
        $handler = new PrimoPermissionHandler($this->primoConfig);
        $mockAuth = $this->getMockBuilder(AuthorizationService::class)
            ->disableOriginalConstructor()
            ->getMock();
        $mockAuth->expects($this->any())->method('isGranted')
            ->with(
                $this->logicalOr(
                    $this->equalTo('primo.MEMBER'),
                    $this->equalTo('primo.defaultRule')
                )
            )
            ->willReturnCallback(
                [$this,
                'handlerMemberAuthNotSuccessfullCallback']
            );
        $handler->setAuthorizationService($mockAuth);

        $this->assertEquals(false, $handler->hasPermission());
    }

    /**
     * Test the handler if permission does not match
     *
     * @return void
     */
    public function testHandlerDefaultAuthSuccessfull()
    {
        $handler = new PrimoPermissionHandler($this->primoConfig);
        $mockAuth = $this->getMockBuilder(AuthorizationService::class)
            ->disableOriginalConstructor()
            ->getMock();
        $mockAuth->expects($this->any())->method('isGranted')
            ->with(
                $this->logicalOr(
                    $this->equalTo('primo.MEMBER'),
                    $this->equalTo('primo.defaultRule')
                )
            )
            ->willReturnCallback(
                [$this,
                'handlerDefaultAuthSuccessfullCallback']
            );
        $handler->setAuthorizationService($mockAuth);

        $this->assertEquals(true, $handler->hasPermission());
    }

    /**
     * Test the handler if permission does not match
     *
     * @return void
     */
    public function testHandlerDefaultAuthNotSuccessfull()
    {
        $handler = new PrimoPermissionHandler($this->primoConfig);
        $mockAuth = $this->getMockBuilder(AuthorizationService::class)
            ->disableOriginalConstructor()
            ->getMock();
        $mockAuth->expects($this->any())->method('isGranted')
            ->with(
                $this->logicalOr(
                    $this->equalTo('primo.MEMBER'),
                    $this->equalTo('primo.defaultRule')
                )
            )
            ->willReturnCallback(
                [$this,
                'handlerDefaultAuthNotSuccessfullCallback']
            );
        $handler->setAuthorizationService($mockAuth);

        $this->assertEquals(false, $handler->hasPermission());
    }

    /**
     * Test the handler if permission does not exist
     *
     * @return void
     */
    public function testAuthNotExisting()
    {
        $handler = new PrimoPermissionHandler($this->primoConfig);
        $handler->setInstCode('NOTEXISTING');
        $mockAuth = $this->getMockBuilder(AuthorizationService::class)
            ->disableOriginalConstructor()
            ->getMock();
        $mockAuth->expects($this->any())->method('isGranted')
            ->willReturn(false);
        $handler->setAuthorizationService($mockAuth);

        $this->assertEquals(false, $handler->getInstCode());
        $this->assertEquals(false, $handler->hasPermission());
    }

    /*****************
     * Tests with configuration without default
     ************/

    /**
     * Test the handler if permission matches
     *
     * @return void
     */
    public function testHandlerWithoutDefaultAuthSuccessfull()
    {
        $handler = new PrimoPermissionHandler($this->primoConfigWithoutDefault);
        $mockAuth = $this->getMockBuilder(AuthorizationService::class)
            ->disableOriginalConstructor()
            ->getMock();
        $mockAuth->expects($this->any())->method('isGranted')
            ->with(
                $this->logicalOr(
                    $this->equalTo('primo.MEMBER'),
                    $this->equalTo('primo.defaultRule')
                )
            )
            ->willReturnCallback(
                [$this,
                'handlerMemberAuthSuccessfullCallback']
            );
        $handler->setAuthorizationService($mockAuth);

        $this->assertEquals(true, $handler->hasPermission());
    }

    /**
     * Test the handler if permission (member and default) does not match
     *
     * @return void
     */
    public function testHandlerWithoutDefaultAuthNotSuccessfull()
    {
        $handler = new PrimoPermissionHandler($this->primoConfigWithoutDefault);
        $mockAuth = $this->getMockBuilder(AuthorizationService::class)
            ->disableOriginalConstructor()
            ->getMock();
        $mockAuth->expects($this->any())->method('isGranted')
            ->with(
                $this->logicalOr(
                    $this->equalTo('primo.MEMBER'),
                    $this->equalTo('primo.defaultRule')
                )
            )
            ->willReturnCallback(
                [$this,
                'handlerMemberAuthNotSuccessfullCallback']
            );
        $handler->setAuthorizationService($mockAuth);

        $this->assertEquals(false, $handler->hasPermission());
    }

    /**
     * Test the handler code if permission matches
     *
     * @return void
     */
    public function testHandlerWithoutDefaultCodeSuccessfull()
    {
        $handler = new PrimoPermissionHandler($this->primoConfigWithoutDefault);
        $mockAuth = $this->getMockBuilder(AuthorizationService::class)
            ->disableOriginalConstructor()
            ->getMock();
        $mockAuth->expects($this->any())->method('isGranted')
            ->with($this->equalTo('primo.MEMBER'))
            ->willReturn(true);
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
        $mockAuth = $this->getMockBuilder(AuthorizationService::class)
            ->disableOriginalConstructor()
            ->getMock();
        $mockAuth->expects($this->any())->method('isGranted')
            ->with($this->equalTo('primo.MEMBER'))
            ->willReturn(false);
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
        $mockAuth = $this->getMockBuilder(AuthorizationService::class)
            ->disableOriginalConstructor()
            ->getMock();
        $mockAuth->expects($this->any())->method('isGranted')
            ->with(
                $this->logicalOr(
                    $this->equalTo('primo.MEMBER'),
                    $this->equalTo('primo.defaultRule')
                )
            )
            ->willReturnCallback(
                [$this,
                'handlerDefaultAuthSuccessfullCallback']
            );
        $handler->setAuthorizationService($mockAuth);

        $this->assertEquals(false, $handler->hasPermission());
    }

    /**
     * Test the handler if permission does not match
     *
     * @return void
     */
    public function testHandlerWithoutDefaultDefaultAuthNotSuccessfull()
    {
        $handler = new PrimoPermissionHandler($this->primoConfigWithoutDefault);
        $mockAuth = $this->getMockBuilder(AuthorizationService::class)
            ->disableOriginalConstructor()
            ->getMock();
        $mockAuth->expects($this->any())->method('isGranted')
            ->with(
                $this->logicalOr(
                    $this->equalTo('primo.MEMBER'),
                    $this->equalTo('primo.defaultRule')
                )
            )
            ->willReturnCallback(
                [$this,
                'handlerDefaultAuthNotSuccessfullCallback']
            );
        $handler->setAuthorizationService($mockAuth);

        $this->assertEquals(false, $handler->hasPermission());
    }

    /*****************
     * Tests with configuration with default and onCampusRule for default
     ************/

    /**
     * Test the handler if permission (member and default) does not match
     *
     * @return void
     */
    public function testHandlerDefaultOnlyAuthNotSuccessfull()
    {
        $handler = new PrimoPermissionHandler($this->primoConfigDefaultOnly);
        $mockAuth = $this->getMockBuilder(AuthorizationService::class)
            ->disableOriginalConstructor()
            ->getMock();
        $mockAuth->expects($this->any())->method('isGranted')
            ->with(
                $this->logicalOr(
                    $this->equalTo('primo.MEMBER'),
                    $this->equalTo('primo.defaultRule')
                )
            )
            ->willReturnCallback(
                [$this,
                'handlerMemberAuthNotSuccessfullCallback']
            );
        $handler->setAuthorizationService($mockAuth);

        $this->assertEquals(false, $handler->hasPermission());
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
        $mockAuth = $this->getMockBuilder(AuthorizationService::class)
            ->disableOriginalConstructor()
            ->getMock();
        $mockAuth->expects($this->any())->method('isGranted')
            ->with(
                $this->logicalOr(
                    $this->equalTo('primo.MEMBER'),
                    $this->equalTo('primo.defaultRule')
                )
            )
            ->willReturnCallback(
                [$this,
                'handlerDefaultAuthNotSuccessfullCallback']
            );
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
        $mockAuth = $this->getMockBuilder(AuthorizationService::class)
            ->disableOriginalConstructor()
            ->getMock();
        $mockAuth->expects($this->any())->method('isGranted')
            ->with(
                $this->logicalOr(
                    $this->equalTo('primo.MEMBER'),
                    $this->equalTo('primo.defaultRule')
                )
            )
            ->willReturnCallback(
                [$this,
                'handlerDefaultAuthSuccessfullCallback']
            );
        $handler->setAuthorizationService($mockAuth);

        $this->assertEquals(true, $handler->hasPermission());
    }

    /**
     * Test the handler if permission does not match
     *
     * @return void
     */
    public function testHandlerDefaultOnlyDefaultAuthNotSuccessfull()
    {
        $handler = new PrimoPermissionHandler($this->primoConfigDefaultOnly);
        $mockAuth = $this->getMockBuilder(AuthorizationService::class)
            ->disableOriginalConstructor()
            ->getMock();
        $mockAuth->expects($this->any())->method('isGranted')
            ->with(
                $this->logicalOr(
                    $this->equalTo('primo.MEMBER'),
                    $this->equalTo('primo.defaultRule')
                )
            )
            ->willReturnCallback(
                [$this,
                'handlerDefaultAuthNotSuccessfullCallback']
            );
        $handler->setAuthorizationService($mockAuth);

        $this->assertEquals(false, $handler->hasPermission());
    }

    /*****************
     * Tests with configuration with one member and onCampusRule
     ************/

    /**
     * Test the handler if permission (member and default) does not match
     *
     * @return void
     */
    public function testHandlerMemberIsOnCampusWithDefault()
    {
        $handler = new PrimoPermissionHandler($this->primoConfigInstitutionCode);
        $mockAuth = $this->getMockBuilder(AuthorizationService::class)
            ->disableOriginalConstructor()
            ->getMock();
        $mockAuth->expects($this->any())->method('isGranted')
            ->with(
                $this->logicalOr(
                    $this->equalTo('primo.defaultRule'),
                    $this->equalTo('primo.isAtMEMBER'),
                    $this->equalTo('primo.isOnCampusAtMEMBER')
                )
            )
            ->willReturnCallback(
                [$this,
                'handlerMemberIsOnCampusCallback']
            );
        $handler->setAuthorizationService($mockAuth);

        $this->assertEquals('MEMBER', $handler->getInstCode());
        $this->assertEquals(true, $handler->hasPermission());
    }

    /**
     * Test the handler if permission does not match
     * This should return the default PrimoInstance code
     * (if this is configured, for this test it is configured)
     *
     * @return void
     */
    public function testHandlerMemberIsNotOnCampusWithDefault()
    {
        $handler = new PrimoPermissionHandler($this->primoConfigInstitutionCode);
        $mockAuth = $this->getMockBuilder(AuthorizationService::class)
            ->disableOriginalConstructor()
            ->getMock();
        $mockAuth->expects($this->any())->method('isGranted')
            ->with(
                $this->logicalOr(
                    $this->equalTo('primo.defaultRule'),
                    $this->equalTo('primo.isAtMEMBER'),
                    $this->equalTo('primo.isOnCampusAtMEMBER')
                )
            )
            ->willReturnCallback(
                [$this,
                'handlerMemberIsNotOnCampusCallback']
            );
        $handler->setAuthorizationService($mockAuth);

        $this->assertEquals('MEMBER', $handler->getInstCode());
        $this->assertEquals(false, $handler->hasPermission());
    }

    /**
     * Test the handler if permission does not match
     *
     * @return void
     */
    public function testHandlerIsNotAMemberAndNotDefaultOnCampus()
    {
        $handler = new PrimoPermissionHandler($this->primoConfigInstitutionCode);
        $mockAuth = $this->getMockBuilder(AuthorizationService::class)
            ->disableOriginalConstructor()
            ->getMock();
        $mockAuth->expects($this->any())->method('isGranted')
            ->with(
                $this->logicalOr(
                    $this->equalTo('primo.defaultRule'),
                    $this->equalTo('primo.isAtMEMBER'),
                    $this->equalTo('primo.isOnCampusAtMEMBER')
                )
            )
            ->willReturnCallback(
                [$this,
                'handlerIsNotAMemberCallback']
            );
        $handler->setAuthorizationService($mockAuth);

        $this->assertEquals('DEFAULT', $handler->getInstCode());
        $this->assertEquals(false, $handler->hasPermission());
    }

    /**
     * Test the handler if permission does not exist
     *
     * @return void
     */
    public function testHandlerIsNotAMemberButOnDefaultCampus()
    {
        $handler = new PrimoPermissionHandler($this->primoConfigInstitutionCode);
        $mockAuth = $this->getMockBuilder(AuthorizationService::class)
            ->disableOriginalConstructor()
            ->getMock();
        $mockAuth->expects($this->any())->method('isGranted')
            ->with(
                $this->logicalOr(
                    $this->equalTo('primo.defaultRule'),
                    $this->equalTo('primo.isAtMEMBER'),
                    $this->equalTo('primo.isOnCampusAtMEMBER')
                )
            )
            ->willReturnCallback(
                [$this,
                'handlerIsOnDefaultCampusCallback']
            );
        $handler->setAuthorizationService($mockAuth);

        $this->assertEquals('DEFAULT', $handler->getInstCode());
        $this->assertEquals(true, $handler->hasPermission());
    }

    /*****************
     * Tests with configuration with one member and onCampusRule
     ************/

    /**
     * Test the handler if permission (member and default) does not match
     *
     * @return void
     */
    public function testHandlerMemberIsOnCampus()
    {
        $handler = new PrimoPermissionHandler(
            $this->primoConfigWithoutDefaultWithInstCode
        );
        $mockAuth = $this->getMockBuilder(AuthorizationService::class)
            ->disableOriginalConstructor()
            ->getMock();
        $mockAuth->expects($this->any())->method('isGranted')
            ->with(
                $this->logicalOr(
                    $this->equalTo('primo.isAtMEMBER'),
                    $this->equalTo('primo.isOnCampusAtMEMBER')
                )
            )
            ->willReturnCallback(
                [$this,
                'handlerMemberIsOnCampusCallback']
            );
        $handler->setAuthorizationService($mockAuth);

        $this->assertEquals('MEMBER', $handler->getInstCode());
        $this->assertEquals(true, $handler->hasPermission());
    }

    /**
     * Test the handler if permission does not match
     * This should return the default PrimoInstance code
     * (if this is configured, for this test it is configured)
     *
     * @return void
     */
    public function testHandlerMemberIsNotOnCampus()
    {
        $handler = new PrimoPermissionHandler(
            $this->primoConfigWithoutDefaultWithInstCode
        );
        $mockAuth = $this->getMockBuilder(AuthorizationService::class)
            ->disableOriginalConstructor()
            ->getMock();
        $mockAuth->expects($this->any())->method('isGranted')
            ->with(
                $this->logicalOr(
                    $this->equalTo('primo.isAtMEMBER'),
                    $this->equalTo('primo.isOnCampusAtMEMBER')
                )
            )
            ->willReturnCallback(
                [$this,
                'handlerMemberIsNotOnCampusCallback']
            );
        $handler->setAuthorizationService($mockAuth);

        $this->assertEquals('MEMBER', $handler->getInstCode());
        $this->assertEquals(false, $handler->hasPermission());
    }

    /**
     * Test the handler if permission does not match
     *
     * @return void
     */
    public function testHandlerIsNotAMember()
    {
        $handler = new PrimoPermissionHandler(
            $this->primoConfigWithoutDefaultWithInstCode
        );
        $mockAuth = $this->getMockBuilder(AuthorizationService::class)
            ->disableOriginalConstructor()
            ->getMock();
        $mockAuth->expects($this->any())->method('isGranted')
            ->with(
                $this->logicalOr(
                    $this->equalTo('primo.isAtMEMBER'),
                    $this->equalTo('primo.isOnCampusAtMEMBER')
                )
            )
            ->willReturnCallback(
                [$this,
                'handlerIsNotAMemberCallback']
            );
        $handler->setAuthorizationService($mockAuth);

        $this->assertEquals(false, $handler->getInstCode());
        $this->assertEquals(false, $handler->hasPermission());
    }

    /*****************
     * Tests with configuration with default only (no default onCampusRule)
     ************/

    /**
     * Permission cannot be granted without an onCampusRule
     *
     * @return void
     */
    public function testHandlerDefaultOnlyNoOncampus()
    {
        $handler = new PrimoPermissionHandler($this->primoConfigDefaultOnlyNoOnCampusRule);
        $mockAuth = $this->getMockBuilder(AuthorizationService::class)
            ->disableOriginalConstructor()
            ->getMock();
        $mockAuth->expects($this->any())->method('isGranted')
            ->willReturn(false);
        $handler->setAuthorizationService($mockAuth);

        $this->assertEquals(false, $handler->hasPermission());
    }

    /**
     * Test the handler if permission does not match
     * This should return the default PrimoInstance code
     * (if this is configured, for this test it is configured)
     *
     * @return void
     */
    public function testHandlerDefaultOnlyCodeNoOncampus()
    {
        $handler = new PrimoPermissionHandler($this->primoConfigDefaultOnlyNoOnCampusRule);
        $mockAuth = $this->getMockBuilder(AuthorizationService::class)
            ->disableOriginalConstructor()
            ->getMock();
        $mockAuth->expects($this->any())->method('isGranted')
            ->willReturn(false);
        $handler->setAuthorizationService($mockAuth);

        $this->assertEquals('DEFAULT', $handler->getInstCode());
    }

    /*****************
     * Callback helper functions
     ************/

    /**
     * Helper function (Callback) to inject different return values
     * for the mock object with different parameters
     *
     * @param string $param Parameter name
     *
     * @return bool
     */
    public function handlerMemberAuthNotSuccessfullCallback($param): bool
    {
        return false;
    }

    /**
     * Helper function (Callback) to inject different return values
     * for the mock object with different parameters
     *
     * @param string $param Parameter name
     *
     * @return bool
     */
    public function handlerMemberAuthSuccessfullCallback($param): bool
    {
        return $param == 'primo.MEMBER';
    }

    /**
     * Helper function (Callback) to inject different return values
     * for the mock object with different parameters
     *
     * @param string $param Parameter name
     *
     * @return bool
     */
    public function handlerDefaultAuthSuccessfullCallback($param): bool
    {
        return $param == 'primo.defaultRule';
    }

    /**
     * Helper function (Callback) to inject different return values
     * for the mock object with different parameters
     *
     * @param string $param Parameter name
     *
     * @return bool
     */
    public function handlerDefaultAuthNotSuccessfullCallback($param): bool
    {
        return false;
    }

    /**
     * Helper function (Callback) to inject different return values
     * for the mock object with different parameters
     *
     * @param string $param Parameter name
     *
     * @return bool
     */
    public function handlerMemberIsOnCampusCallback($param): bool
    {
        if ($param == 'primo.defaultRule') {
            return false;
        }
        if ($param == 'primo.isAtMEMBER') {
            return true;
        }
        return $param == 'primo.isOnCampusAtMEMBER';
    }

    /**
     * Helper function (Callback) to inject different return values
     * for the mock object with different parameters
     *
     * @param string $param Parameter name
     *
     * @return bool
     */
    public function handlerMemberIsNotOnCampusCallback($param): bool
    {
        if ($param == 'primo.defaultRule') {
            return false;
        }
        return $param == 'primo.isAtMEMBER';
    }

    /**
     * Helper function (Callback) to inject different return values
     * for the mock object with different parameters
     *
     * @param string $param Parameter name
     *
     * @return bool
     */
    public function handlerIsNotAMemberCallback($param): bool
    {
        return false;
    }

    /**
     * Helper function (Callback) to inject different return values
     * for the mock object with different parameters
     *
     * @param string $param Parameter name
     *
     * @return bool
     */
    public function handlerIsOnDefaultCampusCallback($param): bool
    {
        return $param == 'primo.defaultRule';
    }
}
