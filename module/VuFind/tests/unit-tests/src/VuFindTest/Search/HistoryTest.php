<?php

/**
 * History unit tests.
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2019.
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

namespace VuFindTest\Search;

use VuFind\Db\Service\SearchServiceInterface;
use VuFind\Search\History;
use VuFind\Search\Results\PluginManager as ResultsManager;

/**
 * History unit tests.
 *
 * @category VuFind
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */
class HistoryTest extends \PHPUnit\Framework\TestCase
{
    /**
     * Test that we get no schedule options when scheduled search is disabled
     * (by default).
     *
     * @return void
     */
    public function testDefaultDisabledScheduleOptions(): void
    {
        $this->assertEquals([], $this->getHistory()->getScheduleOptions());
    }

    /**
     * Test that we get no schedule options when scheduled search is disabled
     * (explicitly).
     *
     * @return void
     */
    public function testExplicitlyDisabledScheduleOptions(): void
    {
        $config = new \Laminas\Config\Config(
            [
                'Account' => [
                    'schedule_searches' => false,
                ],
            ]
        );
        $history = $this->getHistory(null, null, $config);
        $this->assertEquals([], $history->getScheduleOptions());
    }

    /**
     * Test that we get reasonable default schedule options when scheduled search
     * is enabled.
     *
     * @return void
     */
    public function testDefaultScheduleOptions(): void
    {
        $config = new \Laminas\Config\Config(
            [
                'Account' => [
                    'schedule_searches' => true,
                ],
            ]
        );
        $history = $this->getHistory(null, null, $config);
        $this->assertEquals(
            [0 => 'schedule_none', 1 => 'schedule_daily', 7 => 'schedule_weekly'],
            $history->getScheduleOptions()
        );
    }

    /**
     * Test a single non-default schedule option.
     *
     * @return void
     */
    public function testSingleNonDefaultScheduleOption(): void
    {
        $config = new \Laminas\Config\Config(
            [
                'Account' => [
                    'schedule_searches' => true,
                    'scheduled_search_frequencies' => 'Always',
                ],
            ]
        );
        $history = $this->getHistory(null, null, $config);
        $this->assertEquals([0 => 'Always'], $history->getScheduleOptions());
    }

    /**
     * Test multiple non-default schedule options.
     *
     * @return void
     */
    public function testMultipleNonDefaultScheduleOptions(): void
    {
        $config = new \Laminas\Config\Config(
            [
                'Account' => [
                    'schedule_searches' => true,
                    'scheduled_search_frequencies' => [
                        1 => 'One', 2 => 'Two',
                    ],
                ],
            ]
        );
        $history = $this->getHistory(null, null, $config);
        $this->assertEquals(
            [1 => 'One', 2 => 'Two'],
            $history->getScheduleOptions()
        );
    }

    /**
     * Test that purging history proxies to the right place.
     *
     * @return void
     */
    public function testPurgeHistory(): void
    {
        $service = $this->createMock(SearchServiceInterface::class);
        $service->expects($this->once())->method('destroySession')->with('foosession', 1234);
        $history = $this->getHistory($service);
        $history->purgeSearchHistory(1234);
    }

    /**
     * Get object for testing.
     *
     * @param SearchServiceInterface $searchService  Search service
     * @param ResultsManager         $resultsManager Results manager
     * @param \Laminas\Config\Config $config         Configuration
     *
     * @return History
     */
    protected function getHistory(
        SearchServiceInterface $searchService = null,
        ResultsManager $resultsManager = null,
        \Laminas\Config\Config $config = null
    ): History {
        return new History(
            $searchService ?? $this->createMock(SearchServiceInterface::class),
            'foosession',
            $resultsManager ?? $this->createMock(\VuFind\Search\Results\PluginManager::class),
            $config
        );
    }
}
