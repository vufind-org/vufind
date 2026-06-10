<?php

/**
 * PermissionHelper test class.
 *
 * PHP version 8
 *
 * Copyright (C) The National Library of Finland 2026.
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
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */

namespace VuFindTest\ActionHelper;

use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Psr7\ServerRequest;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use VuFind\ActionHelper\LoginHelper;
use VuFind\ActionHelper\PermissionHelper;
use VuFind\ActionHelper\RedirectHelper;
use VuFind\Exception\Forbidden as ForbiddenException;
use VuFind\Role\PermissionDeniedManager;
use VuFind\Role\PermissionManager;
use VuFindTest\Feature\AutowireTrait;
use VuFindTest\Feature\ReflectionTrait;

/**
 * PermissionHelper test class.
 *
 * @category VuFind
 * @package  Tests
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */
class PermissionHelperTest extends TestCase
{
    use AutowireTrait;
    use ReflectionTrait;

    /**
     * Data provider for testCheck.
     *
     * @return \Iterator
     */
    public static function checkProvider(): \Iterator
    {
        yield 'permission not defined, no pass if undefined' => [false, false, []];
        yield 'permission not defined, pass if undefined' => [true, true, []];
        yield 'permission defined, no pass' => [true, false, ['access' => false]];
        yield 'permission defined, pass' => [true, true, ['access' => true]];
        yield 'permission defined, no pass, redirect to login' => [true, false, ['access' => false], 'promptlogin'];
        yield 'permission defined, no pass, show message' => [true, false, ['access' => false], 'showmessage'];
    }

    /**
     * Test the check method.
     *
     * @param bool   $passIfUndefined      Pass if permission is undefined?
     * @param bool   $expectAuthorized     Expect user to be authorized?
     * @param array  $permissions          Array of permissions
     * @param string $accessDeniedBehavior Behavior when access is denied
     *
     * @return void
     */
    #[DataProvider('checkProvider')]
    public function testCheck(
        bool $passIfUndefined,
        bool $expectAuthorized,
        array $permissions,
        string $accessDeniedBehavior = 'exception'
    ): void {
        $permissionManager = $this->createMock(PermissionManager::class);
        $permissionManager->method('isAuthorized')->willReturnCallback(fn ($perm) => $permissions[$perm] ?? false);
        $permissionManager->method('permissionRuleExists')
            ->willReturnCallback(fn ($perm) => isset($permissions[$perm]));

        $permissionDeniedManager = $this->createMock(PermissionDeniedManager::class);
        $permissionDeniedManager->method('getDeniedControllerBehavior')
            ->willReturn(['action' => $accessDeniedBehavior, 'value' => 'Messsage!']);

        $forceLoginResponse = new Response(302, ['Location' => '/MyResearch/Home']);
        $loginHelper = $this->createMock(LoginHelper::class);
        $loginHelper->method('forceLogin')->willReturn($forceLoginResponse);

        $messageResponse = new Response(302, ['Location' => '/Error/PermissionDenied']);
        $redirectHelper = $this->createMock(RedirectHelper::class);
        $redirectHelper->method('redirectToRoute')->willReturn($messageResponse);

        $helper = $this->getAutowiredObject(
            PermissionHelper::class,
            [
                LoginHelper::class => $loginHelper,
                PermissionManager::class => $permissionManager,
                PermissionDeniedManager::class => $permissionDeniedManager,
                RedirectHelper::class => $redirectHelper,
            ]
        );
        if (!$expectAuthorized && 'exception' === $accessDeniedBehavior) {
            $this->expectException(ForbiddenException::class);
        }
        $result = $helper->check(
            new ServerRequest('GET', 'http://localhost/'),
            new Response(),
            'access',
            passIfUndefined: $passIfUndefined
        );
        if ($expectAuthorized) {
            $this->assertNull($result);
        } else {
            $this->assertInstanceOf(ResponseInterface::class, $result);
            $this->assertSame(302, $result->getStatusCode());
            $expectedLocation = 'promptlogin' === $accessDeniedBehavior
                ? '/MyResearch/Home'
                : '/Error/PermissionDenied';
            $this->assertSame([$expectedLocation], $result->getHeader('Location'));
        }
    }
}
