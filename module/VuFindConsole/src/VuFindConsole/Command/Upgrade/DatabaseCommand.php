<?php

/**
 * Console command: database upgrader
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
 * @package  Console
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */

namespace VuFindConsole\Command\Upgrade;

use Closure;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\ConsoleOutputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ChoiceQuestion;
use VuFind\Cache\Manager as CacheManager;
use VuFind\Db\Connection;
use VuFind\Db\ConnectionFactory;
use VuFind\Db\Migration\MigrationManager;

use function is_callable;

/**
 * Console command: database upgrader
 *
 * @category VuFind
 * @package  Console
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
#[AsCommand(
    name: 'upgrade/database',
    description: 'Database upgrader'
)]
class DatabaseCommand extends Command
{
    /**
     * Constructor
     *
     * @param Closure           $migrationManagerFactory Database migration manager factory
     * @param ConnectionFactory $connectionFactory       Database connection factory
     * @param CacheManager      $cacheManager            Cache Manager
     * @param ?string           $name                    The name of the command; passing null means it
     * must be set in configure()
     */
    public function __construct(
        protected Closure $migrationManagerFactory,
        protected ConnectionFactory $connectionFactory,
        protected CacheManager $cacheManager,
        $name = null
    ) {
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
            ->setHelp('Upgrade the database.')
            ->addOption(
                'sql-only',
                null,
                InputOption::VALUE_NONE,
                'output SQL without any actual database interactions'
            )->addOption(
                'interactive',
                null,
                InputOption::VALUE_NONE,
                'run in interactive mode'
            )->addOption(
                'rootUser',
                null,
                InputOption::VALUE_OPTIONAL,
                'username with root access',
                null
            )->addOption(
                'rootPass',
                null,
                InputOption::VALUE_OPTIONAL,
                'password for root user',
                null
            )->addOption(
                'fromVersion',
                null,
                InputOption::VALUE_OPTIONAL,
                'version you are upgrading from (default = autodetect)',
                null
            );
    }

    /**
     * Support method for "interactive mode."
     *
     * @param MigrationManager $migrationManager Migration manager
     * @param string[]         $migrations       Migrations to apply
     * @param Connection       $connection       Active database connection
     * @param InputInterface   $input            Input object
     * @param OutputInterface  $output           Output object
     *
     * @return void
     */
    protected function applyMigrationsInteractively(
        MigrationManager $migrationManager,
        array $migrations,
        Connection $connection,
        InputInterface $input,
        OutputInterface $output
    ): void {
        foreach ($migrations as $migration) {
            $output->writeln('Working on migration: ' . $migrationManager->getShortMigrationName($migration));
            $question = new ChoiceQuestion(
                'Choose an option:',
                [
                    'View',
                    'Apply',
                    'Skip',
                    'Mark as already applied (use after manually applying)',
                ]
            );
            while (true) {
                $choice = $this->getHelper('question')->ask($input, $output, $question);
                switch (substr($choice, 0, 4)) {
                    case 'View':
                        $output->writeln(file_get_contents($migration));
                        break;
                    case 'Appl':
                        $migrationManager->applyMigrations([$migration], $connection);
                        break 2;
                    case 'Skip':
                        break 2;
                    case 'Mark':
                        $migrationManager->markMigrationApplied($migration, $connection);
                        break 2;
                }
            }
        }
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
        $sqlOnly = $input->getOption('sql-only') ? true : false;
        $interactive = $input->getOption('interactive') ? true : false;
        if ($sqlOnly && $interactive) {
            $output->writeln('--sql-only and --interactive options are incompatible; choose only one');
            return 1;
        }
        $rootUser = $input->getOption('rootUser');
        $rootPass = $input->getOption('rootPass');
        $fromVersion = $input->getOption('fromVersion');

        try {
            $connection = $sqlOnly ? null : $this->connectionFactory->getConnection($rootUser, $rootPass);
            $migrationManager = ($this->migrationManagerFactory)();
            $migrations = $migrationManager->getMigrations($fromVersion ?? $migrationManager->determineOldVersion());
            if ($interactive) {
                $this->applyMigrationsInteractively($migrationManager, $migrations, $connection, $input, $output);
            } else {
                $result = $migrationManager->applyMigrations($migrations, $connection);
                if ($sqlOnly) {
                    $output->writeln($result);
                }
            }
        } catch (\Exception $e) {
            $output->writeln('Fatal error: ' . $e->getMessage());
            if (is_callable([$e, 'getPrevious']) && $e = $e->getPrevious()) {
                while ($e) {
                    $output->writeln('Previous exception: ' . $e->getMessage());
                    $e = $e->getPrevious();
                }
            }
            return 1;
        }
        // Display a final message if we're in non-interactive/non-SQL mode, or had nothing to do in interactive mode.
        if (!$sqlOnly && !($interactive && !empty($migrations))) {
            $output->writeln(empty($migrations) ? 'Nothing to do.' : 'Successfully upgraded database.');
        }
        if (!empty($migrations)) {
            $msg = '<info>Please clear the object cache (' . $this->cacheManager->getCacheDir(false) . 'objects) '
                . ($sqlOnly ? 'after applying the migrations' : 'now')
                . ' to ensure that the metadata is up to date.</info>';
            $stdErr = $output instanceof ConsoleOutputInterface ? $output->getErrorOutput() : $output;
            $stdErr->writeln('');
            $stdErr->writeln($msg);
            $stdErr->writeln('');
        }
        return 0;
    }
}
