<?php
/**
 * Language command: copy string.
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
namespace VuFindConsole\Command\Language;

use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Language command: copy string.
 *
 * @category VuFind
 * @package  Console
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
class CopyStringCommand extends AbstractCommand
{
    /**
     * The name of the command (the part after "public/index.php")
     *
     * @var string
     */
    protected static $defaultName = 'language/copystring';

    /**
     * Configure the command.
     *
     * @return void
     */
    protected function configure()
    {
        $note = "(may include 'textdomain::' prefix)";
        $this
            ->setDescription('String copier')
            ->setHelp('Copies one language string to another.')
            ->addArgument(
                'source',
                InputArgument::REQUIRED,
                'the source key to read ' . $note
            )->addArgument(
                'target',
                InputArgument::REQUIRED,
                'the target key to write ' . $note
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
        $source = $input->getArgument('source');
        $target = $input->getArgument('target');

        list($sourceDomain, $sourceKey) = $this->extractTextDomain($source);
        list($targetDomain, $targetKey) = $this->extractTextDomain($target);

        if (!($sourceDir = $this->getLangDir($output, $sourceDomain))
            || !($targetDir = $this->getLangDir($output, $targetDomain, true))
        ) {
            return 1;
        }

        // First, collect the source values from the source text domain:
        $sources = [];
        $sourceCallback = function ($full) use ($output, $sourceKey, & $sources) {
            $strings = $this->reader->getTextDomain($full, false);
            if (!isset($strings[$sourceKey])) {
                $output->writeln('Source key not found.');
            } else {
                $sources[basename($full)] = $strings[$sourceKey];
            }
        };
        $this->processDirectory($sourceDir, $sourceCallback, [$output, 'writeln']);

        // Make sure that all target files exist:
        $this->createMissingFiles($targetDir->path, array_keys($sources));

        // Now copy the values to their destination:
        $targetCallback = function ($full) use ($output, $targetKey, $sources) {
            if (isset($sources[basename($full)])) {
                $fHandle = fopen($full, "a");
                fputs(
                    $fHandle,
                    "\n$targetKey = \"" . $sources[basename($full)] . "\"\n"
                );
                fclose($fHandle);
                $this->normalizer->normalizeFile($full);
            }
        };
        $this->processDirectory($targetDir, $targetCallback, [$output, 'writeln']);

        return 0;
    }
}
