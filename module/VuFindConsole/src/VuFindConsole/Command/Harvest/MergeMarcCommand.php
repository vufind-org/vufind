<?php

/**
 * Console command: Merge MARC records.
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
 * @author   Thomas Schwaerzler <thomas.schwaerzler@uibk.ac.at>
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */

namespace VuFindConsole\Command\Harvest;

use SimpleXMLElement;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Console command: Merge MARC records.
 *
 * @category VuFind
 * @package  Console
 * @author   Thomas Schwaerzler <thomas.schwaerzler@uibk.ac.at>
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
#[AsCommand(
    name: 'harvest/merge-marc',
    description: 'MARC merge tool'
)]
class MergeMarcCommand extends Command
{
    /**
     * XML namespace for MARC21.
     */
    public const MARC21_NAMESPACE = 'http://www.loc.gov/MARC21/slim';

    /**
     * Configure the command.
     *
     * @return void
     */
    protected function configure()
    {
        $this
            ->setHelp(
                'Merges harvested MARCXML files into a single <collection>; '
                . 'writes to stdout.'
            )->addArgument(
                'directory',
                InputArgument::REQUIRED,
                'a directory containing MARC XML files to merge'
            );
    }

    /**
     * Convert a SimpleXMLElement into a string, ensuring that namespace declarations
     * are appropriately included.
     *
     * @param SimpleXMLElement $record Record to reformat
     *
     * @return string
     */
    protected function recordXmlToString(SimpleXMLElement $record): string
    {
        // Normalize unprefixed record tags to use marc namespace; remove extraneous
        // XML headers:
        return str_replace(
            ['<record>', '<record ', '</record>', '<?xml version="1.0"?>'],
            ['<marc:record>', '<marc:record ', '</marc:record>', ''],
            $record->asXml()
        );
    }

    /**
     * Find all XML files in a directory; return a sorted list.
     *
     * @param string $dir Directory to read from
     *
     * @return string[]
     * @throws \Exception
     */
    protected function findXmlFiles($dir): array
    {
        $handle = @opendir($dir);
        if (!$handle) {
            throw new \Exception("Cannot open directory: {$dir}");
        }
        $fileList = [];
        while (false !== ($file = readdir($handle))) {
            // Only operate on XML files:
            if (pathinfo($file, PATHINFO_EXTENSION) === 'xml') {
                // get file content
                $fileList[] = $dir . '/' . $file;
            }
        }
        // Sort filenames so that we have consistent results:
        sort($fileList);
        return $fileList;
    }

    /**
     * Load an XML file, and throw an exception if it is invalid.
     *
     * @param string $filePath File to load
     *
     * @throws \Exception
     * @return SimpleXMLElement
     */
    protected function loadXmlContents(string $filePath): SimpleXMLElement
    {
        // Set up user error handling so we can capture XML errors
        $prev = libxml_use_internal_errors(true);
        $xml = @simplexml_load_file($filePath);
        // Capture any errors before we restore previous error behavior (which will
        // cause them to be lost).
        $errors = libxml_get_errors();
        libxml_use_internal_errors($prev);
        // Build an exception if something has gone wrong
        if ($xml === false) {
            $msg = 'Problem loading XML file: ' . realpath($filePath);
            foreach ($errors as $error) {
                $msg .= "\n" . trim($error->message)
                    . ' in ' . realpath($error->file)
                    . ' line ' . $error->line . ' column ' . $error->column;
            }
            throw new \Exception($msg);
        }
        return $xml;
    }

    /**
     * Given the filename of an XML document, feed any MARC records from the file
     * to the output stream.
     *
     * @param string          $filePath XML filename
     * @param OutputInterface $output   Output stream
     *
     * @return void
     */
    protected function outputRecordsFromFile(
        string $filePath,
        OutputInterface $output
    ): void {
        // We need to find all the possible records; if the top-level tag is a
        // collection, we will search for namespaced and non-namespaced records
        // inside it. Otherwise, we'll just check the top-level tag to see if
        // it's a stand-alone record.
        $xml = $this->loadXmlContents($filePath);
        $childSets = (stristr($xml->getName(), 'collection') !== false)
             ? [$xml->children(self::MARC21_NAMESPACE), $xml->children()]
             : [[$xml]];
        foreach ($childSets as $children) {
            // We'll set a flag to indicate whether or not we found anything in
            // the most recent set. This allows us to break out of the loop once
            // a record has been found, which enables us to favor namespaced
            // matches over non-namespaced matches. This is not ideal (we might
            // miss records in a weird file containing a mix of namespaced and
            // non-namespaced records), but the alternative would cause namespaced
            // but non-prefixed records to get loaded twice.
            $foundSomething = false;
            foreach ($children as $record) {
                if (stristr($record->getName(), 'record') !== false) {
                    $foundSomething = true;
                    $output->write(trim($this->recordXmlToString($record)) . "\n");
                }
            }
            if ($foundSomething) {
                break;
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
        $dir = rtrim($input->getArgument('directory'), '/');

        try {
            $fileList = $this->findXmlFiles($dir);
        } catch (\Exception $e) {
            $output->writeln($e->getMessage());
            return 1;
        }

        $output->writeln(
            '<marc:collection xmlns:marc="' . self::MARC21_NAMESPACE . '">'
        );
        foreach ($fileList as $filePath) {
            // Output comment so we know which file the following records came from:
            $output->writeln("<!-- $filePath -->");
            $this->outputRecordsFromFile($filePath, $output);
        }
        $output->writeln('</marc:collection>');
        return 0;
    }
}
