<?php

/**
 * Console service for processing record stats log table.
 *
 * PHP version 8
 *
 * Copyright (C) The National Library of Finland 2022-2023.
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
 * @package  Service
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */

namespace FinnaConsole\Command\Util;

use Finna\Db\Service\FinnaStatisticsServiceInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Console service for processing record stats log table.
 *
 * @category VuFind
 * @package  Service
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
#[AsCommand(
    name: 'util/process_record_stats_log'
)]
class ProcessRecordStatsLog extends AbstractUtilCommand
{
    /**
     * Record batch size to process at a time
     *
     * @var int
     */
    protected $batchSize = 1000;

    /**
     * Constructor
     *
     * @param protected FinnaStatisticsServiceInterface $statisticsService Statics database service
     */
    public function __construct(protected FinnaStatisticsServiceInterface $statisticsService)
    {
        parent::__construct();
    }

    /**
     * Configure the command.
     *
     * @return void
     */
    protected function configure()
    {
        $this->setDescription(
            'Process record stats log into records and hits tables'
        )->addOption(
            'batch-size',
            null,
            InputOption::VALUE_REQUIRED,
            'Set the processing batch size',
            $this->batchSize
        );
    }

    /**
     * Run the command.
     *
     * @param InputInterface  $input  Input object
     * @param OutputInterface $output Output object
     *
     * @return int 0 for success
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->output = $output;

        $this->batchSize = $input->getOption('batch-size');

        $this->msg(
            "Record stats log processing started (batch size $this->batchSize)"
        );

        $count = 0;
        do {
            $rows = 0;
            foreach ($this->statisticsService->getRecordStatsLogEntriesToProcess($this->batchSize) as $logEntry) {
                $this->statisticsService->addDetailedRecordView($logEntry);
                $this->statisticsService->deleteRecordStatsLogEntry($logEntry);

                ++$rows;
                ++$count;
                $msg = "$count log entries processed";
                if ($count % 1000 == 0) {
                    $this->msg($msg);
                } else {
                    $this->msg($msg, OutputInterface::VERBOSITY_VERY_VERBOSE);
                }
            }
        } while ($rows);

        $this->msg("Completed with $count log entries processed");
        return 0;
    }
}
