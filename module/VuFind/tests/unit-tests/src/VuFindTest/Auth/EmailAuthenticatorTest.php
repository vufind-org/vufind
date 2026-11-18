<?php

/**
 * Email Authenticator Test Class.
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2024.
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
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */

namespace VuFindTest\Auth;

use DateTime;
use Laminas\Http\Request;
use Laminas\Session\ManagerInterface;
use PHPUnit\Event\NoPreviousThrowableException;
use PHPUnit\Framework\InvalidArgumentException;
use PHPUnit\Framework\MockObject\Exception;
use VuFind\Auth\EmailAuthenticator;
use VuFind\Crypt\RandomHash;
use VuFind\Db\Entity\AuthHashEntityInterface;
use VuFind\Db\Service\AuthHashServiceInterface;
use VuFind\Http\RouteHelper;
use VuFind\Http\ServerUrlHelper;
use VuFind\Mailer\Mailer;
use VuFind\Net\UserIpReader;
use VuFind\View\Renderer\TemplateRendererInterface;
use VuFindTest\Feature\TranslatorTrait;

/**
 * Email Authenticator Manager Test Class.
 *
 * @category VuFind
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */
class EmailAuthenticatorTest extends \PHPUnit\Framework\TestCase
{
    use TranslatorTrait;

    /**
     * Get an EmailAuthenticator to test.
     *
     * @param ?ManagerInterface          $sessionManager  Session manager
     * @param ?RandomHash                $randomHash      Random hash generator
     * @param ?Mailer                    $mailer          Mailer service
     * @param ?TemplateRendererInterface $renderer        Template renderer
     * @param ?ServerUrlHelper           $serverUrlHelper Server URL helper
     * @param ?RouteHelper               $routeHelper     Route helper
     * @param ?userIpReader              $userIpReader    User IP reader
     * @param array                      $config          Configuration settings
     * @param ?AuthHashServiceInterface  $authHashService AuthHash database service
     *
     * @return EmailAuthenticator
     * @throws InvalidArgumentException
     * @throws Exception
     * @throws NoPreviousThrowableException
     */
    protected function getEmailAuthenticator(
        ?ManagerInterface $sessionManager = null,
        ?RandomHash $randomHash = null,
        ?Mailer $mailer = null,
        ?TemplateRendererInterface $renderer = null,
        ?ServerUrlHelper $serverUrlHelper = null,
        ?RouteHelper $routeHelper = null,
        ?UserIpReader $userIpReader = null,
        array $config = [],
        ?AuthHashServiceInterface $authHashService = null
    ): EmailAuthenticator {
        $authenticator = new EmailAuthenticator(
            $sessionManager ?? $this->createStub(ManagerInterface::class),
            $randomHash ?? $this->createStub(RandomHash::class),
            $mailer ?? $this->createStub(Mailer::class),
            $renderer ?? $this->createStub(TemplateRendererInterface::class),
            $serverUrlHelper ?? $this->createStub(ServerUrlHelper::class),
            $routeHelper ?? $this->createStub(RouteHelper::class),
            $userIpReader ?? $this->createStub(UserIpReader::class),
            $config,
            $authHashService ?? $this->createStub(AuthHashServiceInterface::class)
        );
        $authenticator->setTranslator($this->getMockTranslator([]));
        return $authenticator;
    }

    /**
     * Test that we can't send links too frequently.
     *
     * @return void
     */
    public function testRecoveryInterval(): void
    {
        $this->expectExceptionMessage('authentication_error_in_progress');
        $sessionManager = $this->createMock(ManagerInterface::class);
        $sessionManager->expects($this->once())->method('getId')->willReturn('foo-session');
        $authHashService = $this->createMock(AuthHashServiceInterface::class);
        $row = $this->createMock(AuthHashEntityInterface::class);
        $row->expects($this->once())->method('getCreated')->willReturn(new DateTime());
        $authHashService->expects($this->once())->method('getLatestBySessionId')->with('foo-session')
            ->willReturn($row);
        $authenticator = $this->getEmailAuthenticator(
            sessionManager: $sessionManager,
            authHashService: $authHashService
        );
        $authenticator->sendAuthenticationLink('', [], []);
    }

