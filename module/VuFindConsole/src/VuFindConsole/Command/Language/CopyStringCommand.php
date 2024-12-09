<?php

/**
 * Language command: copy string.
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
use Symfony\Component\Console\Input\InputOption;
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
#[AsCommand(
    name: 'language/copystring',
    description: 'String copier'
)]
class CopyStringCommand extends AbstractCommand
{
    /**
     * Configure the command.
     *
     * @return void
     */
    protected function configure()
    {
        $note = "(may include 'textdomain::' prefix)";
        $this
            ->setHelp('Copies one language string to another.')
            ->addArgument(
                'source',
                InputArgument::REQUIRED,
                'the source key to read ' . $note
            )->addArgument(
                'target',
                InputArgument::REQUIRED,
                'the target key to write ' . $note
            )->addOption(
                'replace',
                null,
                InputOption::VALUE_REQUIRED,
                'string delimited by replaceDelimiter option, representing '
                . "search-and-replace operation.\ne.g. textToReplace/replacementText"
            )->addOption(
                'replaceDelimiter',
                null,
                InputOption::VALUE_REQUIRED,
                'delimiter used in replace option',
                '/'
            );
    }

    /**
     * Add a line to a language file
     *
     * @param string $filename File to update
     * @param string $key      Name of language key
     * @param string $value    Value of translation
     *
     * @return void
     */
    protected function addLineToFile($filename, $key, $value)
    {
        $fHandle = fopen($filename, 'a');
        if (!$fHandle) {
            throw new \Exception('Cannot open ' . $filename . ' for writing.');
        }
        fwrite($fHandle, "\n$key = \"" . $value . "\"\n");
        fclose($fHandle);
    }

    /**
     * Apply a replacement rule, if necessary.
     *
     * @param string $text Text to transform
     * @param array  $rule Replacement rule (empty for no change; [text to replace,
     * replacement] array to apply a transformation)
     *
     * @return string
     */
    protected function applyReplaceRule(string $text, array $rule): string
    {
        return empty($rule) ? $text : str_replace($rule[0], $rule[1], $text);
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
        $replace = $input->getOption('replace');
        $replaceDelimiter = $input->getOption('replaceDelimiter');
        $replaceRule = empty($replace) ? [] : explode($replaceDelimiter, $replace);

        [$sourceDomain, $sourceKey] = $this->extractTextDomain($source);
        [$targetDomain, $targetKey] = $this->extractTextDomain($target);

        if (
            !($sourceDir = $this->getLangDir($output, $sourceDomain))
            || !($targetDir = $this->getLangDir($output, $targetDomain, true))
        ) {
            return 1;
        }

        // First, collect the source values from the source text domain:
        $sources = [];
        $sourceCallback
            = function ($full) use ($output, $replaceRule, $sourceKey, &$sources) {
                $strings = $this->reader->getTextDomain($full, false);
                if (!isset($strings[$sourceKey])) {
                    $output->writeln('Source key not found.');
                    return;
                }
                $sources[basename($full)] = $this->applyReplaceRule(
                    $strings[$sourceKey],
                    $replaceRule
                );
            };
        $this->processDirectory($sourceDir, $sourceCallback, [$output, 'writeln']);

        // Make sure that all target files exist:
        $this->createMissingFiles($targetDir->path, array_keys($sources));

        // Now copy the values to their destination:
        $targetCallback = function ($full) use ($targetKey, $sources) {
            if (isset($sources[basename($full)])) {
                $this->addLineToFile($full, $targetKey, $sources[basename($full)]);
                $this->normalizer->normalizeFile($full);
            }
        };
        $this->processDirectory($targetDir, $targetCallback, [$output, 'writeln']);

        return 0;
    }
}
