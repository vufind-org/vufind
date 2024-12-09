<?php

/**
 * Unit tests for SOLR search handler.
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

namespace VuFindTest\Backend\Solr;

use PHPUnit\Framework\TestCase;
use VuFindSearch\Backend\Solr\SearchHandler;

/**
 * Unit tests for SOLR search handler.
 *
 * @category VuFind
 * @package  Search
 * @author   David Maus <maus@hab.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org
 */
class SearchHandlerTest extends TestCase
{
    /**
     * Test creating simple dismax query.
     *
     * @return void
     */
    public function testSimpleSearchDismax()
    {
        $spec = ['DismaxParams' => [['foo', 'bar']], 'DismaxFields' => ['field1', 'field2']];
        $hndl = new SearchHandler($spec);
        $this->assertEquals(
            '(_query_:"{!dismax qf=\"field1 field2\" foo=\\\'bar\\\' mm=\\\'100%\\\'}foobar")',
            $hndl->createSimpleQueryString('foobar')
        );
    }

    /**
     * Test creating simple standard query.
     *
     * @return void
     */
    public function testSimpleStandardSearch()
    {
        $spec = ['QueryFields' => ['id' => [['or', '~']]]];
        $hndl = new SearchHandler($spec);
        $this->assertEquals(
            '(id:("escaped\"quote" OR not OR quoted OR "basic phrase"))',
            $hndl->createSimpleQueryString('"escaped\"quote" not quoted "basic phrase"')
        );
    }

    /**
     * Test toArray() method.
     *
     * @return void
     */
    public function testToArray()
    {
        $spec = ['DismaxParams' => [['foo', 'bar'], ['mm', '100%']], 'DismaxFields' => ['field1', 'field2']];
        $hndl = new SearchHandler($spec);
        $defaults = [
            'CustomMunge' => [],
            'DismaxHandler' => 'dismax',
            'QueryFields' => [],
            'FilterQuery' => [],
            'DismaxMunge' => [],
        ];
        $this->assertEquals($spec + $defaults, $hndl->toArray());
    }

    /**
     * Test creating extended dismax query.
     *
     * @return void
     */
    public function testSimpleSearchExtendedDismax()
    {
        $spec = ['DismaxParams' => [['foo', 'bar']], 'DismaxFields' => ['field1', 'field2']];
        $hndl = new SearchHandler($spec, 'edismax');
        $this->assertEquals(
            '(_query_:"{!edismax qf=\"field1 field2\" foo=\\\'bar\\\' mm=\\\'0%\\\'}foobar")',
            $hndl->createSimpleQueryString('foobar')
        );
    }

    /**
     * Test custom munge rules.
     *
     * @return void
     */
    public function testCustomMunge()
    {
        // fake munge rules based on a simplified version of default searchspecs.yaml
        $spec = [
            'CustomMunge' => [
                'callnumber_exact' => [
                    ['uppercase'],
                    ['preg_replace', '/[ "]/', ''],
                    ['preg_replace', '/\*+$/', ''],
                ],
                'callnumber_fuzzy' => [
                    ['uppercase'],
                    ['preg_replace', '/[ "]/', ''],
                    ['preg_replace', '/\*+$/', ''],
                    ['append', '*'],
                    ['prepend', '^'],
                ],
            ],
            'QueryFields' => [
                'callnumber' => [
                    ['callnumber_exact', 1000],
                    ['callnumber_fuzzy', '~'],
                ],
                'dewey-full' => [
                    ['callnumber_exact', 1000],
                    ['callnumber_fuzzy', '~'],
                ],
            ],
        ];

        $hndl = new SearchHandler($spec);
        $this->assertEquals(
            '(callnumber:(ABC123)^1000 OR callnumber:(^ABC123*) OR dewey-full:(ABC123)^1000 OR dewey-full:(^ABC123*))',
            $hndl->createSimpleQueryString('abc"123*')
        );
    }

    /**
     * Test dismax munge rules.
     *
     * @return void
     */
    public function testPreprocessQueryString()
    {
        // fake munge rules based on a simplified version of default searchspecs.yaml
        $spec = [
            'DismaxMunge' => [
                ['uppercase'],
                ['preg_replace', '/[ "]/', ''],
                ['preg_replace', '/\*+$/', ''],
            ],
            'DismaxFields' => ['callnumber'],
            'DismaxHandler' => 'dismax',
        ];

        $hndl = new SearchHandler($spec);
        $this->assertEquals(
            'ABC123',
            $hndl->preprocessQueryString('abc"123*')
        );
    }
}
