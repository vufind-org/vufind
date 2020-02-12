<?php
/**
 * ResultFeed Test Class
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
namespace VuFindTest\Integration\View\Helper\Root;

use VuFind\View\Helper\Root\ResultFeed;

/**
 * ResultFeed Test Class
 *
 * @category VuFind
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */
class ResultFeedTest extends \VuFindTest\Unit\ViewHelperTestCase
{
    /**
     * Standard setup method.
     *
     * @return void
     */
    public function setUp(): void
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
        $currentPath = $this->createMock(\VuFind\View\Helper\Root\CurrentPath::class);
        $currentPath->expects($this->any())->method('__invoke')
            ->will($this->returnValue('/test/path'));

        $recordLink = $this->getMockBuilder(\VuFind\View\Helper\Root\RecordLink::class)
            ->setConstructorArgs(
                [
                    new \VuFind\Record\Router(
                        new \Zend\Config\Config([])
                    )
                ]
            )->getMock();
        $recordLink->expects($this->any())->method('getUrl')
            ->will($this->returnValue('test/url'));

        $serverUrl = $this->createMock(\Zend\View\Helper\ServerUrl::class);
        $serverUrl->expects($this->any())->method('__invoke')
            ->will($this->returnValue('http://server/url'));

        return [
            'currentPath' => $currentPath,
            'recordLink' => $recordLink,
            'serverurl' => $serverUrl
        ];
    }

    /**
     * Mock out the translator.
     *
     * @return \Zend\I18n\Translator\TranslatorInterface
     */
    protected function getMockTranslator()
    {
        $mock = $this->getMockBuilder(\Zend\I18n\Translator\TranslatorInterface::class)
            ->getMock();
        $mock->expects($this->at(1))->method('translate')
            ->with($this->equalTo('showing_results_of_html'), $this->equalTo('default'))
            ->will($this->returnValue('Showing <strong>%%start%% - %%end%%</strong> results of <strong>%%total%%</strong>'));
        return $mock;
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
            ->get(\VuFind\Search\Results\PluginManager::class)->get('Solr');
        $results->getParams()->initFromRequest($request);

        $helper = new ResultFeed();
        $helper->registerExtensions($this->getServiceManager());
        $helper->setTranslator($this->getMockTranslator());
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
            'Showing 1 - 2 results of 2', $parsedFeed->getDescription()
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
