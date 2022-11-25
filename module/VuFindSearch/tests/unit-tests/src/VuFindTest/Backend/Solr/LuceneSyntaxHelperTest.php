<?php

/**
 * Unit tests for Lucene syntax helper
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
     * Test capitalizeBooleans functionality.
     *
     * @return void
     */
    public function testCapitalizeBooleans()
    {
        $lh = new LuceneSyntaxHelper();

        // Set up an array of expected inputs and outputs:
        // @codingStandardsIgnoreStart
        $tests = [
            ['this not that', 'this NOT that'],        // capitalize not
            ['this and that', 'this AND that'],        // capitalize and
            ['this or that', 'this OR that'],          // capitalize or
            ['apples and oranges (not that)', 'apples AND oranges (NOT that)'],
            ['"this not that"', '"this not that"'],    // do not capitalize inside quotes
            ['"this and that"', '"this and that"'],    // do not capitalize inside quotes
            ['"this or that"', '"this or that"'],      // do not capitalize inside quotes
            ['"apples and oranges (not that)"', '"apples and oranges (not that)"'],
            ['this AND that', 'this AND that'],        // don't mess up existing caps
            ['and and and', 'and AND and'],
            ['andornot noted andy oranges', 'andornot noted andy oranges'],
            ['(this or that) and (apples not oranges)', '(this OR that) AND (apples NOT oranges)'],
            ['this aNd that', 'this AND that'],        // strange capitalization of AND
            ['this nOt that', 'this NOT that'],        // strange capitalization of NOT
        ];
        // @codingStandardsIgnoreEnd

        // Test all the operations:
        foreach ($tests as $current) {
            $this->assertEquals(
                $lh->capitalizeBooleans($current[0]),
                $current[1]
            );
        }
    }

    /**
     * Test that booleans are detected properly.
     *
     * @return void
     */
    public function testContainsBooleans()
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
    public function testSelectiveBooleanCapitalization()
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
    public function testGetBoolsToCap()
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
    public function testHasCaseSensitiveBooleans()
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
     * Test capitalizeRanges functionality.
     *
     * @return void
     */
    public function testCapitalizeRanges()
    {
        $lh = new LuceneSyntaxHelper();

        // Set up an array of expected inputs and outputs:
        // @codingStandardsIgnoreStart
        $tests = [
            ['"{a to b}"', '"{a to b}"'],              // don't capitalize inside quotes
            ['"[a to b]"', '"[a to b]"'],
            ['[a to b]', '([a TO b] OR [A TO B])'],    // expand alphabetic cases
            ['[a TO b]', '([a TO b] OR [A TO B])'],
            ['[a To b]', '([a TO b] OR [A TO B])'],
            ['[a tO b]', '([a TO b] OR [A TO B])'],
            ['{a to b}', '({a TO b} OR {A TO B})'],
            ['{a TO b}', '({a TO b} OR {A TO B})'],
            ['{a To b}', '({a TO b} OR {A TO B})'],
            ['{a tO b}', '({a TO b} OR {A TO B})'],
            ['[1900 to 1910]', '[1900 TO 1910]'],      // don't expand numeric cases
            ['[1900 TO 1910]', '[1900 TO 1910]'],
            ['{1900 to 1910}', '{1900 TO 1910}'],
            ['{1900 TO 1910}', '{1900 TO 1910}'],
            ['[a      to      b]', '([a TO b] OR [A TO B])'],   // handle extra spaces
            // special case for timestamps:
            ['[1900-01-01t00:00:00z to 1900-12-31t23:59:59z]', '[1900-01-01T00:00:00Z TO 1900-12-31T23:59:59Z]'],
            ['{1900-01-01T00:00:00Z       TO   1900-12-31T23:59:59Z}', '{1900-01-01T00:00:00Z TO 1900-12-31T23:59:59Z}']
        ];
        // @codingStandardsIgnoreEnd

        // Test all the operations:
        foreach ($tests as $current) {
            $this->assertEquals(
                $lh->capitalizeRanges($current[0]),
                $current[1]
            );
        }
    }

    /**
     * Test advanced query detection (default settings)
     *
     * @return void
     */
    public function testContainsAdvancedLuceneSyntaxWithDefaults()
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
    public function testContainsAdvancedLuceneSyntaxWithCaseInsensitivity()
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
    public function testCaseInsensitiveRangeNormalization()
    {
        $lh = new LuceneSyntaxHelper(false, false);
        $this->assertFalse($lh->hasCaseSensitiveRanges());
        $this->assertEquals(
            'a:([b TO c] OR [B TO C])',
            $lh->normalizeSearchString('a:[b to c]')
        );
    }

    /**
     * Test colon normalization
     *
     * @return void
     */
    public function testColonNormalization()
    {
        $lh = new LuceneSyntaxHelper(false, false);
        $tests = [
            'this : that' => 'this  that',
            'this: that' => 'this that',
            'this that:' => 'this that',
            ':this that' => 'this that',
            'this :that' => 'this that',
            'this:that' => 'this:that',
            'this::::::that' => 'this:that',
            '"this : that"' => '"this : that"',
            '::::::::::::::::::::' => '',
         ];
        foreach ($tests as $input => $expected) {
            $this->assertEquals(
                $expected,
                $lh->normalizeSearchString($input)
            );
        }
    }

    /**
     * Test search term extraction
     *
     * @return void
     */
    public function testExtractSearchTerms()
    {
        $lh = new LuceneSyntaxHelper(false, false);
        $tests = [
            'keyword' => 'keyword',
            'two keywords' => 'two keywords',
            'index:keyword' => 'keyword',
            'index:keyword anotherkeyword' => 'keyword anotherkeyword',
            'index:keyword anotherindex:anotherkeyword' => 'keyword anotherkeyword',
            '(index:keyword)' => 'keyword',
            'index:(keyword1 keyword2)' => '(keyword1 keyword2)',
            '{!local params}keyword' => 'keyword',
            'keyword~' => 'keyword',
            'keyword~0.8' => 'keyword',
            'keyword keyword2^20' => 'keyword keyword2',
            '"keyword keyword2 keyword3"~2' => '"keyword keyword2 keyword3"',
            '"kw1 kw2 kw3"~2 kw4^200' => '"kw1 kw2 kw3" kw4',
            '+keyword -keyword2^20' => 'keyword keyword2',
            'index:+keyword index2:-keyword2^20' => 'keyword keyword2',
            'index:[start TO end]' => '[start TO end]',
            'index:{start TO end}' => '{start TO end}',
            'es\\"caped field:test' => 'es\\"caped test'
        ];
        foreach ($tests as $input => $expected) {
            $this->assertEquals(
                $expected,
                $lh->extractSearchTerms($input)
            );
        }
    }

    /**
     * Data provider for testUnquotedNormalization
     *
     * @return array
     */
    public function getTestUnquotedNormalization(): array
    {
        return [
            // Unquoted ones that need changes:
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
     * @dataProvider getTestUnquotedNormalization
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
