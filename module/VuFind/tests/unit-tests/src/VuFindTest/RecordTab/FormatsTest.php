<?php

/**
 * Formats Test Class
 *
 * PHP version 8
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

use VuFind\RecordTab\Formats;

/**
 * Formats Test Class
 *
 * @category VuFind
 * @package  Tests
 * @author   Sudharma Kellampalli <skellamp@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */
class FormatsTest extends \PHPUnit\Framework\TestCase
{
    /**
     * Test getting Description.
     *
     * @return void
     */
    public function testGetDescription(): void
    {
        $obj = $this->getFormats();
        $expected = 'Formats';

        $this->assertSame($expected, $obj->getDescription());
    }

    /**
     * Data provider for testIsActive.
     *
     * @return array
     */
    public static function isActiveProvider(): array
    {
        return ['Not Enabled' => [false, false], 'Enabled' => [true, true]];
    }

    /**
     * Test if the tab is active.
     *
     * @param bool $enable         Enable the formats tab
     * @param bool $expectedResult Expected return value from isActive
     *
     * @return void
     *
     * @dataProvider isActiveProvider
     */
    public function testisActive(bool $enable, bool $expectedResult): void
    {
        $obj = $this->getFormats($enable);
        $this->assertSame($expectedResult, $obj->isActive());
    }

    /**
     * Get a configured Formats object.
     *
     * @param bool $enabled Is this tab enabled?
     *
     * @return Formats
     */
    protected function getFormats($enabled = true)
    {
        return new Formats($enabled);
    }
}
