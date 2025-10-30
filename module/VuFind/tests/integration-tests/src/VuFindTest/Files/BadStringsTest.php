<?php

/**
 * Check for outdated strings in code files.
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2025.
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
 * along with this program; if not, see
 * <https://www.gnu.org/licenses/>.
 *
 * @category VuFind
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 *
 * @VuFind.SkipBadStringCheck
 */

namespace VuFindTest\Files;

use PHPUnit\Framework\ExpectationFailedException;

/**
 * Check for outdated strings in code files.
 *
 * @category VuFind
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */
class BadStringsTest extends \PHPUnit\Framework\TestCase
{
    /**
     * List of bad strings to check for. Will be matched as plain text unless they
     * start with a '/' character, in which case they will be treated as regex.
     *
     * @var array
     */
    protected array $badStrings = [
        'outdated license address' => '51 Franklin',
        'outdated PHP header comment' => '/\\* (PHP version [^8])\s*\n/',
    ];

    /**
     * Test for bad strings in our source files.
     *
     * @return void
     * @throws ExpectationFailedException
     */
    public function testForBadStrings(): void
    {
        $filesToCheck = $this->getAllFiles(APPLICATION_PATH . '/module', '*.php');
        $failures =  [];
        foreach ($filesToCheck as $fileToCheck) {
            $fileContents = file_get_contents($fileToCheck);
            // Use annotation to skip files:
            if (str_contains($fileContents, '* @VuFind.SkipBadStringTest')) {
                continue;
            }
            $reasons = [];
            foreach ($this->badStrings as $reason => $string) {
                $matches = null;
                $problem = str_starts_with($string, '/')
                    ? preg_match($string, $fileContents, $matches)
                    : str_contains($fileContents, $string);
                if ($problem) {
                    $reasons[] = "$reason: " . trim($matches[1] ?? $matches[0] ?? $string);
                }
            }
            if ($reasons) {
                $reasonMsg = implode('; ', $reasons);
                $failures[] = str_replace(APPLICATION_PATH . '/', '', $fileToCheck) . " ($reasonMsg)";
            }
        }
        // We could use a variety of assertions here, but the goal is to make actionable information
        // conveniently available. By imploding the list of bad files (with some extra spaces to separate
        // the diff markers from the filenames) we make it easier to read (and in some setups, click on)
        // the list of files that need attention.
        $this->assertEquals('', implode(PHP_EOL . ' ', $failures), 'Found bad strings in files.');
    }

    /**
     * Recursively find all files matching a pattern inside a directory.
     *
     * @param string $path    Path to search
     * @param string $pattern Search pattern
     *
     * @return string[]
     */
    protected function getAllFiles(string $path, string $pattern): array
    {
        $filesMatchingPattern = glob("$path/$pattern");
        $dirs = glob("$path/*", GLOB_ONLYDIR);
        $files = array_diff($filesMatchingPattern, $dirs);
        foreach ($dirs as $dir) {
            $files = array_merge($files, $this->getAllFiles($dir, $pattern));
        }
        return $files;
    }
}
