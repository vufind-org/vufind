<?php
/**
 * Generic base class for expiration commands.
 *
 * PHP version 7
 *
 * Copyright (C) Villanova University 2020.
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
 * @package  Console
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
namespace VuFindConsole\Command\Util;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use VuFind\Db\Table\Gateway;

/**
 * Generic base class for expiration commands.
 *
 * @category VuFind
 * @package  Console
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
class AbstractExpireCommand extends Command
{
    /**
     * Help description for the command.
     *
     * @var string
     */
    protected $commandDescription = 'Expiration tool';

    /**
     * Label to use for rows in help messages.
     *
     * @var string
     */
    protected $rowLabel = 'rows';

    /**
     * Minimum legal age of rows to delete.
     *
     * @var int
     */
    protected $minAge = 2;

    /**
     * Default age of rows to delete. $minAge is used if $defaultAge is null.
     *
     * @var int|null
     */
    protected $defaultAge = null;

    /**
     * Table on which to expire rows
     *
     * @var Gateway
     */
    protected $table;

    /**
     * Constructor
     *
     * @param Gateway     $table Table on which to expire rows
     * @param string|null $name  The name of the command; passing null means it
     * must be set in configure()
     */
    public function __construct(Gateway $table, $name = null)
    {
        foreach (['getExpiredIdRange', 'deleteExpired'] as $method) {
            if (!method_exists($table, $method)) {
                $tableName = get_class($table);
                throw new \Exception("$tableName does not support $method()");
            }
        }
        $this->table = $table;
        parent::__construct($name);
    }

    /**
     * Configure the command.
     *
     * @return void
     */
    protected function configure()
    {
        $this
            ->setDescription($this->commandDescription)
            ->setHelp("Expires old {$this->rowLabel} in the database.")
            ->addOption(
                'batch',
                null,
                InputOption::VALUE_REQUIRED,
                'number of records to delete in a single batch',
                1000
            )->addOption(
                'sleep',
                null,
                InputOption::VALUE_REQUIRED,
                'milliseconds to sleep between batches',
                100
            )->addArgument(
                'age',
                InputArgument::OPTIONAL,
                "the age (in days) of {$this->rowLabel} to expire",
                $this->defaultAge ?? $this->minAge
            );
    }

    /**
     * Add a time stamp to a message
     *
     * @param string $msg Message
     *
     * @return string
     */
    protected function getTimestampedMessage($msg)
    {
        return '[' . date('Y-m-d H:i:s') . '] ' . $msg;
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
        // Collect arguments/options:
        $daysOld = floatval($input->getArgument('age'));
        $batchSize = $input->getOption('batch');
        $sleepTime = $input->getOption('sleep');

        // Abort if we have an invalid expiration age.
        if ($daysOld < $this->minAge) {
            $output->writeln(
                str_replace(
                    '%%age%%', number_format($this->minAge, 1, '.', ''),
                    'Expiration age must be at least %%age%% days.'
                )
            );
            return 1;
        }

        // Delete the expired rows--this cleans up any junk left in the database
        // e.g. from old searches or sessions that were not caught by the session
        // garbage collector.
        $idRange = $this->table->getExpiredIdRange($daysOld);
        if (false === $idRange) {
            $output->writeln(
                $this->getTimestampedMessage("No {$this->rowLabel} to delete.")
            );
            return 0;
        }

        // Delete records in batches
        for ($batch = $idRange[0]; $batch <= $idRange[1]; $batch += $batchSize) {
            $count = $this->table->deleteExpired(
                $daysOld, $batch, $batch + $batchSize - 1
            );
            $output->writeln(
                $this->getTimestampedMessage("{$count} {$this->rowLabel} deleted.")
            );
            // Be nice to others and wait between batches
            if ($batch + $batchSize <= $idRange[1]) {
                usleep($sleepTime * 1000);
            }
        }
        return 0;
    }
}
