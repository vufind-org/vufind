<?php

/**
 * Console command: commit to Solr
 *
 * PHP version 8
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

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

use function ini_get;

/**
 * Console command: commit to Solr
 *
 * @category VuFind
 * @package  Console
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
#[AsCommand(
    name: 'util/commit',
    description: 'Solr commit tool'
)]
class CommitCommand extends AbstractSolrCommand
{
    /**
     * The name of the Solr command, for use in help messages.
     *
     * @var string
     */
    protected $solrCommand = 'commit';

    /**
     * Configure the command.
     *
     * @return void
     */
    protected function configure()
    {
        $this
            ->setHelp('Sends a ' . $this->solrCommand . ' command to a Solr index.')
            ->addArgument(
                'core',
                InputArgument::OPTIONAL,
                'Name of Solr core to ' . $this->solrCommand,
                'Solr'
            );
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
        // Check time limit; increase if necessary:
        if (ini_get('max_execution_time') < 3600) {
            ini_set('max_execution_time', '3600');
        }

        // Setup Solr Connection -- Allow core to be specified from command line.
        $core = $input->getArgument('core');

        // Commit to the Solr Index
        $this->solr->commit($core);
        return 0;
    }
}
