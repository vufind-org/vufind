<?php

/**
 * RecordLinker view helper Test Class
 *
 * PHP version 8
 *
 * Copyright (C) The National Library of Finland 2019.
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
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */

namespace VuFindTest\View\Helper\Root;

use Laminas\Config\Config;
use VuFind\Record\Router;
use VuFind\View\Helper\Root\RecordLinker;
use VuFind\View\Helper\Root\Url;

/**
 * RecordLinker view helper Test Class
 *
 * @category VuFind
 * @package  Tests
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */
class RecordLinkerTest extends \PHPUnit\Framework\TestCase
{
    use \VuFindTest\Feature\ViewTrait;

    /**
     * Test record URL creation.
     *
     * @return void
     */
    public function testRecordUrl(): void
    {
        $recordLinker = $this->getRecordLinker();
        $this->assertEquals(
            '/Record/foo?sid=-123',
            $recordLinker->getUrl('Solr|foo')
        );
    }

    /**
     * Make sure any percent signs in record ID are properly URL-encoded
     *
     * @return void
     */
    public function testPercentEscaping(): void
    {
        $recordLinker = $this->getRecordLinker();
        $this->assertEquals(
            '/Record/foo%252fbar?sid=-123',
            $recordLinker->getUrl('Solr|foo%2fbar')
        );
        $this->assertEquals(
            '/Record/foo%252fbar?checkRoute=1&sid=-123',
            $recordLinker->getTabUrl('Solr|foo%2fbar', null, ['checkRoute' => 1])
        );
    }

    /**
     * Test behavior when there are multiple GET parameters
     *
     * @return void
     */
    public function testMultiQueryParams(): void
    {
        $recordLinker = $this->getRecordLinker();
        $this->assertEquals(
            '/Record/foo?param1=1&param2=2&sid=-123',
            $recordLinker->getTabUrl('Solr|foo', null, ['param1' => 1, 'param2' => 2])
        );
    }

    /**
     * Test record URL creation with a non-tab action
     *
     * @return void
     */
    public function testGetActionUrl(): void
    {
        $recordLinker = $this->getRecordLinker();
        $this->assertEquals(
            '/Record/foo/Description?sid=-123',
            $recordLinker->getActionUrl('Solr|foo', 'Description')
        );
        $this->assertEquals(
            '/Record/foo/Description?sid=-123&param1=someValue',
            $recordLinker->getActionUrl('Solr|foo', 'Description', ['param1' => 'someValue'])
        );
        $this->assertEquals(
            '/Record/foo/Description?sid=-123#anchor1',
            $recordLinker->getActionUrl('Solr|foo', 'Description', [], 'anchor1')
        );
        $this->assertEquals(
            '/Record/foo/Description?sid=-123&param1=someValue#anchor1',
            $recordLinker->getActionUrl('Solr|foo', 'Description', ['param1' => 'someValue'], 'anchor1')
        );
    }

    /**
     * Get a RecordLinker object ready for testing.
     *
     * @return RecordLinker
     */
    protected function getRecordLinker(): RecordLinker
    {
        $view = $this->getPhpRenderer(
            [
                'searchMemory' => $this->getSearchMemoryViewHelper(),
                'url' => $this->getUrl(),
            ]
        );

        $recordLinker = new RecordLinker(new Router(new Config([])));
        $recordLinker->setView($view);
        return $recordLinker;
    }

    /**
     * Get a URL helper.
     *
     * @return Url
     */
    protected function getUrl(): Url
    {
        $request = $this->getMockBuilder(\Laminas\Http\PhpEnvironment\Request::class)
            ->onlyMethods(['getQuery'])->getMock();
        $request->expects($this->any())->method('getQuery')
            ->will($this->returnValue(new \Laminas\Stdlib\Parameters()));

        $url = new \VuFind\View\Helper\Root\Url($request);

        // Create router
        $router = new \Laminas\Router\Http\TreeRouteStack();
        $router->setRequestUri(new \Laminas\Uri\Http('http://localhost'));
        $recordRoute = new \Laminas\Router\Http\Segment(
            '/Record/[:id[/[:tab]]]',
            [
                'controller' => '[a-zA-Z][a-zA-Z0-9_-]*',
                'action'     => '[a-zA-Z][a-zA-Z0-9_-]*',
            ],
            [
                'controller' => 'Record',
                'action'     => 'Home',
            ]
        );
        $router->addRoute('record', $recordRoute);

        $actionRoute = new \Laminas\Router\Http\Segment(
            '/Record/[:id]/Description',
            [
                'controller' => '[a-zA-Z][a-zA-Z0-9_-]*',
                'action'     => '[a-zA-Z][a-zA-Z0-9_-]*',
            ],
            [
                'controller' => 'Record',
                'action'     => 'Description',
            ]
        );
        $router->addRoute('record-description', $actionRoute);

        $url->setRouter($router);

        return $url;
    }
}
