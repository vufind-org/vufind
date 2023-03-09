<?php
/**
 * Class to consistently format ExtendedIni language files.
 *
 * PHP version 7
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
        $reader = new Translator\Loader\ExtendedIniReader();

        // Reading and rewriting the file by itself will eliminate all comments;
        // we should extract comments separately and then recombine the parts.
        $fileArray = file($file);

        // Strip off UTF-8 BOM if necessary.
        $bom = html_entity_decode('&#xFEFF;', ENT_NOQUOTES, 'UTF-8');
        $fileArray[0] = str_replace($bom, '', $fileArray[0]);

        // Safeguard to avoid messing up wrong ini files:
        $this->checkFileFormat($fileArray, $file);

        $comments = $this->extractComments($fileArray);
        $strings = $this->formatAsString($reader->getTextDomain($fileArray, false));
        return $comments . $strings;
    }

    /**
     * Normalize a TextDomain or array to a string that can be written to file.
     *
     * @param array|TextDomain $input Language values to format.
     *
     * @return string
     */
    public function formatAsString($input)
    {
        // This is easier to work with as an associative array:
        $input = (array)$input;

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
            $output .= "$key = \"$value\"\n";
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
            if (substr(trim($line), 0, 1) == ';') {
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
            if (substr($line, 0, 1) === '[' && substr($line, -1) === ']') {
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
