<?php

/**
 * Unit tests for Lucene syntax helper
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
 * @package  Search
 * @author   David Maus <maus@hab.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org
 */

namespace VuFindTest\Backend\Solr;

use VuFindSearch\Backend\Solr\LuceneSyntaxHelper;

/**
 * Unit tests for Lucene syntax helper
 *
 * @category VuFind
 * @package  Search
 * @author   David Maus <maus@hab.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org
 */
class LuceneSyntaxHelperTest extends \PHPUnit\Framework\TestCase
{
    use \VuFindTest\Feature\ReflectionTrait;

    /**
     * Data provider for testCapitalizeBooleans
     *
     * @return array
     */
    public static function capitalizeBooleansProvider(): array
    {
        return [
            ['this not that', 'this NOT that'],        // capitalize not
            ['this nOt that', 'this NOT that'],        // strange capitalization

            ['this and that', 'this AND that'],        // capitalize and
            ['and and and', 'and AND and'],
            ['this aNd that', 'this AND that'],        // strange capitalization

            ['this or that', 'this OR that'],          // capitalize or

            // handle multiple operators:
            ['apples and oranges (not that)', 'apples AND oranges (NOT that)'],
            [
                '(this or that) and (apples not oranges)',
                '(this OR that) AND (apples NOT oranges)',
            ],

            // do not capitalize inside quotes:
            ['"this not that"', '"this not that"'],
            ['"this and that"', '"this and that"'],
            ['"this or that"', '"this or that"'],
            ['"apples and oranges (not that)"', '"apples and oranges (not that)"'],

            ['this AND that', 'this AND that'], // don't mess up existing caps

            // handle words resembling operators:
            ['andornot noted andy oranges', 'andornot noted andy oranges'],
        ];
    }

    /**
     * Test capitalizeBooleans functionality.
     *
     * @param $input    Input to test
     * @param $expected Expected output
     *
     * @return void
     *
     * @dataProvider capitalizeBooleansProvider
     */
    public function testCapitalizeBooleans(string $input, string $expected): void
    {
        $lh = new LuceneSyntaxHelper();
        $this->assertEquals($expected, $lh->capitalizeBooleans($input));
    }

    /**
     * Test that booleans are detected properly.
     *
     * @return void
     */
    public function testContainsBooleans(): void
    {
        $lh = new LuceneSyntaxHelper();
        $this->assertTrue($lh->containsBooleans('this AND that'));
        $this->assertTrue($lh->containsBooleans('this OR that'));
        $this->assertTrue($lh->containsBooleans('this NOT that'));
        $this->assertTrue(
            $lh->containsBooleans('"my OR phrase" NOT "your AND phrase"')
        );
        $this->assertFalse($lh->containsBooleans('"this AND that"'));
        $this->assertFalse(
            $lh->containsBooleans('something that has no operators in it')
        );
        $this->assertFalse($lh->containsBooleans('this ANDD that'));
        $this->assertFalse($lh->containsBooleans('this NOR that'));
    }

    /**
     * Test the selective capitalization functionality of capitalizeBooleans.
     *
     * @return void
     */
    public function testSelectiveBooleanCapitalization(): void
    {
        $lh = new LuceneSyntaxHelper();
        $in = 'this or that and the other not everything else (not me)';
        $this->assertEquals(
            'this OR that AND the other NOT everything else (NOT me)',
            $lh->capitalizeBooleans($in, ['AND', 'OR', 'NOT'])
        );
        $this->assertEquals(
            'this OR that and the other NOT everything else (NOT me)',
            $lh->capitalizeBooleans($in, ['OR', 'NOT'])
        );
        $this->assertEquals(
            'this or that and the other NOT everything else (NOT me)',
            $lh->capitalizeBooleans($in, ['NOT'])
        );
        $this->assertEquals(
            'this or that AND the other not everything else (not me)',
            $lh->capitalizeBooleans($in, ['AND'])
        );
        $this->assertEquals(
            'this OR that and the other not everything else (not me)',
            $lh->capitalizeBooleans($in, ['OR'])
        );
    }

