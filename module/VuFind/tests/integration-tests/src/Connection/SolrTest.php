<?php
/**
 * Solr Connection Test Class
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
namespace VuFind\IntegrationTest\Connection;

/**
 * ResultFeed Test Class
 *
 * @category VuFind2
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:unit_tests Wiki
 */
class SolrTest extends \VuFindTest\Unit\TestCase
{
    /**
     * Check AlphaBrowse "see also" functionality.
     *
     * @return void
     */
     public function testAlphaBrowseSeeAlso()
     {
         $solr = \VuFind\Connection\Manager::connectToIndex();
         $result = $solr->alphabeticBrowse('author', 'Dublin Society', 0, 1);
         $item = $result['Browse']['items'][0];
         $this->assertEquals($item['count'], count($item['ids']));
         $this->assertTrue(empty($item['useInstead']));
         $this->assertTrue(in_array('vtls000013187', $item['ids']));
         $this->assertTrue(in_array('Royal Dublin Society', $item['seeAlso']));
         $this->assertEquals('Dublin Society', $item['heading']);
     }

    /**
     * Check AlphaBrowse "use instead" functionality.
     *
     * @return void
     */
     public function testAlphaBrowseUseInstead()
     {
         $solr = \VuFind\Connection\Manager::connectToIndex();
         $result = $solr->alphabeticBrowse('author', 'Dublin Society, Royal', 0, 1);
         $item = $result['Browse']['items'][0];
         $this->assertEquals(0, $item['count']);
         $this->assertEquals($item['count'], count($item['ids']));
         $this->assertEquals('Dublin Society, Royal', $item['heading']);
         $this->assertTrue(empty($item['seeAlso']));
         $this->assertTrue(in_array('Royal Dublin Society', $item['useInstead']));
     }
}