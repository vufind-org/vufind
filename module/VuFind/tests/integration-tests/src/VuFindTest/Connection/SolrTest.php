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
namespace VuFindTest\Integration\Connection;

/**
 * Solr Connection Test Class
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
     * Standard setup method.
     *
     * @return void
     */
    public function setUp()
    {
        // Give up if we're not running in CI:
        if (!$this->continuousIntegrationRunning()) {
            return $this->markTestSkipped('Continuous integration not running.');
        }
    }

    /**
     * Check AlphaBrowse "see also" functionality.
     *
     * @return void
     */
    public function testAlphaBrowseSeeAlso()
    {
        $solr = $this->getServiceManager()->get('VuFind\Search\BackendManager')
            ->get('Solr');
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
        $solr = $this->getServiceManager()->get('VuFind\Search\BackendManager')
            ->get('Solr');
        $result = $solr->alphabeticBrowse('author', 'Dublin Society, Royal', 0, 1);
        $item = $result['Browse']['items'][0];
        $this->assertEquals(0, $item['count']);
        $this->assertEquals($item['count'], count($item['ids']));
        $this->assertEquals('Dublin Society, Royal', $item['heading']);
        $this->assertTrue(empty($item['seeAlso']));
        $this->assertTrue(in_array('Royal Dublin Society', $item['useInstead']));
    }

    /**
     * Check that expected Dewey values are present (tests VUFIND-701).
     *
     * @return void
     */
    public function testDeweyValues()
    {
        $solr = $this->getServiceManager()->get('VuFind\Search\BackendManager')
            ->get('Solr');
        $result = $solr->alphabeticBrowse('dewey', '123.45 .I39', 0, 1);
        $item = $result['Browse']['items'][0];
        $this->assertEquals(1, $item['count']);
        $this->assertEquals($item['count'], count($item['ids']));
        $this->assertEquals('123.45 .I39', $item['heading']);
        $result = $solr->alphabeticBrowse('dewey', '123.46 .Q39', 0, 1);
        $item = $result['Browse']['items'][0];
        $this->assertEquals(1, $item['count']);
        $this->assertEquals($item['count'], count($item['ids']));
        $this->assertEquals('123.46 .Q39', $item['heading']);
    }

    /**
     * Check that the terms handler is working.
     *
     * @return void
     */
    public function testTermsHandler()
    {
        $solr = $this->getServiceManager()->get('VuFind\Search\BackendManager')
            ->get('Solr');
        $currentPageInfo = $solr->terms('id', 'test', 1)->getFieldTerms('id');
        $this->assertEquals(1, count($currentPageInfo));
        foreach ($currentPageInfo as $key => $value) {
            $this->assertEquals('test', substr($key, 0, 4));
        }
    }
}