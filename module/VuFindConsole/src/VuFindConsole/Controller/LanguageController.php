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
            Console::writeLine(
                "(source and target may include 'textdomain::' prefix)"
            );
            return $this->getFailureResponse();
        }

        $reader = new ExtendedIniReader();
        $normalizer = new ExtendedIniNormalizer();
        list($sourceDomain, $sourceKey) = $this->extractTextDomain($argv[0]);
        list($targetDomain, $targetKey) = $this->extractTextDomain($argv[1]);

        if (!($sourceDir = $this->getLangDir($sourceDomain))
            || !($targetDir = $this->getLangDir($targetDomain, true))
        ) {
            return $this->getFailureResponse();
        }

        // First, collect the source values from the source text domain:
        $sources = [];
        $sourceCallback = function ($full) use ($sourceKey, $reader, & $sources) {
            $strings = $reader->getTextDomain($full, false);
            if (!isset($strings[$sourceKey])) {
                Console::writeLine("Source key not found.");
            } else {
                $sources[basename($full)] = $strings[$sourceKey];
            }
        };
        $this->processDirectory($sourceDir, $sourceCallback);

        // Make sure that all target files exist:
        $this->createMissingFiles($targetDir->path, array_keys($sources));

        // Now copy the values to their destination:
        $targetCallback = function ($full) use ($targetKey, $normalizer, $sources) {
            if (isset($sources[basename($full)])) {
                $fHandle = fopen($full, "a");
                fputs(
                    $fHandle,
                    "\n$targetKey = \"" . $sources[basename($full)] . "\"\n"
                );
                fclose($fHandle);
                $normalizer->normalizeFile($full);
            }
        };
        $this->processDirectory($targetDir, $targetCallback);

        return $this->getSuccessResponse();
    }

    /**
     * Delete a language string to another
     *
     * @return \Zend\Console\Response
     */
    public function deleteAction()
    {
        // Display help message if parameters missing:
        $argv = $this->consoleOpts->getRemainingArgs();
        if (!isset($argv[0])) {
            Console::writeLine(
                "Usage: {$_SERVER['argv'][0]} [target]"
            );
            Console::writeLine(
                "\ttarget - the target key to remove "
                . "(may include 'textdomain::' prefix)"
            );
            return $this->getFailureResponse();
        }

        $normalizer = new ExtendedIniNormalizer();
        list($domain, $key) = $this->extractTextDomain($argv[0]);
        $target = $key . ' = "';

        if (!($dir = $this->getLangDir($domain))) {
            return $this->getFailureResponse();
        }
        $callback = function ($full) use ($target, $normalizer) {
            $lines = file($full);
            $out = '';
            $found = false;
            foreach ($lines as $line) {
                if (substr($line, 0, strlen($target)) !== $target) {
                    $out .= $line;
                } else {
                    $found = true;
                }
            }
            if ($found) {
                file_put_contents($full, $out);
                $normalizer->normalizeFile($full);
            } else {
                Console::writeLine("Source key not found.");
            }
        };
        $this->processDirectory($dir, $callback);

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

    /**
     * Extract a text domain and key from a raw language key.
     *
     * @param string $raw Raw language key
     *
     * @return array [textdomain, key]
     */
    protected function extractTextDomain($raw)
    {
        $parts = explode('::', $raw, 2);
        return count($parts) > 1 ? $parts : ['default', $raw];
    }

    /**
     * Open the language directory as an object using dir(). Return false on
     * failure.
     *
     * @param string $domain          Text domain to retrieve.
     * @param bool   $createIfMissing Should we create a missing directory?
     *
     * @return object|bool
     */
    protected function getLangDir($domain = 'default', $createIfMissing = false)
    {
        $subDir = $domain == 'default' ? '' : ('/' . $domain);
        $langDir = __DIR__ . '/../../../../../languages' . $subDir;
        if ($createIfMissing && !is_dir($langDir)) {
            mkdir($langDir);
        }
        $dir = dir(realpath($langDir));
        if (!$dir) {
            Console::writeLine("Could not open directory $langDir");
            return false;
        }
        return $dir;
    }

    /**
     * Create empty files if they do not already exist.
     *
     * @param string $path  Directory path
     * @param array  $files Filenames to create in directory
     *
     * @return void
     */
    protected function createMissingFiles($path, $files)
    {
        foreach ($files as $file) {
            if (!file_exists($path . '/' . $file)) {
                file_put_contents($path . '/' . $file, '');
            }
        }
    }

    /**
     * Process a language directory.
     *
     * @param object   $dir      Directory object from dir() to process
     * @param Callable $callback Function to run on all .ini files in $dir
     *
     * @return void
     */
    protected function processDirectory($dir, $callback)
    {
        while ($file = $dir->read()) {
            // Only process .ini files, and ignore native.ini special case file:
            if (substr($file, -4) == '.ini' && $file !== 'native.ini') {
                Console::writeLine("Processing $file...");
                $callback($dir->path . '/' . $file);
            }
        }
    }
}
