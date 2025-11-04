<?php

/**
 * Statistics reporter trait.
 *
 * PHP version 8
 *
 * Copyright (C) The National Library of Finland 2022.
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
 * @package  Statistics
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */

namespace Finna\Statistics;

use Finna\Statistics\EventHandler as StatisticsEventHandler;

/**
 * Statistics reporter trait.
 *
 * @category VuFind
 * @package  Statistics
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */
trait ReporterTrait
{
    /**
     * Statistics event handler
     *
     * @var StatisticsEventHandler
     */
    protected $statisticsEventHandler = null;

    /**
     * Set statistics event handler
     *
     * @param StatisticsEventHandler $handler Statistics event handler
     *
     * @return void
     */
    public function setStatisticsEventHandler(StatisticsEventHandler $handler): void
    {
        $this->statisticsEventHandler = $handler;
    }

    /**
     * Trigger session start event
     *
     * @param string $sessionId Session id
     *
     * @return void
     */
    protected function triggerStatsSessionStart(string $sessionId): void
    {
        if ($this->statisticsEventHandler) {
            $this->statisticsEventHandler->sessionStart(['id' => $sessionId]);
        }
    }

    /**
     * Trigger record view event
     *
     * @param ?\VuFind\RecordDriver\AbstractBase $driver Record driver
     *
     * @return void
     */
    protected function triggerStatsRecordView(
        ?\VuFind\RecordDriver\AbstractBase $driver
    ): void {
        if ($driver && $this->statisticsEventHandler) {
            $this->statisticsEventHandler->recordView($driver);
        }
    }
}
