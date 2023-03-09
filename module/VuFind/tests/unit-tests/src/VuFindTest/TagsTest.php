<?php
/**
 * Tags Test Class
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
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */

namespace VuFindTest;

/**
 * Tags Test Class
 *
 * @category VuFind
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */
class TagsTest extends \PHPUnit\Framework\TestCase
{
    /**
     * Tag parser
     *
     * @var \VuFind\Tags
     */
    protected $parser;

    /**
     * Standard setup method.
     *
     * @return void
     */
    public function setUp(): void
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
