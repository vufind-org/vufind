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
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301  USA
 *
 * @category VuFind
 * @package  Console
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */

namespace VuFindConsole\Command\Upgrade;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
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
     * @param MigrationManager  $migrationManager  Database migration manager
     * @param ConnectionFactory $connectionFactory Database connection factory
     * @param ?string           $name              The name of the command; passing null means it
     * must be set in configure()
     */
    public function __construct(
        protected MigrationManager $migrationManager,
        protected ConnectionFactory $connectionFactory,
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
        $rootUser = $input->getOption('rootUser');
        $rootPass = $input->getOption('rootPass');
        $fromVersion = $input->getOption('fromVersion');

        try {
            $connection = $sqlOnly ? null : $this->connectionFactory->getConnection($rootUser, $rootPass);
            $migrations = $this->migrationManager
                ->getMigrations($fromVersion ?? $this->migrationManager->determineOldVersion());
            $result = $this->migrationManager->applyMigrations($migrations, $connection);
            if ($sqlOnly) {
                $output->writeln($result);
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
        if (!$sqlOnly) {
            $output->writeln(empty($migrations) ? 'Nothing to do.' : 'Successfully upgraded database.');
        }
        return 0;
    }
}
