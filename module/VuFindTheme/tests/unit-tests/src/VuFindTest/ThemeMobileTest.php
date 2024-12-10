<?php

/**
 * Mobile Test Class
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2010.
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

namespace VuFindTest;

use VuFindTheme\Mobile;

/**
 * Mobile Test Class
 *
 * @category VuFind
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */
class ThemeMobileTest extends \PHPUnit\Framework\TestCase
{
    /**
     * Data provider for testDetection.
     *
     * @return array[]
     */
    public static function detectionProvider(): array
    {
        return [
            'mobile detected' => [true],
            'mobile not detected' => [false],
        ];
    }

    /**
     * Test detection wrapping.
     *
     * @param bool $active Result of mobile detection
     *
     * @return void
     *
     * @dataProvider detectionProvider
     */
    public function testDetection(bool $active): void
    {
        $detector = $this->createMock(\uagent_info::class);
        $detector->expects($this->once())->method('DetectMobileLong')->willReturn($active);
        $mobile = new Mobile($detector);
        $this->assertEquals($active, $mobile->detect());
    }
}
