<?php

/**
 * ChangeTrackerService Test Class
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2010-2023.
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

namespace VuFindTest\Db\Service;

use VuFind\Db\Service\ChangeTrackerService;

/**
 * ChangeTrackerService Test Class
 *
 * Class must be final due to use of "new static()" by LiveDatabaseTrait.
 *
 * @category VuFind
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */
final class ChangeTrackerServiceTest extends \PHPUnit\Framework\TestCase
{
    use \VuFindTest\Feature\LiveDatabaseTrait;
    use \VuFindTest\Feature\LiveDetectionTrait;

    /**
     * Standard setup method.
     *
     * @return void
     */
    public function setUp(): void
    {
        // Give up if we're not running in CI:
        if (!$this->continuousIntegrationRunning()) {
            $this->markTestSkipped('Continuous integration not running.');
            return;
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
        $tracker = $this->getDbService(ChangeTrackerService::class);

        // Create a new row:
        $tracker->index($core, 'test1', 1326833170);
        $row = $tracker->getChangeTrackerEntity($core, 'test1');
        $this->assertIsObject($row);
        $this->assertEmpty($row->getDeleted());
        $this->assertEquals($row->getFirstIndexed(), $row->getLastIndexed());
        $this->assertEquals(
            $row->getLastRecordChange(),
            \DateTime::createFromFormat('Y-m-d H:i:s', '2012-01-17 20:46:10')
        );

        // Try to index an earlier record version -- changes should be ignored:
        $tracker->index($core, 'test1', 1326830000);
        $row = $tracker->getChangeTrackerEntity($core, 'test1');
        $this->assertIsObject($row);
        $this->assertEmpty($row->getDeleted());
        $this->assertEquals($row->getFirstIndexed(), $row->getLastIndexed());
        $this->assertEquals(
            $row->getLastRecordChange(),
            \DateTime::createFromFormat('Y-m-d H:i:s', '2012-01-17 20:46:10')
        );
        $previousFirstIndexed = $row->getFirstIndexed();

        // Sleep two seconds to be sure timestamps change:
        sleep(2);

        // Index a later record version -- this should lead to changes:
        $tracker->index($core, 'test1', 1326833176);
        $row = $tracker->getChangeTrackerEntity($core, 'test1');
        $this->assertIsObject($row);
        $this->assertEmpty($row->getDeleted());
        $this->assertLessThan($row->getLastIndexed(), $row->getFirstIndexed());
        $this->assertEquals(
            $row->getLastRecordChange(),
            \DateTime::createFromFormat('Y-m-d H:i:s', '2012-01-17 20:46:16')
        );

        // Make sure the "first indexed" date hasn't changed!
        $this->assertEquals($row->getFirstIndexed(), $previousFirstIndexed);

        // Delete the record:
        $tracker->markDeleted($core, 'test1');
        $row = $tracker->getChangeTrackerEntity($core, 'test1');
        $this->assertIsObject($row);
        $this->assertNotEmpty($row->getDeleted());

        // Delete a record that hasn't previously been encountered:
        $tracker->markDeleted($core, 'test2');
        $row = $tracker->getChangeTrackerEntity($core, 'test2');
        $this->assertIsObject($row);
        $this->assertTrue(!empty($row->getDeleted()));

        // Index the previously-deleted record and make sure it undeletes properly:
        $tracker->index($core, 'test2', 1326833170);
        $row = $tracker->getChangeTrackerEntity($core, 'test2');
        $this->assertIsObject($row);
        $this->assertEmpty($row->getDeleted());
        $this->assertEquals(
            $row->getLastRecordChange(),
            \DateTime::createFromFormat('Y-m-d H:i:s', '2012-01-17 20:46:10')
        );

        // Clean up after ourselves:
        $tracker->deleteRows($core);
    }
}
