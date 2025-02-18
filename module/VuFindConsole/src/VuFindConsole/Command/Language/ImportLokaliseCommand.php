<?php

/**
 * Language command: ingest and normalise language files exported from Lokalise.
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2023.
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

use function count;
use function in_array;
use function strlen;

/**
 * Language command: ingest and normalise language files exported from Lokalise.
 *
 * @category VuFind
 * @package  Console
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
#[AsCommand(
    name: 'language/importlokalise',
    description: 'Lokalise file importer'
)]
class ImportLokaliseCommand extends AbstractCommand
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
                'Loads and normalizes language strings from Lokalise export files'
            )->addArgument(
                'source',
                InputArgument::REQUIRED,
                'source directory (containing language files from Lokalise)'
            )->addArgument(
                'target',
                InputArgument::REQUIRED,
                'the language directory to update with new strings'
            );
    }

    /**
     * Recurse through a directory collecting all .ini files.
     *
     * @param string $dir Directory to explore
     *
     * @return string[]
     */
    protected function collectSourceFiles(string $dir): array
    {
        $files = [];
        $dirHandle = opendir($dir);
        while ($file = readdir($dirHandle)) {
            if (strlen(trim($file, '.')) === 0) {
                continue;   // skip . and ..
            }
            $next = "$dir/$file";
            if (is_dir($next)) {
                $files = array_merge($files, $this->collectSourceFiles($next));
            } elseif (str_ends_with($next, '.ini')) {
                $files[] = $next;
            }
        }
        closedir($dirHandle);
        sort($files); // sort file list for consistent, predictable order of operations
        return $files;
    }

    /**
     * Given an array of files in $sourceDir, return an array of equivalent matching filenames
     * in $targetDir.
     *
     * @param string   $sourceDir   Source directory
     * @param string   $targetDir   Target directory
     * @param string[] $sourceFiles Source files
     *
     * @return string[]
     */
    protected function matchTargetFiles(string $sourceDir, string $targetDir, array $sourceFiles): array
    {
        $targetFiles = [];
        foreach ($sourceFiles as $sourceFile) {
            $baseName = basename($sourceFile);
            // Change file name from Lokalise format to VuFind format:
            $normalizedFile = preg_replace(
                '/' . preg_quote($baseName, '/') . '$/',
                str_replace('_', '-', strtolower($baseName)),
                $sourceFile
            );
            // Determine the equivalent filename in the target directory:
            $targetFile = preg_replace(
                '/^' . preg_quote($sourceDir, '/') . '/',
                $targetDir,
                $normalizedFile
            );
            // If the target file does not exist, check to see if removing the
            // regional part of the code yields a match; otherwise, accept it as new
            // unless the more general code is already defined separately in the
            // source file list.
            if (!file_exists($targetFile)) {
                $parts = explode('-', $targetFile);
                if (count($parts) > 1) {
                    $lastPart = array_pop($parts);
                    // If there's a slash in the last part, this means there's a hyphen
                    // in a directory name somewhere. We should only process further if
                    // the hyphen is in the FILENAME.
                    if (!str_contains($lastPart, '/')) {
                        $revisedTargetFile = implode('-', $parts) . '.ini';
                        $matchingSourceFile = preg_replace(
                            '/^' . preg_quote($targetDir, '/') . '/',
                            $sourceDir,
                            $revisedTargetFile
                        );
                        if (!in_array($matchingSourceFile, $sourceFiles)) {
                            $targetFile = $revisedTargetFile;
                        }
                    }
                }
            }
            $targetFiles[] = $targetFile;
        }
        return $targetFiles;
    }

    /**
     * Format a single line from a Lokalise language file so it is ready for further
     * processing by the language file normalizer.
     *
     * @param string $line Line to format
     *
     * @return string
     */
    protected function formatLokaliseLine(string $line): string
    {
        // Strip single quotes:
        return preg_replace("/^(.* = )'(.*)'(\\n)?\$/", '$1$2$3', $line);
    }

    /**
     * Write content to disk.
     *
     * @param string $file Filename
     * @param string $text Text to write
     *
     * @return void
     */
    protected function writeToDisk(string $file, string $text): void
    {
        // We wrap the file write here for testing/extensibility purposes.
        file_put_contents($file, $text);
    }

    /**
     * Add new strings from $sourceFile to $targetFile.
     *
     * @param string $sourceFile New file from Lokalise
     * @param string $targetFile Existing file in VuFind
     *
     * @return void
     */
    protected function importStrings(string $sourceFile, string $targetFile): void
    {
        $sourceStrings = array_map(
            [$this, 'formatLokaliseLine'],
            $this->normalizer->loadFileIntoArray($sourceFile)
        );
        $targetStrings = file_exists($targetFile) ? $this->normalizer->loadFileIntoArray($targetFile) : [];
        $this->writeToDisk(
            $targetFile,
            $this->normalizer->normalizeArray(array_merge($targetStrings, $sourceStrings))
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

        if (!is_dir($source)) {
            $output->writeln("{$source} does not exist or is not a directory.");
            return 1;
        }
        if (!is_dir($target)) {
            $output->writeln("{$target} does not exist or is not a directory.");
            return 1;
        }
        $sourceFiles = $this->collectSourceFiles($source);
        $targetFiles = $this->matchTargetFiles($source, $target, $sourceFiles);
        array_map([$this, 'importStrings'], $sourceFiles, $targetFiles);
        $output->writeln('Import complete.');
        return 0;
    }
}
