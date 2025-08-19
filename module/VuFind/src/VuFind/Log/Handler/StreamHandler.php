<?php

/**
 * Stream handler
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
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301  USA
 *
 * @category VuFind
 * @package  Error_Logging
 * @author   Sambhav Pokharel <sambhav.pokharel@gmail.com>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */

namespace VuFind\Log\Handler;

use Monolog\Handler\StreamHandler as MonologStreamHandler;
use Monolog\LogRecord;

use Doctrine\DBAL\Connection;
use Laminas\Log\Exception;
use Laminas\Log\Formatter\Db as DbFormatter;

use function is_array;
use function is_scalar;
use function var_export;

/**
<<<<<<< HEAD:module/VuFind/src/VuFind/Log/Handler/StreamHandler.php
 * This class extends Monolog's StreamHandler to add verbosity control over log records
 *
 * @category VuFind
 * @package  Error_Logging
 * @author   Sambhav Pokharel <sambhav.pokharel@gmail.com>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */

class StreamHandler extends MonologStreamHandler
=======
 * This class is heavily based on \Laminas\Log\Writer\Db, but replaces Laminas\Db
 * functionality with Doctrine\DBAL.
 *
 * @category VuFind
 * @package  Error_Logging
 * @author   Chris Hallberg <challber@villanova.edu>
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */
class Db extends \Laminas\Log\Writer\AbstractWriter
>>>>>>> dev:module/VuFind/src/VuFind/Log/Writer/Db.php
{
    use VerbosityTrait;

    /**
<<<<<<< HEAD:module/VuFind/src/VuFind/Log/Handler/StreamHandler.php
     * Writes the record down to the log
=======
     * Constructor
     *
     * @param Connection $db        Db adapter instance
     * @param string     $tableName Table name
     * @param array      $columnMap Relates database columns names to log data field keys.
     * @param string     $separator Field separator for sub-elements
     */
    public function __construct(
        protected Connection $db,
        protected string $tableName,
        protected array $columnMap,
        protected string $separator = '_'
    ) {
        $this->setFormatter(new DbFormatter());
    }

    /**
     * Write a message to the log.
     *
     * @param array $event Event data
     *
     * @return void
     * @throws Exception\RuntimeException
     */
    protected function doDatabaseWrite(array $event)
    {
        $event = $this->formatter->format($event);

        $dataToInsert = $this->mapEventIntoColumn($event, $this->columnMap);
        $this->db->insert($this->tableName, $dataToInsert);
    }

    /**
     * Map event into column using the $columnMap array
     *
     * @param array  $event     Event to map
     * @param ?array $columnMap Column map
     *
     * @return array
     */
    protected function mapEventIntoColumn(array $event, ?array $columnMap = null)
    {
        if (empty($event)) {
            return [];
        }

        $data = [];
        foreach ($event as $name => $value) {
            if (is_array($value)) {
                foreach ($value as $key => $subvalue) {
                    if (isset($columnMap[$name][$key])) {
                        if (is_scalar($subvalue)) {
                            $data[$columnMap[$name][$key]] = $subvalue;
                            continue;
                        }

                        $data[$columnMap[$name][$key]] = var_export($subvalue, true);
                    }
                }
            } elseif (isset($columnMap[$name])) {
                $data[$columnMap[$name]] = $value;
            }
        }
        return $data;
    }

    /**
     * Write a message to the log.
>>>>>>> dev:module/VuFind/src/VuFind/Log/Writer/Db.php
     *
     * @param LogRecord $record Log record to process
     *
     * @return void
     */
    protected function write(LogRecord $record): void
    {
<<<<<<< HEAD:module/VuFind/src/VuFind/Log/Handler/StreamHandler.php
        $recordData = $record->toArray();

        $modifiedRecordData = $this->applyVerbosity($recordData);

        $modifiedRecord = new LogRecord(
            $record->datetime,
            $record->channel,
            $record->level,
            $modifiedRecordData['message'],
            $modifiedRecordData['context'],
            $modifiedRecordData['extra'],
            $record->formatted
        );

        parent::write($modifiedRecord);
=======
        // Apply verbosity, Call parent method:
        $this->doDatabaseWrite($this->applyVerbosity($event));
>>>>>>> dev:module/VuFind/src/VuFind/Log/Writer/Db.php
    }
}
