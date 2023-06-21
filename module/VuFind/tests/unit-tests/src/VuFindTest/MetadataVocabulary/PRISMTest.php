<?php

/**
 * PRISM Test Class
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2020.
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

namespace VuFindTest\MetadataVocabulary;

use VuFind\MetadataVocabulary\PRISM;

/**
 * PRISM Test Class
 *
 * @category VuFind
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */
class PRISMTest extends \PHPUnit\Framework\TestCase
{
    use FakeDriverTrait;

    /**
     * Test basic functionality of the class.
     *
     * @return void
     */
    public function testMappings()
    {
        $meta = new PRISM();
        $this->assertEquals(
            [
                'prism.title' => ['Fake Title'],
                'prism.doi' => ['FakeDOI'],
                'prism.endingPage' => [10],
                'prism.isbn' => ['FakeISBN'],
                'prism.issn' => ['FakeISSN'],
                'prism.startingPage' => [1],
                'prism.volume' => [7],
            ],
            $meta->getMappedData($this->getDriver())
        );
    }
}
