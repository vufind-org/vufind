<?php

/**
 * Var Dumper Trait Test Class
 *
 * PHP version 8
 *
 * Copyright (C) Moravian Library 2024.
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
 * @author   Josef Moravec <josef.moravec@mzk.cz>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */

namespace VuFindTest\Log;

/**
 * Var Dumper Trait Test Class
 *
 * @category VuFind
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */
class VarDumperTraitTest extends \PHPUnit\Framework\TestCase
{
    use \VuFind\Log\VarDumperTrait;

    /**
     * Test varDump method data provider
     *
     * @return array[]
     */
    public static function varDumpProvider(): array
    {
        return [
            'boolean' => [
                true,
                'true\n',
            ],
            'int' => [
                42,
                '42\n',
            ],
            'string' => [
                'some_string',
                '"some_string"\n',
            ],
            'array' => [
                [42, 'string', false],
                'array:3 \[\n  0 => 42\n  1 => "string"\n  2 => false\n\]\n',
            ],
            'object' => [
                new class (42, 'string', false) {
                    /**
                     * Test class constructor
                     *
                     * @param int    $number Some number
                     * @param string $text   Some text
                     * @param bool   $flag   A flag
                     */
                    public function __construct(public int $number, public string $text, public bool $flag)
                    {
                    }
                },
                'class@anonymous \{#[0-9]+\n  \+number: 42\n  \+text: "string"\n  \+flag: false\n\}\n',

            ],
        ];
    }

    /**
     * Test varDump method
     *
     * @param mixed  $var      Variable to dump
     * @param string $expected Expected dumped string
     *
     * @dataProvider varDumpProvider
     *
     * @return void
     */
    public function testVarDump(mixed $var, string $expected): void
    {
        $this->assertMatchesRegularExpression('/' . $expected . '/', $this->varDump($var));
    }
}
