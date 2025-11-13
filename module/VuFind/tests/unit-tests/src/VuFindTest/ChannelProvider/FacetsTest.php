<?php

/**
 * Facets Channel Provider Test Class
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2025.
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

namespace VuFindTest\ChannelProvider;

use Laminas\Mvc\Controller\Plugin\Url;
use VuFind\ChannelProvider\Facets;
use VuFind\Search\Results\PluginManager;
use VuFindTest\Feature\SearchObjectsTrait;
use VuFindTest\RecordDriver\TestHarness;

/**
 * Facets Channel Provider Test Class
 *
 * @category VuFind
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */
class FacetsTest extends \PHPUnit\Framework\TestCase
{
    use SearchObjectsTrait;

    /**
     * Test that getFromRecord deduplicates redundant facet values.
     *
     * @return void
     */
    public function testGetFromRecordDeduplicatesValues(): void
    {
        $resultsManager = $this->createMock(PluginManager::class);
        $resultsManager->method('get')->willReturn($this->getMockResults());
        $urlHelper = $this->createMock(Url::class);
        $facets = new Facets($resultsManager, $urlHelper, ['maxFieldsToSuggest' => 0]);
        $this->assertEquals(
            [
                [
                    'title' => 'Author: foo',
                    'providerId' => '',
                    'groupId' => 'author_facet',
                    'token' => 'Author: foo|author_facet:foo',
                    'links' => [],
                ],
                [
                    'title' => 'Author: bar',
                    'providerId' => '',
                    'groupId' => 'author_facet',
                    'token' => 'Author: bar|author_facet:bar',
                    'links' => [],
                ],
            ],
            $facets->getFromRecord($this->getDriver(['author_facet' => ['foo', 'bar', 'bar']]))
        );
    }

    /**
     * Get a fake record driver
     *
     * @param array $data Custom record data
     *
     * @return TestHarness
     */
    protected function getDriver(array $data = [])
    {
        $driver = new TestHarness();
        $finalData = $data + [
            'Title' => 'foo_Title',
            'SourceIdentifier' => 'foo_Identifier',
            'Thumbnail' => 'foo_Thumbnail',
            'UniqueID' => 'foo_Id',
        ];
        $driver->setRawData($finalData);
        return $driver;
    }
}
