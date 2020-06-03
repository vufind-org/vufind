<?php
/**
 * MergeMarc command test.
 *
 * PHP version 7
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
namespace VuFindTest\Command\Harvest;

use Symfony\Component\Console\Tester\CommandTester;
use VuFindConsole\Command\Harvest\MergeMarcCommand;

/**
 * MergeMarc command test.
 *
 * @category VuFind
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */
class MergeMarcCommandTest extends \PHPUnit\Framework\TestCase
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
            'Not enough arguments (missing: "directory").'
        );
        $command = new MergeMarcCommand();
        $commandTester = new CommandTester($command);
        $commandTester->execute([]);
    }

    /**
     * Test that merging a directory yields valid results.
     *
     * @return void
     */
    public function testMergingDirectory()
    {
        $command = new MergeMarcCommand();
        $commandTester = new CommandTester($command);
        $directory = __DIR__ . '/../../../../../fixtures/xml';
        $commandTester->execute(compact('directory'));
        $expected = <<<EXPECTED
<collection>
<!-- $directory/a.xml -->
<record id="a" />
<!-- $directory/b.xml -->
<record id="b" />
</collection>

EXPECTED;
        $this->assertEquals($expected, $commandTester->getDisplay());
        $this->assertEquals(0, $commandTester->getStatusCode());
    }

    /**
     * Test that merging a non-existent directory yields an error message.
     *
     * @return void
     */
    public function testMissingDirectory()
    {
        $command = new MergeMarcCommand();
        $commandTester = new CommandTester($command);
        $directory = __DIR__ . '/../../../../../fixtures/does-not-exist';
        $commandTester->execute(compact('directory'));
        $expected = "Cannot open directory: $directory\n";
        $this->assertEquals($expected, $commandTester->getDisplay());
        $this->assertEquals(1, $commandTester->getStatusCode());
    }
}
