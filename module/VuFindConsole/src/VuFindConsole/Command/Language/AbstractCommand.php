<?php

/**
 * Abstract base class for language commands.
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

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Output\OutputInterface;
use VuFind\I18n\ExtendedIniNormalizer;
use VuFind\I18n\Translator\Loader\ExtendedIniReader;

use function count;
use function in_array;
use function is_callable;

/**
 * Abstract base class for language commands.
 *
 * @category VuFind
 * @package  Console
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
abstract class AbstractCommand extends Command
{
    /**
     * Normalizer for .ini files
     *
     * @var ExtendedIniNormalizer
     */
    protected $normalizer;

    /**
     * Reader for .ini files
     *
     * @var ExtendedIniReader
     */
    protected $reader;

    /**
     * Language directory
     *
     * @var string
     */
    protected $languageDir;

    /**
     * Files to ignore when processing directories
     *
     * @var string[]
     */
    protected $filesToIgnore = ['aliases.ini', 'native.ini'];

    /**
     * Constructor
     *
     * @param ExtendedIniNormalizer $normalizer  Normalizer for .ini files
     * @param ExtendedIniReader     $reader      Reader for .ini files
     * @param string                $languageDir Base language file directory
     * @param string|null           $name        The name of the command; passing
     * null means it must be set in configure()
     */
    public function __construct(
        ExtendedIniNormalizer $normalizer = null,
        ExtendedIniReader $reader = null,
        $languageDir = null,
        $name = null
    ) {
        $this->normalizer = $normalizer ?? new ExtendedIniNormalizer();
        $this->reader = $reader ?? new ExtendedIniReader();
        $this->languageDir = $languageDir
            ?? realpath(__DIR__ . '/../../../../../../languages');
        parent::__construct($name);
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
     * @param OutputInterface $output          Output object
     * @param string          $domain          Text domain to retrieve.
     * @param bool            $createIfMissing Should we create a missing directory?
     *
     * @return object|bool
     */
    protected function getLangDir(
        OutputInterface $output,
        $domain = 'default',
        $createIfMissing = false
    ) {
        $subDir = $domain == 'default' ? '' : ('/' . $domain);
        $langDir = $this->languageDir . $subDir;
        if ($createIfMissing && !is_dir($langDir)) {
            mkdir($langDir);
        }
        $dir = dir(realpath($langDir));
        if (!$dir) {
            $output->writeln("Could not open directory $langDir");
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
     * @param object   $dir            Directory object from dir() to process
     * @param callable $callback       Function to run on all .ini files in $dir
     * @param bool     $statusCallback Callback function to display status messages
     * (omit to suppress messages)
     *
     * @return void
     */
    protected function processDirectory($dir, $callback, $statusCallback = false)
    {
        while ($file = $dir->read()) {
            // Only process .ini files, and ignore special case files:
            if (str_ends_with($file, '.ini') && !in_array($file, $this->filesToIgnore)) {
                if (is_callable($statusCallback)) {
                    $statusCallback("Processing $file...");
                }
                $callback($dir->path . '/' . $file);
            }
        }
    }
}
