<?php

/**
 * Language/ImportLokalise command test.
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
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */

namespace VuFindTest\Command\Language;

use Symfony\Component\Console\Tester\CommandTester;
use VuFind\I18n\ExtendedIniNormalizer;
use VuFindConsole\Command\Language\ImportLokaliseCommand;

/**
 * Language/Normalize command test.
 *
 * @category VuFind
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */
class ImportLokaliseCommandTest extends \PHPUnit\Framework\TestCase
{
    use \VuFindTest\Feature\FixtureTrait;
    use \VuFindTest\Feature\WithConsecutiveTrait;

    /**
     * Base fixture directory
     *
     * @var string
     */
    protected $baseFixtureDir = null;

    /**
     * Standard setup method.
     *
     * @return void
     */
    public function setUp(): void
    {
        $this->baseFixtureDir = $this->getFixtureDir('VuFindConsole') . 'lokalise';
    }

    /**
     * Test that missing parameters yield an error message.
     *
     * @return void
     */
    public function testWithoutParameters(): void
    {
        $this->expectException(
            \Symfony\Component\Console\Exception\RuntimeException::class
        );
        $this->expectExceptionMessage(
            'Not enough arguments (missing: "source, target").'
        );
        $command = new ImportLokaliseCommand(new ExtendedIniNormalizer());
        $commandTester = new CommandTester($command);
        $commandTester->execute([]);
    }

    /**
     * Test bad input parameter.
     *
     * @return void
     */
    public function testBadInputParameter(): void
    {
        $source = $this->baseFixtureDir . '/doesNotExist';
        $target = $this->baseFixtureDir . '/existing';
        $command = new ImportLokaliseCommand(new ExtendedIniNormalizer());
        $commandTester = new CommandTester($command);
        $commandTester->execute(compact('source', 'target'));
        $this->assertEquals(
            "{$source} does not exist or is not a directory.\n",
            $commandTester->getDisplay()
        );
        $this->assertEquals(1, $commandTester->getStatusCode());
    }

    /**
     * Test bad output parameter.
     *
     * @return void
     */
    public function testBadOutputParameter(): void
    {
        $source = $this->baseFixtureDir . '/incoming';
        $target = $this->baseFixtureDir . '/doesNotExist';
        $command = new ImportLokaliseCommand(new ExtendedIniNormalizer());
        $commandTester = new CommandTester($command);
        $commandTester->execute(compact('source', 'target'));
        $this->assertEquals(
            "{$target} does not exist or is not a directory.\n",
            $commandTester->getDisplay()
        );
        $this->assertEquals(1, $commandTester->getStatusCode());
    }

    /**
     * Test a successful load.
     *
     * @return void
     */
    public function testDataLoad(): void
    {
        $source = $this->baseFixtureDir . '/incoming';
        $target = $this->baseFixtureDir . '/existing';
        $command = $this->getMockCommand();
        $this->expectConsecutiveCalls(
            $command,
            'writeToDisk',
            [
                [$target . '/da.ini', "new = \"NEW!\"\nstripped = \"NoQuotes\"\n"],
                [$target . '/en.ini', "bar = \"enbaz\"\nfoo = \"enINCOMING\"\nxyzzy = \"enXYZZY\"\n"],
                [$target . '/pt-br.ini', "bar = \"pt-brbaz\"\nfoo = \"pt-brINCOMING\"\nxyzzy = \"pt-brXYZZY\"\n"],
                [$target . '/zh.ini', "bar = \"zhbaz\"\nfoo = \"zhINCOMING\"\nxyzzy = \"zhXYZZY\"\n"],
            ]
        );
        $commandTester = new CommandTester($command);
        $commandTester->execute(compact('source', 'target'));
    }

    /**
     * Get a mock command (with file writing stubbed out).
     *
     * @return ImportLokaliseCommand
     */
    protected function getMockCommand(): ImportLokaliseCommand
    {
        return $this->getMockBuilder(ImportLokaliseCommand::class)
            ->setConstructorArgs([new ExtendedIniNormalizer()])
            ->onlyMethods(['writeToDisk'])
            ->getMock();
    }
}
