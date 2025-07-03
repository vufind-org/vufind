<?php

/**
 * Unit tests for ProQuestFSG backend.
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2024.
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
 * @package  Search
 * @author   Maccabee Levine <msl321@lehigh.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org
 */

namespace VuFindTest\Backend\ProQuestFSG;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use VuFindSearch\Backend\ProQuestFSG\Backend;
use VuFindSearch\Backend\ProQuestFSG\Connector;
use VuFindSearch\Backend\ProQuestFSG\Response\XML\RecordCollectionFactory;

/**
 * Unit tests for ProQuestFSG backend.
 *
 * @category VuFind
 * @package  Search
 * @author   Maccabee Levine <msl321@lehigh.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org
 */
class BackendTest extends TestCase
{
    use \VuFindTest\Feature\FixtureTrait;

    /**
     * Test performing a search.
     *
     * @return void
     */
    public function testSearch()
    {
        $connector = $this->getConnector('proquestfsg/connector_searchresult.json');
        $backend = new Backend(
            $connector,
            $this->getRecordCollectionFactory()
        );
        $collection = $backend->search(
            new \VuFindSearch\Query\Query(
                'painted pomegranates and needlepoint rabbis',
                'cql.serverChoice'
            ),
            1,
            20
        );
        $this->assertCount(2, $collection);
        $this->assertEquals('ProQuestFSG', $collection->getSourceIdentifier());
        $this->assertEquals(
            'ProQuestFSG',
            $collection->getRecords()[0]->getSourceIdentifier()
        );
    }

    /**
     * Test retrieving a record.
     *
     * @return void
     */
    public function testRetrieve()
    {
        $connector = $this->getConnector('proquestfsg/connector_record.json');
        $backend = new Backend(
            $connector,
            $this->getRecordCollectionFactory()
        );
        $collection = $backend->retrieve(
            '2811962947'
        );
        $this->assertCount(1, $collection);
        $this->assertEquals('ProQuestFSG', $collection->getSourceIdentifier());
        $this->assertEquals(
            'ProQuestFSG',
            $collection->getRecords()[0]->getSourceIdentifier()
        );
    }

    /**
     * Build a RecordCollectionFactory.
     *
     * @return RecordCollectionFactory
     */
    protected function getRecordCollectionFactory(): RecordCollectionFactory
    {
        $callback = function ($data) {
            $driver = new \VuFind\RecordDriver\ProQuestFSG();
            $driver->setRawData($data);
            return $driver;
        };
        return new RecordCollectionFactory($callback);
    }

    /**
     * Mock a connector.
     *
     * @param string $responseFixtureName Fixture to load for connector response
     *
     * @return Connector
     */
    protected function getConnector($responseFixtureName): MockObject&Connector
    {
        $fixture = $this->getFixture($responseFixtureName);
        $response = json_decode($fixture, true);
        $connector = $this->createMock(Connector::class);
        $connector->method('search')->willReturn($response);
        $connector->method('getRecord')->willReturn($response);
        return $connector;
    }
}
