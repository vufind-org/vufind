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
    name: 'language/addusingtemplate',
    description: 'Template-based string builder'
)]
class AddUsingTemplateCommand extends AbstractCommand
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
                'Builds new language strings from existing ones using a template'
            )->addArgument(
                'target',
                InputArgument::REQUIRED,
                "the target key to add (may include 'textdomain::' prefix)"
            )->addArgument(
                'template',
                InputArgument::REQUIRED,
                'the template to build the string, using ||string||'
                . ' to import existing strings'
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
        $target = $input->getArgument('target');
        $template = $input->getArgument('template');

        // Make sure a valid target has been specified:
        [$targetDomain, $targetKey] = $this->extractTextDomain($target);
        if (!($targetDir = $this->getLangDir($output, $targetDomain, true))) {
            return 1;
        }

        // Extract required source values from template:
        preg_match_all('/\|\|[^|]+\|\|/', $template, $matches);
        $lookups = [];
        foreach ($matches[0] as $current) {
            $key = trim($current, '|');
            [$sourceDomain, $sourceKey] = $this->extractTextDomain($key);
            $lookups[$sourceDomain][$current] = [
                'key' => $sourceKey,
                'translations' => [],
            ];
        }

        // Look up translations of all references in template:
        foreach ($lookups as $domain => & $tokens) {
            $sourceDir = $this->getLangDir($output, $domain, false);
            if (!$sourceDir) {
                return 1;
            }
            $sourceCallback = function ($full) use (&$tokens) {
                $strings = $this->reader->getTextDomain($full, false);
                foreach ($tokens as & $current) {
                    $sourceKey = $current['key'];
                    if (isset($strings[$sourceKey])) {
                        $current['translations'][basename($full)]
                            = $strings[$sourceKey];
                    }
                }
            };
            $this->processDirectory($sourceDir, $sourceCallback, false);
        }

        // Fill in template, write results:
        $targetCallback = function ($full) use (
            $output,
            $template,
            $targetKey,
            $lookups
        ) {
            $lang = basename($full);
            $in = $out = [];
            foreach ($lookups as $domain => $tokens) {
                foreach ($tokens as $token => $details) {
                    if (!isset($details['translations'][$lang])) {
                        $output->writeln("Skipping; no match for token: $token");
                        return;
                    }
                    $in[] = $token;
                    $out[] = $details['translations'][$lang];
                }
            }
            $this->addLineToFile(
                $full,
                $targetKey,
                str_replace($in, $out, $template)
            );
            $this->normalizer->normalizeFile($full);
        };
        $this->processDirectory($targetDir, $targetCallback, [$output, 'writeln']);
        return 0;
    }
}
