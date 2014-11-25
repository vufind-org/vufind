<?php
/**
 * CLI Controller Module (language tools)
 *
 * PHP version 5
 *
 * Copyright (C) Villanova University 2010.
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
 * Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
 *
 * @category VuFind2
 * @package  Controller
 * @author   Chris Hallberg <challber@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:building_a_controller Wiki
 */
namespace VuFindConsole\Controller;
use VuFind\I18n\ExtendedIniNormalizer,
    VuFind\I18n\Translator\Loader\ExtendedIniReader,
    Zend\Console\Console;

/**
 * This controller handles various command-line tools for dealing with language files
 *
 * @category VuFind2
 * @package  Controller
 * @author   Chris Hallberg <challber@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:building_a_controller Wiki
 */
class LanguageController extends AbstractBase
{
    /**
     * Copy one language string to another
     *
     * @return \Zend\Console\Response
     */
    public function copystringAction()
    {
        // Display help message if parameters missing:
        $argv = $this->consoleOpts->getRemainingArgs();
        if (!isset($argv[1])) {
            Console::writeLine(
                "Usage: {$_SERVER['argv'][0]} [source] [target]"
            );
            Console::writeLine("\tsource - the source key to read");
            Console::writeLine("\ttarget - the target key to write");
            return $this->getFailureResponse();
        }

        $reader = new ExtendedIniReader();
        $normalizer = new ExtendedIniNormalizer();
        $source = $argv[0];
        $target = $argv[1];

        $langDir = realpath(__DIR__ . '/../../../../../languages');
        $handle = opendir($langDir);
        if (!$handle) {
            Console::writeLine("Could not open directory $langDir");
            return $this->getFailureResponse();
        }
        while ($file = readdir($handle)) {
            // Only process .ini files, and ignore native.ini special case file:
            if (substr($file, -4) == '.ini' && $file !== 'native.ini') {
                Console::writeLine("Processing $file...");
                $full = $langDir . '/' . $file;
                $strings = $reader->getTextDomain($full, false);
                if (!isset($strings[$source])) {
                    Console::writeLine("Source key not found.");
                } else {
                    $fHandle = fopen($full, "a");
                    fputs($fHandle, "\n$target = \"" . $strings[$source] . "\"\n");
                    fclose($fHandle);
                    $normalizer->normalizeFile($full);
                }
            }
        }

        return $this->getSuccessResponse();
    }

    /**
     * Normalizer
     *
     * @return \Zend\Console\Response
     */
    public function normalizeAction()
    {
        // Display help message if parameters missing:
        $argv = $this->consoleOpts->getRemainingArgs();
        if (!isset($argv[0])) {
            Console::writeLine(
                "Usage: {$_SERVER['argv'][0]} [target]"
            );
            Console::writeLine("\ttarget - a file or directory to normalize");
            return $this->getFailureResponse();
        }

        $normalizer = new ExtendedIniNormalizer();
        $target = $argv[0];
        if (is_dir($target)) {
            $normalizer->normalizeDirectory($target);
        } else if (is_file($target)) {
            $normalizer->normalizeFile($target);
        } else {
            Console::writeLine("{$target} does not exist.");
            return $this->getFailureResponse();
        }
        return $this->getSuccessResponse();
    }
}
