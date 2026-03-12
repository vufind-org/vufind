<?php

/**
 * LoginHelper test class.
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
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */

namespace VuFindTest\AjaxHandler;

use Generator;
use Laminas\Diactoros\Response;
use Laminas\Diactoros\ServerRequest;
use Laminas\Router\Http\RouteMatch;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use VuFind\ActionHelper\ForwardHelper;
use VuFind\ActionHelper\LoginHelper as ActionHelperLoginHelper;
use VuFind\ActionHelper\RedirectHelper;
use VuFind\Auth\ILSAuthenticator;
use VuFind\Auth\Manager as AuthManager;
use VuFind\Db\Entity\User;
use VuFind\Exception\Auth as AuthException;
use VuFind\Exception\ILS as ILSException;
use VuFind\ILS\Connection;
use VuFind\Session\Helper\FollowupHelper;
use VuFind\View\FlashMessenger\FlashMessenger;
use VuFind\View\FlashMessenger\FlashMessengerInterface;
use VuFindTest\Feature\AutowireTrait;

/**
 * LoginHelper test class.
 *
 * @category VuFind
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */
class LoginHelperTest extends TestCase
{
    use AutowireTrait;

    /**
     * Data provider for testForceLogin().
     *
     * @return Generator<string, array>
     */
    public static function forceLoginProvider(): Generator
    {
        yield 'forward' => ['forward', null, [], true, null];
        yield 'message with forward' => ['forward', 'Foo', ['extra' => 2], true, 'foo'];
        yield 'redirect' => ['redirect', null, ['extra' => 3], false, null];
        yield 'message with redirect' => ['redirect', 'Foo', [], false, 'bar'];
    }

    /**
     * Test the forceLogin method.
     *
     * @param string  $expectedResponse Expected body of response
     * @param ?string $msg              Flash message to display on login screen
     * @param array   $extras           Associative array of extra fields to store
     * @param bool    $forward          True to forward, false to redirect
     * @param ?string $lbParent         Lightbox parent
     *
     * @return void
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('forceLoginProvider')]
    public function testForceLogin(
        string $expectedResponse,
        ?string $msg,
        array $extras,
        bool $forward,
        ?string $lbParent
    ): void {
        $request = new ServerRequest();
        if ($lbParent) {
            $request = $request->withQueryParams(['lightboxParent' => $lbParent]);
        }
        $response = new Response();
        $forwardHelper = $this->createMock(ForwardHelper::class);
        $redirectHelper = $this->createMock(RedirectHelper::class);
        if ($forward) {
            $forwardHelper->expects($this->once())
                ->method('forwardTo')
                ->willReturnCallback(
                    function (
                        ServerRequestInterface $request,
                        ResponseInterface $response,
                        string $target
                    ): ResponseInterface {
                        $this->assertSame(['forcingLogin' => true], $request->getParsedBody());
                        $this->assertSame('myresearch/login', $target);
                        return $response->withHeader('X-Method', 'forward');
                    }
                );
        } else {
            $redirectHelper->expects($this->once())
                ->method('redirectToRoute')
                ->willReturnCallback(
                    function (ResponseInterface $response, string $target): ResponseInterface {
                        $this->assertSame('myresearch-home', $target);
                        return $response->withHeader('X-Method', 'redirect');
                    }
                );
        }

        $expectedExtras = $extras + [
            'lightboxParent' => $lbParent,
        ];
        $followupHelper = $this->createMock(FollowupHelper::class);
        $followupHelper->expects($this->once())
            ->method('store')
            ->with($expectedExtras);

        $flashMessenger = $this->createMock(FlashMessenger::class);
        $flashMessenger->expects($this->once())
            ->method('addErrorMessage')
            ->with($msg ?? 'You must be logged in first');

        $helper = $this->getAutowiredObject(
            ActionHelperLoginHelper::class,
            [
                ForwardHelper::class => $forwardHelper,
                RedirectHelper::class => $redirectHelper,
                FollowupHelper::class => $followupHelper,
                FlashMessengerInterface::class => $flashMessenger,
            ]
        );
        $result = $helper->forceLogin($request, $response, $msg, $extras, $forward);
        $this->assertSame([$expectedResponse], $result->getHeader('X-Method'));
    }

    /**
     * Data provider for testCatalogLogin().
     *
     * @return Generator<string, array>
     */
    public static function catalogLoginProvider(): Generator
    {
        yield 'no user' => [false, [], null, 200, 'myresearch/login', false];

        yield 'user, no credentials' => [true, [], null, 200, 'myresearch/cataloglogin', false];

        yield 'user, correct credentials' => [
            true,
            ['cat_username' => 'correct', 'cat_password' => 'password'],
            'correct',
            null,
            null,
            false,
        ];

        yield 'user, correct credentials with target' => [
            true,
            ['cat_username' => 'correct', 'cat_password' => 'password', 'target' => 'ils1'],
            'ils1.correct',
            null,
            null,
            false,
        ];

        yield 'user, incorrect credentials' => [
            true,
            ['cat_username' => 'incorrect', 'cat_password' => 'password'],
            null,
            200,
            'myresearch/cataloglogin',
            false,
        ];

        yield 'user, credentials, ILS failure' => [
            true,
            ['cat_username' => 'correct', 'cat_password' => 'password'],
            'correct',
            200,
            'myresearch/cataloglogin',
            true,
        ];

        yield 'user, no credentials, ILS failure' => [
            true,
            [],
            null,
            null,
            null,
            true,
        ];
    }

