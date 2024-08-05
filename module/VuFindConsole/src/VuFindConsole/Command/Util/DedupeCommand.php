<?php

/**
 * Console command: deduplicate lines in a sorted file.
 *
 * Needed for the Windows version of the alphabetical browse database generator,
 * since Windows sort does not support deduplication. Assumes presorted input.
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
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;

/**
 * Console command: deduplicate lines in a sorted file.
 *
 * @category VuFind
 * @package  Console
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
#[AsCommand(
    name: 'util/dedupe',
    description: 'Tool for deduplicating lines in a sorted file'
)]
class DedupeCommand extends Command
{
    /**
     * Configure the command.
     *
     * @return void
     */
    protected function configure()
    {
        $this
            ->setHelp('Deduplicates lines in a sorted file.')
            ->addArgument(
                'input',
                InputArgument::OPTIONAL,
                'the file to deduplicate (omit for interactive prompt).'
            )->addArgument(
                'output',
                InputArgument::OPTIONAL,
                'the output file (omit for interactive prompt).'
            );
    }

    /**
     * Fetch a single line of input from the user.
     *
     * @param InputInterface  $input  Input object
     * @param OutputInterface $output Output object
     * @param string          $prompt Prompt to display to the user.
     *
     * @return string        User-entered response.
     */
    protected function getInput(
        InputInterface $input,
        OutputInterface $output,
        string $prompt
    ): string {
        $question = new Question($prompt, '');
        return $this->getHelper('question')->ask($input, $output, $question);
    }

    /**
     * Open a file for writing.
     *
     * @param string $filename File to open
     *
     * @return resource
     */
    protected function openOutputFile($filename)
    {
        return @fopen($filename, 'w');
    }

    /**
     * Write a line to an output file.
     *
     * @param resource $handle File handle
     * @param string   $text   Text to write
     *
     * @return void
     */
    protected function writeToOutputFile($handle, $text)
    {
        fwrite($handle, $text);
    }

    /**
     * Close a file handle.
     *
     * @param resource $handle Handle from openOutputFile()
     *
     * @return void
     */
    protected function closeOutputFile($handle)
    {
        fclose($handle);
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
        $infile = $input->getArgument('input');
        if (empty($infile)) {
            $inprompt = 'Please specify an input file: ';
            $infile = $this->getInput($input, $output, $inprompt);
        }
        $inHandle = @fopen($infile, 'r');
        if (!$inHandle) {
            $output->writeln('Could not open input file: ' . $infile);
            return 1;
        }
        $outfile = $input->getArgument('output');
        if (empty($outfile)) {
            $outprompt = 'Please specify an output file: ';
            $outfile = $this->getInput($input, $output, $outprompt);
        }
        $outHandle = $this->openOutputFile($outfile);
        if (!$outHandle) {
            $output->writeln('Could not open output file: ' . $outfile);
            return 1;
        }

        $last = '';
        while ($tmp = fgets($inHandle)) {
            if ($tmp != $last) {
                $this->writeToOutputFile($outHandle, $tmp);
            }
            $last = $tmp;
        }

        fclose($inHandle);
        $this->closeOutputFile($outHandle);

        return 0;
    }
}
