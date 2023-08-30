<?php

/**
 * CachingDownloader Test Class
 *
 * PHP version 8
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

use Laminas\Http\Response;
use VuFind\Exception\HttpDownloadException;
use VuFind\Http\CachingDownloader;
use VuFindHttp\HttpService;

/**
 * CachingDownloader Test Class
 *
 * @category VuFind
 * @package  Tests
 * @author   Mario Trojan <mario.trojan@uni-tuebingen.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */
class CachingDownloaderTest extends \PHPUnit\Framework\TestCase
{
    /**
     * Test a download
     *
     * @return void
     */
    public function testDownload()
    {
        $container = new \VuFindTest\Container\MockContainer($this);

        $testUrl = 'https://dummyjson.com/products/1';
        $testBody = '{"id":1,"title":"iPhone 9"}';
        $testCacheKey = md5($testUrl);

        // httpService
        $service = $this->getMockBuilder(HttpService::class)
            ->disableOriginalConstructor()
            ->getMock();

        $response = $this->getMockBuilder(Response::class)
            ->disableOriginalConstructor()
            ->getMock();
        $response->expects($this->once())->method('isOk')->willReturn(true);
        $response->expects($this->once())->method('getBody')->willReturn($testBody);

        $service->expects($this->once())->method('get')->with($testUrl)->willReturn($response);

        // cacheManager
        $storage = $this->getMockBuilder(\Laminas\Cache\Storage\StorageInterface::class)
            ->disableOriginalConstructor()
            ->getMock();

        $storage->expects($this->once())->method('hasItem')->with($testCacheKey)->willReturn(false);
        $storage->expects($this->once())->method('addItem')->with($testCacheKey, $testBody);

        $cacheManagerMock = $container->createMock(\VuFind\Cache\Manager::class);
        $cacheManagerMock
            ->expects($this->once())
            ->method('addDownloaderCache')
            ->with('default')
            ->willReturn('downloader-default');
        $cacheManagerMock
            ->expects($this->once())
            ->method('getCache')
            ->with('downloader-default')
            ->willReturn($storage);

        // configManager
        $configManagerMock = $this->createMock(\VuFind\Config\PluginManager::class);

        // downloader
        $downloader = new CachingDownloader($cacheManagerMock, $configManagerMock);
        $downloader->setHttpService($service);

        $body = $downloader->download(
            $testUrl
        );
        $this->assertEquals($body, $testBody);
    }

    /**
     * Test exception handling
     *
     * @return void
     */
    public function testException()
    {
        $this->expectException(HttpDownloadException::class);

        $container = new \VuFindTest\Container\MockContainer($this);

        $testUrl = 'https://mock.codes/404';
        $testCacheKey = md5($testUrl);

        // httpService
        $service = $this->getMockBuilder(HttpService::class)
            ->disableOriginalConstructor()
            ->getMock();
        $service->expects($this->once())
            ->method('get')
            ->with($testUrl)
            ->willThrowException(new \Exception('Download failed (404): ' . $testUrl));

        // cacheManager
        $storage = $this->getMockBuilder(\Laminas\Cache\Storage\StorageInterface::class)
            ->disableOriginalConstructor()
            ->getMock();

        $storage->expects($this->once())->method('hasItem')->with($testCacheKey)->willReturn(false);

        $cacheManagerMock = $container->createMock(\VuFind\Cache\Manager::class);
        $cacheManagerMock->expects($this->once())
            ->method('addDownloaderCache')
            ->with('default')
            ->willReturn('downloader-default');
        $cacheManagerMock->expects($this->once())
            ->method('getCache')
            ->with('downloader-default')
            ->willReturn($storage);

        // configManager
        $configManagerMock = $this->createMock(\VuFind\Config\PluginManager::class);

        // downloader
        $downloader = new CachingDownloader($cacheManagerMock, $configManagerMock);
        $downloader->setHttpService($service);

        $downloader->download(
            $testUrl
        );
    }
}
