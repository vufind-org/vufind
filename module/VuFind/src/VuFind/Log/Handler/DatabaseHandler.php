<?php

/**
 * Custom Database log handler
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
 * @package  Error_Logging
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Sambhav Pokharel <sambhav.pokharel@gmail.com>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */

namespace VuFind\Log\Handler;

use Doctrine\DBAL\Connection;
use Monolog\Handler\AbstractProcessingHandler;
use Monolog\Level;
use Monolog\LogRecord;

/**
 * Custom Database Handler for VuFind with verbosity support and flexible column mapping
 *
 * @category VuFind
 * @package  Error_Logging
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Sambhav Pokharel <sambhav.pokharel@gmail.com>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */

class DatabaseHandler extends AbstractProcessingHandler
{
    use VerbosityTrait;

    /**
     * Column mapping for log data
     */
    protected array $columnMapping = [
        'priority' => 'priority',
        'message' => 'message',
        'logtime' => 'logtime',
        'ident' => 'ident',
    ];

    /**
     * Constructor
     *
     * @param Connection $connection Database connection instance
     * @param string     $tableName  Name of the database table to store logs
     */
    public function __construct(protected Connection $connection, protected string $tableName)
    {
    }

    /**
     * Set column mapping
     *
     * @param array $mapping Associative array of field-column mappings
     *
     * @return void
     */
    public function setColumnMapping(array $mapping): void
    {
        $this->columnMapping = array_merge($this->columnMapping, $mapping);
    }

    /**
     * Write log record to database
     *
     * @param LogRecord $record Log record to write to database
     *
     * @return void
     */
    protected function write(LogRecord $record): void
    {
        $this->connection->insert($this->tableName, $this->formatRecordData($record));
    }

    /**
     * Format log record data for database insertion
     *
     * @param LogRecord $record Log record to format
     *
     * @return array Formatted data ready for database insertion
     */
    protected function formatRecordData(LogRecord $record): array
    {
        $recordData = $record->toArray();
        $modifiedRecordData = $this->applyVerbosity($recordData);
        $data = [];
        if ($column = $this->columnMapping['priority'] ?? null) {
            $data[$column] = $this->levelToPriority($record->level);
        }
        if ($column = $this->columnMapping['message'] ?? null) {
            $data[$column] = $modifiedRecordData['message'];
        }
        if ($column = $this->columnMapping['logtime'] ?? null) {
            $data[$column] = $record->datetime->format(VUFIND_DATABASE_DATETIME_FORMAT);
        }
        if ($column = $this->columnMapping['ident'] ?? null) {
            $data[$column] = $record->channel;
        }
        return $data;
    }

    /**
     * Convert Monolog log level to syslog priority
     *
     * @param Level $level Monolog log level
     *
     * @return int priority number (0-7)
     */
    protected function levelToPriority(Level $level): int
    {
        return match ($level) {
            Level::Debug => 7,
            Level::Info => 6,
            Level::Notice => 5,
            Level::Warning => 4,
            Level::Error => 3,
            Level::Critical => 2,
            Level::Alert => 1,
            Level::Emergency => 0,
            default => 7
        };
    }
}
