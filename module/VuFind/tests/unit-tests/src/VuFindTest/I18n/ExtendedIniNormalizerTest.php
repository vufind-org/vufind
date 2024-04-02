<?php

/**
 * ExtendedIniNormalizer Test Class
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
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */

namespace VuFindTest\I18n;

use VuFind\I18n\ExtendedIniNormalizer;
use VuFind\I18n\Translator\Loader\ExtendedIniReader;

/**
 * ExtendedIniNormalizer Test Class
 *
 * @category VuFind
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Chris Hallberg <challber@villanova.edu>
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
    public function testLanguageFileIntegrity(): void
    {
        $normalizer = new ExtendedIniNormalizer();
        $langDir = realpath(__DIR__ . '/../../../../../../../languages');
        $this->checkDirectory($normalizer, $langDir);
    }

    /**
     * Test bypassing of non-language-files.
     *
     * @return void
     */
    public function testLanguageFileCheck(): void
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
     * Test bypassing of files with sections.
     *
     * @return void
     */
    public function testLanguageFileSectionCheck(): void
    {
        $file = $this->getFixtureDir() . 'language/base/non-language-section.ini';
        $normalizer = new ExtendedIniNormalizer();

        $this->expectExceptionMessage(
            "Cannot normalize a file with sections; $file line 1 contains: [Main]"
        );

        $normalizer->normalizeFileToString($file);
    }

    /**
     * Data provider for testEscaping
     *
     * @return array
     */
    public static function escapingProvider(): array
    {
        return [
            ['foo = "This is a backslash: \\\\"'],
            ["foo = 'Single \\'quote\\' vs. double \"quote\"'"],
        ];
    }

    /**
     * Test escaping.
     *
     * @param string $value Value to test
     *
     * @dataProvider escapingProvider
     *
     * @return void
     */
    public function testEscaping(string $value): void
    {
        $reader = new ExtendedIniReader();
        $normalizer = new ExtendedIniNormalizer();

        $this->assertEquals(
            "$value\n",
            $normalizer->formatAsString($reader->getTextDomain([$value]))
        );
    }

    /**
     * Test key normalization.
     *
     * @return void
     */
    public function testKeyNormalization(): void
    {
        $reader = new ExtendedIniReader();
        $normalizer = new ExtendedIniNormalizer();
        $this->assertEquals(
            "_21_21_21_21 = \"bar\"\n_28_29_3F_21 = \"foo\"\n",
            $normalizer->formatAsString($reader->getTextDomain(['()?! = foo', '!!!! = bar']))
        );
    }

    /**
     * Test language integrity inside a directory.
     *
     * @param ExtendedIniNormalizer $normalizer Normalizer to test
     * @param string                $dir        Directory name.
     *
     * @return void
     */
    protected function checkDirectory(ExtendedIniNormalizer $normalizer, string $dir): void
    {
        $handle = opendir($dir);
        while ($file = readdir($handle)) {
            $full = $dir . '/' . $file;
            if ($file != '.' && $file != '..' && is_dir($full)) {
                $this->checkDirectory($normalizer, $full);
            } elseif (str_ends_with($file, '.ini')) {
                $this->assertEquals(
                    $normalizer->normalizeFileToString($full),
                    file_get_contents($full),
                    $file
                );
            }
        }
        closedir($handle);
    }
}
