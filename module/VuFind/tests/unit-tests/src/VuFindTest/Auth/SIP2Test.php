<?php

/**
 * SIP2 authentication test class.
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2011.
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
 * @link     https://vufind.org Main Page
 */

namespace VuFindTest\Auth;

use Laminas\Config\Config;
use Laminas\Http\Request;
use VuFind\Auth\ILSAuthenticator;
use VuFind\Auth\SIP2;

/**
 * SIP2 authentication test class.
 *
 * @category VuFind
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */
class SIP2Test extends \PHPUnit\Framework\TestCase
{
    /**
     * Get an authentication object.
     *
     * @param ?Config $config Configuration to use (null for default)
     *
     * @return SIP2
     */
    public function getAuthObject(?Config $config = null): SIP2
    {
        if (null === $config) {
            $config = $this->getAuthConfig();
        }
        $obj = new SIP2($this->createMock(ILSAuthenticator::class));
        $obj->setConfig($config);
        return $obj;
    }

    /**
     * Get a working configuration for the LDAP object
     *
     * @return Config
     */
    public function getAuthConfig(): Config
    {
        $config = new Config(
            [
                'host' => 'my.fake.host',
                'port' => '6002',
            ],
            true
        );
        return new Config(['MultiAuth' => $config], true);
    }

    /**
     * Support method -- get parameters to log into an account (but allow override of
     * individual parameters so we can test different scenarios).
     *
     * @param array $overrides Associative array of parameters to override.
     *
     * @return Request
     */
    protected function getLoginRequest(array $overrides = []): Request
    {
        $post = $overrides + [
            'username' => 'testuser', 'password' => 'testpass',
        ];
        $request = new Request();
        $request->setPost(new \Laminas\Stdlib\Parameters($post));
        return $request;
    }

    /**
     * Test login with blank username.
     *
     * @return void
     */
    public function testLoginWithBlankUsername(): void
    {
        $this->expectException(\VuFind\Exception\Auth::class);

        $request = $this->getLoginRequest(['username' => '']);
        $this->getAuthObject()->authenticate($request);
    }

    /**
     * Test login with blank password.
     *
     * @return void
     */
    public function testLoginWithBlankPassword(): void
    {
        $this->expectException(\VuFind\Exception\Auth::class);

        $request = $this->getLoginRequest(['password' => '']);
        $this->getAuthObject()->authenticate($request);
    }
}
