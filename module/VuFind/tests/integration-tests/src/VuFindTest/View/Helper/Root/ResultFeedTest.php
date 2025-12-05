<?php

/**
 * ResultFeed Test Class
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
 * along with this program; if not, see
 * <https://www.gnu.org/licenses/>.
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
class ResultFeedTest extends \PHPUnit\Framework\TestCase
{
    use \VuFindTest\Feature\LiveDetectionTrait;
    use \VuFindTest\Feature\LiveSolrTrait;
    use \VuFindTest\Feature\ViewTrait;
    use \VuFindTest\Feature\TranslatorTrait;

    /**
     * Standard setup method.
     *
     * @return void
     */
    public function setUp(): void
    {
        // Give up if we're not running in CI:
        if (!$this->continuousIntegrationRunning()) {
            $this->markTestSkipped('Continuous integration not running.');
            return;
        }
    }

    /**
     * Get plugins to register to support view helper being tested
     *
     * @return array
     */
    protected function getPlugins(): array
    {
        $currentPath = $this->createMock(\VuFind\View\Helper\Root\CurrentPath::class);
        $currentPath->expects($this->any())->method('__invoke')->willReturn('/test/path');

        $record = $this->createMock(\VuFind\View\Helper\Root\Record::class);
        $record->method('__invoke')->willReturn($record);
        $record->method('getLinkDetails')->willReturn([['url' => 'http://driver-url']]);

        $recordLinker = $this->getMockBuilder(\VuFind\View\Helper\Root\RecordLinker::class)
            ->setConstructorArgs(
                [
                    new \VuFind\Record\Router(
                        new \VuFind\Config\Config([])
                    ),
                ]
            )->getMock();
        $recordLinker->expects($this->any())->method('getUrl')->willReturn('test/url');

        $serverUrl = $this->createMock(\Laminas\View\Helper\ServerUrl::class);
        $serverUrl->expects($this->any())->method('__invoke')->willReturn('http://server/url');

        return compact('currentPath', 'record', 'recordLinker') + ['serverurl' => $serverUrl];
    }

    /**
     * Data provider for testRSS.
     *
     * @return array[]
     */
    public static function rssProvider(): array
    {
        $routeLink = 'http://server/url';
        $driverLink = 'http://driver-url';
        return [
            'default options' => [[], $routeLink],
            'prioritizeRecordDriverLinks = false' => [['prioritizeRecordDriverLinks' => false], $routeLink],
            'prioritizeRecordDriverLinks = true' => [['prioritizeRecordDriverLinks' => true], $driverLink],
        ];
    }

    /**
     * Test feed generation
     *
     * @param array  $options      Options to pass to the ResultFeed object.
     * @param string $expectedLink The link URL we expect to find in the first result in the feed.
     *
     * @return void
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('rssProvider')]
    public function testRSS(array $options, string $expectedLink): void
    {
        // Set up a request -- we'll sort by title to ensure a predictable order
        // for the result list (relevance or last_indexed may lead to unstable test
        // cases).
        $request = new \Laminas\Stdlib\Parameters();
        $request->set('lookfor', 'id:testbug2 OR id:testsample1');
        $request->set('skip_rss_sort', 1);
        $request->set('sort', 'title');
        $request->set('view', 'rss');

        $results = $this->getResultsObject();
        $results->getParams()->initFromRequest($request);

        $helper = new ResultFeed($options);
        $helper->registerExtensions(new \VuFindTest\Container\MockContainer($this));
        $translator = $this->getMockTranslator(
            [
                'default' => [
                    'Results for' => 'Results for',
                    'showing_results_of_html' => 'Showing <strong>%%start%% - %%end%%'
                        . '</strong> results of <strong>%%total%%</strong>',
                ],
            ]
        );
        $helper->setTranslator($translator);
        $helper->setView($this->getPhpRenderer($this->getPlugins()));
        $feed = $helper($results, '/test/path');
        $this->assertIsObject($feed);
        $rss = $feed->export('rss');

        // Make sure it's really an RSS feed:
        $this->assertTrue(strstr($rss, '<rss') !== false);

        // Make sure custom Dublin Core elements are present:
        $this->assertTrue(strstr($rss, 'dc:format') !== false);

        // Make sure custom Atom link elements are present:
        $this->assertTrue(strstr($rss, 'atom:link') !== false);

        // Now re-parse it and check for some expected values:
        $parsedFeed = \Laminas\Feed\Reader\Reader::importString($rss);
        $this->assertEquals(
            'Showing 1 - 2 results of 2',
            $parsedFeed->getDescription()
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
        $this->assertEquals($expectedLink, $items[1]->getLink());
    }
}
