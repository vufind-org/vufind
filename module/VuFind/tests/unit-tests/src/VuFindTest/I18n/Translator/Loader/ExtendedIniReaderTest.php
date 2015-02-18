<?php
/**
 * ExtendedIniReader Test Class
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
namespace VuFindTest\I18n\Translator\Loader;
use VuFind\I18n\Translator\Loader\ExtendedIniReader;

/**
 * ExtendedIniReader Test Class
 *
 * @category VuFind2
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Chris Hallberg <challber@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:unit_tests Wiki
 */
class ExtendedIniReaderTest extends \VuFindTest\Unit\TestCase
{
    /**
     * Test non-joiner functionality.
     *
     * @return void
     */
    public function testNonJoinerOptions()
    {
        $reader = new ExtendedIniReader();
        $input = ['foo="bar"', 'baz=""'];
        $output = ['foo' => 'bar', 'baz' => ''];
        $nonJoiner = html_entity_decode('&#x200C;', ENT_NOQUOTES, 'UTF-8');
        $nonJoinerOutput = ['foo' => 'bar', 'baz' => $nonJoiner];
        // Test behavior with and without the $convertBlanks switch:
        $this->assertEquals($output, (array)$reader->getTextDomain($input, false));
        $this->assertEquals($nonJoinerOutput, (array)$reader->getTextDomain($input));
    }
}