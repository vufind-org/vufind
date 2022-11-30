<?php
/**
 * CachingDownloader Test Class
 *
 * PHP version 7
 *
 * Copyright (C) Villanova University 2022.
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
 * @author   Mario Trojan <mario.trojan@uni-tuebingen.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */
namespace VuFindTest\Http;

use VuFind\Http\CachingDownloader;

/**
 * CachingDownloader Test Class
 *
 * @category VuFind
 * @package  Tests
 * @author   @author   Mario Trojan <mario.trojan@uni-tuebingen.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */
class CachingDownloaderTest extends \PHPUnit\Framework\TestCase
{
    public function testDownload() {
        $container = new \VuFindTest\Container\MockContainer($this);
        $cacheManager = $container->get(\VuFind\Cache\Manager::class);
        $downloader = new CachingDownloader($cacheManager);

        $body = $downloader->download(
            'https://dummyjson.com/products/1'
        );
        $this->assertEquals($body, '{"id":1,"title":"iPhone 9","description":"An apple mobile which is nothing like apple","price":549,"discountPercentage":12.96,"rating":4.69,"stock":94,"brand":"Apple","category":"smartphones","thumbnail":"https://dummyjson.com/image/i/products/1/thumbnail.jpg","images":["https://dummyjson.com/image/i/products/1/1.jpg","https://dummyjson.com/image/i/products/1/2.jpg","https://dummyjson.com/image/i/products/1/3.jpg","https://dummyjson.com/image/i/products/1/4.jpg","https://dummyjson.com/image/i/products/1/thumbnail.jpg"]}');
    }
}
