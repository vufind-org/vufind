<?php

/**
 * Console command: database builder
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

namespace VuFindConsole\Command\Generate;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use VuFind\Db\DbBuilder;

use function is_callable;

/**
 * Console command: database builder
 *
 * @category VuFind
 * @package  Console
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
#[AsCommand(
    name: 'generate/database',
    description: 'Database builder'
)]
class DatabaseCommand extends Command
{
    /**
     * Constructor
     *
     * @param DbBuilder   $builder Database builder
     * @param string|null $name    The name of the command; passing null means it
     * must be set in configure()
     */
    public function __construct(protected DbBuilder $builder, $name = null)
    {
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
            ->setHelp('Build a new database.')
            ->addArgument(
                'newName',
                InputArgument::REQUIRED,
                'Name of database to create'
            )->addArgument(
                'newUser',
                InputArgument::REQUIRED,
                'Username to create with access to new database'
            )->addArgument(
                'newPass',
                InputArgument::REQUIRED,
                'Password for newly-created user'
            )->addOption(
                'test-only',
                null,
                InputOption::VALUE_NONE,
                'activates test mode, which displays SQL without creating anything'
            )->addOption(
                'driver',
                null,
                InputOption::VALUE_OPTIONAL,
                'database driver to use (e.g. mysql or pgsql)',
                'mysql'
            )->addOption(
                'dbHost',
                null,
                InputOption::VALUE_OPTIONAL,
                'database host to connect to',
                'localhost'
            )->addOption(
                'vufindHost',
                null,
                InputOption::VALUE_OPTIONAL,
                'host running VuFind (used for scoping users)',
                'localhost'
            )->addOption(
                'rootUser',
                null,
                InputOption::VALUE_OPTIONAL,
                'username with root access (for creating the database)',
                'root'
            )->addOption(
                'rootPass',
                null,
                InputOption::VALUE_OPTIONAL,
                'password for root user',
                ''
            )->addOption(
                'skip',
                null,
                InputOption::VALUE_OPTIONAL,
                'comma-separated list of steps to skip (legal options: pre, main, post)',
                ''
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
        $testMode = $input->getOption('test-only') ? true : false;
        $driver = $input->getOption('driver');
        $dbHost = $input->getOption('dbHost');
        $vufindHost = $input->getOption('vufindHost');
        $skip = array_filter(array_map('trim', explode(',', $input->getOption('skip'))));
        $rootUser = $input->getOption('rootUser');
        $rootPass = $input->getOption('rootPass');
        $newName = $input->getArgument('newName');
        $newUser = $input->getArgument('newUser');
        $newPass = $input->getArgument('newPass');
        // Try to import the document if successful:
        try {
            $result = $this->builder->build(
                $driver,
                $dbHost,
                $vufindHost,
                $rootUser,
                $rootPass,
                $newName,
                $newUser,
                $newPass,
                $testMode,
                $skip
            );
            if ($testMode) {
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
        if (!$testMode) {
            $output->writeln('Successfully created database.');
        }
        return 0;
    }
}