    /**
     * Test catalog login.
     *
     * @param bool    $loggedIn         User logged in?
     * @param array   $postParams       POST parameters
     * @param ?string $expectedUsername Expected username
     * @param ?int    $expectedStatus   Expected HTTP status code
     * @param ?string $expectedForward  Expected forward route
     * @param bool    $ilsFailure       Should ILSAuthenticator calls throw an ILS exception?
     *
     * @return void
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('catalogLoginProvider')]
    public function testCatalogLogin(
        bool $loggedIn,
        array $postParams,
        ?string $expectedUsername,
        ?int $expectedStatus,
        ?string $expectedForward,
        bool $ilsFailure,
    ): void {
        $request = (new ServerRequest())
            ->withParsedBody($postParams);
        $response = new Response();
        $authManager = $this->getAuthManager($loggedIn ? $this->createMock(User::class) : null, true);

        $ilsAuthenticator = $this->createMock(ILSAuthenticator::class);
        $ilsAuthenticator->expects($postParams ? $this->once() : $this->never())
            ->method('newCatalogLogin')
            ->willReturnCallback(
                function ($username) use ($expectedUsername, $ilsFailure) {
                    if ($ilsFailure) {
                        throw new ILSException('boom');
                    }
                    return $username === $expectedUsername ? ['id' => $username] : null;
                }
            );
        $ilsAuthenticator->expects($loggedIn && !$postParams ? $this->once() : $this->never())
            ->method('storedCatalogLogin')
            ->willReturnCallback(
                function () use ($ilsFailure) {
                    if ($ilsFailure) {
                        throw new ILSException('kaboom');
                    }
                    return [];
                }
            );

        $flashMessenger = $this->createMock(FlashMessenger::class);
        if ($username = $postParams['cat_username'] ?? null) {
            if ($target = $postParams['target'] ?? null) {
                $username = "$target.$username";
            }
        }
        if (!$loggedIn) {
            $flashMessenger->expects($this->once())
                ->method('addErrorMessage')
                ->with('You must be logged in first');
        } elseif ($ilsFailure) {
            $flashMessenger->expects($this->once())
                ->method('addErrorMessage')
                ->with('ils_connection_failed');
        } elseif ($username !== $expectedUsername) {
            $flashMessenger->expects($this->once())
                ->method('addErrorMessage')
                ->with('Invalid Patron Login');
        }

        $helper = $this->getAutowiredObject(
            ActionHelperLoginHelper::class,
            [
                ForwardHelper::class => $this->getForwardHelper($expectedForward),
                AuthManager::class => $authManager,
                ILSAuthenticator::class => $ilsAuthenticator,
                FlashMessengerInterface::class => $flashMessenger,
            ]
        );
        $result = $helper->catalogLogin($request, $response);
        if ($expectedUsername && !$ilsFailure) {
            $this->assertSame(['id' => $expectedUsername], $result);
        } elseif (!$postParams && $ilsFailure) {
            $this->assertNull($result);
        } else {
            $this->assertSame($expectedStatus, $result->getStatusCode());
            $this->assertSame($expectedForward ? ['forward'] : [], $result->getHeader('X-Method'));
        }
    }

    /**
     * Test disabled catalog login.
     *
     * @return void
     */
    public function testDisabledCatalogLogin(): void
    {
        $request = (new ServerRequest())
            ->withParsedBody(['cat_username' => 'foo', 'cat_password' => 'bar']);
        $response = new Response();
        $helper = $this->getAutowiredObject(
            ActionHelperLoginHelper::class,
            [
                AuthManager::class => $this->getAuthManager($this->createMock(User::class), false),
            ]
        );

        $this->expectExceptionMessage('Unexpected ILS credential submission.');
        $helper->catalogLogin($request, $response);
    }

