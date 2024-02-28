<?php

/**
 * Import/ImportXsl command test.
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

namespace VuFindTest\Command\Import;

use Symfony\Component\Console\Tester\CommandTester;
use VuFind\XSLT\Importer;
use VuFindConsole\Command\Import\ImportXslCommand;

/**
 * Import/ImportXsl command test.
 *
 * @category VuFind
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */
class ImportXslCommandTest extends \PHPUnit\Framework\TestCase
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
            'Not enough arguments (missing: "XML_file, properties_file").'
        );
        $command = new ImportXslCommand(
            $this->getMockImporter()
        );
        $commandTester = new CommandTester($command);
        $commandTester->execute([]);
    }

    /**
     * Test the simplest possible success case.
     *
     * @return void
     */
    public function testSuccessWithMinimalParameters()
    {
        $importer = $this->getMockImporter();
        $importer->expects($this->once())->method('save')
            ->with(
                $this->equalTo('foo.xml'),
                $this->equalTo('bar.properties'),
                $this->equalTo('Solr'),
                $this->equalTo(false)
            );
        $command = new ImportXslCommand($importer);
        $commandTester = new CommandTester($command);
        $commandTester->execute(
            [
                'XML_file' => 'foo.xml',
                'properties_file' => 'bar.properties',
            ]
        );
        $this->assertEquals(
            "Successfully imported foo.xml...\n",
            $commandTester->getDisplay()
        );
        $this->assertEquals(0, $commandTester->getStatusCode());
    }

    /**
     * Test a failure scenario
     *
     * @return void
     */
    public function testFailure()
    {
        $e = new \Exception('foo', 0, new \Exception('bar'));
        $importer = $this->getMockImporter();
        $importer->expects($this->once())->method('save')
            ->with(
                $this->equalTo('foo.xml'),
                $this->equalTo('bar.properties'),
                $this->equalTo('SolrTest'),
                $this->equalTo(true)
            )->will($this->throwException($e));
        $command = new ImportXslCommand($importer);
        $commandTester = new CommandTester($command);
        $commandTester->execute(
            [
                'XML_file' => 'foo.xml',
                'properties_file' => 'bar.properties',
                '--index' => 'SolrTest',
                '--test-only' => true,
            ]
        );
        $this->assertEquals(
            "Fatal error: foo\nPrevious exception: bar\n",
            $commandTester->getDisplay()
        );
        $this->assertEquals(1, $commandTester->getStatusCode());
    }

    /**
     * Get a mock importer object
     *
     * @param array $methods Methods to mock
     *
     * @return Importer
     */
    protected function getMockImporter($methods = [])
    {
        $builder = $this->getMockBuilder(Importer::class)
            ->disableOriginalConstructor();
        if (!empty($methods)) {
            $builder->onlyMethods($methods);
        }
        return $builder->getMock();
    }
}
