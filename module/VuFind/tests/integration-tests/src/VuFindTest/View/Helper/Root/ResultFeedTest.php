<?php
/**
 * ResultFeed Test Class
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
namespace VuFindTest\Integration\View\Helper\Root;
use VuFind\View\Helper\Root\ResultFeed;

/**
 * ResultFeed Test Class
 *
 * @category VuFind2
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:unit_tests Wiki
 */
class ResultFeedTest extends \VuFindTest\Unit\ViewHelperTestCase
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
     * Get plugins to register to support view helper being tested
     *
     * @return array
     */
    protected function getPlugins()
    {
        $currentPath = $this->getMock('VuFind\View\Helper\Root\CurrentPath');
        $currentPath->expects($this->any())->method('__invoke')
            ->will($this->returnValue('/test/path'));

        $recordLink = $this->getMock(
            'VuFind\View\Helper\Root\RecordLink', [],
            [new \VuFind\Record\Router(
                $this->getServiceManager()->get('VuFind\RecordLoader'),
                new \Zend\Config\Config([])
            )
            ]
        );
        $recordLink->expects($this->any())->method('getUrl')
            ->will($this->returnValue('test/url'));

        $serverUrl = $this->getMock('Zend\View\Helper\ServerUrl');
        $serverUrl->expects($this->any())->method('__invoke')
            ->will($this->returnValue('http://server/url'));

        return [
            'currentpath' => $currentPath,
            'recordlink' => $recordLink,
            'serverurl' => $serverUrl
        ];
    }

    /**
     * Test feed generation
     *
     * @return void
     */
    public function testRSS()
    {
        // Set up a request -- we'll sort by title to ensure a predictable order
        // for the result list (relevance or last_indexed may lead to unstable test
        // cases).
        $request = new \Zend\Stdlib\Parameters();
        $request->set('lookfor', 'id:testbug2 OR id:testsample1');
        $request->set('skip_rss_sort', 1);
        $request->set('sort', 'title');
        $request->set('view', 'rss');

        $results = $this->getServiceManager()
            ->get('VuFind\SearchResultsPluginManager')->get('Solr');
        $results->getParams()->initFromRequest($request);

        $helper = new ResultFeed();
        $helper->setView($this->getPhpRenderer($this->getPlugins()));
        $feed = $helper->__invoke($results, '/test/path');
        $this->assertTrue(is_object($feed));
        $rss = $feed->export('rss');

        // Make sure it's really an RSS feed:
        $this->assertTrue(strstr($rss, '<rss') !== false);

        // Make sure custom Dublin Core elements are present:
        $this->assertTrue(strstr($rss, 'dc:format') !== false);

        // Now re-parse it and check for some expected values:
        $parsedFeed = \Zend\Feed\Reader\Reader::importString($rss);
        $this->assertEquals(
            'Showing 1-2 of 2', $parsedFeed->getDescription()
        );
        $items = [];
        $i = 0;
        foreach ($parsedFeed as $item) {
            $items[$i++] = $item;
        }
        $this->assertEquals(
            'Journal of rational emotive therapy : '
            . 'the journal of the Institute for Rational-Emotive Therapy.',
            $items[1]->getTitle()
        );
    }
}