<?php
/**
 * Model for Solr reserves records.
 *
 * PHP version 5
 *
 * Copyright (C) Villanova University 2011.
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
 * @package  RecordDrivers
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:record_drivers Wiki
 */
namespace VuFind\RecordDriver;

/**
 * Model for Solr reserves records.
 *
 * @category VuFind2
 * @package  RecordDrivers
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:record_drivers Wiki
 */
class SolrReserves extends SolrDefault
{
    /**
     * Get the instructor.
     *
     * @return string
     */
    public function getInstructor()
    {
        return isset($this->fields['instructor']) ? $this->fields['instructor'] : '';
    }

    /**
     * Get the instructor ID.
     *
     * @return string
     */
    public function getInstructorId()
    {
        return isset($this->fields['instructor_id'])
            ? $this->fields['instructor_id'] : '';
    }

    /**
     * Get the course.
     *
     * @return string
     */
    public function getCourse()
    {
        return isset($this->fields['course']) ? $this->fields['course'] : '';
    }

    /**
     * Get the course ID.
     *
     * @return string
     */
    public function getCourseId()
    {
        return isset($this->fields['course_id']) ? $this->fields['course_id'] : '';
    }

    /**
     * Get the department.
     *
     * @return string
     */
    public function getDepartment()
    {
        return isset($this->fields['department']) ? $this->fields['department'] : '';
    }

    /**
     * Get the department ID.
     *
     * @return string
     */
    public function getDepartmentId()
    {
        return isset($this->fields['department_id'])
            ? $this->fields['department_id'] : '';
    }

    /**
     * Get a count of items associated with this record.
     *
     * @return int
     */
    public function getItemCount()
    {
        return isset($this->fields['bib_id'])
            ? count($this->fields['bib_id']) : 0;
    }

    /**
     * Get the list of attached reserves.
     *
     * @return array
     */
    public function getItemIds()
    {
        return isset($this->fields['bib_id'])
            ? $this->fields['bib_id'] : [];
    }
}
