<?php

/**
 * IndexReservesCommand test.
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
use VuFindConsole\Command\Util\IndexReservesCommand;

/**
 * IndexReservesCommand test.
 *
 * @category VuFind
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */
class IndexReservesCommandTest extends \PHPUnit\Framework\TestCase
{
    use \VuFindTest\Feature\FixtureTrait;
    use \VuFindTest\Feature\WithConsecutiveTrait;

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
    public function testBadParameterCombination()
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

    /**
     * Test missing file.
     *
     * @return void
     */
    public function testBadFilename()
    {
        $command = $this->getCommand();
        $commandTester = new CommandTester($command);
        $commandTester->execute(['--filename' => '/does/not/exist']);
        $this->assertEquals(1, $commandTester->getStatusCode());
        $this->assertEquals(
            "Could not open /does/not/exist!\n",
            $commandTester->getDisplay()
        );
    }

    /**
     * Test successful file loading.
     *
     * @return void
     */
    public function testSuccessWithMultipleFiles()
    {
        $writer = $this->getMockSolrWriter();
        $writer->expects($this->once())->method('deleteAll')
            ->with($this->equalTo('SolrReserves'));
        $that = $this;
        $updateValidator = function ($update) use ($that) {
            $expectedXml = "<?xml version=\"1.0\"?>\n"
                . '<add>'
                . '<doc>'
                . '<field name="id">course1|inst1|dept1</field>'
                . '<field name="bib_id">1</field>'
                . '<field name="instructor_id">inst1</field>'
                . '<field name="instructor">inst1</field>'
                . '<field name="course_id">course1</field>'
                . '<field name="course">course1</field>'
                . '<field name="department_id">dept1</field>'
                . '<field name="department">dept1</field>'
                . '</doc>'
                . '<doc>'
                . '<field name="id">course2|inst2|dept2</field>'
                . '<field name="bib_id">2</field>'
                . '<field name="instructor_id">inst2</field>'
                . '<field name="instructor">inst2</field>'
                . '<field name="course_id">course2</field>'
                . '<field name="course">course2</field>'
                . '<field name="department_id">dept2</field>'
                . '<field name="department">dept2</field>'
                . '</doc>'
                . '<doc>'
                . '<field name="id">course3|inst3|dept3</field>'
                . '<field name="bib_id">3</field>'
                . '<field name="instructor_id">inst3</field>'
                . '<field name="instructor">inst3</field>'
                . '<field name="course_id">course3</field>'
                . '<field name="course">course3</field>'
                . '<field name="department_id">dept3</field>'
                . '<field name="department">dept3</field>'
                . '</doc>'
                . '</add>';
            $that->assertEquals($expectedXml, trim($update->getContent()));
            return true;
        };
        $writer->expects($this->once())->method('save')
            ->with(
                $this->equalTo('SolrReserves'),
                $this->callback($updateValidator)
            );
        $writer->expects($this->once())->method('commit')
            ->with($this->equalTo('SolrReserves'));
        $writer->expects($this->once())->method('optimize')
            ->with($this->equalTo('SolrReserves'));
        $command = $this->getCommand($writer);
        $commandTester = new CommandTester($command);
        $fixture1 = $this->getFixtureDir('VuFindConsole') . 'reserves/fixture1';
        $fixture2 = $this->getFixtureDir('VuFindConsole') . 'reserves/fixture2';
        $commandTester->execute(
            [
                '--filename' => [$fixture1, $fixture2],
                '--delimiter' => '|',
                '--template' => 'BIB_ID,SKIP,COURSE,DEPARTMENT,INSTRUCTOR',
            ]
        );
        $this->assertEquals(0, $commandTester->getStatusCode());
        $this->assertEquals(
            "Successfully loaded 3 rows.\n",
            $commandTester->getDisplay()
        );
    }

    /**
     * Test unsuccessful ILS loading (missing data elements).
     *
     * @return void
     */
    public function testMissingData()
    {
        $ils = $this->getMockIlsConnection();
        $this->expectConsecutiveCalls(
            $ils,
            '__call',
            [
                ['getInstructors'],
                ['getCourses'],
                ['getDepartments'],
                ['findReserves'],
            ],
            []
        );
        $command = $this->getCommand($this->getMockSolrWriter(), $ils);
        $commandTester = new CommandTester($command);
        $commandTester->execute([]);
        $this->assertEquals(1, $commandTester->getStatusCode());
        $this->assertEquals(
            'Unable to load data. No data found for: '
            . "instructors, courses, departments, reserves\n",
            $commandTester->getDisplay()
        );
    }

