<?php

/**
 * AIPA test class.
 *
 * PHP version 8
 *
 * Copyright (C) The National Library of Finland 2023-2024.
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
 * @author   Aleksi Peebles <aleksi.peebles@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */

namespace Finna\RecordDriver;

use Finna\Record\Loader;
use PHPUnit\Framework\TestCase;
use VuFindTest\Feature\FixtureTrait;

/**
 * AIPA test class.
 *
 * @category VuFind
 * @package  Tests
 * @author   Aleksi Peebles <aleksi.peebles@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */
class SolrAipaTest extends TestCase
{
    use FixtureTrait;

    protected $pluginManager;

    protected $recordLoader;

    /**
     * Test filtered XML for public APIs.
     *
     * @return void
     */
    public function testFilteredXML()
    {
        $driver = $this->getSolrAipaDriver();
        $this->assertEquals(
            $this->getFixture('aipa/aipa_test_filtered.xml', 'Finna'),
            $driver->getFilteredXML()
        );
    }

    /**
     * Get an AIPA record driver with fake data.
     *
     * @return SolrAipa
     */
    protected function getSolrAipaDriver(): SolrAipa
    {
        $fixture = $this->getFixture('aipa/aipa_test.xml', 'Finna');
        $record = new SolrAipa();
        $record->attachRecordDriverManager($this->getPluginManager());
        $record->setRawData([
            'id' => 'aipa.node-2785',
            'fullrecord' => $fixture,
        ]);
        return $record;
    }

    /**
     * Get an AIPA LRMI record driver.
     *
     * @return AipaLrmi
     */
    protected function getAipaLrmiDriver(): AipaLrmi
    {
        $record = new AipaLrmi();
        $record->attachRecordDriverManager($this->getPluginManager());
        $record->attachRecordLoader($this->getRecordLoader());
        return $record;
    }

    /**
     * Get a mock record driver plugin manager.
     *
     * @return PluginManager
     */
    protected function getPluginManager(): PluginManager
    {
        if (!isset($this->pluginManager)) {
            $pluginManager = $this->createMock(PluginManager::class);
            $pluginManager
                ->method('get')
                ->willReturnCallback(function ($name) {
                    switch ($name) {
                        case 'AipaLrmi':
                            return $this->getAipaLrmiDriver();
                        case 'CuratedRecord':
                            return new CuratedRecord();
                    }
                });
            $this->pluginManager = $pluginManager;
        }
        return $this->pluginManager;
    }

    /**
     * Get a mock record loader.
     *
     * @return Loader
     */
    protected function getRecordLoader(): Loader
    {
        if (!isset($this->recordLoader)) {
            $recordLoader = $this->createMock(Loader::class);
            $recordLoader
                ->method('load')
                ->willReturnCallback(function ($id) {
                    $record = new SolrDefault();
                    $record->setRawData(['id' => $id]);
                    return $record;
                });
            $this->recordLoader = $recordLoader;
        }
        return $this->recordLoader;
    }
}
