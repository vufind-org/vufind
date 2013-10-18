<?php

/**
 * Unit tests for spellcheck information.
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

namespace VuFindTest\Backend\Solr\Json\Response;

use VuFindSearch\Backend\Solr\Response\Json\Spellcheck;
use PHPUnit_Framework_TestCase as TestCase;

/**
 * Unit tests for spellcheck information.
 *
 * @category VuFind2
 * @package  Search
 * @author   David Maus <maus@hab.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org
 */
class SpellcheckTest extends TestCase
{
    /**
     * Test merge.
     *
     * @return void
     */
    public function testMerge()
    {
        $s1 = new Spellcheck(
            array(
                array('this is a phrase', array()),
                array('foo', array()),
                array('foobar', array())
            ),
            'fake query'
        );
        $s2 = new Spellcheck(
            array(
                array('is a', array()),
                array('bar', array()),
                array('foo bar', array())
            ),
            'fake query'
        );
        $s1->mergeWith($s2);
        $this->assertCount(5, $s1);
        $this->assertEquals($s2, $s1->getSecondary());
    }

    /**
     * Test getQuery()
     *
     * @return void
     */
    public function testGetQuery()
    {
        $s = new Spellcheck(array(), 'test');
        $this->assertEquals('test', $s->getQuery());
    }
}