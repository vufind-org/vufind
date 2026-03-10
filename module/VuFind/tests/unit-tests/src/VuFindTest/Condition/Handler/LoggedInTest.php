<?php

/**
 * LoggedIn handler test
 *
 * PHP version 8
 *
 * Copyright (C) Hebis Verbundzentrale 2026.
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
 * @link     https://vufind.org Main Page
 */

namespace VuFindTest\Condition\Handler;

use VuFind\Auth\Manager;
use VuFind\Condition\Handler\LoggedIn;
use VuFind\Db\Entity\User;

/**
 * LoggedIn handler test
 *
 * @category VuFind
 * @package  Tests
 * @author   Thomas Wagener <wagener@hebis.uni-frankfurt.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */
class LoggedInTest extends \PHPUnit\Framework\TestCase
{
    /**
     * Test true condition.
     *
     * @return void
     */
    public function testTrueMatching(): void
    {
        $user = $this->createMock(User::class);
        $authManagerMock = $this->createMock(Manager::class);
        $authManagerMock->expects($this->once())->method('getIdentity')
            ->willReturn($user);
        $loggedInHandler = new LoggedIn($authManagerMock);
        $this->assertTrue($loggedInHandler->checkCondition([
            'type' => 'logged_in',
            'comparator' => '=',
            'checkedValues' => 'true',
        ]));
    }

    /**
     * Test false condition.
     *
     * @return void
     */
    public function testFalseMatching(): void
    {
        $authManagerMock = $this->createMock(Manager::class);
        $authManagerMock->expects($this->once())->method('getIdentity')
            ->willReturn(null);
        $loggedInHandler = new LoggedIn($authManagerMock);
        $this->assertFalse($loggedInHandler->checkCondition([
            'type' => 'logged_in',
            'comparator' => '=',
            'checkedValues' => 'true',
        ]));
    }
}
