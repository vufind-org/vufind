<?php

/**
 * Trait to add configurable verbosity settings to loggers
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2016.
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
 * @package  Log
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */

namespace VuFind\Log\Handler;

use function is_array;

/**
 * Trait to add configurable verbosity settings to loggers
 *
 * @category VuFind
 * @package  Log
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */
trait VerbosityTrait
{
    /**
     * Holds the verbosity level
     *
     * @var int
     */
    protected int $verbosity = 1;

    /**
     * Set verbosity
     *
     * @param int $verb verbosity setting
     *
     * @return void
     */
    public function setVerbosity(int $verb): void
    {
        $this->verbosity = $verb;
    }

    /**
     * Apply verbosity setting to the log record's message.
     *
     * This method is designed to be called within a Monolog handler's write method
     * to process the log record *before* it's formatted.
     *
     * @param array $record The log record data, where the detailed message array
     * is expected in $record['context']['vufind_log_details'].
     *
     * @return array The modified log record data.
     */
    protected function applyVerbosity(array $record): array
    {
        $vufindDetails = $record['context']['details'] ?? null;
        if (is_array($vufindDetails) && isset($vufindDetails[$this->verbosity])) {
            $record['message'] = $vufindDetails[$this->verbosity];
            unset($record['context']['details']);
        }
        return $record;
    }
}
