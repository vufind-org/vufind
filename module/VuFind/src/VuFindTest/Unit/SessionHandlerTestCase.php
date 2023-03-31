<?php

/**
 * Abstract base class for session handler test cases.
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

namespace VuFindTest\Unit;

use VuFind\Session\AbstractBase as SessionHandler;

/**
 * Abstract base class for session handler test cases.
 *
 * @category VuFind
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */
abstract class SessionHandlerTestCase extends \PHPUnit\Framework\TestCase
{
    /**
     * Mock database tables.
     *
     * @var \VuFind\Db\Table\PluginManager
     */
    protected $tables = false;

    /**
     * Get mock database plugin manager
     *
     * @return \VuFind\Db\Table\PluginManager
     */
    protected function getTables()
    {
        if (!$this->tables) {
            $this->tables
                = new \VuFindTest\Container\MockDbTablePluginManager($this);
        }
        return $this->tables;
    }

    /**
     * Set up mock databases for a session handler.
     *
     * @param SessionHandler $handler Session handler
     *
     * @return void
     */
    protected function injectMockDatabaseTables(SessionHandler $handler)
    {
        $handler->setDbTableManager($this->getTables());
    }

    /**
     * Set up expectations for the standard abstract handler's destroy behavior.
     *
     * @param string $sessId Session ID that we expect will be destroyed.
     *
     * @return void
     */
    protected function setUpDestroyExpectations($sessId)
    {
        $search = $this->getMockBuilder(\VuFind\Db\Table\Search::class)
            ->disableOriginalConstructor()
            ->getMock();
        $search->expects($this->once())
            ->method('destroySession')
            ->with($this->equalTo($sessId));
        $external = $this->getMockBuilder(\VuFind\Db\Table\ExternalSession::class)
            ->disableOriginalConstructor()
            ->getMock();
        $external->expects($this->once())
            ->method('destroySession')
            ->with($this->equalTo($sessId));
        $tables = $this->getTables();
        $tables->set('Search', $search);
        $tables->set('ExternalSession', $external);
    }
}
