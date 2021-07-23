<?php
/**
 * RecordLink view helper Test Class
 *
 * PHP version 7
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
use VuFind\View\Helper\Root\RecordLink;

/**
 * RecordLink view helper Test Class
 *
 * @category VuFind
 * @package  Tests
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */
class RecordLinkTest extends \PHPUnit\Framework\TestCase
{
    /**
     * Test record URL creation.
     *
     * @return void
     */
    public function testRecordUrl()
    {
        $recordLink = $this->getRecordLink();
        $this->assertEquals(
            '/Record/foo',
            $recordLink->getUrl('Solr|foo')
        );

        // Make sure any percent signs in record ID are properly URL-encoded
        $this->assertEquals(
            '/Record/foo%252fbar',
            $recordLink->getUrl('Solr|foo%2fbar')
        );
        $this->assertEquals(
            '/Record/foo%252fbar?checkRoute=1',
            $recordLink->getTabUrl('Solr|foo%2fbar', null, ['checkRoute' => 1])
        );
    }

    /**
     * Get a RecordLink object ready for testing.
     *
     * @return RecordLink
     */
    protected function getRecordLink(): RecordLink
    {
        $view = new \Laminas\View\Renderer\PhpRenderer();
        $container = new \VuFindTest\Container\MockViewHelperContainer($this);
        $container->set('url', $this->getUrl());
        $view->setHelperPluginManager($container);

        $recordLink = new RecordLink(new Router(new Config([])));
        $recordLink->setView($view);
        return $recordLink;
    }

    /**
     * Get a URL helper.
     *
     * @return \VuFind\View\Helper\Root\Url
     */
    protected function getUrl()
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
        $url->setRouter($router);

        return $url;
    }
}
