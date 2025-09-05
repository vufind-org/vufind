<?php

/**
 * ExtendedIniNormalizer Test Class
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2010.
 * Copyright (C) The National Library of Finland 2023.
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
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Chris Hallberg <challber@villanova.edu>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */

namespace FinnaTest\I18n;

use VuFind\I18n\ExtendedIniNormalizer;

/**
 * ExtendedIniNormalizer Test Class
 *
 * @category VuFind
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Chris Hallberg <challber@villanova.edu>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */
class ExtendedIniNormalizerTest extends \PHPUnit\Framework\TestCase
{
    use \VuFindTest\Feature\FixtureTrait;

    /**
     * Test consistent normalization of translation files on disk. This tests not
     * only the functionality of ExtendedIniNormalizer but also the integrity of
     * the language files themselves.
     *
     * @return void
     */
    public function testLanguageFileIntegrity()
    {
        $normalizer = new ExtendedIniNormalizer();
        $langDir = realpath(__DIR__ . '/../../../../../../../local/languages');
        $this->checkDirectory($normalizer, $langDir);
    }

    /**
     * Test bypassing of non-language-files.
     *
     * @return void
     */
    public function testLanguageFileCheck()
    {
        $file = $this->getFixtureDir() . 'language/base/non-language.ini';
        $normalizer = new ExtendedIniNormalizer();

        $this->expectExceptionMessage(
            "Equals sign not found in $file line 2: this is not a proper language"
            . ' file'
        );

        $normalizer->normalizeFileToString($file);
    }

    /**
     * Test language integrity inside a directory.
     *
     * @param ExtendedIniNormalizer $normalizer Normalizer to test
     * @param string                $dir        Directory name.
     *
     * @return void
     */
    protected function checkDirectory($normalizer, $dir)
    {
        $handle = opendir($dir);
        while ($file = readdir($handle)) {
            $full = $dir . '/' . $file;
            if ($file != '.' && $file != '..' && is_dir($full)) {
                $this->checkDirectory($normalizer, $full);
            } elseif (substr($file, -4) == '.ini' && substr($file, -16) !== '-datasources.ini') {
                $this->checkFile($normalizer, $full);
            }
        }
        closedir($handle);
    }

    /**
     * Relaxed comparison of language file content that doesn't care about empty lines, commments or alphabetical order.
     *
     * @param ExtendedIniNormalizer $normalizer Normalizer to test
     * @param string                $filename   Language file
     *
     * @return void
     */
    protected function checkFile(ExtendedIniNormalizer $normalizer, string $filename): void
    {
        $expected = explode("\n", $normalizer->normalizeFileToString($filename));
        $actual = file($filename, FILE_IGNORE_NEW_LINES);
        // Ignore trailing whitespace:
        $actual = array_map('rtrim', $actual);

        foreach ($expected as $current) {
            if ('' === $current || str_starts_with($current, ';')) {
                continue;
            }
            $key = array_search($current, $actual, true);
            $this->assertNotFalse($key, "Translation not found in correct form in $filename: $current");
            unset($actual[$key]);
            // Allow duplicates in datasources translations:
            if (str_ends_with($filename, '-datasources.ini')) {
                while ($key = array_search($current, $actual, true)) {
                    unset($actual[$key]);
                }
            }
        }
        // Check for any remaining lines other than whitespace or comment:
        foreach ($actual as $current) {
            [$current] = explode(';', $current);
            $current = trim($current);
            $this->assertEquals('', $current, "Unexpected line in translation file $filename");
        }
    }
}
