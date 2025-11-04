<?php

/**
 * Trait which returns pre-configured mocks
 *
 * PHP version 8
 *
 * Copyright (C) The National Library of Finland 2025.
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
 * @author   Juha Luoma <juha.luoma@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */

namespace FinnaTest\Traits;

use Finna\Cache\Manager;
use Finna\File\Loader as FileLoader;
use Finna\Record\Loader;
use Finna\RecordDriver\SolrAipa;
use Finna\RecordDriver\SolrEad;
use Finna\RecordDriver\SolrEad3;
use Finna\RecordDriver\SolrLido;
use Finna\RecordDriver\SolrMarc;
use Finna\RecordDriver\SolrQdc;
use FinnaSearch\Backend\Solr\Response\Json\RecordCollection;
use FinnaTest\Container\MockContainer;
use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Response;
use Laminas\Mvc\I18n\Translator;
use VuFind\Config\Config;
use VuFind\Http\GuzzleService;
use VuFind\RecordDriver\Missing;

use function in_array;

/**
 * Trait which returns pre-configured mocks
 *
 * @category VuFind
 * @package  Tests
 * @author   Juha Luoma <juha.luoma@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */
trait MockLoadersTrait
{
    /**
     * Get Finna Record Loader.
     *
     * @param array $records Array containing data for each record
     *                       [
     *                       'fixture' => Path of the fixture to load or omit for none,
     *                       'raw_data' => Raw data for the record i.e index fields
     *                       ];
     *
     * @return Loader
     */
    public function getFinnaRecordLoader(array $records = []): Loader
    {
        $searchService = $this->getMockBuilder(\VuFindSearch\Service::class)->onlyMethods(['invoke'])
            ->disableOriginalConstructor()->getMock();
        $searchService->expects($this->any())->method('invoke')->willReturnCallback(function ($command) use ($records) {
            $backendIdentifier = $command->getTargetIdentifier();
            $recordIdentifier = $command->getRecordIdentifier();
            $foundRecords = [];
            foreach ($records as $record) {
                if (
                    $record['raw_data']['id'] === $recordIdentifier &&
                    $record['raw_data']['source'] === $backendIdentifier
                ) {
                    $split = explode('/', $record['fixture'], 2);
                    $recordClass = match ($split[0]) {
                        'marc' => SolrMarc::class,
                        'lido' => SolrLido::class,
                        'ead3' => SolrEad3::class,
                        'ead' => SolrEad::class,
                        'qdc' => SolrQdc::class,
                        'aipa' => SolrAipa::class,
                        default => Missing::class,
                    };
                    $mockedRecord = $this->getMockBuilder($recordClass)->onlyMethods([])
                        ->disableOriginalConstructor()->getMock();
                    $fixture = $this->getFixture($record['fixture'], 'Finna');
                    $rawData = $record['raw_data'] ?? [];
                    $rawData['fullrecord'] = $fixture;
                    $mockedRecord->setRawData($rawData);
                    $foundRecords[] = $mockedRecord;
                }
            }
            $mockedCommand = $this->getMockBuilder(\VuFindSearch\Command\RetrieveCommand::class)
                ->onlyMethods(['getResult'])->disableOriginalConstructor()->getMock();
            $recordCollection = $this->getMockBuilder(RecordCollection::class)->onlyMethods(['getRecords'])
                ->disableOriginalConstructor()->getMock();
            $recordCollection->expects($this->any())->method('getRecords')->willReturn($foundRecords);
            $mockedCommand->expects($this->any())->method('getResult')->willReturn($recordCollection);
            return $mockedCommand;
        });

        // Use the real class for improving test coverage passively
        return $this->getMockBuilder(Loader::class)->onlyMethods([])->setConstructorArgs([
            $searchService,
            $this->getRecordDriverPluginManager(),
        ])->getMock();
    }

    /**
     * Get record driver plugin manager
     *
     * @param array $config Main config
     *
     * @return \Finna\RecordDriver\PluginManager
     */
    public function getRecordDriverPluginManager(array $config = []): \Finna\RecordDriver\PluginManager
    {
        $configManager = $this->getMockBuilder(\VuFind\Config\ConfigManager::class)->onlyMethods(['getConfigObject'])
            ->disableOriginalConstructor()->getMock();
        $configMap = [
            ['config', null, new Config($config)],
        ];
        $configManager->expects($this->any())->method('getConfigObject')->willReturnMap($configMap);

        $dbServicePluginManager = $this->getMockBuilder(\VuFind\Db\Service\PluginManager::class)->onlyMethods([])
            ->disableOriginalConstructor()->getMock();
        $translator = $this->getMockBuilder(Translator::class)->onlyMethods([])
            ->disableOriginalConstructor()->getMock();

        // Create a mock container for factory
        $mockContainer = new MockContainer($this);
        $mockContainer->add('Missing', new Missing());
        $mockContainer->add(\VuFind\Config\ConfigManagerInterface::class, $configManager);
        $mockContainer->add(\VuFind\Db\Service\PluginManager::class, $dbServicePluginManager);
        $mockContainer->add(Translator::class, $translator);

        return $this->getMockBuilder(\Finna\RecordDriver\PluginManager::class)->onlyMethods([])
            ->setConstructorArgs([$mockContainer, []])->getMock();
    }

    /**
     * Get a Finna file loader object.
     *
     * @param array $urls Urls which results in response 200
     *
     * @return FileLoader
     */
    public function getFinnaFileLoader(array $urls = []): FileLoader
    {
        $mockedGuzzle = $this->getMockBuilder(GuzzleService::class)->onlyMethods(['createClient'])
            ->disableOriginalConstructor()->getMock();
        $mockedGuzzleClient = $this->getMockBuilder(Client::class)->onlyMethods(['request'])
            ->disableOriginalConstructor()->getMock();
        $mockedGuzzleClient->expects($this->any())->method('request')->willReturnCallback(
            function ($method, $uri, $options = []) use ($urls) {
                if (in_array($uri, $urls)) {
                    return new Response();
                }
                return new Response(404);
            }
        );
        $mockedGuzzle->expects($this->any())->method('createClient')->willReturn($mockedGuzzleClient);
        $mockedCacheManager = $this->getMockBuilder(Manager::class)->onlyMethods([])
            ->disableOriginalConstructor()->getMock();

        $config = new \VuFind\Config\Config([]);
        return $this->getMockBuilder(FileLoader::class)->onlyMethods([])
            ->setConstructorArgs([$mockedCacheManager, $config, $mockedGuzzle])->getMock();
    }
}
