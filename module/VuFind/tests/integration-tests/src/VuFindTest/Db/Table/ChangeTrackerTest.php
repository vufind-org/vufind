<?php
/**
 * ChangeTracker Test Class
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
namespace VuFindTest\Db\Table;
use VuFind\Db\Table\ChangeTracker;

/**
 * ChangeTracker Test Class
 *
 * @category VuFind2
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:unit_tests Wiki
 */
class ChangeTrackerTest extends \VuFindTest\Unit\DbTestCase
{
    /**
     * Standard setup method.
     *
     * @return void
     */
    public function setUp()
    {
        // Give up if we're not running in CI:
        if (!$this->continuousIntegrationRunning()) {
            return $this->markTestSkipped('Continuous integration not running.');
        }
    }

    /**
     * Test change tracking
     *
     * @return void
     */
    public function testChangeTracker()
    {
        $core = 'testCore';
        $tracker = $this->getTable('ChangeTracker');

        // Create a new row:
        $tracker->index($core, 'test1', 1326833170);
        $row = $tracker->retrieve($core, 'test1');
        $this->assertTrue(is_object($row));
        $this->assertTrue(empty($row->deleted));
        $this->assertEquals($row->first_indexed, $row->last_indexed);
        $this->assertEquals($row->last_record_change, '2012-01-17 20:46:10');

        // Try to index an earlier record version -- changes should be ignored:
        $tracker->index($core, 'test1', 1326830000);
        $row = $tracker->retrieve($core, 'test1');
        $this->assertTrue(is_object($row));
        $this->assertTrue(empty($row->deleted));
        $this->assertEquals($row->first_indexed, $row->last_indexed);
        $this->assertEquals($row->last_record_change, '2012-01-17 20:46:10');
        $previousFirstIndexed = $row->first_indexed;

        // Sleep two seconds to be sure timestamps change:
        sleep(2);

        // Index a later record version -- this should lead to changes:
        $tracker->index($core, 'test1', 1326833176);
        $row = $tracker->retrieve($core, 'test1');
        $this->assertTrue(is_object($row));
        $this->assertTrue(empty($row->deleted));
        $this->assertTrue(
            // use <= in case test runs too fast for values to become unequal:
            strtotime($row->first_indexed) <= strtotime($row->last_indexed)
        );
        $this->assertEquals($row->last_record_change, '2012-01-17 20:46:16');

        // Make sure the "first indexed" date hasn't changed!
        $this->assertEquals($row->first_indexed, $previousFirstIndexed);

        // Delete the record:
        $tracker->markDeleted($core, 'test1');
        $row = $tracker->retrieve($core, 'test1');
        $this->assertTrue(is_object($row));
        $this->assertTrue(!empty($row->deleted));

        // Delete a record that hasn't previously been encountered:
        $tracker->markDeleted($core, 'test2');
        $row = $tracker->retrieve($core, 'test2');
        $this->assertTrue(is_object($row));
        $this->assertTrue(!empty($row->deleted));

        // Index the previously-deleted record and make sure it undeletes properly:
        $tracker->index($core, 'test2', 1326833170);
        $row = $tracker->retrieve($core, 'test2');
        $this->assertTrue(is_object($row));
        $this->assertTrue(empty($row->deleted));
        $this->assertEquals($row->last_record_change, '2012-01-17 20:46:10');

        // Clean up after ourselves:
        $tracker->delete(['core' => $core]);
    }
}