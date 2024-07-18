<?php

/**
 * Console command: purge a record from cache
 *
 * PHP version 8
 *
 * Copyright (C) The National Library of Finland 2023.
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
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */

namespace VuFindConsole\Command\Util;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use VuFind\Db\Service\RecordServiceInterface;
use VuFind\Db\Service\ResourceServiceInterface;

/**
 * Console command: purge a record from cache
 *
 * @category VuFind
 * @package  Console
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
#[AsCommand(
    name: 'util/purge_cached_record',
    description: 'Purge a cached record and optionally a resource'
)]
class PurgeCachedRecordCommand extends Command
{
    /**
     * Constructor
     *
     * @param RecordServiceInterface   $recordService   Record table object
     * @param ResourceServiceInterface $resourceService Resource table object
     * @param string|null              $name            The name of the command; passing null means it
     * must be set in configure()
     */
    public function __construct(
        protected RecordServiceInterface $recordService,
        protected ResourceServiceInterface $resourceService,
        string $name = null
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
            ->setHelp('Removes a record and optionally a resource from the database.')
            ->addOption(
                'purge-resource',
                null,
                InputOption::VALUE_NONE,
                'Purge the resource entry as well (deletes the record from favorites)'
            )->addArgument('source', InputArgument::REQUIRED, 'Record source (e.g. Solr)')
            ->addArgument('id', InputArgument::REQUIRED, 'Record ID');
    }

    /**
     * Run the command.
     *
     * @param InputInterface  $input  Input object
     * @param OutputInterface $output Output object
     *
     * @return int 0 for success
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $source = $input->getArgument('source');
        $id = $input->getArgument('id');
        if ($this->recordService->deleteRecord($id, $source)) {
            $output->writeln('Cached record deleted');
        } else {
            $output->writeln('No cached record found');
        }
        if ($input->getOption('purge-resource')) {
            if ($this->resourceService->deleteResourceByRecordId($id, $source)) {
                $output->writeln('Resource deleted');
            } else {
                $output->writeln('No resource found');
            }
        }
        return 0;
    }
}
