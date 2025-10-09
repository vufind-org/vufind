<?php

/**
 * PermissionProvider Username Test Class
 *
 * PHP version 8
 *
 * Copyright (C) Hebis Verbundzentrale 2025.
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
 * @author   Thomas Wagener <wagener@hebis.uni-frankfurt.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */

namespace VuFindTest\Role\PermissionProvider;

use Lmc\Rbac\Mvc\Service\AuthorizationService;
use VuFind\Db\Entity\UserEntityInterface;

/**
 * PermissionProvider Username Test Class
 *
 * @category VuFind
 * @package  Tests
 * @author   Thomas Wagener <wagener@hebis.uni-frankfurt.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */
class UsernameTest extends \PHPUnit\Framework\TestCase
{
    /**
     * Test permissions with allowed username.
     *
     * @return void
     */
    public function testGetPermissionsGranted(): void
    {
        $permissionProvider = $this->getPermissionProvider('testuser1');
        $this->assertEquals(
            ['loggedin'],
            $permissionProvider->getPermissions('testuser1')
        );
    }

    /**
     * Test permissions without allowed username.
     *
     * @return void
     */
    public function testGetPermissionsDenied(): void
    {
        $permissionProvider = $this->getPermissionProvider('testuser2');
        $this->assertEquals(
            [],
            $permissionProvider->getPermissions('testuser1')
        );
    }

    /**
     * Get a username permission provider object.
     *
     * @param string $username Username of mocked user
     *
     * @return \VuFind\Role\PermissionProvider\Username
     */
    protected function getPermissionProvider(string $username): \VuFind\Role\PermissionProvider\Username
    {
        $user = $this->createMock(UserEntityInterface::class);
        $user->method('getUsername')->willReturn($username);

        $authorizationService = $this->createMock(AuthorizationService::class);
        $authorizationService
            ->method('getIdentity')
            ->willReturn($user);

        return new \VuFind\Role\PermissionProvider\Username($authorizationService);
    }
}
