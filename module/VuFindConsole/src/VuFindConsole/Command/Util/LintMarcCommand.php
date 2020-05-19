<?php
/**
 * Console command: Lint MARC records.
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

use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use VuFindConsole\Command\RelativeFileAwareCommand;

/**
 * Console command: Lint MARC records.
 *
 * @category VuFind
 * @package  Console
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
class LintMarcCommand extends RelativeFileAwareCommand
{
    /**
     * The name of the command (the part after "public/index.php")
     *
     * @var string
     */
    protected static $defaultName = 'util/lint_marc';

    /**
     * Configure the command.
     *
     * @return void
     */
    protected function configure()
    {
        $this
            ->setDescription('MARC validator')
            ->setHelp('This command lets you validate MARC file contents.')
            ->addArgument('filename', InputArgument::REQUIRED, 'MARC filename');
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
        $marc = substr($filename, -3) !== 'xml'
            ? new \File_MARC($filename) : new \File_MARCXML($filename);
        $linter = new \File_MARC_Lint();
        $i = 0;
        while ($record = $marc->next()) {
            $i++;
            $field001 = $record->getField('001');
            $field001 = $field001 ? (string)$field001->getData() : 'undefined';
            $output->writeln("Checking record $i (001 = $field001)...");
            $warnings = $linter->checkRecord($record);
            if (count($warnings) > 0) {
                $output->writeln('Warnings: ' . implode("\n", $warnings));
            }
        }
        return 0;
    }
}
