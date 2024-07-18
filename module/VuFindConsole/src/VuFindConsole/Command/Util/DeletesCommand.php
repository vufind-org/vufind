<?php

/**
 * Console command: delete from Solr
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
use Symfony\Component\Console\Formatter\OutputFormatter;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use VuFind\Marc\MarcCollectionFile;

use function count;
use function strlen;

/**
 * Console command: delete from Solr
 *
 * @category VuFind
 * @package  Console
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
#[AsCommand(
    name: 'util/deletes',
    description: 'Tool for deleting Solr records'
)]
class DeletesCommand extends AbstractSolrCommand
{
    /**
     * Configure the command.
     *
     * @return void
     */
    protected function configure()
    {
        $this
            ->setHelp('Deletes a set of records from the Solr index.')
            ->addArgument(
                'filename',
                InputArgument::REQUIRED,
                'the file containing records to delete.'
            )->addArgument(
                'format',
                InputArgument::OPTIONAL,
                "the format of the file -- it may be one of the following:\n"
                . 'flat - flat text format '
                . "(deletes all IDs in newline-delimited file)\n"
                . 'marc - MARC record in binary or MARCXML format (deletes all '
                . "record IDs from 001 fields)\n"
                . "marcxml - DEPRECATED; use marc instead\n",
                'marc'
            )->addArgument(
                'index',
                InputArgument::OPTIONAL,
                'Name of Solr core/backend to update',
                'Solr'
            )->addOption(
                'id-prefix',
                null,
                InputOption::VALUE_REQUIRED,
                'Prefix to prepend to all IDs',
                ''
            );
    }

    /**
     * Load IDs from a flat file.
     *
     * @param string $filename Filename to load from
     *
     * @return array
     */
    protected function getIdsFromFlatFile(string $filename): array
    {
        $ids = [];
        foreach (array_map('trim', file($filename)) as $id) {
            if (strlen($id)) {
                $ids[] = $id;
            }
        }
        return $ids;
    }

    /**
     * Load IDs from a MARC file
     *
     * @param string          $filename MARC file
     * @param OutputInterface $output   Output object
     *
     * @return array
     */
    protected function getIdsFromMarcFile(
        string $filename,
        OutputInterface $output
    ): array {
        $ids = [];
        // MARC file mode:
        $messageCallback = function (string $msg, int $level) use ($output) {
            if ($output->isVerbose() || $level !== E_NOTICE) {
                $output->writeln(
                    '<comment>' . OutputFormatter::escape($msg) . '</comment>'
                );
            }
        };
        $collection = new MarcCollectionFile($filename, $messageCallback);

        // Once the records are loaded, the rest of the logic is always the same:
        $missingIdCount = 0;
        foreach ($collection as $record) {
            if ($id = $record->getField('001')) {
                $ids[] = $id;
            } else {
                $missingIdCount++;
            }
        }
        if ($output->isVerbose() && $missingIdCount) {
            $output->writeln(
                "Encountered $missingIdCount record(s) without IDs."
            );
        }
        return $ids;
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
        $filename = $input->getArgument('filename');
        $mode = $input->getArgument('format');
        $index = $input->getArgument('index');
        $prefix = $input->getOption('id-prefix');

        // File doesn't exist?
        if (!file_exists($filename)) {
            $output->writeln("Cannot find file: {$filename}");
            return 1;
        }

        $output->writeln(
            "Loading IDs in {$mode} mode.",
            OutputInterface::VERBOSITY_VERBOSE
        );

        // Build list of records to delete:
        $ids = ($mode == 'flat')
            ? $this->getIdsFromFlatFile($filename)
            : $this->getIdsFromMarcFile($filename, $output);

        // Delete, Commit and Optimize if necessary:
        if (!empty($ids)) {
            $output->writeln(
                'Attempting to delete ' . count($ids) . ' record(s): '
                . implode(', ', $ids),
                OutputInterface::VERBOSITY_VERBOSE
            );
            if (!empty($prefix)) {
                $callback = function ($id) use ($prefix) {
                    return $prefix . $id;
                };
                $ids = array_map($callback, $ids);
            }
            $this->solr->deleteRecords($index, $ids);
            $output->writeln(
                'Delete operation completed.',
                OutputInterface::VERBOSITY_VERBOSE
            );
        } elseif ($output->isVerbose()) {
            $output->writeln('Nothing to delete.');
        }

        return 0;
    }
}