    /**
     * Data provider for testEmailCatalogLogin().
     *
     * @return Generator<string, array>
     */
    public static function emailCatalogLoginProvider(): Generator
    {
        yield 'default route' => [null, []];
        yield 'non-default route' => ['myresearch-checkedout', ['foo' => 'bar']];
    }

    /**
     * Test email catalog login.
     *
     * @param ?string $route       Route name
     * @param array   $routeParams Route parameters
     *
     * @return void
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('emailCatalogLoginProvider')]
    public function testEmailCatalogLogin(?string $route, array $routeParams): void
    {
        $request = (new ServerRequest())
            ->withParsedBody(['cat_username' => 'user@localhost', 'cat_password' => '****', 'target' => 'ils1']);
        if ($route) {
            $routeMatch = $this->createMock(RouteMatch::class);
            $routeMatch
                ->method('getMatchedRouteName')
                ->willReturn($route);
            $routeMatch
                ->method('getParams')
                ->willReturn($routeParams);
            $request = $request->withAttribute('route-match', $routeMatch);
        }

        $response = new Response();
        $user = $this->createMock(User::class);

        $ilsAuthenticator = $this->createMock(ILSAuthenticator::class);
        $ilsAuthenticator->expects($this->once())
            ->method('sendEmailLoginLink')
            ->with(
                'ils1.user@localhost',
                $route ?? 'myresearch-profile',
                $routeParams,
                ['catalogLogin' => 'true'],
                $user
            );

        $flashMessenger = $this->createMock(FlashMessenger::class);
        $flashMessenger->expects($this->once())
            ->method('addSuccessMessage')
            ->with('email_login_link_sent');

        $helper = $this->getAutowiredObject(
            ActionHelperLoginHelper::class,
            [
                AuthManager::class => $this->getAuthManager($user, true),
                Connection::class => $this->getIls(['loginMethod' => 'email'], 'ils1'),
                ILSAuthenticator::class => $ilsAuthenticator,
                FlashMessengerInterface::class => $flashMessenger,
            ]
        );

        $helper->catalogLogin($request, $response);
    }

    /**
     * Data provider for testEmailCatalogLoginHash().
     *
     * @return Generator<string, array>
     */
    public static function emailCatalogLoginHashProvider(): Generator
    {
        yield 'valid hash' => [true];
        yield 'invalid hash' => [false];
    }

    /**
     * Test email catalog login hash handling.
     *
     * @param bool $validHash Is the hash valid?
     *
     * @return void
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('emailCatalogLoginHashProvider')]
    public function testEmailCatalogLoginHash(bool $validHash): void
    {
        $request = (new ServerRequest())
            ->withQueryParams(['auth_method' => 'ILS', 'hash' => $validHash ? 'correct' : 'incorrect']);
        $response = new Response();
        $patron = ['id' => 'patron'];

        $ilsAuthenticator = $this->createMock(ILSAuthenticator::class);
        $ilsAuthenticator->expects($this->once())
            ->method('processEmailLoginHash')
            ->willReturnCallback(
                function ($hash) use ($patron) {
                    if ('correct' === $hash) {
                        return $patron;
                    }
                    throw new AuthException('Invalid hash');
                }
            );

        $flashMessenger = $this->createMock(FlashMessenger::class);
        $flashMessenger->expects($validHash ? $this->never() : $this->once())
            ->method('addErrorMessage')
            ->with('Invalid hash');

        $helper = $this->getAutowiredObject(
            ActionHelperLoginHelper::class,
            [
                AuthManager::class => $this->getAuthManager($this->createMock(User::class), false),
                ILSAuthenticator::class => $ilsAuthenticator,
                FlashMessengerInterface::class => $flashMessenger,
                ForwardHelper::class => $this->getForwardHelper($validHash ? null : 'myresearch/cataloglogin'),
            ]
        );

        $result = $helper->catalogLogin($request, $response);
        if ($validHash) {
            $this->assertSame($patron, $result);
        } else {
            $this->assertSame(['forward'], $result->getHeader('X-Method'));
        }
    }

    /**
     * Test catalog login ILS connection failure.
     *
     * @return void
     */
    public function testCatalogLoginIlsException(): void
    {
        $request = (new ServerRequest())
            ->withParsedBody(['cat_username' => 'foo', 'cat_password' => 'bar']);
        $response = new Response();

        $ilsAuthenticator = $this->createMock(ILSAuthenticator::class);
        $ilsAuthenticator->expects($this->once())
            ->method('newCatalogLogin')
            ->willThrowException(new ILSException('Connection failed'));

        $flashMessenger = $this->createMock(FlashMessenger::class);
        $flashMessenger->expects($this->once())
            ->method('addErrorMessage')
            ->with('ils_connection_failed');

        $helper = $this->getAutowiredObject(
            ActionHelperLoginHelper::class,
            [
                AuthManager::class => $this->getAuthManager($this->createMock(User::class), true),
                ILSAuthenticator::class => $ilsAuthenticator,
                FlashMessengerInterface::class => $flashMessenger,
                ForwardHelper::class => $this->getForwardHelper('myresearch/cataloglogin'),
            ]
        );

        $result = $helper->catalogLogin($request, $response);
        $this->assertSame(['forward'], $result->getHeader('X-Method'));
    }

