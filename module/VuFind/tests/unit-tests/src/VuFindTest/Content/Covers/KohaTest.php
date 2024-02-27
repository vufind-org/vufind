<?php

/**
 * Unit tests for Koha cover loader.
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2023.
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
 * @link     https://vufind.org
 */

namespace VuFindTest\Content\Covers;

use VuFind\Content\Covers\Koha;

/**
 * Unit tests for Koha cover loader.
 *
 * @category VuFind
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org
 */
class KohaTest extends \PHPUnit\Framework\TestCase
{
    /**
     * Data provider for testValidCoverLoading
     *
     * @return array
     */
    public static function getCoverData(): array
    {
        return [
            'no id' => [false, [null, 'small', []]],
            'small image' => [
                'http://base?thumbnail=1&biblionumber=foo',
                [null, 'small', ['recordid' => 'foo']],
            ],
            'medium image' => [
                'http://base?thumbnail=1&biblionumber=foo',
                [null, 'medium', ['recordid' => 'foo']],
            ],
            'large image' => [
                'http://base?biblionumber=foo',
                [null, 'large', ['recordid' => 'foo']],
            ],
        ];
    }

    /**
     * Test cover loading
     *
     * @param string|bool $expected Expected response
     * @param array       $params   Parameters to send to cover loader
     *
     * @return void
     *
     * @dataProvider getCoverData
     */
    public function testValidCoverLoading($expected, array $params): void
    {
        $loader = new Koha('http://base');
        $this->assertEquals($expected, $loader->getUrl(...$params));
    }
}
