<?php

/**
 * Class to consistently format ExtendedIni language files.
 *
 * PHP version 8
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
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301  USA
 *
 * @category VuFind
 * @package  Translator
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */

namespace VuFind\I18n;

use Laminas\I18n\Translator\TextDomain;

use function in_array;

/**
 * Class to consistently format ExtendedIni language files.
 *
 * @category VuFind
 * @package  Translator
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */
class ExtendedIniNormalizer
{
    use \VuFind\I18n\Translator\TranslatorAwareTrait;

    /**
     * Reserved words that need to be quoted when used as keys.
     *
     * @var string[]
     */
    protected $reservedWords = ['yes'];

    /**
     * Normalize a directory on disk.
     *
     * @param string $dir    Directory to normalize.
     * @param string $filter File name filter.
     *
     * @return void
     */
    public function normalizeDirectory($dir, $filter)
    {
        $dir = rtrim($dir, '/');
        $handle = opendir($dir);
        while ($file = readdir($handle)) {
            $full = $dir . '/' . $file;
            if ($file != '.' && $file != '..' && is_dir($full)) {
                $this->normalizeDirectory($full, $filter);
            } elseif ($this->filenameMatchesFilter($file, $filter)) {
                $this->normalizeFile($full);
            }
        }
        closedir($handle);
    }

    /**
     * Normalize a file on disk.
     *
     * @param string $file Filename.
     *
     * @return void
     */
    public function normalizeFile($file)
    {
        file_put_contents($file, $this->normalizeFileToString($file));
    }

    /**
     * Normalize a file from disk and returns the result as a string.
     *
     * @param string $file Filename.
     *
     * @return string
     */
    public function normalizeFileToString($file)
    {
        // Safeguard to avoid messing up wrong ini files:
        $fileArray = $this->loadFileIntoArray($file);
        $this->checkFileFormat($fileArray, $file);
        return $this->normalizeArray($fileArray);
    }

    /**
     * Load a language file into an array of lines, stripping UTF-8 BOM if necessary.
     *
     * @param string $filename File to load
     *
     * @return array
     */
    public function loadFileIntoArray(string $filename): array
    {
        $fileArray = file($filename);

        // Strip off UTF-8 BOM if necessary.
        if ($fileArray) {
            $bom = html_entity_decode('&#xFEFF;', ENT_NOQUOTES, 'UTF-8');
            $fileArray[0] = str_replace($bom, '', $fileArray[0]);
        }

        return $fileArray;
    }

    /**
     * Normalize an array of lines from a file and return the result as a string.
     *
     * @param string[] $fileArray Array of lines to normalize
     *
     * @return string
     */
    public function normalizeArray(array $fileArray): string
    {
        // Reading and rewriting the file by itself will eliminate all comments;
        // we should extract comments separately and then recombine the parts.
        $comments = $this->extractComments($fileArray);
        $reader = new Translator\Loader\ExtendedIniReader();
        $strings = $this->formatAsString($reader->getTextDomain($fileArray, false));
        return $comments . $strings;
    }

    /**
     * Normalize a TextDomain or array to a string that can be written to file.
     *
     * @param array|TextDomain $rawInput Language values to format.
     *
     * @return string
     */
    public function formatAsString($rawInput)
    {
        // Sanitize keys before sorting:
        $input = [];
        foreach ($rawInput as $key => $value) {
            $input[$this->sanitizeTranslationKey($key)] = $value;
        }

        // Perform a case-insensitive sort:
        $sortCallback = function ($a, $b) {
            // We need absolutely consistent sorting; a pure case-insensitive
            // sort will randomly reorder strings that evaluate to the same
            // thing (e.g. "by" vs. "By"). In our custom sort function, we'll
            // do a case-sensitive sort on otherwise identical strings to
            // ensure 100% consistent behavior.
            $lowerA = strtolower($a);
            $lowerB = strtolower($b);
            if ($lowerA === $lowerB) {
                return strcmp($a, $b);
            }
            return strcmp($lowerA, $lowerB);
        };
        uksort($input, $sortCallback);

        // Format the lines:
        $output = '';
        foreach ($input as $key => $value) {
            // Put purely numeric keys in single quotes for Lokalise compatibility:
            $normalizedKey = is_numeric($key) || in_array($key, $this->reservedWords)
                ? "'$key'" : $key;
            // Choose most appropriate type of outer quotes to reduce need for escaping:
            $quote = str_contains($value, '"') ? "'" : '"';
            // Apply minimal escaping (to existing slashes and quotes matching the outer ones):
            $escapedValue = str_replace(['\\', $quote], ['\\\\', '\\' . $quote], $value);
            // Put it all together!
            $output .= "$normalizedKey = $quote$escapedValue$quote\n";
        }
        return trim($output) . "\n";
    }

    /**
     * Extract comments from an array of lines read from a file.
     *
     * @param array $contents Contents to scan for comments.
     *
     * @return string
     */
    public function extractComments($contents)
    {
        $comments = '';
        foreach ($contents as $line) {
            if (str_starts_with(trim($line), ';')) {
                $comments .= $line;
            }
        }
        return $comments;
    }

    /**
     * Check if the given filename matches the filter pattern
     *
     * @param string $filename Filename
     * @param string $filter   Filter
     *
     * @return bool
     */
    protected function filenameMatchesFilter(string $filename, string $filter): bool
    {
        foreach (explode('|', $filter) as $pattern) {
            if (fnmatch($pattern, $filename)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Check that the file to process is a valid language file.
     *
     * Throws an exception if unexpected content is detected.
     *
     * @param array  $lines    File contents
     * @param string $filename Filename
     *
     * @return void
     * @throws \Exception
     */
    protected function checkFileFormat(array $lines, string $filename): void
    {
        $lineNum = 0;
        foreach ($lines as $line) {
            ++$lineNum;
            $line = trim($line);
            if ('' === $line || strncmp($line, ';', 1) === 0) {
                continue;
            }
            if (str_starts_with($line, '[') && str_ends_with($line, ']')) {
                throw new \Exception(
                    "Cannot normalize a file with sections; $filename line $lineNum"
                    . " contains: $line"
                );
            }
            if (strstr($line, '=') === false) {
                throw new \Exception(
                    "Equals sign not found in $filename line $lineNum: $line"
                );
            }
        }
    }
}
