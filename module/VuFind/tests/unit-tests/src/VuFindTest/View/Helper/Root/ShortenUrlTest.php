<?php

/**
 * ShortenUrl view helper Test Class
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2019.
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

namespace VuFindTest\View\Helper\Root;

use VuFind\UrlShortener\Database;
use VuFind\UrlShortener\UrlShortenerInterface;
use VuFind\View\Helper\Root\ShortenUrl;
use VuFind\View\Helper\Root\ShortenUrlFactory;

/**
 * ShortenUrl view helper Test Class
 *
 * @category VuFind
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */
class ShortenUrlTest extends \PHPUnit\Framework\TestCase
{
    /**
     * Test that helper proxies to appropriate service.
     *
     * @return void
     */
    public function testHelper()
    {
        $container = new \VuFindTest\Container\MockContainer($this);
        $service = $container->createMock(Database::class, ['shorten']);
        $service->expects($this->once())->method('shorten')
            ->with($this->equalTo('foo'))->will($this->returnValue('bar'));
        $container->set(UrlShortenerInterface::class, $service);
        $factory = new ShortenUrlFactory();
        $helper = $factory($container, ShortenUrl::class);
        $this->assertEquals('bar', $helper('foo'));
    }
}