    /**
     * Test getBoolsToCap().
     *
     * @return void
     */
    public function testGetBoolsToCap(): void
    {
        $lh = new LuceneSyntaxHelper();

        // Default behavior: do not capitalize:
        $this->assertEquals(
            [],
            $this->callMethod($lh, 'getBoolsToCap')
        );

        // Test "capitalize all":
        $lh = new LuceneSyntaxHelper(false);
        $this->assertEquals(
            ['AND', 'OR', 'NOT'],
            $this->callMethod($lh, 'getBoolsToCap')
        );

        // Test selective capitalization:
        $lh = new LuceneSyntaxHelper(' not ');
        $this->assertEquals(
            ['AND', 'OR'],
            $this->callMethod($lh, 'getBoolsToCap')
        );
        $lh = new LuceneSyntaxHelper('NOT');
        $this->assertEquals(
            ['AND', 'OR'],
            $this->callMethod($lh, 'getBoolsToCap')
        );
        $lh = new LuceneSyntaxHelper('AND,OR');
        $this->assertEquals(
            ['NOT'],
            $this->callMethod($lh, 'getBoolsToCap')
        );
        $lh = new LuceneSyntaxHelper('and, or');
        $this->assertEquals(
            ['NOT'],
            $this->callMethod($lh, 'getBoolsToCap')
        );
    }

    /**
     * Test hasCaseSensitiveBooleans().
     *
     * @return void
     */
    public function testHasCaseSensitiveBooleans(): void
    {
        $lh = new LuceneSyntaxHelper();

        // Default behavior: do not capitalize:
        $this->assertTrue($lh->hasCaseSensitiveBooleans());

        // Test "capitalize all":
        $lh = new LuceneSyntaxHelper(false);
        $this->assertFalse($lh->hasCaseSensitiveBooleans());

        // Test selective capitalization:
        $lh = new LuceneSyntaxHelper(' not ');
        $this->assertTrue($lh->hasCaseSensitiveBooleans());
        $lh = new LuceneSyntaxHelper('NOT');
        $this->assertTrue($lh->hasCaseSensitiveBooleans());
        $lh = new LuceneSyntaxHelper('AND,OR');
        $this->assertTrue($lh->hasCaseSensitiveBooleans());
        $lh = new LuceneSyntaxHelper('and, or');
        $this->assertTrue($lh->hasCaseSensitiveBooleans());
    }

    /**
     * Data provider for testCapitalizeRanges
     *
     * @return array
     */
    public static function capitalizeRangesProvider(): array
    {
        return [
            // don't capitalize inside quotes
            ['"{a to b}"', '"{a to b}"'],
            ['"[a to b]"', '"[a to b]"'],

            // expand alphabetic cases
            ['[a to b]', '([a TO b] OR [A TO B])'],
            ['[a TO b]', '([a TO b] OR [A TO B])'],
            ['[a To b]', '([a TO b] OR [A TO B])'],
            ['[a tO b]', '([a TO b] OR [A TO B])'],
            ['{a to b}', '({a TO b} OR {A TO B})'],
            ['{a TO b}', '({a TO b} OR {A TO B})'],
            ['{a To b}', '({a TO b} OR {A TO B})'],
            ['{a tO b}', '({a TO b} OR {A TO B})'],

            // don't expand numeric cases
            ['[1900 to 1910]', '[1900 TO 1910]'],
            ['[1900 TO 1910]', '[1900 TO 1910]'],
            ['{1900 to 1910}', '{1900 TO 1910}'],
            ['{1900 TO 1910}', '{1900 TO 1910}'],

            // handle extra spaces
            ['[a      to      b]', '([a TO b] OR [A TO B])'],

            // special case for timestamps:
            [
                '[1900-01-01t00:00:00z to 1900-12-31t23:59:59z]',
                '[1900-01-01T00:00:00Z TO 1900-12-31T23:59:59Z]',
            ],
            [
                '{1900-01-01T00:00:00Z       TO   1900-12-31T23:59:59Z}',
                '{1900-01-01T00:00:00Z TO 1900-12-31T23:59:59Z}',
            ],
        ];
    }

    /**
     * Test capitalizeRanges functionality.
     *
     * @param $input    Input to test
     * @param $expected Expected output
     *
     * @return void
     *
     * @dataProvider capitalizeRangesProvider
     */
    public function testCapitalizeRanges(string $input, string $expected): void
    {
        $lh = new LuceneSyntaxHelper();
        $this->assertEquals($expected, $lh->capitalizeRanges($input));
    }