    /**
     * Test that a link is sent when everything is successful.
     *
     * @return void
     */
    public function testSendAuthenticationLink(): void
    {
        $sessionManager = $this->createMock(ManagerInterface::class);
        $sessionManager->expects($this->once())->method('getId')->willReturn('foo-session');
        $authHashService = $this->createMock(AuthHashServiceInterface::class);
        $row = $this->createMock(AuthHashEntityInterface::class);
        $row->expects($this->once())->method('setSessionId')->with('foo-session')->willReturn($row);
        $assertData = function ($data): void {
            $this->assertIsInt($data['timestamp']);
            $this->assertEquals(['foo-data'], $data['data']);
            $this->assertEquals('me@example.com', $data['email']);
            $this->assertEquals('foo-ip', $data['ip']);
        };
        $checkJson = function ($json) use ($assertData) {
            $data = json_decode($json, true);
            $assertData($data);
            return true;
        };
        $row->expects($this->once())->method('setData')->with($this->callback($checkJson))->willReturn($row);
        $authHashService->expects($this->once())->method('getLatestBySessionId')->with('foo-session')
            ->willReturn(null);
        $authHashService->expects($this->once())->method('getByHashAndType')->with('foo-hash', 'email')
            ->willReturn($row);
        $authHashService->expects($this->once())->method('persistEntity')->with($row);
        $randomHash = $this->createMock(RandomHash::class);
        $randomHash->expects($this->once())->method('getHash')->willReturn('foo-hash');
        $mailer = $this->createMock(Mailer::class);
        $mailer->expects($this->once())->method('send')
            ->with('me@example.com', 'from@example.com', 'email_login_subject', 'foo-message');
        $renderer = $this->createMock(TemplateRendererInterface::class);
        $mockServerUrl = $this->createMock(ServerUrlHelper::class);
        $mockServerUrl->method('getUrlForPath')
            ->willReturnCallback(
                function (string $url) {
                    $this->assertSame('foo-url', $url);
                    return 'foo-serverurl';
                }
            );
        $mockRouteHelper = $this->createMock(RouteHelper::class);
        $mockRouteHelper->method('getUrlFromRoute')
            ->willReturnCallback(
                function (string $route, array $params, array $query) {
                    $this->assertSame('account-home', $route);
                    $this->assertEquals([], $params);
                    $this->assertEquals(['hash' => 'foo-hash'], $query);
                    return 'foo-url';
                }
            );
        $checkViewParams = function ($params) use ($assertData) {
            $assertData($params);
            $this->assertEquals('foo-serverurl', $params['url']);
            $this->assertEquals('foo-site-title', $params['title']);
            return true;
        };
        $renderer->expects($this->once())->method('renderTemplateAsString')
            ->with(null, 'Email/login-link.phtml', $this->callback($checkViewParams))
            ->willReturn('foo-message');
        $userIpReader = $this->createMock(userIpReader::class);
        $userIpReader->expects($this->once())->method('getUserIp')->willReturn('foo-ip');
        $authenticator = $this->getEmailAuthenticator(
            sessionManager: $sessionManager,
            randomHash: $randomHash,
            mailer: $mailer,
            renderer: $renderer,
            serverUrlHelper: $mockServerUrl,
            routeHelper: $mockRouteHelper,
            userIpReader: $userIpReader,
            config: ['Site' => ['title' => 'foo-site-title', 'email' => 'from@example.com']],
            authHashService: $authHashService
        );
        $authenticator->sendAuthenticationLink('me@example.com', ['foo-data'], []);
    }

    /**
     * If no hash can be found in the table, an exception should be thrown.
     *
     * @return void
     */
    public function testExpiredHash(): void
    {
        $this->expectExceptionMessage('authentication_error_expired');
        $authenticator = $this->getEmailAuthenticator();
        $authenticator->authenticate('foo');
    }

    /**
     * If there's a session/IP mismatch, an exception should be thrown.
     *
     * @return void
     */
    public function testSessionAndIpMismatch(): void
    {
        $this->expectExceptionMessage('authentication_error_session_ip_mismatch');
        $row = $this->createMock(AuthHashEntityInterface::class);
        $row->expects($this->once())->method('getSessionId')->willReturn('bad-session');
        $row->expects($this->once())->method('getData')->willReturn(json_encode(['ip' => 'foo-ip']));
        $authHashService = $this->createMock(AuthHashServiceInterface::class);
        $authHashService->expects($this->once())->method('getByHashAndType')->with('foo-hash', 'email')
            ->willReturn($row);
        $sessionManager = $this->createMock(ManagerInterface::class);
        $sessionManager->expects($this->once())->method('getId')->willReturn('foo-session');
        $authenticator = $this->getEmailAuthenticator(
            sessionManager: $sessionManager,
            authHashService: $authHashService
        );
        $authenticator->authenticate('foo-hash');
    }

    /**
     * Test a successful authentication.
     *
     * @return void
     */
    public function testSuccessfulAuthentication(): void
    {
        $row = $this->createMock(AuthHashEntityInterface::class);
        $row->expects($this->once())->method('getSessionId')->willReturn('foo-session');
        $row->expects($this->once())->method('getData')->willReturn(json_encode(['ip' => 'foo-ip', 'data' => ['bar']]));
        $row->expects($this->once())->method('getCreated')->willReturn(new DateTime());
        $authHashService = $this->createMock(AuthHashServiceInterface::class);
        $authHashService->expects($this->once())->method('getByHashAndType')->with('foo-hash', 'email')
            ->willReturn($row);
        $sessionManager = $this->createMock(ManagerInterface::class);
        $sessionManager->expects($this->once())->method('getId')->willReturn('foo-session');
        $authenticator = $this->getEmailAuthenticator(
            sessionManager: $sessionManager,
            authHashService: $authHashService
        );
        $this->assertEquals(['bar'], $authenticator->authenticate('foo-hash'));
    }

    /**
     * Test invalid login request.
     *
     * @return void
     */
    public function testInvalidLoginRequest(): void
    {
        $request = new Request();
        $this->assertFalse($this->getEmailAuthenticator()->isValidLoginRequest($request));
    }

    /**
     * Test valid login request.
     *
     * @return void
     */
    public function testValidLoginRequest(): void
    {
        $request = new Request();
        $request->getPost()->set('hash', 'foo-hash');
        $row = $this->createMock(AuthHashEntityInterface::class);
        $authHashService = $this->createMock(AuthHashServiceInterface::class);
        $authHashService->expects($this->once())->method('getByHashAndType')->with('foo-hash', 'email')
            ->willReturn($row);
        $authenticator = $this->getEmailAuthenticator(authHashService: $authHashService);
        $this->assertTrue($authenticator->isValidLoginRequest($request));
    }
}
