<?php

/**
 * Record loader tests.
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
 * @author   Squiz Pty Ltd <products@squiz.net>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:unit_tests Wiki
 */
namespace VuFindTest\Record;
use VuFind\Record\Cache;
use VuFind\RecordDriver\AbstractBase as RecordDriver;
use VuFind\RecordDriver\PluginManager as RecordFactory;
use VuFindSearch\Response\RecordCollectionInterface;
use VuFindSearch\Service as SearchService;
use VuFindTest\Unit\TestCase as TestCase;

/**
 * Record loader tests.
 *
 * @category VuFind2
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public
 *          License
 * @link     http://vufind.org/wiki/vufind2:unit_tests Wiki
 */
class CacheTest extends TestCase
{

    protected $recordTable = [
                [
                        'cId' => '1acac94e37e2e60b3da9d680b41bcf01',
                        'source' => 'Solr',
                        'version' => 1,
                        'data' => 'dumy_solr_record'
                ],
                [
                        'cId' => '71b87df587f08cabd62a5e579ff5bcd4',
                        'source' => 'WorldCat',
                        'version' => 1,
                        'data' => 'dummy_wordlcat_record'
                ]
        ];
    
    /**
     * testLookupSuccess
     *
     * @return null
     */
    function testLookupSuccess ()
    {
        $recordCache = $this->getRecordCache();
        
        $_SESSION['Account'] = $this->getUser(2);
        $record = $recordCache->lookup(['020645147'], 'Solr');
        $this->assertNotEmpty($record);
        
        $_SESSION['Account'] = $this->getUser(2);
        $record = $recordCache->lookup(['70764764'], 'WorldCat');
        $this->assertNotEmpty($record);
        
    }
    
    /**
     * testLookupFailure
     *
     * @return null
     */
    function testLookupFailure ()
    {
        $recordCache = $this->getRecordCache();
        
        $_SESSION['Account'] = $this->getUser(3);
        $record = $recordCache->lookup(
            ['Solr|020645147']
        );
        
        $this->assertEmpty($record);
    }
    
    /**
     * testIsFallback
     *
     * @return null
     */
    function testIsFallback ()
    {
        $recordCache = $this->getRecordCache();
        
        $this->assertFalse($recordCache->isFallback('Solr'));
        $this->assertTrue($recordCache->isFallback('WorldCat'));
        
        $this->assertFalse($recordCache->isFallback('Summon'));
    }
    
    /**
     * testIsPrimary
     *
     * @return null
     */
    function testIsPrimary ()
    {
        $recordCache = $this->getRecordCache();
        
        $this->assertTrue($recordCache->isPrimary('Solr'));
        $this->assertFalse($recordCache->isPrimary('WorldCat'));
        
        $this->assertFalse($recordCache->isPrimary('Summon'));
    }
    
    /**
     * testIsCachable
     *
     * @return null
     */
    function testIsCachable ()
    {
        $recordCache = $this->getRecordCache();
        
        $this->assertTrue($recordCache->isCacheable('Solr'));
        $this->assertTrue($recordCache->isCacheable('WorldCat'));
        $this->assertFalse($recordCache->isCacheable('Summon'));
    }
    
    /**
     * testSetPolicy
     *
     * @return null
     */
    function testSetPolicy()     
    {
        $recordCache = $this->getRecordCache();
        
        $recordCache->setPolicy('TestCase1');
        
        $this->assertFalse($recordCache->isPrimary('Solr'));
        $this->assertFalse($recordCache->isFallback('Solr'));
    
        $recordCache->setPolicy('TestCase2');
        $_SESSION['Account'] = $this->getUser(2);
        $record = $recordCache->lookup(['020645147'], 'Solr');
        $this->assertTrue($recordCache->isPrimary('Solr'));
        $this->assertFalse($recordCache->isFallback('Solr'));
        $this->assertNotEmpty($record);

        $recordCache->setPolicy('TestCase3');
        $_SESSION['Account'] = $this->getUser(2);
        $record = $recordCache->lookup(['020645147'], 'Solr');
        $this->assertTrue($recordCache->isPrimary('Solr'));
        $this->assertEmpty($record);
    
    }
    
    
 
    /**
     * testCreateOrUpdate
     * 
     * @return null;
     */
    public function testCreateOrUpdate() 
    {
        $recordCache = $this->getRecordCache();
        
        $recordCache->createOrUpdate('112233', 2, 'Solr', 'dummy_data', null, null);

        $_SESSION['Account'] = $this->getUser(2);
        $record = $recordCache->lookup(['112233'], 'Solr');
        $this->assertNotEmpty($record);
        
        $_SESSION['Account'] = $this->getUser(3);
        $record = $recordCache->lookup(['112233'], 'Solr');
        $this->assertEmpty($record);
        
        $recordCache->cleanup(2);
        $_SESSION['Account'] = $this->getUser(2);
        $record = $recordCache->lookup(['112233'], 'Solr');
        $this->assertEmpty($record);
        
    }
    
    /**
     * getConfig
     *
     * @return \Zend\Config\Config
     */
    protected function getConfig ()
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
     * getDbTableManager
     *
     * @return multitype:multitype:string number
     *         |multitype:|PHPUnit_Framework_MockObject_MockObject
     */
    protected function getDbTableManager ()
    {
        $callback = function ($id)
        {
            foreach ($this->recordTable as $row) {
                if ($row['cId'] == $id[0]) {
                    return [$row];
                }
            }
            return [];
        };
        
        $recordTable = $this->getMock('VuFind\Db\Table\Record');
        $recordTable->method('findRecord')->will($this->returnCallback($callback));
        
        $updateRecordCallback = function(
                $cId, $source, $rawData, $recordId, $userId, $sessionId, $resourceId
        ) {
            $this->recordTable[] 
                = ["cId" => "$cId",
                   "source" => "$source",
                   "version" => 1,
                   "data" => "$rawData"
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
     * getUser
     *
     * @param unknown $userId userId
     *            
     * @return PHPUnit_Framework_MockObject_MockObject
     */
    protected function getUser ($userId)
    {
        $mb = $this->getMockBuilder('VuFind\Db\Table\User')
            ->disableOriginalConstructor();
        $user = $mb->getMock();
        $user->method('__get')
            ->will($this->returnValue($userId));
        
        return $user;
    }
    
    /**
     * getRecordFactoryManager
     *
     * @return PHPUnit_Framework_MockObject_MockObject
     */
    protected function getRecordFactoryManager ()
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
     * getRecordCache
     *
     * @return \VuFind\Record\Cache
     */
    protected function getRecordCache ()
    {
        $recordFactoryManager = $this->getRecordFactoryManager();
        $config = $this->getConfig();
        $dbTableManager = $this->getDbTableManager();
        
        $recordCache = new Cache($recordFactoryManager, $config, $dbTableManager);
        
        return $recordCache;
    }

    /**
     * getDriver
     *
     * @param string $id     id
     * @param string $source source
     *            
     * @return PHPUnit_Framework_MockObject_MockObject
     */
    protected function getDriver ($id = 'test', $source = 'Solr')
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
