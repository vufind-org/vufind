<?php
/**
 * ExtendedIniNormalizer Test Class
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
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:unit_tests Wiki
 */
namespace VuFindTest\I18n;
use VuFind\I18n\ExtendedIniNormalizer;

/**
 * ExtendedIniNormalizer Test Class
 *
 * @category VuFind2
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Chris Hallberg <challber@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:unit_tests Wiki
 */
class ExtendedIniNormalizerTest extends \VuFindTest\Unit\TestCase
{
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
        $langDir = realpath(__DIR__ . '/../../../../../../../languages');
        $this->checkDirectory($normalizer, $langDir);
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
            } else if (substr($file, -4) == '.ini') {
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