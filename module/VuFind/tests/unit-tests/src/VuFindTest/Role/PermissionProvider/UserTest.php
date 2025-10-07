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
 * along with this program; if not, see
 * <https://www.gnu.org/licenses/>.
 *
 * @category VuFind
 * @package  Tests
 * @author   Markus Beh <markus.beh@ub.uni-freiburg.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */

namespace VuFindTest\Role\PermissionProvider;

use Lmc\Rbac\Mvc\Service\AuthorizationService;
use PHPUnit\Framework\MockObject\MockObject;
use VuFind\Db\Entity\UserEntityInterface;

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
            ['username', 'mbeh'],
            ['email', 'markus.beh@ub.uni-freiburg.de'],
            ['college', 'Albert Ludwigs Universität Freiburg'],
        ],
        'testuser2' =>
        [
            ['username', 'mbeh2'],
            ['email', 'markus.beh@ub.uni-freiburg.de'],
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
        $authorizationService = $this->createMock(AuthorizationService::class);
        $authorizationService
            ->method('getIdentity')
            ->willReturn($this->getMockUser());

        return $authorizationService;
    }

    /**
     * Get a mock user object
     *
     * @return UserEntityInterface&MockObject
     */
    protected function getMockUser(): UserEntityInterface&MockObject
    {
        $user = $this->createMock(UserEntityInterface::class);

        // Dynamically mock getter methods
        foreach ($this->userValueMap[$this->testuser] ?? [] as $entry) {
            [$property, $value] = $entry;
            $user->method('get' . ucfirst($property))->willReturn($value);
        }

        return $user;
    }
}
