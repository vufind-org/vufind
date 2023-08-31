<?php

/**
 * Record cache tests.
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2010.
 * Copyright (C) The National Library of Finland 2015.
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
 * @author   Squiz Pty Ltd <products@squiz.net>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */

namespace VuFindTest\Record;

use VuFind\Db\Entity\Record;
use VuFind\Record\Cache;

use function in_array;

/**
 * Record cache tests.
 *
 * @category VuFind
 * @package  Tests
 * @author   Squiz Pty Ltd <products@squiz.net>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */
class CacheTest extends \PHPUnit\Framework\TestCase
{
    protected $recordTable = [];

    /**
     * Set up everything for testing
     *
     * @return void
     */
    protected function setUp(): void
    {
        $cache = $this->getRecordCache();
        $record1 = new Record();
        $record2 = new Record();
        $record3 = new Record();
        $this->recordTable = [
            $record1->setRecordId('020645147')
                ->setSource('Solr')
                ->setVersion('2.5')
                ->setData('s:17:"dummy_solr_record";'),

            $record2->setRecordId('70764764')
                ->setSource('WorldCat')
                ->setVersion('2.5')
                ->setData('s:21:"dummy_worldcat_record";'),

            $record3->setRecordId('00033321')
                ->setSource('Solr')
                ->setVersion('2.5')
                ->setData('s:19:"dummy_solr_record_2";'),
        ];
    }

    /**
     * Test lookup
     *
     * @return void
     */
    public function testLookup()
    {
        $recordCache = $this->getRecordCache();

        $record = $recordCache->lookup('020645147', 'Solr');
        $this->assertNotEmpty($record);

        $record = $recordCache->lookup('70764764', 'WorldCat');
        $this->assertNotEmpty($record);

        $record = $recordCache->lookup('1234', 'Solr');
        $this->assertEmpty($record);

        $record = $recordCache->lookup('0206451', 'Solr');
        $this->assertEmpty($record);
    }

    /**
     * Test lookupBatch
     *
     * @return void
     */
    public function testLookupBatch()
    {
        $recordCache = $this->getRecordCache();

        $records = $recordCache->lookupBatch(['020645147', '00033321'], 'Solr');
        $this->assertCount(2, $records);

        $records = $recordCache->lookupBatch(['020645147', '1234'], 'Solr');
        $this->assertCount(1, $records);

        $records = $recordCache->lookupBatch(['020645147', '00033321'], 'WorldCat');
        $this->assertEmpty($records);

        $records = $recordCache->lookupBatch(['0206451', '1234'], 'Solr');
        $this->assertEmpty($records);
    }

    /**
     * Test isFallback
     *
     * @return void
     */
    public function testIsFallback()
    {
        $recordCache = $this->getRecordCache();

        $this->assertFalse($recordCache->isFallback('Solr'));
        $this->assertTrue($recordCache->isFallback('WorldCat'));

        $this->assertFalse($recordCache->isFallback('Summon'));
    }

    /**
     * Test isPrimary
     *
     * @return void
     */
    public function testIsPrimary()
    {
        $recordCache = $this->getRecordCache();

        $this->assertTrue($recordCache->isPrimary('Solr'));
        $this->assertFalse($recordCache->isPrimary('WorldCat'));

        $this->assertFalse($recordCache->isPrimary('Summon'));
    }

    /**
     * Test IsCachable
     *
     * @return void
     */
    public function testIsCachable()
    {
        $recordCache = $this->getRecordCache();

        $this->assertTrue($recordCache->isCachable('Solr'));
        $this->assertTrue($recordCache->isCachable('WorldCat'));
        $this->assertFalse($recordCache->isCachable('Summon'));
    }

    /**
     * Test setContext
     *
     * @return void
     */
    public function testSetContext()
    {
        $recordCache = $this->getRecordCache();

        $recordCache->setContext('Disabled');
        $this->assertFalse($recordCache->isPrimary('Solr'));
        $this->assertFalse($recordCache->isFallback('Solr'));

        $recordCache->setContext('Fallback');
        $this->assertFalse($recordCache->isPrimary('Solr'));
        $this->assertTrue($recordCache->isFallback('Solr'));

        $recordCache->setContext('Default');
        $this->assertTrue($recordCache->isPrimary('Solr'));
        $this->assertFalse($recordCache->isFallback('Solr'));
    }

