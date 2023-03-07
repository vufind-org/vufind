<?php

/**
 * Console command: Merge MARC records.
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
 * @author   Thomas Schwaerzler <thomas.schwaerzler@uibk.ac.at>
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */

namespace VuFindConsole\Command\Harvest;

use SimpleXMLElement;
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
class MergeMarcCommand extends Command
{
    /**
     * XML namespace for MARC21.
     */
    public const MARC21_NAMESPACE = 'http://www.loc.gov/MARC21/slim';

    /**
     * The name of the command (the part after "public/index.php")
     *
     * @var string
     */
    protected static $defaultName = 'harvest/merge-marc';

    /**
     * Configure the command.
     *
     * @return void
     */
    protected function configure()
    {
        $this
            ->setDescription('MARC merge tool')
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
            if (pathinfo($file, PATHINFO_EXTENSION) === "xml") {
                // get file content
                $fileList[] = $dir . '/' . $file;
            }
        }
        // Sort filenames so that we have consistent results:
        sort($fileList);
        return $fileList;
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
        $fileContent = file_get_contents($filePath);
        $xml = simplexml_load_string($fileContent);
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
