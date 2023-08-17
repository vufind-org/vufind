<?php

/**
 * DeletesCommand test.
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
use VuFindConsole\Command\Util\DeletesCommand;

/**
 * DeletesCommand test.
 *
 * @category VuFind
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */
class DeletesCommandTest extends \PHPUnit\Framework\TestCase
{
    use \VuFindTest\Feature\FixtureTrait;

    /**
     * Get mock Solr writer.
     *
     * @return \VuFind\Solr\Writer
     */
    protected function getMockWriter()
    {
        return $this->getMockBuilder(\VuFind\Solr\Writer::class)
            ->disableOriginalConstructor()
            ->getMock();
    }

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
        $writer = $this->getMockWriter();
        $command = new DeletesCommand($writer);
        $commandTester = new CommandTester($command);
        $commandTester->execute([]);
    }

    /**
     * Test that missing file yields an error message.
     *
     * @return void
     */
    public function testWithMissingFile()
    {
        $writer = $this->getMockWriter();
        $command = new DeletesCommand($writer);
        $commandTester = new CommandTester($command);
        $commandTester->execute(['filename' => '/does/not/exist']);
        $this->assertEquals(1, $commandTester->getStatusCode());
        $this->assertEquals(
            "Cannot find file: /does/not/exist\n",
            $commandTester->getDisplay()
        );
    }

    /**
     * Test success with a flat file and default index.
     *
     * @return void
     */
    public function testSuccessWithFlatFileAndDefaultIndex()
    {
        $writer = $this->getMockWriter();
        $writer->expects($this->once())->method('deleteRecords')
            ->with($this->equalTo('Solr'), $this->equalTo(['rec1', 'rec2', 'rec3']));
        $command = new DeletesCommand($writer);
        $commandTester = new CommandTester($command);
        $fixture = $this->getFixtureDir('VuFindConsole') . 'deletes';
        $commandTester->execute(
            [
                'filename' => $fixture,
                'format' => 'flat',
            ]
        );
        $this->assertEquals(0, $commandTester->getStatusCode());
        $this->assertEquals('', $commandTester->getDisplay());
    }

    /**
     * Test success with a flat file, ID prefix and default index.
     *
     * @return void
     */
    public function testSuccessWithFlatFileIdPrefixAndDefaultIndex()
    {
        $writer = $this->getMockWriter();
        $writer->expects($this->once())->method('deleteRecords')
            ->with($this->equalTo('Solr'), $this->equalTo(['x.rec1', 'x.rec2', 'x.rec3']));
        $command = new DeletesCommand($writer);
        $commandTester = new CommandTester($command);
        $fixture = $this->getFixtureDir('VuFindConsole') . 'deletes';
        $commandTester->execute(
            [
                'filename' => $fixture,
                'format' => 'flat',
                '--id-prefix' => 'x.',
            ]
        );
        $this->assertEquals(0, $commandTester->getStatusCode());
        $this->assertEquals('', $commandTester->getDisplay());
    }

    /**
     * Test success with a MARC file and non-default index.
     *
     * @return void
     */
    public function testSuccessWithMarcFileAndNonDefaultIndex()
    {
        $writer = $this->getMockWriter();
        $writer->expects($this->once())->method('deleteRecords')
            ->with($this->equalTo('foo'), $this->equalTo(['testbug2']));
        $command = new DeletesCommand($writer);
        $commandTester = new CommandTester($command);
        $fixture = __DIR__ . '/../../../../../../../../tests/data/testbug2.mrc';
        $commandTester->execute(
            [
                'filename' => $fixture,
                'index' => 'foo',
            ]
        );
        $this->assertEquals(0, $commandTester->getStatusCode());
        $this->assertEquals('', $commandTester->getDisplay());
    }
}
