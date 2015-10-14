<?php
/**
 * Class to consistently format ExtendedIni language files.
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
 * @package  Translator
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org   Main Site
 */
namespace VuFind\I18n;
use Zend\I18n\Translator\TextDomain;

/**
 * Class to consistently format ExtendedIni language files.
 *
 * @category VuFind2
 * @package  Translator
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org   Main Site
 */
class ExtendedIniNormalizer
{
    /**
     * Normalize a directory on disk.
     *
     * @param string $dir Directory to normalize.
     *
     * @return void
     */
    public function normalizeDirectory($dir)
    {
        $dir = rtrim($dir, '/');
        $handle = opendir($dir);
        while ($file = readdir($handle)) {
            $full = $dir . '/' . $file;
            if ($file != '.' && $file != '..' && is_dir($full)) {
                $this->normalizeDirectory($full);
            } else if (substr($file, -4) == '.ini') {
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
}