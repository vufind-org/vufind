<?php

/**
 * Class AuthTokenTest
 *
 * PHP version 8
 *
 * Copyright (C) The National Library of Finland 2023
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
 * @package  VuFindTest\Auth
 * @author   Jaro Ravila <jaro.ravila@helsinki.fi>
 * @license  https://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */

declare(strict_types=1);

namespace VuFindTest\Auth;

use Laminas\Config\Config;
use Laminas\Session\SessionManager;
use VuFind\Auth\LoginToken;
use VuFind\Cookie\CookieManager;
use VuFind\Db\Table\User as UserTable;
use VuFind\Exception\LoginToken as LoginTokenException;

/**
 * Class AuthTokenTest
 *
 * @category VuFind
 * @package  VuFindTest\Auth
 * @author   Jaro Ravila <jaro.ravila@helsinki.fi>
 * @license  https://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */
class LoginTokenTest extends \PHPUnit\Framework\TestCase
{
    /**
     * Test login exception
     *
     * @return void
     */
    public function testTokenLoginException()
    {
        $user = $this->getMockUser();
        $cookieManager = $this->getCookieManager(
            [
              'loginToken' => '222;0;111',
            ]
        );
        $mockToken = $this->getMockLoginToken();
        $userTable = $this->getMockUserTable();
        $userTable->expects($this->once())->method('getById')
            ->with($this->equalTo(0))
            ->will($this->returnValue($this->getMockUser()));
        $tokenTable = $this->getMockLoginTokenTable();
        $tokenTable->expects($this->once())->method('matchToken')
            ->will($this->returnValue($mockToken));
        $loginToken = $this->getLoginToken($cookieManager, $tokenTable, $userTable);

        // Expect exception due to browscap.ini requirements
        $this->expectException(\VuFind\Exception\Auth::class);
        $loginToken->tokenLogin('123');
    }

    /**
     * Test logging in with invalid token
     *
     * @return void
     */
    public function testTokenLoginInvalidToken()
    {
        $user = $this->getMockUser();
        $cookieManager = $this->getCookieManager(
            [
              'loginToken' => '222;0;111',
            ]
        );
        $mockToken = $this->getMockLoginToken();
        $userTable = $this->getMockUserTable();
        $userTable->expects($this->once())->method('getById')
            ->with($this->equalTo(0))
            ->will($this->returnValue($this->getMockUser()));
        $tokenTable = $this->getMockLoginTokenTable();
        $tokenTable->expects($this->once())->method('matchToken')
            ->will($this->throwException(new LoginTokenException()));
        $tokenTable->expects($this->once())->method('getByUserId')
            ->will($this->returnValue($mockToken));
        $loginToken = $this->getLoginToken($cookieManager, $tokenTable, $userTable);
        $this->assertNull($loginToken->tokenLogin('123'));
    }

    /**
     * Test failed login
     *
     * @return void
     */
    public function testTokenLoginFail()
    {
        $user = $this->getMockUser();
        $userTable = $this->getMockUserTable();
        $cookieManager = $this->getCookieManager(
            [
              'loginToken' => '222;0;111',
            ]
        );
        $token = $this->getMockLoginToken();
        $tokenTable = $this->getMockLoginTokenTable();
        $tokenTable->expects($this->once())->method('matchToken')
            ->will($this->returnValue(false));
        $loginToken = $this->getLoginToken($cookieManager, $tokenTable, $userTable);
        $this->assertNull($loginToken->tokenLogin('123'));
    }

    /**
     * Get a mock user table.
     *
     * @return UserTable
     */
    protected function getMockUserTable()
    {
        $table = $this->getMockBuilder(\VuFind\Db\Table\User::class)
            ->disableOriginalConstructor()
            ->getMock();
        return $table;
    }

    /**
     * Get a mock user.
     *
     * @return User
     */
    protected function getMockUser()
    {
        $userData = [
            ['id', 0],
        ];
        $user = $this->getMockBuilder(\VuFind\Db\Row\User::class)
            ->disableOriginalConstructor()
            ->getMock();
        $user->method('__get')
            ->will($this->returnValueMap($userData));
        $user->method('offsetGet')
            ->will($this->returnValueMap($userData));
        return $user;
    }

    /**
     * Get a mock Login Token.
     *
     * @return User
     */
    protected function getMockLoginToken()
    {
        $tokenData = [
            ['token', '111'],
            ['user_id', 0],
            ['series', '222'],
            ['expires', 2],
        ];
        $token = $this->getMockBuilder(\VuFind\Db\Row\LoginToken::class)
            ->disableOriginalConstructor()
            ->getMock();
        $token->method('__get')
            ->will($this->returnValueMap($tokenData));
        $token->method('offsetGet')
            ->will($this->returnValueMap($tokenData));
        return $token;
    }

    /**
     * Get a mock user table.
     *
     * @return LoginTokenTable
     */
    protected function getMockLoginTokenTable()
    {
        $table = $this->getMockBuilder(\VuFind\Db\Table\LoginToken::class)
            ->disableOriginalConstructor()
            ->getMock();
        return $table;
    }

    /**
     * Get cookie manager
     *
     * @param array $cookies Cookies
     *
     * @return CookieManager
     */
    protected function getCookieManager(array $cookies): CookieManager
    {
        return new CookieManager(
            $cookies,
            '/first',
            'localhost',
            false,
            'SESS',
        );
    }

    /**
     * Get login token
     *
     * @param CookieManager $cookieManager cookie manager
     * @param LoginToken    $tokenTable    Login token table
     * @param USer          $userTable     User table
     *
     * @return LoginToken
     */
    protected function getLoginToken($cookieManager, $tokenTable, $userTable)
    {
        $config = new Config([]);
        $sessionManager = new SessionManager();
        $mailer = $this->getMockBuilder(\VuFind\Mailer\Mailer::class)
            ->disableOriginalConstructor()
            ->getMock();
        $viewRenderer = $this->getMockBuilder(\Laminas\View\Renderer\RendererInterface::class)
            ->disableOriginalConstructor()
            ->getMock();
        return new LoginToken(
            $config,
            $userTable,
            $tokenTable,
            $cookieManager,
            $sessionManager,
            $mailer,
            $viewRenderer
        );
    }
}
