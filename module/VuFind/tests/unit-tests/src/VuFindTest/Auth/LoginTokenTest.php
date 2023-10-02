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

use VuFind\Auth\LoginToken;
use Laminas\Config\Config;
use VuFind\Db\Row\User as UserRow;
use VuFind\Db\Table\User as UserTable;
use VuFind\Db\Row\LoginToken as LoginTokenRow;
use Laminas\Session\SessionManager;
use VuFind\Cookie\CookieManager;
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
     * Test logging in with a login token
     *
     * @return void
     */
    public function testTokenLogin()
    {
        $user = $this->getMockUser();
        $cookieManager = $this->getCookieManager(
            [
              'loginToken' => '222;0;111'
            ]
        );
        $loginToken = $this->getLoginToken($cookieManager);
        

        $this->assertEquals($user, $loginToken->tokenLogin('123'));
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
        $table->expects($this->once())->method('getById')
            ->with($this->equalTo(0))
            ->will($this->returnValue($this->getMockUser()));

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
            ['id', 0]
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
            ['expires', 2]
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
        $mockToken = $this->getMockLoginToken();
        $table = $this->getMockBuilder(\VuFind\Db\Table\LoginToken::class)
            ->disableOriginalConstructor()
            ->getMock();
        $table->expects($this->once())->method('matchToken')
            ->with($this->equalTo(['token' => '111', 'user_id' => 0, 'series' => '222']))
            ->will($this->returnValue($mockToken));
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
     *
     * @return LoginToken
     */
    protected function getLoginToken($cookieManager)
    {
        $config = new Config([]);
        $userTable = $this->getMockUserTable();
        $loginTokenTable = $this->getMockLoginTokenTable();
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
            $loginTokenTable,
            $cookieManager,
            $sessionManager,
            $mailer,
            $viewRenderer
        );
    }
}