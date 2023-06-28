<?php

/**
 * LintMarc command test.
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2020.
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

namespace VuFindTest\Command\Util;

use Symfony\Component\Console\Tester\CommandTester;
use VuFindConsole\Command\Util\LintMarcCommand;

/**
 * LintMarc command test.
 *
 * @category VuFind
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */
class LintMarcCommandTest extends \PHPUnit\Framework\TestCase
{
    /**
     * Test that missing parameters yield an error message.
     *
     * @return void
     */
    public function testWithoutParameters()
    {
        $this->expectException(
            \Symfony\Component\Console\Exception\RuntimeException::class
        );
        $this->expectExceptionMessage(
            'Not enough arguments (missing: "filename").'
        );
        $command = new LintMarcCommand();
        $commandTester = new CommandTester($command);
        $commandTester->execute([]);
    }

    /**
     * Test that linting a file yields useful messages.
     *
     * @return void
     */
    public function testLintingFile()
    {
        $command = new LintMarcCommand();
        $commandTester = new CommandTester($command);
        $filename = __DIR__ . '/../../../../../../../../tests/data/heb.mrc';
        $commandTester->execute(compact('filename'));
        $expected = <<<EXPECTED
            Checking record 1 (001 = testbug1)...
            Warnings: 245: Must end with . (period).
            245: Subfield _b should be preceded by space-colon, space-semicolon, or space-equals sign.

            EXPECTED;
        $this->assertEquals($expected, $commandTester->getDisplay());
        $this->assertEquals(0, $commandTester->getStatusCode());
    }
}
