<?php
/**
 * Solr Reserves Class
 *
 * PHP version 5
 *
 * Copyright (C) Villanova University 2007.
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
 * @package  Solr
 * @author   Tuan Nguyen <tuan@yorku.ca>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/system_classes#index_interface Wiki
 */
namespace VuFind\Connection;

/**
 * Solr Reserves Class
 *
 * Allows indexing/searching reserves records.
 *
 * @category VuFind2
 * @package  Solr
 * @author   Tuan Nguyen <tuan@yorku.ca>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/system_classes#index_interface Wiki
 */
class SolrReserves extends Solr
{
    /**
     * Constructor
     *
     * @param string $host The URL for the local Solr Server
     */
    public function __construct($host)
    {
        parent::__construct($host, 'reserves');
        $this->searchSpecsFile = 'reservessearchspecs.yaml';
    }

    /**
     * Find reserves record matching the given instructor ID and course ID.
     *
     * @param string $course     The course ID
     * @param string $instructor The instructor ID
     * @param string $department The department ID
     *
     * @return array             A matching solr reserves record.
     */
    public function findReserves($course, $instructor, $department)
    {
        $id = $course . '|' . $instructor . '|' . $department;
        return $this->getRecord($id);
    }

    /**
     * Build the reserves index from date returned by the ILS driver,
     * specifically: getInstructors, getDepartments, getCourses, findReserves
     *
     * @param array $instructors Array of instructors $instructor_id => $instructor
     * @param array $courses     Array of courses     $course_id => $course
     * @param array $departments Array of department  $dept_id => $department
     * @param array $reserves    Array of reserves records from driver's
     * findReserves.
     *
     * @return void
     */
    public function buildIndex($instructors, $courses, $departments, $reserves)
    {
        foreach ($reserves as $record) {
            if (!isset($record['INSTRUCTOR_ID']) || !isset($record['COURSE_ID'])
                || !isset($record['DEPARTMENT_ID'])
            ) {
                throw new \Exception(
                    'INSTRUCTOR_ID and/or COURSE_ID and/or DEPARTMENT_ID fields ' .
                    'not present in reserve records. Please update ILS driver.'
                );
            }
            $instructor_id = $record['INSTRUCTOR_ID'];
            $course_id = $record['COURSE_ID'];
            $department_id = $record['DEPARTMENT_ID'];
            $id = $course_id . '|' . $instructor_id . '|' . $department_id;

            if (!isset($index[$id])) {
                $index[$id] = array(
                    'id' => $id,
                    'bib_id' => array(),
                    'instructor_id' => $instructor_id,
                    'instructor' => isset($instructors[$instructor_id])
                        ? $instructors[$instructor_id] : '',
                    'course_id' => $course_id,
                    'course' => isset($courses[$course_id])
                        ? $courses[$course_id] : '',
                    'department_id' => $department_id,
                    'department' => isset($departments[$department_id])
                        ? $departments[$department_id] : ''
                );
            }
            $index[$id]['bib_id'][] = $record['BIB_ID'];
        }

        foreach ($index as $id => $data) {
            if (!empty($data['bib_id'])) {
                $xml = $this->getSaveXML($data);
                $this->saveRecord($xml);
            }
        }
    }
}
