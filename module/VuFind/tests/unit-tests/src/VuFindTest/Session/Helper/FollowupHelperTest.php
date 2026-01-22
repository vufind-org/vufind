<?php

/**
 * FollowupHelper tests.
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2010-2025.
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

namespace VuFindTest\Session\Helper;

use Laminas\Session\Container;
use VuFind\Http\ServerUrlHelper;
use VuFind\Session\Helper\FollowupHelper;

/**
 * FollowupHelper tests.
 *
 * @category VuFind
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */
class FollowupHelperTest extends \PHPUnit\Framework\TestCase
{
    /**
     * Test clear behavior
     *
     * @return void
     */
    public function testClear(): void
    {
        $helper = $this->getFollowupHelper();
        $this->assertFalse($helper->clear('url'));  // nothing to clear yet
        $helper->store();
        $this->assertTrue($helper->clear('url'));   // clear the url set by store
        $this->assertFalse($helper->clear('url'));  // already cleared
    }

    /**
     * Test retrieve
     *
     * @return void
     */
    public function testRetrieve(): void
    {
        $helper = $this->getFollowupHelper();
        $helper->store();
        // standard URL retrieval:
        $this->assertEquals('http://localhost/default-url', $helper->retrieve('url'));
        // no parameters retrieves session object:
        $this->assertInstanceOf(Container::class, $helper->retrieve());
        // test defaulting behavior:
        $this->assertEquals('foo', $helper->retrieve('bar', 'foo'));
    }

    /**
     * Test retrieve and clear
     *
     * @return void
     */
    public function testRetrieveAndClear(): void
    {
        $helper = $this->getFollowupHelper();
        $helper->store(['foo' => 'bar'], 'baz');
        $this->assertEquals('bar', $helper->retrieveAndClear('foo'));
        $this->assertEquals('baz', $helper->retrieveAndClear('url'));
        $this->assertNull($helper->retrieveAndClear('foo'));
        $this->assertNull($helper->retrieveAndClear('url'));
    }

    /**
     * Test that lightboxParent query parameter is removed from stored URL
     *
     * @return void
     */
    public function testLightboxParentRemoval(): void
    {
        $urlWithLightbox = 'http://localhost/search?lookfor=test&lightboxParent=true&page=2';
        $helper = $this->getFollowupHelper($urlWithLightbox);
        $helper->store();

        $storedUrl = $helper->retrieve('url');
        $this->assertStringNotContainsString('lightboxParent', (string)$storedUrl);
        $this->assertStringContainsString('lookfor=test', (string)$storedUrl);
        $this->assertStringContainsString('page=2', (string)$storedUrl);
    }

    /**
     * Test storing with override URL
     *
     * @return void
     */
    public function testStoreWithOverrideUrl(): void
    {
        $helper = $this->getFollowupHelper('http://localhost/default-url');
        $overrideUrl = 'http://localhost/custom-url?foo=bar';
        $helper->store([], $overrideUrl);

        $this->assertEquals($overrideUrl, $helper->retrieve('url'));
    }

    /**
     * Test storing extras
     *
     * @return void
     */
    public function testStoreExtras(): void
    {
        $helper = $this->getFollowupHelper();
        $extras = [
            'recordId' => '123',
            'tab' => 'holdings',
            'customData' => ['nested' => 'value'],
        ];
        $helper->store($extras);

        $this->assertEquals('123', $helper->retrieve('recordId'));
        $this->assertEquals('holdings', $helper->retrieve('tab'));
        $this->assertEquals(['nested' => 'value'], $helper->retrieve('customData'));
        $this->assertEquals('http://localhost/default-url', $helper->retrieve('url'));
    }

    /**
     * Get a FollowupHelper instance for testing
     *
     * @param string $url URL for ServerUrlHelper to report
     *
     * @return FollowupHelper
     */
    protected function getFollowupHelper(
        string $url = 'http://localhost/default-url'
    ): FollowupHelper {
        $session = new Container('test');
        $serverUrlHelper = $this->getMockServerUrlHelper($url);
        return new FollowupHelper($session, $serverUrlHelper);
    }

    /**
     * Get a mock ServerUrlHelper
     *
     * @param string $url URL for helper to report
     *
     * @return ServerUrlHelper
     */
    protected function getMockServerUrlHelper(string $url): ServerUrlHelper
    {
        $helper = $this->createMock(ServerUrlHelper::class);
        $helper->method('getCurrentUrl')->willReturn($url);
        return $helper;
    }
}
