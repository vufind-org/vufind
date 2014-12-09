<?php
/**
 * SimpleXML Test Class
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
 * SimpleXML Test Class
 *
 * @category VuFind2
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:unit_tests Wiki
 */
class SimpleXMLTest extends \PHPUnit_Framework_TestCase
{
    /**
     * Test attaching one item to another.
     *
     * @return void
     */
    public function testAppendElement()
    {
        $parent = simplexml_load_string('<top><children></children></top>');
        $child = simplexml_load_string('<child attr="true" />');
        $expected = simplexml_load_string(
            '<top><children>
<child attr="true" />
</children></top>'
        );
        \VuFind\SimpleXML::appendElement($parent->children, $child);
        $this->assertEquals($expected->asXML(), $parent->asXML());
    }
}