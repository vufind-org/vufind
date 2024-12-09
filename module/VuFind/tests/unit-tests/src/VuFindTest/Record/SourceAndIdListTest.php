<?php

/**
 * SourceAndIdList tests.
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2021.
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

namespace VuFindTest\Record;

use VuFind\Record\SourceAndIdList;
use VuFindTest\RecordDriver\TestHarness;

/**
 * SourceAndIdList tests.
 *
 * @category VuFind
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */
class SourceAndIdListTest extends \PHPUnit\Framework\TestCase
{
    /**
     * Test normalization -- regardless of how the data is sent in, the result should
     * be the same.
     *
     * @return void
     */
    public function testListNormalization(): void
    {
        $array = [
            ['source' => 'foo', 'id' => 'record1'],
            ['source' => 'bar', 'id' => 'record2'],
            ['source' => 'bar', 'id' => 'record1'],
        ];
        $arrayList = new SourceAndIdList($array);
        $string = ['foo|record1', 'bar|record2', 'bar|record1'];
        $stringList = new SourceAndIdList($string);
        $mixed = [
            ['source' => 'foo', 'id' => 'record1'],
            'bar|record2',
            'bar|record1',
        ];
        $mixedList = new SourceAndIdList($mixed);

        // Regardless of whether the input was an array of arrays, strings, or both,
        // the output should match the array format.
        $this->assertEquals($array, $arrayList->getAll());
        $this->assertEquals($array, $stringList->getAll());
        $this->assertEquals($array, $mixedList->getAll());

        // The sorted format should also be the same:
        $expected = [
            'foo' => ['record1'],
            'bar' => ['record2', 'record1'],
        ];
        $this->assertEquals($expected, $arrayList->getIdsBySource());
        $this->assertEquals($expected, $stringList->getIdsBySource());
        $this->assertEquals($expected, $mixedList->getIdsBySource());
    }

    /**
     * Test retrieving the position of a regular record from the list.
     *
     * @return void
     */
    public function testRegularRetrieve(): void
    {
        $bar = new TestHarness();
        $bar->setRawData(['SourceIdentifier' => 'source', 'UniqueID' => 'bar']);
        $baz = new TestHarness();
        $baz->setRawData(['SourceIdentifier' => 'source', 'UniqueID' => 'baz']);
        $xyzzy = new TestHarness();
        $xyzzy->setRawData(['SourceIdentifier' => 'source', 'UniqueID' => 'xyzzy']);

        // Confirm that we get correct positions for the two records in the list,
        // and an empty array for the record not in the list.
        $list = new SourceAndIdList(['source|bar', 'source|baz']);
        $this->assertEquals([0], $list->getRecordPositions($bar));
        $this->assertEquals([1], $list->getRecordPositions($baz));
        $this->assertEquals([], $list->getRecordPositions($xyzzy));
    }

    /**
     * Test retrieving the position of a record with a changed ID from the list.
     *
     * @return void
     */
    public function testChangedRecordId(): void
    {
        $record = new TestHarness();
        $record->setRawData(
            [
                'SourceIdentifier' => 'source',
                'UniqueID' => 'newID',
                'PreviousUniqueId' => 'oldID',
            ]
        );

        // Confirm that when the list contains an old record ID, it still matches as
        // long as the record remembers its previous ID.
        $list = new SourceAndIdList(['source|fake1', 'source|oldID', 'source|fake2']);
        $this->assertEquals([1], $list->getRecordPositions($record));
    }

    /**
     * Test retrieving the positions of a record that appears in the list repeatedly.
     *
     * @return void
     */
    public function testRepeatingRecord(): void
    {
        $record = new TestHarness();
        $record->setRawData(['SourceIdentifier' => 'source', 'UniqueID' => 'fake1']);

        // Confirm that when the list contains an old record ID, it still matches as
        // long as the record remembers its previous ID.
        $list = new SourceAndIdList(['source|fake1', 'source|fake2', 'source|fake1']);
        $this->assertEquals([0, 2], $list->getRecordPositions($record));
    }

    /**
     * Test that source has to be matched to retrieve a record.
     *
     * @return void
     */
    public function testSourceRequired(): void
    {
        $record = new TestHarness();
        $record->setRawData(['UniqueID' => 'fake1']); // no source specified!
        $list = new SourceAndIdList(['source|fake1', 'source|fake2', 'source|fake1']);
        $this->assertEquals([], $list->getRecordPositions($record));
    }
}
