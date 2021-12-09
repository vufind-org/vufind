<?php
/**
 * Console command: delete from Solr
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

use File_MARC;
use File_MARCXML;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Console command: delete from Solr
 *
 * @category VuFind
 * @package  Console
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
class DeletesCommand extends AbstractSolrCommand
{
    /**
     * The name of the command (the part after "public/index.php")
     *
     * @var string
     */
    protected static $defaultName = 'util/deletes';

    /**
     * Configure the command.
     *
     * @return void
     */
    protected function configure()
    {
        $this
            ->setDescription('Tool for deleting Solr records')
            ->setHelp('Deletes a set of records from the Solr index.')
            ->addArgument(
                'filename',
                InputArgument::REQUIRED,
                'the file containing records to delete.'
            )->addArgument(
                'format',
                InputArgument::OPTIONAL,
                "the format of the file -- it may be one of the following:\n"
                . "flat - flat text format "
                . "(deletes all IDs in newline-delimited file)\n"
                . "marc - binary MARC format (delete all record IDs from 001 "
                . "fields)\n"
                . "marcxml - MARC-XML format (delete all record IDs from 001 "
                . "fields)\n",
                'marc'
            )->addArgument(
                'index',
                InputArgument::OPTIONAL,
                'Name of Solr core/backend to update',
                'Solr'
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
     * @param string          $mode     Type of file (marc or marcxml)
     * @param OutputInterface $output   Output object
     *
     * @return array
     */
    protected function getIdsFromMarcFile(
        string $filename,
        string $mode,
        OutputInterface $output
    ): array {
        $ids = [];
        // MARC file mode...  We need to load the MARC record differently if it's
        // XML or binary:
        $collection = ($mode == 'marcxml')
            ? new File_MARCXML($filename) : new File_MARC($filename);

        // Once the records are loaded, the rest of the logic is always the same:
        $missingIdCount = 0;
        while ($record = $collection->next()) {
            $idField = $record->getField('001');
            if ($idField) {
                $ids[] = (string)$idField->getData();
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
            : $this->getIdsFromMarcFile($filename, $mode, $output);

        // Delete, Commit and Optimize if necessary:
        if (!empty($ids)) {
            $output->writeln(
                'Attempting to delete ' . count($ids) . ' record(s): '
                . implode(', ', $ids),
                OutputInterface::VERBOSITY_VERBOSE
            );
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
