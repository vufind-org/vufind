<?php

/**
 * ChangeTrackerService Test Class.
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
 * along with this program; if not, see
 * <https://www.gnu.org/licenses/>.
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
 * ChangeTrackerService Test Class.
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
     * Standard teardown method.
     *
     * @return void
     */
    public function tearDown(): void
    {
        $this->tearDownLiveDatabaseContainer();
    }

    /**
     * Test that appropriate values were created by the standard test environment setup.
     *
     * @return void
     */
    public function testMarcIndexingSucceeded(): void
    {
        // We index author_relators.mrc and author_relators_updated_record.mrc as part of the startup process.
        // The second file contains the same records as the first, but with some of the 005 fields changed to
        // newer dates to trigger change tracker updates. We want to test that a record with matching dates in
        // both files is treated as unchanged (first indexed = last indexed) and a record with non-matching
        // dates is treated as updated (first indexed != last indexed).
        $tracker = $this->getDbService(ChangeTrackerService::class);
        $unchanged = $tracker->getChangeTrackerEntity('biblio', '0000652212-0');
        $this->assertEquals($unchanged->getFirstIndexed(), $unchanged->getLastIndexed());
        $changed = $tracker->getChangeTrackerEntity('biblio', '0000183626-0');
        $this->assertGreaterThan($changed->getFirstIndexed(), $changed->getLastIndexed());
    }

    /**
     * Test change tracking.
     *
     * @return void
     */
    public function testChangeTracker(): void
    {
        $core = 'testCore';
        $tracker = $this->getDbService(ChangeTrackerService::class);

        // Ensure that we have a clean slate:
        $tracker->deleteRows($core);

        // Create a new row:
        $tracker->index($core, 'test1', 1326833170);
        $row = $tracker->getChangeTrackerEntity($core, 'test1');
        $this->assertIsObject($row);
        $this->assertEmpty($row->getDeleted());
        $this->assertEquals($row->getFirstIndexed(), $row->getLastIndexed());
        $this->assertEquals(
            $row->getLastRecordChange(),
            \DateTime::createFromFormat('Y-m-d H:i:s', '2012-01-17 20:46:10', new \DateTimeZone('UTC'))
        );

        // Try to index an earlier record version -- changes should be ignored:
        $tracker->index($core, 'test1', 1326830000);
        $row = $tracker->getChangeTrackerEntity($core, 'test1');
        $this->assertIsObject($row);
        $this->assertEmpty($row->getDeleted());
        $this->assertEquals($row->getFirstIndexed(), $row->getLastIndexed());
        $this->assertEquals(
            $row->getLastRecordChange(),
            \DateTime::createFromFormat('Y-m-d H:i:s', '2012-01-17 20:46:10', new \DateTimeZone('UTC'))
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
            \DateTime::createFromFormat('Y-m-d H:i:s', '2012-01-17 20:46:16', new \DateTimeZone('UTC'))
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
        $this->assertNotEmpty($row->getDeleted());

        // Index the previously-deleted record and make sure it undeletes properly:
        $tracker->index($core, 'test2', 1326833170);
        $row = $tracker->getChangeTrackerEntity($core, 'test2');
        $this->assertIsObject($row);
        $this->assertEmpty($row->getDeleted());
        $this->assertEquals(
            $row->getLastRecordChange(),
            \DateTime::createFromFormat('Y-m-d H:i:s', '2012-01-17 20:46:10', new \DateTimeZone('UTC'))
        );

        // Clean up after ourselves:
        $tracker->deleteRows($core);
    }
}