    /**
     * Test advanced query detection (default settings)
     *
     * @return void
     */
    public function testContainsAdvancedLuceneSyntaxWithDefaults(): void
    {
        $lh = new LuceneSyntaxHelper();

        // Fielded search:
        $this->assertTrue($lh->containsAdvancedLuceneSyntax('*:*'));
        $this->assertTrue($lh->containsAdvancedLuceneSyntax('this:that'));

        // Parens:
        $this->assertTrue($lh->containsAdvancedLuceneSyntax('(this) (that)'));
        $this->assertFalse($lh->containsAdvancedLuceneSyntax('\(this\) \(that\)'));
        $this->assertTrue($lh->containsAdvancedLuceneSyntax('(this) (that)'));

        // Wildcards:
        $this->assertTrue($lh->containsAdvancedLuceneSyntax('this*'));
        $this->assertTrue($lh->containsAdvancedLuceneSyntax('th?s'));

        // Proximity:
        $this->assertTrue($lh->containsAdvancedLuceneSyntax('this~4'));

        // Boosts:
        $this->assertTrue($lh->containsAdvancedLuceneSyntax('this^4'));

        // Plain search:
        $this->assertFalse($lh->containsAdvancedLuceneSyntax('this that the other'));

        // Default: case sensitive ranges:
        $this->assertTrue($lh->containsAdvancedLuceneSyntax('[this TO that]'));
        $this->assertFalse($lh->containsAdvancedLuceneSyntax('[this to that]'));

        // Default: case sensitive booleans:
        $this->assertTrue($lh->containsAdvancedLuceneSyntax('this AND that'));
        $this->assertTrue($lh->containsAdvancedLuceneSyntax('this OR that'));
        $this->assertTrue($lh->containsAdvancedLuceneSyntax('this NOT that'));
        $this->assertFalse($lh->containsAdvancedLuceneSyntax('this and that'));
        $this->assertFalse($lh->containsAdvancedLuceneSyntax('this or that'));
        $this->assertFalse($lh->containsAdvancedLuceneSyntax('this not that'));
    }

    /**
     * Test advanced query detection (with case insensitivity)
     *
     * @return void
     */
    public function testContainsAdvancedLuceneSyntaxWithCaseInsensitivity(): void
    {
        $lh = new LuceneSyntaxHelper(false, false);

        // Case insensitive ranges:
        $this->assertTrue($lh->containsAdvancedLuceneSyntax('[this TO that]'));
        $this->assertTrue($lh->containsAdvancedLuceneSyntax('[this to that]'));

        // Case insensitive booleans:
        $this->assertTrue($lh->containsAdvancedLuceneSyntax('this AND that'));
        $this->assertTrue($lh->containsAdvancedLuceneSyntax('this OR that'));
        $this->assertTrue($lh->containsAdvancedLuceneSyntax('this NOT that'));
        $this->assertTrue($lh->containsAdvancedLuceneSyntax('this and that'));
        $this->assertTrue($lh->containsAdvancedLuceneSyntax('this or that'));
        $this->assertTrue($lh->containsAdvancedLuceneSyntax('this not that'));
    }

    /**
     * Test case insensitive range normalization
     *
     * @return void
     */
    public function testCaseInsensitiveRangeNormalization(): void
    {
        $lh = new LuceneSyntaxHelper(false, false);
        $this->assertFalse($lh->hasCaseSensitiveRanges());
        $this->assertEquals(
            'a:([b TO c] OR [B TO C])',
            $lh->normalizeSearchString('a:[b to c]')
        );
    }

    /**
     * Data provider for testColonNormalization
     *
     * @return array
     */
    public static function colonNormalizationProvider(): array
    {
        return [
            ['this : that', 'this  that'],
            ['this: that', 'this that'],
            ['this that:', 'this that'],
            [':this that', 'this that'],
            ['this :that', 'this that'],
            ['this:that', 'this:that'],
            ['this::::::that', 'this:that'],
            ['"this : that"', '"this : that"'],
            ['::::::::::::::::::::', ''],
        ];
    }

    /**
     * Test colon normalization
     *
     * @param $input    Input to test
     * @param $expected Expected output
     *
     * @return void
     *
     * @dataProvider colonNormalizationProvider
     */
    public function testColonNormalization(string $input, string $expected): void
    {
        $lh = new LuceneSyntaxHelper(false, false);
        $this->assertEquals(
            $expected,
            $lh->normalizeSearchString($input)
        );
    }

