<?php
/**
 * Base Search Object Results Test
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
 * @author   Preetha Rao <vufind-tech@lists.sourceforge.net>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:unit_tests Wiki
 */
namespace VuFindTest\Search\Base;

/**
 * Base Search Object Results Test
 *
 * @category VuFind2
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:unit_tests Wiki
 */
class ResultsTest extends \VuFindTest\Unit\TestCase
{
    /**
     * Test that spelling tokenization works correctly.
     *
     * @return void
     */
    public function testSpellingTokenization()
    {
        // Use Solr results since base results is an abstract class.
        $solr = $this->getSearchManager()->setSearchClassId('Solr')->getResults();

        $this->assertEquals(array('single'), $solr->spellingTokens('single'));
        $this->assertEquals(array('two', 'terms'), $solr->spellingTokens('two terms'));
        $this->assertEquals(array('two', 'terms'), $solr->spellingTokens('two    terms'));
        $this->assertEquals(array('apples', 'oranges'), $solr->spellingTokens('apples OR oranges'));
        $this->assertEquals(array('"word"'), $solr->spellingTokens('"word"'));
        $this->assertEquals(array('"word"', 'second'), $solr->spellingTokens('"word" second'));
        $this->assertEquals(array("'word'"), $solr->spellingTokens("'word'"));
        $this->assertEquals(array("'word'", 'second'), $solr->spellingTokens("'word' second"));
        $this->assertEquals(array('word'), $solr->spellingTokens('(word)'));
        $this->assertEquals(array('word', 'second'), $solr->spellingTokens('(word) second'));
        $this->assertEquals(array('apples', 'oranges', 'pears'), $solr->spellingTokens('(apples OR oranges) AND pears'));
        $this->assertEquals(array('two', 'terms'), $solr->spellingTokens("two\tterms"));
        $this->assertEquals(
            array('"two words"', 'single', "'three word phrase'", 'single'),
            $solr->spellingTokens('((("two words" OR single) NOT \'three word phrase\') AND single)')
        );
    }
}