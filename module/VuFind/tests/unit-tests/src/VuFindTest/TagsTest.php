<?php
/**
 * Tags Test Class
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
namespace VuFindTest;

/**
 * Tags Test Class
 *
 * @category VuFind2
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:unit_tests Wiki
 */
class TagsTest extends \PHPUnit_Framework_TestCase
{
    /**
     * Tag parser
     *
     * @var \VuFind\Tags
     */
    protected $parser;

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->parser = new \VuFind\Tags();
    }

    /**
     * Test tag parsing
     *
     * @return void
     */
    public function testTagParsing()
    {
        $this->assertEquals(
            ['this', 'that', 'the other'],
            $this->parser->parse('this that "the other"')
        );
    }

    /**
     * Test empty tag parsing
     *
     * @return void
     */
    public function testEmptyTagParsing()
    {
        $this->assertEquals([], $this->parser->parse(''));
    }

    /**
     * Test deduplication
     *
     * @return void
     */
    public function testDeduplication()
    {
        $this->assertEquals(['test'], $this->parser->parse('test test test'));
    }

    /**
     * Test truncation
     *
     * @return void
     */
    public function testTruncation()
    {
        // Create custom object w/ small size limit:
        $parser = new \VuFind\Tags(10);
        $this->assertEquals(['0123456789'], $parser->parse('01234567890'));
    }
}