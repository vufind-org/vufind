<?php

/**
 * LoggedIn handler test.
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

use VuFind\Condition\Handler\LoggedIn;
use VuFind\Condition\Handler\UserIp;
use VuFind\Net\UserIpReader;

/**
 * LoggedIn handler test.
 *
 * @category VuFind
 * @package  Tests
 * @author   Thomas Wagener <wagener@hebis.uni-frankfurt.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */
class UserIpTest extends \PHPUnit\Framework\TestCase
{
    /**
     * Test true condition.
     *
     * @return void
     */
    public function testTrueMatching(): void
    {
        $userIpReader = $this->createMock(UserIpReader::class);
        $userIpReader->expects($this->once())->method('getUserIp')
            ->willReturn('1.2.3.4');
        $userIpHandler = new UserIp($userIpReader);
        $this->assertTrue($userIpHandler->checkCondition([
            'type' => 'user_ip',
            'comparator' => '=',
            'checkedValues' => '1.2.3.4',
        ]));
    }

    /**
     * Test false condition.
     *
     * @return void
     */
    public function testFalseMatching(): void
    {
        $userIpReader = $this->createMock(UserIpReader::class);
        $userIpReader->expects($this->once())->method('getUserIp')
            ->willReturn('1.2.3.4');
        $userIpHandler = new UserIp($userIpReader);
        $this->assertFalse($userIpHandler->checkCondition([
            'type' => 'user_ip',
            'comparator' => '=',
            'checkedValues' => '0.0.0.0',
        ]));
    }
}