    /**
     * Data provider for testGetILSLoginMethod().
     *
     * @return Generator<string, array>
     */
    public static function getILSLoginMethodProvider(): Generator
    {
        yield 'defaults (null configuration)' => ['password', null, ''];
        yield 'defaults (empty array configuration)' => ['password', [], ''];
        yield 'non-default with empty target' => ['foo', ['loginMethod' => 'foo'], ''];
        yield 'non-default with non-empty target' => ['foo', ['loginMethod' => 'foo'], 'bar'];
    }

    /**
     * Test getting the ILS login method.
     *
     * @param string $expectedMethod    Expected return value of getILSLoginMethod()
     * @param ?array $patronLoginConfig Configuration for patronLogin method
     * @param string $target            Target to pass to getILSLoginMethod()
     *
     * @return void
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('getILSLoginMethodProvider')]
    public function testGetILSLoginMethod(string $expectedMethod, ?array $patronLoginConfig, string $target): void
    {
        $ils = $this->getIls($patronLoginConfig, $target);
        $helper = $this->getAutowiredObject(ActionHelperLoginHelper::class, [Connection::class => $ils]);
        $this->assertEquals($expectedMethod, $helper->getILSLoginMethod($target));
    }

    /**
     * Get mock ILS connection.
     *
     * @param ?array $patronLoginConfig Configuration for patronLogin method
     * @param string $target            Target to pass to getILSLoginMethod()
     *
     * @return MockObject&Connection
     */
    protected function getIls(?array $patronLoginConfig, string $target): Connection
    {
        $ils = $this->createMock(Connection::class);
        $ils->expects($this->once())
            ->method('checkFunction')
            ->with('patronLogin', ['patron' => ['cat_username' => "$target.login"]])
            ->willReturn($patronLoginConfig);
        return $ils;
    }

    /**
     * Get mock auth manager.
     *
     * @param ?User $user          Logged-in user, if any
     * @param bool  $allowIlsLogin Allow ILS login?
     *
     * @return MockObject&AuthManager
     */
    protected function getAuthManager(?User $user, bool $allowIlsLogin): AuthManager
    {
        $authManager = $this->createMock(AuthManager::class);
        $authManager->expects($this->once())
            ->method('getUserObject')
            ->willReturn($user);
        $authManager
            ->method('allowsUserIlsLogin')
            ->willReturn($allowIlsLogin);
        return $authManager;
    }

    /**
     * Get mock ForwardHelper.
     *
     * @param ?string $expectedRoute Expected forward route, or null to not expect forwarding.
     *
     * @return MockObject&ForwardHelper
     */
    protected function getForwardHelper(?string $expectedRoute): ForwardHelper
    {
        $forwardHelper = $this->createMock(ForwardHelper::class);
        $forwardHelper->expects($expectedRoute ? $this->once() : $this->never())
            ->method('forwardTo')
            ->willReturnCallback(
                function (
                    ServerRequestInterface $request,
                    ResponseInterface $response,
                    string $target
                ) use ($expectedRoute): ResponseInterface {
                    $this->assertSame($expectedRoute, $target);
                    return $response->withHeader('X-Method', 'forward');
                }
            );
        return $forwardHelper;
    }
}
