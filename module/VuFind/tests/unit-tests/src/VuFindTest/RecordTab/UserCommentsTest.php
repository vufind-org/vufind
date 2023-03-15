<?php

/**
 * UserComments Test Class
 *
 * PHP version 7
 *
 * Copyright (C) Villanova University 2022.
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
 * @author   Sudharma Kellampalli <skellamp@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */

namespace VuFindTest\RecordTab;

use VuFind\RecordTab\UserComments;

/**
 * UserComments Test Class
 *
 * @category VuFind
 * @package  Tests
 * @author   Sudharma Kellampalli <skellamp@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */
class UserCommentsTest extends \PHPUnit\Framework\TestCase
{
    /**
     * Test getting Description.
     *
     * @return void
     */
    public function testGetDescription(): void
    {
        $obj = new UserComments();
        $this->assertSame("Comments", $obj->getDescription());
    }

    /**
     * Data provider for testIsActive.
     *
     * @return array
     */
    public function isActiveProvider(): array
    {
        return ['Enabled' => [true, true], 'Not Enabled' => [false, false]];
    }

    /**
     * Test if the tab is active.
     *
     * @param bool $enable         is this tab enabled
     * @param bool $expectedResult Expected return value from isActive
     *
     * @return void
     *
     * @dataProvider isActiveProvider
     */
    public function testIsActive(bool $enable, bool $expectedResult): void
    {
        $obj = new UserComments($enable);
        $this->assertSame($expectedResult, $obj->isActive());
    }

    /**
     * Data provider for testIsCaptchaActive.
     *
     * @return array
     */
    public function isCaptchaActiveProvider(): array
    {
        return ['Active' => [true, true], 'InActive' => [false, false]];
    }

    /**
     * Test if the Captcha is Active.
     *
     * @param bool $captcha        is captcha active
     * @param bool $expectedResult Expected return value from isActive
     *
     * @return void
     *
     * @dataProvider isCaptchaActiveProvider
     */
    public function testIsCaptchaActive(bool $captcha, bool $expectedResult): void
    {
        $obj = new UserComments(true, $captcha);
        $this->assertSame($expectedResult, $obj->isCaptchaActive());
    }
}
