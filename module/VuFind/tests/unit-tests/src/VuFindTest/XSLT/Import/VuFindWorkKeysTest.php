<?php

/**
 * XSLT helper tests for VuFindWorkKeys.
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
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */

namespace VuFindTest\XSLT\Import;

use VuFind\XSLT\Import\VuFindWorkKeys;

/**
 * XSLT helper tests for VuFindWorkKeys.
 *
 * @category VuFind
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */
class VuFindWorkKeysTest extends \PHPUnit\Framework\TestCase
{
    /**
     * Test the work keys helper with an include regex.
     *
     * @return void
     */
    public function testGetWorkKeysWithIncludeRegEx()
    {
        $expected = '<?xml version="1.0" encoding="utf-8"?>'
            . "\n<workKey>UT uniformtitle</workKey>\n"
            . "<workKey>AT author1 thenonuniformtitle</workKey>\n"
            . "<workKey>AT author2 thenonuniformtitle</workKey>\n"
            . "<workKey>AT author1 nonuniformtitle</workKey>\n"
            . "<workKey>AT author2 nonuniformtitle</workKey>\n";

        $result = VuFindWorkKeys::getWorkKeys(
            'UNIFORM title.',
            ['The nonUniform Title'],
            ['nonUniform Title'],
            ['AUTHOR 1', 'author 2'],
            '/([0-9A-Za-z]+)/',
            ''
        );
        $this->assertEquals(
            $expected,
            simplexml_import_dom($result)->asXml()
        );
    }

    /**
     * Test the work keys helper with an include regex and trimmed titles === titles.
     *
     * @return void
     */
    public function testGetWorkKeysWithIncludeRegExAndDuplicateTitles()
    {
        $expected = '<?xml version="1.0" encoding="utf-8"?>'
            . "\n<workKey>UT uniformtitle</workKey>\n"
            . "<workKey>AT author1 nonuniformtitle</workKey>\n"
            . "<workKey>AT author2 nonuniformtitle</workKey>\n";

        $result = VuFindWorkKeys::getWorkKeys(
            'UNIFORM title.',
            ['nonUniform Title'],
            ['nonUniform Title'],
            ['AUTHOR 1', 'author 2'],
            '/([0-9A-Za-z]+)/',
            ''
        );
        $this->assertEquals(
            $expected,
            simplexml_import_dom($result)->asXml()
        );
    }

    /**
     * Test the work keys helper with a very long Greek title.
     *
     * @return void
     */
    public function testGetWorkKeysWithLongGreekTitle()
    {
        $expected = '<?xml version="1.0" encoding="utf-8"?>'
            . "\n<workKey>UT αυτοματοποίηση συστημάτων ηλεκτρικής ενέργειας: σχεδιασμός και προσομοίωση συστήματος "
            . 'για τον εντοπισμό-απομόνωση σφαλμάτων γραμμής και την αποκατάσταση της ηλεκτροδότησης σε δίκτυα '
            . "διανομής μέσης τάσης με την εφαρμογή μεθόδων διανεμημένης τεχνητής νοημοσ</workKey>\n"
            . '<workKey>AT μπαξεβάνος, ιωάννης σ. αυτοματοποίηση συστημάτων ηλεκτρικής ενέργειας: σχεδιασμός και '
            . 'προσομοίωση συστήματος για τον εντοπισμό-απομόνωση σφαλμάτων γραμμής και την αποκατάσταση της '
            . 'ηλεκτροδότησης σε δίκτυα διανομής μέσης τάσης με την εφαρμογή μεθόδων διανεμημένης τεχνητής '
            . "νοημοσ</workKey>\n";
        $title = 'Αυτοματοποίηση συστημάτων ηλεκτρικής ενέργειας: σχεδιασμός και προσομοίωση συστήματος για τον '
            . 'εντοπισμό-απομόνωση σφαλμάτων γραμμής και την αποκατάσταση της ηλεκτροδότησης σε δίκτυα διανομής '
            . 'μέσης τάσης με την εφαρμογή μεθόδων διανεμημένης τεχνητής νοημοσύνης';
        $result = VuFindWorkKeys::getWorkKeys(
            $title,
            [$title],
            [$title],
            ['Μπαξεβάνος, Ιωάννης Σ.']
        );
        $this->assertEquals(
            $expected,
            simplexml_import_dom($result)->asXml()
        );
    }

    /**
     * Test the work keys helper with an exclude regex.
     *
     * @return void
     */
    public function testGetWorkKeysWithExcludeRegEx()
    {
        $expected = '<?xml version="1.0" encoding="utf-8"?>'
            . "\n<workKey>UT unformttle</workKey>\n"
            . "<workKey>AT author1 thenonunformttle</workKey>\n"
            . "<workKey>AT author2 thenonunformttle</workKey>\n"
            . "<workKey>AT author1 nonunformttle</workKey>\n"
            . "<workKey>AT author2 nonunformttle</workKey>\n";

        $result = VuFindWorkKeys::getWorkKeys(
            'UNIFORM title',
            ['The nonUniform Title'],
            ['nonUniform Title'],
            ['AUTHOR 1', 'author 2'],
            '',
            '/[i ]/i' // arbitrarily exclude spaces and i's for testing purposes
        );
        $this->assertEquals(
            $expected,
            simplexml_import_dom($result)->asXml()
        );
    }

    /**
     * Test the work keys helper with an ICU transliteration.
     *
     * @return void
     */
    public function testGetWorkKeysWithTransliteration()
    {
        $expected = '<?xml version="1.0" encoding="utf-8"?>'
            . "\n<workKey>UT uniformtitlea</workKey>\n"
            . "<workKey>AT author1 thenonuniformtitle</workKey>\n"
            . "<workKey>AT author2 thenonuniformtitle</workKey>\n"
            . "<workKey>AT author1 nonuniformtitle</workKey>\n"
            . "<workKey>AT author2 nonuniformtitle</workKey>\n";

        $result = VuFindWorkKeys::getWorkKeys(
            'UNIFORM title +Å',
            ['The nonUniform  Titlë'],
            ['nonUniform  Titlë'],
            ['AUTHOR * 1', 'author - 2'],
            '',
            '',
            ':: NFD; :: lower; :: Latin; :: [^[:letter:] [:number:]] Remove; :: NFKC;'
        );
        $this->assertEquals(
            $expected,
            simplexml_import_dom($result)->asXml()
        );
    }

    /**
     * Test the work keys helper with an ICU transliteration.
     *
     * @return void
     */
    public function testGetWorkKeysWithoutAuthors()
    {
        $expected = '<?xml version="1.0" encoding="utf-8"?>'
            . "\n<workKey>UT uniformtitlea</workKey>\n";

        $result = VuFindWorkKeys::getWorkKeys(
            'UNIFORM title +Å',
            ['The nonUniform  Titlë'],
            ['nonUniform  Titlë'],
            [],
            '',
            '',
            ':: NFD; :: lower; :: Latin; :: [^[:letter:] [:number:]] Remove; :: NFKC;'
        );
        $this->assertEquals(
            $expected,
            simplexml_import_dom($result)->asXml()
        );
    }
}
