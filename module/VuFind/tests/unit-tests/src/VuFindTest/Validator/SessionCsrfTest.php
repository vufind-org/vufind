<?php

/**
 * CSRF Test Class
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2018.
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

namespace VuFindTest\Validator;

use Laminas\Session\Container;
use VuFind\Validator\SessionCsrf;

/**
 * CSRF Test Class
 *
 * @category VuFind
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */
class SessionCsrfTest extends \PHPUnit\Framework\TestCase
{
    /**
     * Test counting behavior.
     *
     * @return void
     */
    public function testCounting()
    {
        $csrf = new SessionCsrf(['session' => new Container('csrftest1')]);
        $this->assertEquals(0, $csrf->getTokenCount());
        $csrf->getHash();
        $this->assertEquals(1, $csrf->getTokenCount());
        $csrf->getHash(true);
        $this->assertEquals(2, $csrf->getTokenCount());
    }

    /**
     * Test trimming behavior.
     *
     * @return void
     */
    public function testTrimming()
    {
        $csrf = new SessionCsrf(['session' => new Container('csrftest2')]);
        // Try trimming an empty list:
        $csrf->trimTokenList(5);
        $this->assertEquals(0, $csrf->getTokenCount());

        // Now populate the list:
        $firstToken = $csrf->getHash();
        $secondToken = $csrf->getHash(true);
        $thirdToken = $csrf->getHash(true);
        $this->assertEquals(3, $csrf->getTokenCount());

        // All tokens are valid now!
        $this->assertTrue($csrf->isValid($firstToken));
        $this->assertTrue($csrf->isValid($secondToken));
        $this->assertTrue($csrf->isValid($thirdToken));

        // Trim the list down to one:
        $csrf->trimTokenList(1);
        $this->assertEquals(1, $csrf->getTokenCount());

        // Now only the latest token is valid:
        $this->assertFalse($csrf->isValid($firstToken));
        $this->assertFalse($csrf->isValid($secondToken));
        $this->assertTrue($csrf->isValid($thirdToken));
    }
}
