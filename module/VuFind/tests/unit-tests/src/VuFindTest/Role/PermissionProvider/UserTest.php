<?php

/**
 * PermissionProvider User Test Class
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
 * @package  Tests
 * @author   Markus Beh <markus.beh@ub.uni-freiburg.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */

namespace VuFindTest\Role\PermissionProvider;

use LmcRbacMvc\Service\AuthorizationService;

/**
 * PermissionProvider User Test Class
 *
 * @category VuFind
 * @package  Tests
 * @author   Markus Beh <markus.beh@ub.uni-freiburg.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */
class UserTest extends \PHPUnit\Framework\TestCase
{
    /**
     * Current test user
     *
     * @var string
     */
    protected $testuser = 'testuser1';

    /**
     * User test data for testing.
     *
     * @var array
     */
    protected $userValueMap = [
        'testuser1' =>
        [
                ['username','mbeh'],
                ['email','markus.beh@ub.uni-freiburg.de'],
                ['college', 'Albert Ludwigs Universität Freiburg'],
        ],
        'testuser2' =>
        [
                ['username','mbeh2'],
                ['email','markus.beh@ub.uni-freiburg.de'],
                ['college', 'Villanova University'],
                ['major', 'alumni'],
        ],
    ];

    /**
     * Test single option with matching string
     *
     * @return void
     */
    public function testGetPermissions()
    {
        $this->check(
            'testuser1',
            ['college .*Freiburg'],
            ['loggedin']
        );

        $this->check(
            'testuser2',
            ['college .*Freiburg'],
            []
        );
    }

    /**
     * Test an invalid configuration
     *
     * @return void
     */
    public function testBadConfig()
    {
        $this->check(
            'testuser1',
            ['college'],
            []
        );
    }

    /**
     * Convenience method for executing similar tests
     *
     * @param string $testuser Name of testuser
     * @param array  $options  Options like settings in permissions.ini
     * @param array  $roles    Roles to return if match
     *
     * @return void
     */
    protected function check($testuser, $options, $roles)
    {
        $this->testuser
            = (isset($this->userValueMap[$testuser]))
            ? $testuser
            : 'testuser1';

        $auth = $this->getMockAuthorizationService();
        $permissionProvider = new \VuFind\Role\PermissionProvider\User($auth);

        $this->assertEquals(
            $roles,
            $permissionProvider->getPermissions($options)
        );
    }

    /**
     * Get a mock authorization service object
     *
     * @return AuthorizationService
     */
    protected function getMockAuthorizationService()
    {
        $authorizationService
            = $this->getMockBuilder(\LmcRbacMvc\Service\AuthorizationService::class)
            ->disableOriginalConstructor()
            ->getMock();
        $authorizationService
            ->method('getIdentity')
            ->will($this->returnValue($this->getMockUser()));

        return $authorizationService;
    }

    /**
     * Get a mock user object
     *
     * @return \VuFind\Db\Row\User
     */
    protected function getMockUser(): \VuFind\Db\Row\User
    {
        $user = $this->getMockBuilder(\VuFind\Db\Row\User::class)
            ->disableOriginalConstructor()
            ->getMock();
        $user->method('__get')
            ->will($this->returnValueMap($this->userValueMap[$this->testuser]));
        $user->method('offsetGet')
            ->will($this->returnValueMap($this->userValueMap[$this->testuser]));

        return $user;
    }
}