    /**
     * Data provider for testExtractSearchTerms.
     *
     * @return array
     */
    public static function extractSearchTermsProvider(): array
    {
        return [
            ['keyword', 'keyword'],
            ['two keywords', 'two keywords'],
            ['index:keyword', 'keyword'],
            ['index:keyword anotherkeyword', 'keyword anotherkeyword'],
            ['index:keyword anotherindex:anotherkeyword', 'keyword anotherkeyword'],
            ['(index:keyword)', 'keyword'],
            ['index:(keyword1 keyword2)', '(keyword1 keyword2)'],
            ['{!local params}keyword', 'keyword'],
            ['keyword~', 'keyword'],
            ['keyword~0.8', 'keyword'],
            ['keyword keyword2^20', 'keyword keyword2'],
            ['"keyword keyword2 keyword3"~2', '"keyword keyword2 keyword3"'],
            ['"kw1 kw2 kw3"~2 kw4^200', '"kw1 kw2 kw3" kw4'],
            ['+keyword -keyword2^20', 'keyword keyword2'],
            ['index:+keyword index2:-keyword2^20', 'keyword keyword2'],
            ['index:[start TO end]', '[start TO end]'],
            ['index:{start TO end}', '{start TO end}'],
            ['es\\"caped field:test', 'es\\"caped test'],
            ['field:"quoted:contents"', '"quoted:contents"'],
        ];
    }

    /**
     * Test search term extraction
     *
     * @param $input    Input to test
     * @param $expected Expected output
     *
     * @return void
     *
     * @dataProvider extractSearchTermsProvider
     */
    public function testExtractSearchTerms(string $input, string $expected): void
    {
        $lh = new LuceneSyntaxHelper(false, false);
        $this->assertEquals($expected, $lh->extractSearchTerms($input));
    }

    /**
     * Data provider for testUnquotedNormalization
     *
     * @return array
     */
    public static function unquotedNormalizationProvider(): array
    {
        return [
            // Unquoted ones that may need changes:
            ['this - that', 'this that'],
            ['this -- that', 'this that'],
            ['- this that', 'this that'],
            ['this that -', 'this that'],
            ['-- this -- that --', 'this that'],
            ['this -that', 'this -that'],
            ['this + that', 'this that'],
            ['+ this ++ that +', 'this that'],
            ['this +that', 'this +that'],
            ['this / that', 'this "/" that'],
            ['this/that', 'this/that'],
            ['/this', 'this'],
            ['/this that', 'this that'],
            ['this/', 'this'],
            ['this that/', 'this that'],
            ['/this that/', 'this that'],
            ['(this that', 'this that'],
            ['((this) that', 'this that'],
            ['this that)', 'this that'],
            ['this (that))', 'this that'],
            ['((( this that', 'this that'],
            ['\\((( this that', '\\( this that'],
            ['\\\\\\((( this that', '\\\\\\( this that'],
            ['\\"((( this that\\"', '\\" this that\\"'],
            ['&', '&'],
            ['&&', ''],
            ['|', '|'],
            ['||', ''],
            ['AND', 'and'],
            ['OR', 'or'],
            ['NOT', 'not'],
            ['*:*', ''],
            [' AND OR', ''],
            ['AND OR NOT +-"&&||', ''],
            ['AND OR NOT +-"&&|| &', 'AND OR NOT +-"&&|| &'],

            // Quoted ones that must not be affected:
            ['"this - that"', '"this - that"'],
            ['"- this that"', '"- this that"'],
            ['"this that -"', '"this that -"'],
            ['"this + that"', '"this + that"'],
            ['"+ this ++ that +"', '"+ this ++ that +"'],
            ['"this / that"', '"this / that"'],
            ['"(this that"', '"(this that"'],
            ['"(this (that"', '"(this (that"'],
            ['"this) that"', '"this) that"'],
            ['"((( this that"', '"((( this that"'],
            ['"((("', '"((("'],
            ['"\\((("', '"\\((("'],
            ['"\\\\((("', '"\\\\((("'],
        ];
    }

    /**
     * Test normalization of unquoted special characters
     *
     * @param string $input    Input string
     * @param string $expected Expected result
     *
     * @dataProvider unquotedNormalizationProvider
     *
     * @return void
     */
    public function testUnquotedNormalization(string $input, string $expected)
    {
        $lh = new LuceneSyntaxHelper(false, false);
        $this->assertEquals(
            $expected,
            $lh->normalizeSearchString($input)
        );
    }
}
