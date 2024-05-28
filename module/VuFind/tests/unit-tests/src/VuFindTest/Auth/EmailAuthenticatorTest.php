<?php

/**
 * Email Authenticator Test Class
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
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301  USA
 *
 * @category VuFind
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */

namespace VuFindTest\Auth;

use DateTime;
use Laminas\Config\Config;
use Laminas\Http\PhpEnvironment\RemoteAddress;
use Laminas\Session\SessionManager;
use Laminas\View\Renderer\RendererInterface;
use PHPUnit\Event\NoPreviousThrowableException;
use PHPUnit\Framework\InvalidArgumentException;
use PHPUnit\Framework\MockObject\Exception;
use VuFind\Auth\EmailAuthenticator;
use VuFind\Db\Entity\AuthHashEntityInterface;
use VuFind\Db\Service\AuthHashServiceInterface;
use VuFind\Mailer\Mailer;
use VuFind\Validator\CsrfInterface;

/**
 * Email Authenticator Manager Test Class
 *
 * @category VuFind
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */
class EmailAuthenticatorTest extends \PHPUnit\Framework\TestCase
{
    /**
     * Get an EmailAuthenticator to test.
     *
     * @param ?SessionManager           $sessionManager  Session manager
     * @param ?CsrfInterface            $csrf            CSRF validator
     * @param ?Mailer                   $mailer          Mailer service
     * @param ?RendererInterface        $renderer        View renderer
     * @param ?RemoteAddress            $remoteAddress   Remote address details
     * @param array                     $config          Configuration settings
     * @param ?AuthHashServiceInterface $authHashService AuthHash daabase service
     *
     * @return EmailAuthenticator
     * @throws InvalidArgumentException
     * @throws Exception
     * @throws NoPreviousThrowableException
     */
    protected function getEmailAuthenticator(
        SessionManager $sessionManager = null,
        CsrfInterface $csrf = null,
        Mailer $mailer = null,
        RendererInterface $renderer = null,
        RemoteAddress $remoteAddress = null,
        array $config = [],
        AuthHashServiceInterface $authHashService = null
    ): EmailAuthenticator {
        return new EmailAuthenticator(
            $sessionManager ?? $this->createMock(SessionManager::class),
            $csrf ?? $this->createMock(CsrfInterface::class),
            $mailer ?? $this->createMock(Mailer::class),
            $renderer ?? $this->createMock(RendererInterface::class),
            $remoteAddress ?? $this->createMock(RemoteAddress::class),
            new Config($config),
            $authHashService ?? $this->createMock(AuthHashServiceInterface::class)
        );
    }

    /**
     * Test that we can't send links too frequently.
     *
     * @return void
     */
    public function testRecoveryInterval(): void
    {
        $this->expectExceptionMessage('authentication_error_in_progress');
        $sessionManager = $this->createMock(SessionManager::class);
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
        $authenticator->sendAuthenticationLink('', [], [], '', [], '', '');
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
}
