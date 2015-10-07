<?php

/**
 * Record cache tests.
 *
 * PHP version 5
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
 * Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
 *
 * @category VuFind2
 * @package  Tests
 * @author   Squiz Pty Ltd <products@squiz.net>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:unit_tests Wiki
 */
namespace VuFindTest\Record;
use VuFind\Record\Cache;
use VuFindTest\Unit\TestCase as TestCase;

/**
 * Record cache tests.
 *
 * @category VuFind2
 * @package  Tests
 * @author   Squiz Pty Ltd <products@squiz.net>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:unit_tests Wiki
 */
class CacheTest extends TestCase
{
    protected $recordTable = [];

    /**
     * Set up everything for testing
     *
     * @return void
     */
    protected function setUp()
    {
        $cache = $this->getRecordCache();
        $this->recordTable = [
            [
                'record_id' => '020645147',
                'source' => 'Solr',
                'version' => '2.5',
                'data' => 's:17:"dummy_solr_record";'
            ],
            [
                'record_id' => '70764764',
                'source' => 'WorldCat',
                'version' => '2.5',
                'data' => 's:21:"dummy_worldcat_record";'
            ],
            [
                'record_id' => '00033321',
                'source' => 'Solr',
                'version' => '2.5',
                'data' => 's:19:"dummy_solr_record_2";'
            ],
        ];
    }

    /**
     * Test successful lookups
     *
     * @return void
     */
    public function testLookupSuccess()
    {
        $recordCache = $this->getRecordCache();

        $record = $recordCache->lookup(['020645147'], 'Solr');
        $this->assertNotEmpty($record);

        $record = $recordCache->lookup(['70764764'], 'WorldCat');
        $this->assertNotEmpty($record);
    }

    /**
     * Test lookup failures
     *
     * @return void
     */
    public function testLookupFailure()
    {
        $recordCache = $this->getRecordCache();

        $record = $recordCache->lookup(
            ['Solr|020645147']
        );

        $record = $recordCache->lookup(
            ['Solr|1234']
        );

        $record = $recordCache->lookup(
            ['Solr|0206451']
        );

        $this->assertEmpty($record);
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

        $record = $recordCache->lookup(['00033321'], 'Solr');
        $this->assertEmpty($record);

        $recordCache->setContext('Fallback');
        $this->assertFalse($recordCache->isPrimary('Solr'));
        $this->assertTrue($recordCache->isFallback('Solr'));

        $record = $recordCache->lookup(['00033321'], 'Solr');
        $this->assertNotEmpty($record);

        $recordCache->setContext('Default');
        $this->assertTrue($recordCache->isPrimary('Solr'));
        $this->assertFalse($recordCache->isFallback('Solr'));

        $record = $recordCache->lookup(['00033321'], 'Solr');
        $this->assertNotEmpty($record);
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

        $record = $recordCache->lookup(['112233'], 'Solr');
        $this->assertNotEmpty($record);

        $recordCache->cleanup(['112233'], 'Solr');

        $record = $recordCache->lookup(['112233'], 'Solr');
        $this->assertEmpty($record);
    }

    /**
     * Create configuration
     *
     * @return \Zend\Config\Config
     */
    protected function getConfig()
    {
        $configArr = [
            'Default' => [
                'Solr' => ['operatingMode' => 'primary'],
                'WorldCat' => ['operatingMode' => 'fallback'],
            ],
            'Disabled' => [
                'Solr' => []
            ],
            'Fallback' => [
                'Solr' => ['operatingMode' => 'fallback']
            ],
        ];

        $config = new \Zend\Config\Config($configArr);

        return $config;
    }

    /**
     * Create Record Table
     *
     * @return PHPUnit_Framework_MockObject_MockObject
     */
    protected function getRecordTable()
    {
        $callback = function ($id) {
            foreach ($this->recordTable as $row) {
                if ($row['record_id'] == $id[0]['id']
                    && $row['source'] == $id[0]['source']
                ) {
                    return [$row];
                }
            }
            return [];
        };

        $recordTable = $this->getMock('VuFind\Db\Table\Record');
        $recordTable->method('findRecords')->will($this->returnCallback($callback));

        $updateRecordCallback = function(
            $recordId, $source, $rawData
        ) {
            $this->recordTable[] = [
                'record_id' => $recordId,
                'source' => $source,
                'version' => '2.5',
                'data' => serialize($rawData)
            ];
        };
        $recordTable->method('updateRecord')
            ->will($this->returnCallback($updateRecordCallback));

        $cleanupCallback = function ($userId) {
            $this->recordTable = [];
        };
        $recordTable->method('cleanup')
            ->will($this->returnCallback($cleanupCallback));

        return $recordTable;
    }

    /**
     * Create a Record Factory Manager
     *
     * @return PHPUnit_Framework_MockObject_MockObject
     */
    protected function getRecordFactoryManager()
    {
        $recordFactoryManager = $this->getMock(
            'VuFind\RecordDriver\PluginManager'
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
     * @return PHPUnit_Framework_MockObject_MockObject
     */
    protected function getDriver($id = 'test', $source = 'Solr')
    {
        $driver = $this->getMock('VuFind\RecordDriver\AbstractBase');
        $driver->expects($this->any())
            ->method('getUniqueId')
            ->will($this->returnValue($id));
        $driver->expects($this->any())
            ->method('getResourceSource')
            ->will($this->returnValue($source));
        return $driver;
    }
}
