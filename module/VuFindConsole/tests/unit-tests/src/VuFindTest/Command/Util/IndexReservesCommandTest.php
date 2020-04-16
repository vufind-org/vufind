<?php
/**
 * IndexReservesCommand command test.
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
namespace VuFindTest\Command\Util;

use Symfony\Component\Console\Tester\CommandTester;
use VuFind\ILS\Connection;
use VuFind\Solr\Writer;
use VuFindConsole\Command\Util\IndexReservesCommand;

/**
 * IndexReservesCommand command test.
 *
 * @category VuFind
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */
class IndexReservesCommandTest extends \PHPUnit\Framework\TestCase
{
    /**
     * Get mock ILS connection.
     *
     * @return Connection
     */
    protected function getMockIlsConnection()
    {
        return $this->getMockBuilder(\VuFind\ILS\Connection::class)
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
        return $this->getMockBuilder(\VuFind\Solr\Writer::class)
            ->disableOriginalConstructor()
            ->getMock();
    }

    /**
     * Get command to test.
     *
     * @param Writer     $solr Solr writer
     * @param Connection $ils  ILS connection
     *
     * @return IndexReservesCommand
     */
    protected function getCommand(Writer $solr = null, Connection $ils = null)
    {
        return new IndexReservesCommand(
            $solr ?? $this->getMockSolrWriter(),
            $ils ?? $this->getMockIlsConnection()
        );
    }

    /**
     * Test bad parameter combination.
     *
     * @return void
     */
    public function testSuccessWithOptions()
    {
        $command = $this->getCommand();
        $commandTester = new CommandTester($command);
        $commandTester->execute(['--delimiter' => '|']);
        $this->assertEquals(1, $commandTester->getStatusCode());
        $this->assertEquals(
            "-d (delimiter) is meaningless without -f (filename)\n",
            $commandTester->getDisplay()
        );
    }
}
