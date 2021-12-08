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

        if (!($handle = @opendir($dir))) {
            $output->writeln("Cannot open directory: {$dir}");
            return 1;
        }

        $output->writeln('<collection>');
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
        foreach ($fileList as $filePath) {
            $fileContent = file_get_contents($filePath);

            // output content:
            $output->writeln("<!-- $filePath -->");

            // If the current file is a collection, we need to extract records:
            $xml = simplexml_load_string($fileContent);
            if (stristr($xml->getName(), 'collection') !== false) {
                foreach ($xml->children(self::MARC21_NAMESPACE) as $record) {
                    $output->write($record->asXml());
                }
            } else {
                $output->write($fileContent);
            }
        }
        $output->writeln('</collection>');
        return 0;
    }
}
