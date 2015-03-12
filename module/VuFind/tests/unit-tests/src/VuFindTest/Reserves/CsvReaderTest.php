<?php
/**
 * Course Reserves CSV Loader Test Class
 *
 * PHP version 5
 *
 * Copyright (C) Villanova University 2010.
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
 * Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
 *
 * @category VuFind2
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:unit_tests Wiki
 */
namespace VuFindTest\Reserves;
use VuFind\Reserves\CsvReader;

/**
 * Course Reserves CSV Loader Test Class
 *
 * @category VuFind2
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:unit_tests Wiki
 */
class CsvReaderTest extends \VuFindTest\Unit\TestCase
{
    /**
     * Test getInstructors()
     *
     * @return void
     */
    public function testGetInstructors()
    {
        $instructors = [
            'Mr. English' => 'Mr. English',
            'Ms. Math' => 'Ms. Math',
            'Junk' => 'Junk',
            'M. Geography' => 'M. Geography',
        ];
        $this->assertEquals($instructors, $this->getReader()->getInstructors());
    }

    /**
     * Test getCourses()
     *
     * @return void
     */
    public function testGetCourses()
    {
        $courses = [
            'English 101' => 'English 101',
            'Math 101' => 'Math 101',
            'Bad Row' => 'Bad Row',
            'Geography 101' => 'Geography 101',
        ];
        $this->assertEquals($courses, $this->getReader()->getCourses());
    }

    /**
     * Test getDepartments()
     *
     * @return void
     */
    public function testGetDepartments()
    {
        $departments = [
            'English' => 'English',
            'Math' => 'Math',
            'Garbage' => 'Garbage',
            'Geography' => 'Geography',
        ];
        $this->assertEquals($departments, $this->getReader()->getDepartments());
    }

    /**
     * Test getReserves()
     *
     * @return void
     */
    public function testGetReserves()
    {
        $reserves = [
            [
                'BIB_ID' => 1,
                'INSTRUCTOR_ID' => 'Mr. English',
                'COURSE_ID' => 'English 101',
                'DEPARTMENT_ID' => 'English',
            ],
            [
                'BIB_ID' => 2,
                'INSTRUCTOR_ID' => 'Ms. Math',
                'COURSE_ID' => 'Math 101',
                'DEPARTMENT_ID' => 'Math',
            ],
            [
                'BIB_ID' => 3,
                'INSTRUCTOR_ID' => 'M. Geography',
                'COURSE_ID' => 'Geography 101',
                'DEPARTMENT_ID' => 'Geography',
            ],
        ];
        $this->assertEquals($reserves, $this->getReader()->getReserves());
    }

    /**
     * Test getErrors()
     *
     * @return void
     */
    public function testGetErrors()
    {
        $reader = $this->getReader();
        $reader->getReserves();
        $fixture = $this->getFixturePath('reserves.csv');
        $errors = "Skipping empty/missing Bib ID: $fixture, line 3\nSkipping incomplete row: $fixture, line 5\n";
        $this->assertEquals($errors, $reader->getErrors());
    }

    /**
     * Test loading an empty file.
     *
     * @return void
     *
     * @expectedException        \Exception
     * @expectedExceptionMessage Could not find valid data. Details:
     */
    public function testEmptyFile()
    {
        $this->getReader('empty.csv')->getReserves();
    }

    /**
     * Get a reader object with the fixture loaded.
     *
     * @param string $fixture Name of file to load
     *
     * @return CsvReader
     */
    protected function getReader($fixture = 'reserves.csv')
    {
        return new CsvReader($this->getFixturePath($fixture));
    }

    /**
     * Get a fixture path
     *
     * @param string $fixture Name of file to load
     *
     * @return string
     */
    protected function getFixturePath($fixture)
    {
        return realpath(__DIR__ . '/../../../../fixtures/reserves/' . $fixture);
    }
}