    /**
     * Test successful ILS loading.
     *
     * @return void
     */
    public function testSuccessWithILS()
    {
        $ils = $this->getMockIlsConnection();
        $instructors = ['inst1' => 'inst1', 'inst2' => 'inst2', 'inst3' => 'inst3'];
        $courses = [
            'course1' => 'course1', 'course2' => 'course2', 'course3' => 'course3',
        ];
        $departments = ['dept1' => 'dept1', 'dept2' => 'dept2', 'dept3' => 'dept3'];
        $reserves = [
            [
                'BIB_ID' => 1,
                'COURSE_ID' => 'course1',
                'DEPARTMENT_ID' => 'dept1',
                'INSTRUCTOR_ID' => 'inst1',
            ],
            [
                'BIB_ID' => 2,
                'COURSE_ID' => 'course2',
                'DEPARTMENT_ID' => 'dept2',
                'INSTRUCTOR_ID' => 'inst2',
            ],
            [
                'BIB_ID' => 3,
                'COURSE_ID' => 'course3',
                'DEPARTMENT_ID' => 'dept3',
                'INSTRUCTOR_ID' => 'inst3',
            ],
        ];
        $this->expectConsecutiveCalls(
            $ils,
            '__call',
            [
                ['getInstructors'],
                ['getCourses'],
                ['getDepartments'],
                ['findReserves'],
            ],
            [
                $instructors,
                $courses,
                $departments,
                $reserves,
            ]
        );
        $writer = $this->getMockSolrWriter();
        $writer->expects($this->once())->method('deleteAll')
            ->with($this->equalTo('SolrReserves'));
        $that = $this;
        $updateValidator = function ($update) use ($that) {
            $expectedXml = "<?xml version=\"1.0\"?>\n"
                . '<add>'
                . '<doc>'
                . '<field name="id">course1|inst1|dept1</field>'
                . '<field name="bib_id">1</field>'
                . '<field name="instructor_id">inst1</field>'
                . '<field name="instructor">inst1</field>'
                . '<field name="course_id">course1</field>'
                . '<field name="course">course1</field>'
                . '<field name="department_id">dept1</field>'
                . '<field name="department">dept1</field>'
                . '</doc>'
                . '<doc>'
                . '<field name="id">course2|inst2|dept2</field>'
                . '<field name="bib_id">2</field>'
                . '<field name="instructor_id">inst2</field>'
                . '<field name="instructor">inst2</field>'
                . '<field name="course_id">course2</field>'
                . '<field name="course">course2</field>'
                . '<field name="department_id">dept2</field>'
                . '<field name="department">dept2</field>'
                . '</doc>'
                . '<doc>'
                . '<field name="id">course3|inst3|dept3</field>'
                . '<field name="bib_id">3</field>'
                . '<field name="instructor_id">inst3</field>'
                . '<field name="instructor">inst3</field>'
                . '<field name="course_id">course3</field>'
                . '<field name="course">course3</field>'
                . '<field name="department_id">dept3</field>'
                . '<field name="department">dept3</field>'
                . '</doc>'
                . '</add>';
            $that->assertEquals($expectedXml, trim($update->getContent()));
            return true;
        };
        $writer->expects($this->once())->method('save')
            ->with(
                $this->equalTo('SolrReserves'),
                $this->callback($updateValidator)
            );
        $writer->expects($this->once())->method('commit')
            ->with($this->equalTo('SolrReserves'));
        $writer->expects($this->once())->method('optimize')
            ->with($this->equalTo('SolrReserves'));
        $command = $this->getCommand($writer, $ils);
        $commandTester = new CommandTester($command);
        $commandTester->execute([]);
        $this->assertEquals(0, $commandTester->getStatusCode());
        $this->assertEquals(
            "Successfully loaded 3 rows.\n",
            $commandTester->getDisplay()
        );
    }
}
