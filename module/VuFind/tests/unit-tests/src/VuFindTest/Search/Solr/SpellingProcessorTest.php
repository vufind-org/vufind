<?php

/**
 * Unit tests for spelling processor.
 *
 * PHP version 5
 *
 * Copyright (C) Villanova University 2013.
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
 * @link     http://vufind.org   Main Site
 */

namespace VuFindTest\Search\Solr;

use VuFind\Search\Solr\SpellingProcessor;
use VuFindTest\Unit\TestCase;
use Zend\Config\Config;

/**
 * Unit tests for spelling processor.
 *
 * @category VuFind2
 * @package  Search
 * @author   David Maus <maus@hab.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org   Main Site
 */
class SpellingProcessorTest extends TestCase
{
    /**
     * Test defaults.
     *
     * @return void
     */
    public function testDefaultConfigs()
    {
        $sp = new SpellingProcessor();
        $this->assertEquals(true, $sp->shouldSkipNumericSpelling());
        $this->assertEquals(3, $sp->getSpellingLimit());
    }

    /**
     * Test non-default configs.
     *
     * @return void
     */
    public function testNonDefaultConfigs()
    {
        $config = new Config(array('limit' => 5, 'skip_numeric' => false));
        $sp = new SpellingProcessor($config);
        $this->assertEquals(false, $sp->shouldSkipNumericSpelling());
        $this->assertEquals(5, $sp->getSpellingLimit());
    }

    /**
     * Test that spelling tokenization works correctly.
     *
     * @return void
     */
    public function testSpellingTokenization()
    {
        $sp = new SpellingProcessor();
        $this->assertEquals(array('single'), $sp->tokenize('single'));
        $this->assertEquals(array('two', 'terms'), $sp->tokenize('two terms'));
        $this->assertEquals(array('two', 'terms'), $sp->tokenize('two    terms'));
        $this->assertEquals(array('apples', 'oranges'), $sp->tokenize('apples OR oranges'));
        $this->assertEquals(array('"word"'), $sp->tokenize('"word"'));
        $this->assertEquals(array('"word"', 'second'), $sp->tokenize('"word" second'));
        $this->assertEquals(array(), $sp->tokenize(''));
        $this->assertEquals(array('0', 'is', 'zero'), $sp->tokenize('0 is zero'));
        $this->assertEquals(array("'twas", 'successful'), $sp->tokenize("'twas successful"));
        $this->assertEquals(array('word'), $sp->tokenize('(word)'));
        $this->assertEquals(array('word', 'second'), $sp->tokenize('(word) second'));
        $this->assertEquals(array('apples', 'oranges', 'pears'), $sp->tokenize('(apples OR oranges) AND pears'));
        $this->assertEquals(array('two', 'terms'), $sp->tokenize("two\tterms"));
        $this->assertEquals(
            array('"two words"', 'single', '"three word phrase"', 'single'),
            $sp->tokenize('((("two words" OR single) NOT "three word phrase") AND single)')
        );
        $this->assertEquals(array('"unfinished phrase'), $sp->tokenize('"unfinished phrase'));
        $this->assertEquals(array('"'), $sp->tokenize('"'));
        $this->assertEquals(array('""'), $sp->tokenize('""'));
    }
}
