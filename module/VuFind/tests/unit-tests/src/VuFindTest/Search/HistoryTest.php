<?php

/**
 * History unit tests.
 *
 * PHP version 7
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

use VuFind\Search\History;

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
    public function testDefaultDisabledScheduleOptions()
    {
        $this->assertEquals([], $this->getHistory()->getScheduleOptions());
    }

    /**
     * Test that we get no schedule options when scheduled search is disabled
     * (explicitly).
     *
     * @return void
     */
    public function testExplicitlyDisabledScheduleOptions()
    {
        $config = new \Laminas\Config\Config(
            [
                'Account' => [
                    'schedule_searches' => false,
                ]
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
    public function testDefaultScheduleOptions()
    {
        $config = new \Laminas\Config\Config(
            [
                'Account' => [
                    'schedule_searches' => true,
                ]
            ]
        );
        $history = $this->getHistory(null, null, $config);
        $this->assertEquals(
            [0 => 'schedule_none', 1 => 'schedule_daily', 7 => 'schedule_weekly'],
            $history->getScheduleOptions()
        );
    }

    /**
     * Test that purging history proxies to the right place.
     *
     * @return void
     */
    public function testPurgeHistory()
    {
        $table = $this->getMockBuilder(\VuFind\Db\Table\Search::class)
            ->disableOriginalConstructor()->setMethods(['destroySession'])
            ->getMock();
        $table->expects($this->once())->method('destroySession')
            ->with($this->equalTo('foosession'), $this->equalTo(1234));
        $history = $this->getHistory($table);
        $history->purgeSearchHistory(1234);
    }

    /**
     * Get object for testing.
     *
     * @param \VuFind\Db\Table\Search              $searchTable    Search table
     * @param \VuFind\Search\Results\PluginManager $resultsManager Results manager
     * @param \Laminas\Config\Config                  $config         Configuration
     *
     * @return History
     */
    protected function getHistory($searchTable = null,
        $resultsManager = null, \Laminas\Config\Config $config = null
    ) {
        return new History(
            $searchTable ?: $this->getMockBuilder(\VuFind\Db\Table\Search::class)
                ->disableOriginalConstructor()->getMock(),
            'foosession',
            $resultsManager ?: $this
                ->getMockBuilder(\VuFind\Search\Results\PluginManager::class)
                ->disableOriginalConstructor()->getMock(),
            $config
        );
    }
}
