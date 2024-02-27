<?php

/**
 * Index Plugin Test Class
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

namespace VuFindTest\Sitemap\Plugin;

use VuFind\Sitemap\Plugin\Index;
use VuFind\Sitemap\Plugin\Index\AbstractIdFetcher;

/**
 * Index Plugin Test Class
 *
 * @category VuFind
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */
class IndexTest extends \PHPUnit\Framework\TestCase
{
    use \VuFindTest\Feature\WithConsecutiveTrait;

    /**
     * Test that nothing happens if configuration is empty.
     *
     * @return void
     */
    public function testEmptyConfigs(): void
    {
        $plugin = new Index([], $this->getMockIdFetcher(), 100, []);
        $this->assertEmpty(iterator_to_array($plugin->getUrls()));
    }

    /**
     * Test retrieving data from the index.
     *
     * @return void
     */
    public function testRetrieval(): void
    {
        $backendId = 'bar';
        $countPerPage = 2;
        $fq = ['format:Book'];
        $fetcher = $this->getMockIdFetcher();
        $fetcher->expects($this->once())->method('getInitialOffset')
            ->will($this->returnValue('*'));
        $fetcher->expects($this->once())->method('setupBackend')
            ->with($this->equalTo($backendId));
        $this->expectConsecutiveCalls(
            $fetcher,
            'getIdsFromBackend',
            [
                [$backendId, '*', $countPerPage, $fq],
                [$backendId, 'offset', $countPerPage, $fq],
            ],
            [
                ['ids' => [1, 2], 'nextOffset' => 'offset'],
                ['ids' => [3]],
            ]
        );
        $config = [
            ['url' => 'http://foo/', 'id' => $backendId],
        ];
        $plugin = new Index($config, $fetcher, $countPerPage, $fq);
        $this->assertEquals(
            ['http://foo/1', 'http://foo/2', 'http://foo/3'],
            iterator_to_array($plugin->getUrls())
        );
    }

    /**
     * Get a mock ID fetcher
     *
     * @return AbstractIdFetcher
     */
    protected function getMockIdFetcher(): AbstractIdFetcher
    {
        return $this->getMockBuilder(AbstractIdFetcher::class)
            ->disableOriginalConstructor()->getMock();
    }
}
