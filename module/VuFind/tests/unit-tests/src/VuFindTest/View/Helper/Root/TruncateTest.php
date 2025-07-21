<?php

/**
 * Truncate view helper Test Class
 *
 * PHP version 8
 *
 * Copyright (C) The National Library of Finland 2025.
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
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */

namespace VuFindTest\View\Helper\Root;

use VuFind\String\PropertyString;
use VuFind\View\Helper\Root\Truncate;

/**
 * Truncate view helper Test Class
 *
 * @category VuFind
 * @package  Tests
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */
class TruncateTest extends \PHPUnit\Framework\TestCase
{
    /**
     * Data provider for testTruncate
     *
     * @return array
     */
    public static function truncateProvider(): array
    {
        $shortPropertyString = PropertyString::fromHtml('<strong>short</strong>');
        $longPropertyString = PropertyString::fromHtml('<strong>long string</strong>');
        return [
            'short string, no append specified' => ['short', 5, null, 'short'],
            'long string, no append specified' => ['long string', 5, null, 'long...'],
            'long string, append specified' => ['long string', 5, '…', 'long…'],
            'long string, long limit' => ['long string', 11, null, 'long string'],
            'long string, zero limit' => ['long string', 0, null, ''],
            'short PropertyString, no append specified' => [$shortPropertyString, 5, null, $shortPropertyString],
            'long PropertyString, no append specified' => [$longPropertyString, 5, null, 'long...'],
            'long PropertyString, append specified' => [$longPropertyString, 5, '…', 'long…'],
        ];
    }

    /**
     * Test truncation
     *
     * @param string|PropertyString $input    Input string
     * @param int                   $len      Maximum result string length
     * @param ?string               $append   Truncation indicator to append or null for default
     * @param string|PropertyString $expected Expected result
     *
     * @return void
     *
     * @dataProvider truncateProvider
     */
    public function testTruncate($input, int $len, ?string $append, $expected): void
    {
        $truncate = new Truncate();
        $result = null !== $append ? $truncate($input, $len, $append) : $truncate($input, $len);
        $this->assertEquals($expected, $result);
    }
}
