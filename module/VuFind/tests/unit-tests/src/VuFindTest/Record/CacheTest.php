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

use Laminas\Config\Config;
use PHPUnit\Framework\MockObject\MockObject;
use VuFind\Db\Entity\RecordEntityInterface;
use VuFind\Db\Service\RecordServiceInterface;
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
    /**
     * Set of test records.
     *
     * @var RecordEntityInterface[]
     */
    protected $recordData = [];

    /**
     * Create a mock record that will return the provided values.
     *
     * @param string $id      Record ID
     * @param string $source  Record source
     * @param string $data    Data
     * @param string $version Version
     *
     * @return MockObject&RecordEntityInterface
     */
    protected function getMockRecord(
        string $id,
        string $source,
        string $data,
        string $version = '2.5'
    ): MockObject&RecordEntityInterface {
        $mock = $this->createMock(RecordEntityInterface::class);
        $mock->method('getRecordId')->willReturn($id);
        $mock->method('getSource')->willReturn($source);
        $mock->method('getData')->willReturn($data);
        $mock->method('getVersion')->willReturn($version);
        return $mock;
    }

    /**
     * Set up everything for testing
     *
     * @return void
     */
    protected function setUp(): void
    {
        $this->recordData = [
            $this->getMockRecord('020645147', 'Solr', 's:17:"dummy_solr_record";'),
            $this->getMockRecord('70764764', 'WorldCat2', 's:22:"dummy_worldcat2_record";'),
            $this->getMockRecord('00033321', 'Solr', 's:19:"dummy_solr_record_2";'),
        ];
    }

    /**
     * Test lookup
     *
     * @return void
     */
    public function testLookup(): void
    {
        $recordCache = $this->getRecordCache();

        $record = $recordCache->lookup('020645147', 'Solr');
        $this->assertNotEmpty($record);

        $record = $recordCache->lookup('70764764', 'WorldCat2');
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
    public function testLookupBatch(): void
    {
        $recordCache = $this->getRecordCache();

        $records = $recordCache->lookupBatch(['020645147', '00033321'], 'Solr');
        $this->assertCount(2, $records);

        $records = $recordCache->lookupBatch(['020645147', '1234'], 'Solr');
        $this->assertCount(1, $records);

        $records = $recordCache->lookupBatch(['020645147', '00033321'], 'WorldCat2');
        $this->assertEmpty($records);

        $records = $recordCache->lookupBatch(['0206451', '1234'], 'Solr');
        $this->assertEmpty($records);
    }

    /**
     * Test isFallback
     *
     * @return void
     */
    public function testIsFallback(): void
    {
        $recordCache = $this->getRecordCache();

        $this->assertFalse($recordCache->isFallback('Solr'));
        $this->assertTrue($recordCache->isFallback('WorldCat2'));

        $this->assertFalse($recordCache->isFallback('Summon'));
    }

    /**
     * Test isPrimary
     *
     * @return void
     */
    public function testIsPrimary(): void
    {
        $recordCache = $this->getRecordCache();

        $this->assertTrue($recordCache->isPrimary('Solr'));
        $this->assertFalse($recordCache->isPrimary('WorldCat2'));

        $this->assertFalse($recordCache->isPrimary('Summon'));
    }

    /**
     * Test IsCachable
     *
     * @return void
     */
    public function testIsCachable(): void
    {
        $recordCache = $this->getRecordCache();

        $this->assertTrue($recordCache->isCachable('Solr'));
        $this->assertTrue($recordCache->isCachable('WorldCat2'));
        $this->assertFalse($recordCache->isCachable('Summon'));
    }

    /**
     * Test setContext
     *
     * @return void
     */
    public function testSetContext(): void
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
    public function testCreateOrUpdate(): void
    {
        $recordCache = $this->getRecordCache();

        $recordCache->createOrUpdate('112233', 'Solr', serialize('dummy_data'));
        $record = $recordCache->lookup('112233', 'Solr');
        $this->assertNotEmpty($record);
    }

    /**
     * Create configuration
     *
     * @return Config
     */
    protected function getConfig(): Config
    {
        $configArr = [
            'Default' => [
                'Solr' => ['operatingMode' => 'primary'],
                'WorldCat2' => ['operatingMode' => 'fallback'],
            ],
            'Disabled' => [
                'Solr' => [],
            ],
            'Fallback' => [
                'Solr' => ['operatingMode' => 'fallback'],
            ],
        ];

        return new Config($configArr);
    }

    /**
     * Create Record Table
     *
     * @return MockObject&RecordServiceInterface
     */
    protected function getRecordService(): MockObject&RecordServiceInterface
    {
        $findRecordsCallback = function (array $ids, string $source): array {
            $results = [];
            foreach ($this->recordData as $row) {
                if (in_array($row->getRecordId(), $ids) && $row->getSource() == $source) {
                    $results[] = $row;
                }
            }
            return $results;
        };

        $findRecordCallback = function ($id, $source): ?RecordEntityInterface {
            foreach ($this->recordData as $row) {
                if ($row->getRecordId() == $id && $row->getSource() == $source) {
                    return $row;
                }
            }
            return null;
        };

        $updateRecordCallback = function ($recordId, $source, $rawData): RecordEntityInterface {
            $record = $this->getMockRecord($recordId, $source, $rawData);
            $this->recordData[] = $record;
            return $record;
        };

        $recordService = $this->createMock(RecordServiceInterface::class);
        $recordService->method('getRecords')->willReturnCallback($findRecordsCallback);
        $recordService->method('getRecord')->willReturnCallback($findRecordCallback);
        $recordService->method('updateRecord')->willReturnCallback($updateRecordCallback);

        return $recordService;
    }

    /**
     * Create a Record Factory Manager
     *
     * @return MockObject&\VuFind\RecordDriver\PluginManager
     */
    protected function getRecordFactoryManager(): MockObject&\VuFind\RecordDriver\PluginManager
    {
        $recordFactoryManager = $this->createMock(\VuFind\RecordDriver\PluginManager::class);
        $recordFactoryManager->method('getSolrRecord')->willReturn($this->getDriver('test', 'Solr'));
        $recordFactoryManager->method('get')->willReturn($this->getDriver('test', 'WorldCat2'));
        return $recordFactoryManager;
    }

    /**
     * Create a Cache object
     *
     * @return Cache
     */
    protected function getRecordCache(): Cache
    {
        $recordCache = new Cache(
            $this->getRecordFactoryManager(),
            $this->getConfig(),
            $this->getRecordService()
        );

        return $recordCache;
    }

    /**
     * Create a record driver
     *
     * @param string $id     id
     * @param string $source source
     *
     * @return MockObject&\VuFind\RecordDriver\AbstractBase
     */
    protected function getDriver(
        $id = 'test',
        $source = 'Solr'
    ): MockObject&\VuFind\RecordDriver\AbstractBase {
        $driver = $this->createMock(\VuFind\RecordDriver\AbstractBase::class);
        $driver->method('getUniqueId')->willReturn($id);
        $driver->method('getSourceIdentifier')->willReturn($source);
        return $driver;
    }
}
