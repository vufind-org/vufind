<?php

/**
 * Unit tests for terms information.
 *
 * PHP version 8
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

namespace VuFindTest\Backend\Solr\Json\Response;

use PHPUnit\Framework\TestCase;
use VuFindSearch\Backend\Solr\Response\Json\Terms;

use function get_class;

/**
 * Unit tests for terms information.
 *
 * @category VuFind
 * @package  Search
 * @author   David Maus <maus@hab.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org
 */
class TermsTest extends TestCase
{
    /**
     * Test terms
     *
     * @return void
     */
    public function testTerms()
    {
        $field1 = [['a', 1], ['b', 2], ['c', 3]];
        $terms = new Terms(
            [
                'terms' => [
                    'field1' => $field1,
                ],
            ]
        );

        $this->assertEquals(\ArrayIterator::class, get_class($terms->getIterator()));
        $this->assertNull($terms->getFieldTerms('field2'));
        $fieldTerms = $terms->getFieldTerms('field1');
        $this->assertCount(3, $fieldTerms);
        $fieldTerms->rewind();
        $this->assertEquals('a', $fieldTerms->key());
        $this->assertEquals('1', $fieldTerms->current());
    }
}