    /**
     * Test createOrUpdate
     *
     * @return void
     */
    public function testCreateOrUpdate()
    {
        $recordCache = $this->getRecordCache();

        $recordCache->createOrUpdate('112233', 'Solr', serialize('dummy_data'));

        $record = $recordCache->lookup('112233', 'Solr');
        $this->assertNotEmpty($record);
    }

    /**
     * Create configuration
     *
     * @return \Laminas\Config\Config
     */
    protected function getConfig()
    {
        $configArr = [
            'Default' => [
                'Solr' => ['operatingMode' => 'primary'],
                'WorldCat' => ['operatingMode' => 'fallback'],
            ],
            'Disabled' => [
                'Solr' => [],
            ],
            'Fallback' => [
                'Solr' => ['operatingMode' => 'fallback'],
            ],
        ];

        $config = new \Laminas\Config\Config($configArr);

        return $config;
    }

    /**
     * Create Record Table
     *
     * @return \VuFind\Db\Service\RecordService
     */
    protected function getRecordTable(): \VuFind\Db\Service\RecordService
    {
        $findRecordsCallback = function (array $ids, string $source): array {
            $results = [];
            foreach ($this->recordTable as $row) {
                if (
                    in_array($row->getRecordId(), $ids)
                    && $row->getSource() == $source
                ) {
                    $results[] = $row;
                }
            }
            return $results;
        };

        $findRecordCallback = function ($id, $source) {
            foreach ($this->recordTable as $row) {
                if (
                    $row->getRecordId() == $id
                    && $row->getSource() == $source
                ) {
                    return $row;
                }
            }
            return false;
        };

        $recordTable = $this->getMockBuilder(\VuFind\Db\Service\RecordService::class)
            ->disableOriginalConstructor()->getMock();
        $recordTable->method('findRecords')
            ->will($this->returnCallback($findRecordsCallback));
        $recordTable->method('findRecord')
            ->will($this->returnCallback($findRecordCallback));

        $updateRecordCallback = function ($recordId, $source, $rawData): void {
            $record = new Record();
            $record->setRecordId($recordId)
                ->setSource($source)
                ->setVersion('2.5')
                ->setData(serialize($rawData));
            $this->recordTable[] = $record;
        };
        $recordTable->method('updateRecord')
            ->will($this->returnCallback($updateRecordCallback));

        return $recordTable;
    }

    /**
     * Create a Record Factory Manager
     *
     * @return \PHPUnit\Framework\MockObject\MockObject
     */
    protected function getRecordFactoryManager(): \PHPUnit\Framework\MockObject\MockObject
    {
        $recordFactoryManager = $this->createMock(
            \VuFind\RecordDriver\PluginManager::class
        );
        $recordFactoryManager->method('getSolrRecord')->will(
            $this->returnValue($this->getDriver('test', 'Solr'))
        );

        $recordFactoryManager->method('get')->will(
            $this->returnValue($this->getDriver('test', 'WorldCat'))
        );

        return $recordFactoryManager;
    }

    /**
     * Create a Cache object
     *
     * @return \VuFind\Record\Cache
     */
    protected function getRecordCache()
    {
        $recordCache = new Cache(
            $this->getRecordFactoryManager(),
            $this->getConfig(),
            $this->getRecordTable()
        );

        return $recordCache;
    }

    /**
     * Create a record driver
     *
     * @param string $id     id
     * @param string $source source
     *
     * @return \VuFind\RecordDriver\AbstractBase
     */
    protected function getDriver(
        $id = 'test',
        $source = 'Solr'
    ): \VuFind\RecordDriver\AbstractBase {
        $driver = $this->createMock(\VuFind\RecordDriver\AbstractBase::class);
        $driver->expects($this->any())
            ->method('getUniqueId')
            ->will($this->returnValue($id));
        $driver->expects($this->any())
            ->method('getSourceIdentifier')
            ->will($this->returnValue($source));
        return $driver;
    }
}
