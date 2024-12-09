<?php

/**
 * Support class to build reserves data from CSV file(s).
 *
 * PHP version 8
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
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301  USA
 *
 * @category VuFind
 * @package  Reserves
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki Wiki
 */

namespace VuFind\Reserves;

use function count;
use function is_array;

/**
 * Support class to build reserves data from CSV file(s).
 *
 * @category VuFind
 * @package  Reserves
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki Wiki
 */
class CsvReader
{
    /**
     * Files to load
     *
     * @var array
     */
    protected $files;

    /**
     * CSV delimiter
     *
     * @var string
     */
    protected $delimiter;

    /**
     * Field template (value => index)
     *
     * @var array
     */
    protected $template;

    /**
     * Instructor data loaded from files
     *
     * @var array
     */
    protected $instructors = [];

    /**
     * Course data loaded from files
     *
     * @var array
     */
    protected $courses = [];

    /**
     * Department data loaded from files
     *
     * @var array
     */
    protected $departments = [];

    /**
     * Reserves data loaded from files
     *
     * @var array
     */
    protected $reserves = [];

    /**
     * Flag indicating whether or not we have processed data yet.
     *
     * @var bool
     */
    protected $loaded = false;

    /**
     * Error messages collected during loading.
     *
     * @var string
     */
    protected $errors = '';

    /**
     * Constructor
     *
     * @param array|string $files     Array of files to load (or single filename).
     * @param string       $delimiter Delimiter used by file(s).
     * @param string       $template  Template showing field positions within
     * file(s). Comma-separated list containing BIB_ID, INSTRUCTOR, COURSE,
     * DEPARTMENT and/or SKIP. Default = BIB_ID,COURSE,INSTRUCTOR,DEPARTMENT
     *
     * @throws \Exception
     */
    public function __construct($files, $delimiter = ',', $template = null)
    {
        $this->files = is_array($files) ? $files : [$files];
        $this->delimiter = $delimiter;

        // Provide default template if none passed in:
        if (null === $template) {
            $template = 'BIB_ID,COURSE,INSTRUCTOR,DEPARTMENT';
        }

        // Convert template from comma-delimited list to map of name => index:
        $this->template = array_flip(array_map('trim', explode(',', $template)));

        if (!isset($this->template['BIB_ID'])) {
            throw new \Exception('Template must include BIB_ID field.');
        }
    }

    /**
     * Load the appropriate data field from the line using our template.
     *
     * @param array  $line CSV row
     * @param string $key  Value to load
     *
     * @return string
     */
    protected function getValueFromLine($line, $key)
    {
        return isset($this->template[$key]) ? $line[$this->template[$key]] : '';
    }

    /**
     * Load data from a single file.
     *
     * @param string $fn Filename
     *
     * @return void
     * @throws \Exception
     */
    protected function loadFile($fn)
    {
        if (!file_exists($fn) || !($fh = fopen($fn, 'r'))) {
            throw new \Exception("Could not open $fn!");
        }
        $lineNo = $goodLines = 0;
        while ($line = fgetcsv($fh, 0, $this->delimiter)) {
            $lineNo++;

            if (count($line) < count($this->template)) {
                $this->errors .= "Skipping incomplete row: $fn, line $lineNo\n";
                continue;
            }

            $instructor = $this->getValueFromLine($line, 'INSTRUCTOR');
            if (!empty($instructor)) {
                $this->instructors[$instructor] = $instructor;
            }

            $course = $this->getValueFromLine($line, 'COURSE');
            if (!empty($course)) {
                $this->courses[$course] = $course;
            }

            $department = $this->getValueFromLine($line, 'DEPARTMENT');
            if (!empty($department)) {
                $this->departments[$department] = $department;
            }

            $bibId = trim($line[$this->template['BIB_ID']]);
            if ($bibId == '') {
                $this->errors
                    .= "Skipping empty/missing Bib ID: $fn, line $lineNo\n";
                continue;
            }

            $goodLines++;
            $this->reserves[] = [
                'BIB_ID' => $bibId,
                'INSTRUCTOR_ID' => $instructor,
                'COURSE_ID' => $course,
                'DEPARTMENT_ID' => $department,
            ];
        }
        fclose($fh);
        if ($goodLines == 0) {
            throw new \Exception(
                "Could not find valid data. Details:\n" . trim($this->errors)
            );
        }
    }

    /**
     * Load data if it is not already loaded.
     *
     * @return void
     * @throws \Exception
     */
    protected function load()
    {
        // Only load data if we haven't already retrieved it.
        if (!$this->loaded) {
            foreach ($this->files as $fn) {
                $this->loadFile($fn);
            }

            $this->loaded = true;
        }
    }

    /**
     * Get instructor data
     *
     * @return array
     * @throws \Exception
     */
    public function getInstructors()
    {
        $this->load();
        return $this->instructors;
    }

    /**
     * Get course data
     *
     * @return array
     * @throws \Exception
     */
    public function getCourses()
    {
        $this->load();
        return $this->courses;
    }

    /**
     * Get department data
     *
     * @return array
     * @throws \Exception
     */
    public function getDepartments()
    {
        $this->load();
        return $this->departments;
    }

    /**
     * Get reserves data
     *
     * @return array
     * @throws \Exception
     */
    public function getReserves()
    {
        $this->load();
        return $this->reserves;
    }

    /**
     * Get collected error messages
     *
     * @return string
     */
    public function getErrors()
    {
        return $this->errors;
    }
}
