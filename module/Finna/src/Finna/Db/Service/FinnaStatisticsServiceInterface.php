<?php

/**
 * Database service interface for Finna statistics.
 *
 * PHP version 8
 *
 * Copyright (C) The National Library of Finland 2024.
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
 * @package  Database
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:database_gateways Wiki
 */

namespace Finna\Db\Service;

use Finna\Db\Entity\FinnaPageViewStatsEntityInterface;
use Finna\Db\Entity\FinnaRecordStatsLogEntityInterface;
use Finna\Db\Entity\FinnaRecordViewRecordEntityInterface;
use Finna\Db\Entity\FinnaRecordViewRecordFormatEntityInterface;
use Finna\Db\Entity\FinnaRecordViewRecordRightsEntityInterface;
use Finna\Db\Entity\FinnaSessionStatsEntityInterface;
use VuFind\Db\Service\DbServiceInterface;

/**
 * Database service interface for Finna statistics.
 *
 * @category VuFind
 * @package  Database
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:database_gateways Wiki
 */
interface FinnaStatisticsServiceInterface extends DbServiceInterface
{
    /**
     * Create a new session stats entity
     *
     * @return FinnaSessionStatsEntityInterface
     */
    public function createSessionEntity(): FinnaSessionStatsEntityInterface;

    /**
     * Create a new page view entity
     *
     * @return FinnaPageViewStatsEntityInterface
     */
    public function createPageViewEntity(): FinnaPageViewStatsEntityInterface;

    /**
     * Create a new record stats log entity
     *
     * @return FinnaRecordStatsLogEntityInterface
     */
    public function createRecordStatsLogEntity(): FinnaRecordStatsLogEntityInterface;

    /**
     * Get a batch of log entries to process from finna_record_stats_log table
     *
     * @param int $batchSize Number of records to retrieve
     *
     * @return FinnaRecordStatsLogEntityInterface[]
     */
    public function getRecordStatsLogEntriesToProcess(int $batchSize): array;

    /**
     * Delete a record stats log entry
     *
     * @param FinnaRecordStatsLogEntityInterface $entry Log entry
     *
     * @return void
     */
    public function deleteRecordStatsLogEntry(FinnaRecordStatsLogEntityInterface $entry): void;

    /**
     * Get a record view format entity by id
     *
     * @param int $id Id
     *
     * @return FinnaRecordViewRecordFormatEntityInterface
     */
    public function getRecordViewRecordFormatById(int $id): FinnaRecordViewRecordFormatEntityInterface;

    /**
     * Get a record view format entity by id
     *
     * @param int $id Id
     *
     * @return FinnaRecordViewRecordRightsEntityInterface
     */
    public function getRecordViewRecordUsageRightsById(int $id): FinnaRecordViewRecordRightsEntityInterface;

    /**
     * Get a record view record by log entry
     *
     * @param FinnaRecordStatsLogEntityInterface $logEntry Log entry
     *
     * @return FinnaRecordViewRecordEntityInterface
     */
    public function getRecordViewRecordByLogEntry(
        FinnaRecordStatsLogEntityInterface $logEntry
    ): FinnaRecordViewRecordEntityInterface;

    /**
     * Add a new session entry
     *
     * @param FinnaSessionStatsEntityInterface $session Session
     *
     * @return void
     */
    public function addSession(FinnaSessionStatsEntityInterface $session): void;

    /**
     * Add a page view
     *
     * @param FinnaPageViewStatsEntityInterface $pageView Page view
     *
     * @return void
     */
    public function addPageView(FinnaPageViewStatsEntityInterface $pageView): void;

    /**
     * Add a record view from a log entry
     *
     * @param FinnaRecordStatsLogEntityInterface $logEntry Log entry
     *
     * @return void
     */
    public function addRecordView(FinnaRecordStatsLogEntityInterface $logEntry): void;

    /**
     * Add a record stats log entry (a detailed entry for processing later via addDetailedRecordView)
     *
     * @param FinnaRecordStatsLogEntityInterface $logEntry Log entry
     *
     * @return void
     */
    public function addRecordStatsLogEntry(FinnaRecordStatsLogEntityInterface $logEntry): void;

    /**
     * Add a detailed record view from a log entry
     *
     * Note: This is a relatively slow and complex function and should only be
     * executed from a batch processing utility
     *
     * @param FinnaRecordStatsLogEntityInterface $logEntry Log entry
     *
     * @return void
     */
    public function addDetailedRecordView(FinnaRecordStatsLogEntityInterface $logEntry): void;
}
