<?php

/**
 * Console command: remove suppressed records from index
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
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

use function is_array;

/**
 * Console command: remove suppressed records from index
 *
 * @category VuFind
 * @package  Console
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
#[AsCommand(
    name: 'util/suppressed',
    description: 'Remove ILS-suppressed records from Solr'
)]
class SuppressedCommand extends AbstractSolrAndIlsCommand
{
    /**
     * Configure the command.
     *
     * @return void
     */
    protected function configure()
    {
        $this
            ->setHelp(
                'This tool removes ILS-suppressed records from Solr.'
            )->addOption(
                'authorities',
                null,
                InputOption::VALUE_NONE,
                'Delete authority records instead of bibliographic records'
            )->addOption(
                'outfile',
                null,
                InputOption::VALUE_REQUIRED,
                'Write the ID list to the specified file instead of updating Solr'
            );
    }

    /**
     * Write content to disk.
     *
     * @param string $filename Target filename
     * @param string $content  Content to write
     *
     * @return bool
     */
    protected function writeToDisk($filename, $content)
    {
        return file_put_contents($filename, $content);
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
        // Setup Solr Connection
        $backend = $input->getOption('authorities') ? 'SolrAuth' : 'Solr';

        // Make ILS Connection
        try {
            $result = ($backend == 'SolrAuth')
                ? $this->catalog->getSuppressedAuthorityRecords()
                : $this->catalog->getSuppressedRecords();
        } catch (\Exception $e) {
            $output->writeln('ILS error -- ' . $e->getMessage());
            return 1;
        }

        // Validate result:
        if (!is_array($result)) {
            $output->writeln('Could not obtain suppressed record list from ILS.');
            return 1;
        } elseif (empty($result)) {
            $output->writeln('No suppressed records to delete.');
            return 0;
        }

        // If 'outfile' set, write the list
        if ($file = $input->getOption('outfile')) {
            if (!$this->writeToDisk($file, implode("\n", $result))) {
                $output->writeln("Problem writing to $file");
                return 1;
            }
        } else {
            // Default behavior: Delete from Solr index
            $this->solr->deleteRecords($backend, $result);
            $this->solr->commit($backend);
            $this->solr->optimize($backend);
        }
        return 0;
    }
}
