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
 * @author   Chris Hallberg <challber@villanova.edu>
 * @author   Sambhav Pokharel <sambhav.pokharel@gmail.com>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */

namespace VuFind\Log\Handler;

use Monolog\Formatter\LineFormatter;
use Monolog\Handler\StreamHandler as MonologStreamHandler;
use Monolog\LogRecord;

/**
 * This class extends Monolog's StreamHandler to add verbosity control over log records
 *
 * @category VuFind
 * @package  Error_Logging
 * @author   Chris Hallberg <challber@villanova.edu>
 * @author   Sambhav Pokharel <sambhav.pokharel@gmail.com>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */
class StreamHandler extends MonologStreamHandler
{
    use VerbosityTrait;

    protected LineFormatter $standardFileFormatter;

    /**
     * Writes the record down to the log
     *
     * @param LogRecord $record Log record to process
     *
     * @return void
     */
    protected function write(LogRecord $record): void
    {
        $recordData = $record->toArray();

        $modifiedRecordData = $this->applyVerbosity($recordData);
        $modifiedRecord = new LogRecord(
            $record->datetime,
            $record->channel,
            $record->level,
            $modifiedRecordData['message'],
            $modifiedRecordData['context'],
            $modifiedRecordData['extra']
        );
        $modifiedRecord->formatted = $this->getStandardFileFormatter()->format($modifiedRecord);
        parent::write($modifiedRecord);
    }

    /**
     * Get a standard LineFormatter for file logging.
     *
     * @return LineFormatter
     */
    protected function getStandardFileFormatter(): LineFormatter
    {
        if (!$this->standardFileFormatter) {
            $this->standardFileFormatter = new LineFormatter(
                "[%datetime%] %channel%.%level_name%: %message%\n %extra%\n",
                'c',
                true,
                true
            );
        }
        return $this->standardFileFormatter;
    }
}
