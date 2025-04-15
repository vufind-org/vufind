<?php

/**
 * Language command: add string using template.
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

namespace VuFindConsole\Command\Language;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Language command: add string using template.
 *
 * @category VuFind
 * @package  Console
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
#[AsCommand(
    name: 'language/delete',
    description: 'Delete string tool'
)]
class DeleteCommand extends AbstractCommand
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
                'Removes a language string from all files'
            )->addArgument(
                'target',
                InputArgument::REQUIRED,
                "the target key to remove (may include 'textdomain::' prefix)"
            );
    }

    /**
     * Write file contents to disk.
     *
     * @param string $filename Filename
     * @param string $content  Content
     *
     * @return bool
     */
    protected function writeFileToDisk($filename, $content)
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
        $target = $input->getArgument('target');

        [$domain, $key] = $this->extractTextDomain($target);
        $target = $key . ' = ';

        if (!($dir = $this->getLangDir($output, $domain))) {
            return 1;
        }
        $callback = function ($full) use ($output, $target) {
            $lines = file($full);
            $out = '';
            $found = false;
            foreach ($lines as $line) {
                if (!str_starts_with($line, $target . '"') && !str_starts_with($line, $target . "'")) {
                    $out .= $line;
                } else {
                    $found = true;
                }
            }
            if ($found) {
                $this->writeFileToDisk($full, $out);
                $this->normalizer->normalizeFile($full);
            } else {
                $output->writeln('Source key not found.');
            }
        };
        $this->processDirectory($dir, $callback, [$output, 'writeln']);

        return 0;
    }
}
