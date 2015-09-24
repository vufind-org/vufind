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

    protected $currentUser = false;

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
                'cacheId' => md5('Solr|020645147|2'),
                'source' => 'Solr',
                'version' => '2.5',
                'data' => 's:17:"dummy_solr_record";'
            ],
            [
                'cacheId' => md5('WorldCat|70764764|2'),
                'source' => 'WorldCat',
                'version' => '2.5',
                'data' => 's:21:"dummy_worldcat_record";'
            ],
            [
                'cacheId' => md5('Solr|00033321'),
                'source' => 'Solr',
                'version' => '2.5',
                'data' => 's:30:"dummy_solr_record_without_user";'
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
        $this->setCurrentUser(2);

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

        $this->setCurrentUser(2);

        $record = $recordCache->lookup(
            ['Solr|1234']
        );

        $this->setCurrentUser(3);

        $record = $recordCache->lookup(
            ['Solr|020645147']
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

        $recordCache->setContext('TestCase1');
        $this->assertFalse($recordCache->isPrimary('Solr'));
        $this->assertFalse($recordCache->isFallback('Solr'));

        $recordCache->setContext('TestCase2');
        $this->assertTrue($recordCache->isPrimary('Solr'));
        $this->assertFalse($recordCache->isFallback('Solr'));

        $this->setCurrentUser(false);
        $record = $recordCache->lookup(['00033321'], 'Solr');
        $this->assertEmpty($record);
        $record = $recordCache->lookup(['020645147'], 'Solr');
        $this->assertEmpty($record);

        $this->setCurrentUser(2);
        $record = $recordCache->lookup(['00033321'], 'Solr');
        $this->assertEmpty($record);
        $record = $recordCache->lookup(['020645147'], 'Solr');
        $this->assertNotEmpty($record);

        $recordCache->setContext('TestCase3');
        $this->assertTrue($recordCache->isPrimary('Solr'));
        $record = $recordCache->lookup(['020645147'], 'Solr');
        $this->assertEmpty($record);
    }

    /**
     * Test createOrUpdate
     *
     * @return void
     */
    public function testCreateOrUpdate()
    {
        $recordCache = $this->getRecordCache();

        $recordCache->createOrUpdate(
            '112233', 2, 'Solr', serialize('dummy_data'), null
        );

        $this->setCurrentUser(2);

        $record = $recordCache->lookup(['112233'], 'Solr');
        $this->assertNotEmpty($record);

        $this->setCurrentUser(3);

        $record = $recordCache->lookup(['112233'], 'Solr');
        $this->assertEmpty($record);

        $recordCache->cleanup(2);
        $this->setCurrentUser(2);

        $record = $recordCache->lookup(['112233'], 'Solr');
        $this->assertEmpty($record);
    }

    /**
     * Get current user
     *
     * @return Object|false
     */
    public function getCurrentUser()
    {
        return $this->currentUser === false
            ? false : $this->getUser($this->currentUser);
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
                'Solr' => [
                    'operatingMode' => 'primary',
                    'cacheIdComponents' => 'userId'
                ],
                'WorldCat' => [
                    'operatingMode' => 'fallback',
                    'cacheIdComponents' => 'userId'
                ],
            ],
            'TestCase1' => [
                'Solr' => [
                    'cacheIdComponents' => 'userId'
                ]
            ],
            'TestCase2' => [
                'Solr' => [
                    'operatingMode' => 'primary',
                    'cacheIdComponents' => 'userId'
                ]
            ],
            'TestCase3' => [
                'Solr' => [
                    'operatingMode' => 'primary',
                    'cacheIdComponents' => ''
                ]
            ]
        ];

        $config = new \Zend\Config\Config($configArr);

        return $config;
    }

    /**
     * Create DbTableManager
     *
     * @return multitype:multitype:string number
     *         |multitype:|PHPUnit_Framework_MockObject_MockObject
     */
    protected function getDbTableManager()
    {
        $callback = function ($id) {
            foreach ($this->recordTable as $row) {
                if ($row['cacheId'] == $id[0]) {
                    return [$row];
                }
            }
            return [];
        };

        $recordTable = $this->getMock('VuFind\Db\Table\Record');
        $recordTable->method('findRecords')->will($this->returnCallback($callback));

        $updateRecordCallback = function(
            $cacheId, $source, $rawData, $recordId, $userId, $resourceId
        ) {
            $this->recordTable[] = [
                'cacheId' => $cacheId,
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

        $mb = $this->getMockBuilder('VuFind\Db\Table\PluginManager');
        $dbTableManager = $mb->getMock();
        $dbTableManager->method('get')->will($this->returnValue($recordTable));

        return $dbTableManager;
    }

    /**
     * Create a user
     *
     * @param unknown $userId userId
     *
     * @return PHPUnit_Framework_MockObject_MockObject
     */
    protected function getUser($userId)
    {
        $mb = $this->getMockBuilder('VuFind\Db\Table\User')
            ->disableOriginalConstructor();
        $user = $mb->getMock();
        $user->method('__get')
            ->will($this->returnValue($userId));

        return $user;
    }

    /**
     * Activate a user
     *
     * @param int|false $id User id
     *
     * @return void
     */
    protected function setCurrentUser($id)
    {
        $this->currentUser = $id;
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
        $recordFactoryManager = $this->getRecordFactoryManager();
        $config = $this->getConfig();
        $dbTableManager = $this->getDbTableManager();

        $mb = $this->getMockBuilder('VuFind\Auth\Manager')
            ->disableOriginalConstructor();
        $authManager = $mb->getMock();
        $authManager->method('isLoggedIn')
            ->will($this->returnCallback([$this, 'getCurrentUser']));

        $recordCache = new Cache(
            $recordFactoryManager, $config, $dbTableManager, $authManager
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
