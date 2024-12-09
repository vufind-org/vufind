<?php

/**
 * SuppressedCommand test.
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
use VuFind\ILS\Connection;
use VuFind\Solr\Writer;
use VuFindConsole\Command\Util\SuppressedCommand;

/**
 * SuppressedCommand test.
 *
 * @category VuFind
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */
class SuppressedCommandTest extends \PHPUnit\Framework\TestCase
{
    /**
     * Get mock ILS connection.
     *
     * @return Connection
     */
    protected function getMockIlsConnection()
    {
        return $this->getMockBuilder(Connection::class)
            ->disableOriginalConstructor()
            ->getMock();
    }

    /**
     * Get mock Solr writer.
     *
     * @return Writer
     */
    protected function getMockSolrWriter()
    {
        return $this->getMockBuilder(Writer::class)
            ->disableOriginalConstructor()
            ->getMock();
    }

    /**
     * Get command to test.
     *
     * @param Writer     $solr Solr writer
     * @param Connection $ils  ILS connection
     *
     * @return SuppressedCommand
     */
    protected function getCommand(Writer $solr = null, Connection $ils = null)
    {
        $args = [
            $solr ?? $this->getMockSolrWriter(),
            $ils ?? $this->getMockIlsConnection(),
        ];
        return $this->getMockBuilder(SuppressedCommand::class)
            ->setConstructorArgs($args)
            ->onlyMethods(['writeToDisk'])
            ->getMock();
    }

    /**
     * Test no results coming back from ILS
     *
     * @return void
     */
    public function testNoRecordsToDelete()
    {
        $ils = $this->getMockIlsConnection();
        $ils->expects($this->once())->method('__call')
            ->with($this->equalTo('getSuppressedRecords'))
            ->will($this->returnValue([]));
        $command = $this->getCommand(null, $ils);
        $commandTester = new CommandTester($command);
        $commandTester->execute([]);
        $this->assertEquals(0, $commandTester->getStatusCode());
        $this->assertEquals(
            "No suppressed records to delete.\n",
            $commandTester->getDisplay()
        );
    }

    /**
     * Test successful Solr update.
     *
     * @return void
     */
    public function testRecordsToDelete()
    {
        $ils = $this->getMockIlsConnection();
        $ils->expects($this->once())->method('__call')
            ->with($this->equalTo('getSuppressedRecords'))
            ->will($this->returnValue([1, 2]));
        $solr = $this->getMockSolrWriter();
        $solr->expects($this->once())->method('deleteRecords')
            ->with($this->equalTo('Solr'), $this->equalTo([1, 2]));
        $solr->expects($this->once())->method('commit')
            ->with($this->equalTo('Solr'));
        $solr->expects($this->once())->method('optimize')
            ->with($this->equalTo('Solr'));
        $command = $this->getCommand($solr, $ils);
        $commandTester = new CommandTester($command);
        $commandTester->execute([]);
        $this->assertEquals(0, $commandTester->getStatusCode());
        $this->assertEquals('', $commandTester->getDisplay());
    }

    /**
     * Test no results coming back from ILS
     *
     * @return void
     */
    public function testNoAuthorityRecordsToDelete()
    {
        $ils = $this->getMockIlsConnection();
        $ils->expects($this->once())->method('__call')
            ->with($this->equalTo('getSuppressedAuthorityRecords'))
            ->will($this->returnValue([]));
        $command = $this->getCommand(null, $ils);
        $commandTester = new CommandTester($command);
        $commandTester->execute(['--authorities' => true]);
        $this->assertEquals(0, $commandTester->getStatusCode());
        $this->assertEquals(
            "No suppressed records to delete.\n",
            $commandTester->getDisplay()
        );
    }

    /**
     * Test write to file.
     *
     * @return void
     */
    public function testWriteToFile()
    {
        $ils = $this->getMockIlsConnection();
        $ils->expects($this->once())->method('__call')
            ->with($this->equalTo('getSuppressedRecords'))
            ->will($this->returnValue([1, 2]));
        $command = $this->getCommand(null, $ils);
        $command->expects($this->once())->method('writeToDisk')
            ->with($this->equalTo('foo'), $this->equalTo("1\n2"))
            ->will($this->returnValue(true));
        $commandTester = new CommandTester($command);
        $commandTester->execute(['--outfile' => 'foo']);
        $this->assertEquals(0, $commandTester->getStatusCode());
        $this->assertEquals('', $commandTester->getDisplay());
    }

    /**
     * Test failed write to file.
     *
     * @return void
     */
    public function testFailedWriteToFile()
    {
        $ils = $this->getMockIlsConnection();
        $ils->expects($this->once())->method('__call')
            ->with($this->equalTo('getSuppressedRecords'))
            ->will($this->returnValue([1, 2]));
        $command = $this->getCommand(null, $ils);
        $command->expects($this->once())->method('writeToDisk')
            ->with($this->equalTo('foo'), $this->equalTo("1\n2"))
            ->will($this->returnValue(false));
        $commandTester = new CommandTester($command);
        $commandTester->execute(['--outfile' => 'foo']);
        $this->assertEquals(1, $commandTester->getStatusCode());
        $this->assertEquals(
            "Problem writing to foo\n",
            $commandTester->getDisplay()
        );
    }
}
