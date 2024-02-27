<?php

/**
 * Class AuthTokenTest
 *
 * PHP version 8
 *
 * Copyright (C) Moravian Library 2021.
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
 * @author   Josef Moravec <moravec@mzk.cz>
 * @license  https://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */

declare(strict_types=1);

namespace VuFindTest\Auth;

use VuFind\Auth\AuthToken;

/**
 * Class AuthTokenTest
 *
 * @category VuFind
 * @package  VuFindTest\Auth
 * @author   Josef Moravec <moravec@mzk.cz>
 * @license  https://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */
class AuthTokenTest extends \PHPUnit\Framework\TestCase
{
    /**
     * Test getting a header value
     *
     * @return void
     */
    public function testGetHeaderValue()
    {
        $token = new AuthToken('token', 10);
        $this->assertEquals('Bearer token', $token->getHeaderValue());
        $token = new AuthToken('token', 10, 'Basic');
        $this->assertEquals('Basic token', $token->getHeaderValue());
    }

    /**
     * Test isExpired() method
     *
     * @return void
     */
    public function testIsExpired()
    {
        $token = new AuthToken('token', 1);
        $this->assertFalse($token->isExpired());
        sleep(1);
        $this->assertTrue($token->isExpired());
    }

    /**
     * Test getExpiresIn() method
     *
     * @return void
     */
    public function testGetExpiresIn()
    {
        $token = new AuthToken('token', 11);
        $this->assertEquals(11, $token->getExpiresIn());
        $token = new AuthToken('token', null);
        $this->assertNull($token->getExpiresIn());
    }
}
