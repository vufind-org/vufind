<?php

/**
 * Unit tests for Lucene syntax helper
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
 * @package  Search
 * @author   David Maus <maus@hab.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org
 */

namespace VuFindTest\Backend\Solr;

use VuFindSearch\Backend\Solr\LuceneSyntaxHelper;

/**
 * Unit tests for Lucene syntax helper
 *
 * @category VuFind2
 * @package  Search
 * @author   David Maus <maus@hab.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org
 */
class LuceneSyntaxHelperTest extends \VuFindTest\Unit\TestCase
{
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
        $tests = array(
            array('this not that', 'this NOT that'),        // capitalize not
            array('this and that', 'this AND that'),        // capitalize and
            array('this or that', 'this OR that'),          // capitalize or
            array('apples and oranges (not that)', 'apples AND oranges (NOT that)'),
            array('"this not that"', '"this not that"'),    // do not capitalize inside quotes
            array('"this and that"', '"this and that"'),    // do not capitalize inside quotes
            array('"this or that"', '"this or that"'),      // do not capitalize inside quotes
            array('"apples and oranges (not that)"', '"apples and oranges (not that)"'),
            array('this AND that', 'this AND that'),        // don't mess up existing caps
            array('and and and', 'and AND and'),
            array('andornot noted andy oranges', 'andornot noted andy oranges'),
            array('(this or that) and (apples not oranges)', '(this OR that) AND (apples NOT oranges)'),
            array('this aNd that', 'this AND that'),        // strange capitalization of AND
            array('this nOt that', 'this NOT that'),        // strange capitalization of NOT
        );
        // @codingStandardsIgnoreEnd

        // Test all the operations:
        foreach ($tests as $current) {
            $this->assertEquals(
                $lh->capitalizeBooleans($current[0]), $current[1]
            );
        }
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
            $lh->capitalizeBooleans($in, array('AND', 'OR', 'NOT'))
        );
        $this->assertEquals(
            'this OR that and the other NOT everything else (NOT me)',
            $lh->capitalizeBooleans($in, array('OR', 'NOT'))
        );
        $this->assertEquals(
            'this or that and the other NOT everything else (NOT me)',
            $lh->capitalizeBooleans($in, array('NOT'))
        );
        $this->assertEquals(
            'this or that AND the other not everything else (not me)',
            $lh->capitalizeBooleans($in, array('AND'))
        );
        $this->assertEquals(
            'this OR that and the other not everything else (not me)',
            $lh->capitalizeBooleans($in, array('OR'))
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
            array(), $this->callMethod($lh, 'getBoolsToCap')
        );

        // Test "capitalize all":
        $lh = new LuceneSyntaxHelper(false);
        $this->assertEquals(
            array('AND', 'OR', 'NOT'), $this->callMethod($lh, 'getBoolsToCap')
        );

        // Test selective capitalization:
        $lh = new LuceneSyntaxHelper(' not ');
        $this->assertEquals(
            array('AND', 'OR'), $this->callMethod($lh, 'getBoolsToCap')
        );
        $lh = new LuceneSyntaxHelper('NOT');
        $this->assertEquals(
            array('AND', 'OR'), $this->callMethod($lh, 'getBoolsToCap')
        );
        $lh = new LuceneSyntaxHelper('AND,OR');
        $this->assertEquals(
            array('NOT'), $this->callMethod($lh, 'getBoolsToCap')
        );
        $lh = new LuceneSyntaxHelper('and, or');
        $this->assertEquals(
            array('NOT'), $this->callMethod($lh, 'getBoolsToCap')
        );
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
        $tests = array(
            array('"{a to b}"', '"{a to b}"'),              // don't capitalize inside quotes
            array('"[a to b]"', '"[a to b]"'),
            array('[a to b]', '([a TO b] OR [A TO B])'),    // expand alphabetic cases
            array('[a TO b]', '([a TO b] OR [A TO B])'),
            array('[a To b]', '([a TO b] OR [A TO B])'),
            array('[a tO b]', '([a TO b] OR [A TO B])'),
            array('{a to b}', '({a TO b} OR {A TO B})'),
            array('{a TO b}', '({a TO b} OR {A TO B})'),
            array('{a To b}', '({a TO b} OR {A TO B})'),
            array('{a tO b}', '({a TO b} OR {A TO B})'),
            array('[1900 to 1910]', '[1900 TO 1910]'),      // don't expand numeric cases
            array('[1900 TO 1910]', '[1900 TO 1910]'),
            array('{1900 to 1910}', '{1900 TO 1910}'),
            array('{1900 TO 1910}', '{1900 TO 1910}'),
            array('[a      to      b]', '([a TO b] OR [A TO B])'),   // handle extra spaces
            // special case for timestamps:
            array('[1900-01-01t00:00:00z to 1900-12-31t23:59:59z]', '[1900-01-01T00:00:00Z TO 1900-12-31T23:59:59Z]'),
            array('{1900-01-01T00:00:00Z       TO   1900-12-31T23:59:59Z}', '{1900-01-01T00:00:00Z TO 1900-12-31T23:59:59Z}')
        );
        // @codingStandardsIgnoreEnd

        // Test all the operations:
        foreach ($tests as $current) {
            $this->assertEquals(
                $lh->capitalizeRanges($current[0]), $current[1]
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
}