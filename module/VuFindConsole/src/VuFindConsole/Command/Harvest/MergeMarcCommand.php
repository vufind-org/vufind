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
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use VuFindConsole\Command\RelativeFileAwareCommand;

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
class MergeMarcCommand extends RelativeFileAwareCommand
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
        // Add missing namespace declarations:
        $xml = $record->asXml();
        foreach ($record->getNamespaces() as $prefix => $uri) {
            // Not an ideal way to check if the namespace is already defined, but
            // SimpleXML doesn't seem to provide a more reliable option.
            if (!stristr($xml, 'xmlns:' . $prefix . '=')) {
                $record->addAttribute('xmlnls:xmlns:' . $prefix, $uri);
                $xml = $record->asXml();
            }
        }
        return $xml;
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
        $fileContent = file_get_contents($filePath);

        // output content:
        $output->writeln("<!-- $filePath -->");

        // If the current file is a collection, we need to extract records:
        $xml = simplexml_load_string($fileContent);
        if (stristr($xml->getName(), 'collection') !== false) {
            $childSets = [
                $xml->children(self::MARC21_NAMESPACE),
                $xml->children(),
            ];
            foreach ($childSets as $children) {
                foreach ($children as $record) {
                    if (stristr($record->getName(), 'record') !== false) {
                        $output->write($this->recordXmlToString($record) . "\n");
                    }
                }
            }
        } else {
            $output->write($fileContent);
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

        $output->writeln('<collection>');
        foreach ($fileList as $filePath) {
            $this->outputRecordsFromFile($filePath, $output);
        }
        $output->writeln('</collection>');
        return 0;
    }
}
