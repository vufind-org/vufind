<?php

/**
 * DOI recommendation module Test Class
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

namespace VuFindTest\Recommend;

use VuFind\Recommend\DOI;

/**
 * DOI recommendation module Test Class
 *
 * @category VuFind
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */
class DOITest extends \PHPUnit\Framework\TestCase
{
    /**
     * Test an empty query.
     *
     * @return void
     */
    public function testEmptyQuery()
    {
        $doi = $this->getDOI();
        $this->assertNull($doi->getDOI());
        $this->assertNull($doi->getURL());
        $this->assertFalse($doi->isFullMatch());
        $this->assertTrue($doi->redirectFullMatch());
    }

    /**
     * Test a non-DOI query.
     *
     * @return void
     */
    public function testNonDoiQuery()
    {
        $doi = $this->getDOI($this->getMockResults('hello world'));
        $this->assertNull($doi->getDOI());
        $this->assertNull($doi->getURL());
        $this->assertFalse($doi->isFullMatch());
        $this->assertTrue($doi->redirectFullMatch());
    }

    /**
     * Test an exact DOI query.
     *
     * @return void
     */
    public function testExactDoiQuery()
    {
        $doi = $this->getDOI($this->getMockResults('10.1109/CC.2018.8485472'));
        $this->assertEquals('10.1109/CC.2018.8485472', $doi->getDOI());
        $this->assertEquals('http://doi/10.1109%2FCC.2018.8485472', $doi->getURL());
        $this->assertTrue($doi->isFullMatch());
        $this->assertTrue($doi->redirectFullMatch());
    }

    /**
     * Test a non-exact DOI query.
     *
     * @return void
     */
    public function testNonExactDoiQuery()
    {
        $doi = $this->getDOI($this->getMockResults('Yes 10.1109/CC.2018.8485472'));
        $this->assertEquals('10.1109/CC.2018.8485472', $doi->getDOI());
        $this->assertEquals('http://doi/10.1109%2FCC.2018.8485472', $doi->getURL());
        $this->assertFalse($doi->isFullMatch());
        $this->assertTrue($doi->redirectFullMatch());
    }

    /**
     * Test configuration of the redirect setting:
     *
     * @return void
     */
    public function testDoiRedirectConfigs()
    {
        $testData = [
            'true' => true,
            'false' => false,
            '' => false,
            '1' => true,
            '0' => false,
        ];
        $url = 'https://doi/';
        foreach ($testData as $config => $expected) {
            $doi = $this->getDOI(
                $this->getMockResults('Yes 10.1109/CC.2018.8485472'),
                $url . ':' . $config
            );
            $this->assertEquals('10.1109/CC.2018.8485472', $doi->getDOI());
            $this->assertEquals($url . '10.1109%2FCC.2018.8485472', $doi->getURL());
            $this->assertFalse($doi->isFullMatch());
            $this->assertEquals($expected, $doi->redirectFullMatch());
        }
    }

    /**
     * Get a fully configured module
     *
     * @param \VuFind\Search\Solr\Results $results  results object
     * @param string                      $settings settings
     *
     * @return DOI
     */
    protected function getDOI($results = null, $settings = 'http://doi/')
    {
        if (null === $results) {
            $results = $this->getMockResults();
        }
        $doi = new DOI();
        $doi->setConfig($settings);
        $doi->init($results->getParams(), new \Laminas\Stdlib\Parameters([]));
        $doi->process($results);
        return $doi;
    }

    /**
     * Get a mock results object.
     *
     * @param string $query Query to include
     * @param string $type  Query type ('basic' or 'advanced')
     *
     * @return \VuFind\Search\Solr\Results
     */
    protected function getMockResults($query = '', $type = 'basic')
    {
        $params = $this->getMockParams($query, $type);
        $results = $this->getMockBuilder(\VuFind\Search\Solr\Results::class)
            ->disableOriginalConstructor()->getMock();
        $results->expects($this->any())->method('getParams')
            ->will($this->returnValue($params));
        return $results;
    }

    /**
     * Get a mock params object.
     *
     * @param string $query Query to include
     * @param string $type  Query type ('basic' or 'advanced')
     *
     * @return \VuFind\Search\Solr\Params
     */
    protected function getMockParams($query = '', $type = 'basic')
    {
        $params = $this->getMockBuilder(\VuFind\Search\Solr\Params::class)
            ->disableOriginalConstructor()->getMock();
        $params->expects($this->any())->method('getDisplayQuery')
            ->will($this->returnValue($query));
        $params->expects($this->any())->method('getSearchType')
            ->will($this->returnValue($type));
        return $params;
    }
}
