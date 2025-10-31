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
 * along with this program; if not, see
 * <https://www.gnu.org/licenses/>.
 *
 * @category VuFind
 * @package  Tests
 * @author   Mario Trojan <mario.trojan@uni-tuebingen.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */

namespace VuFindTest\Http;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamInterface;
use VuFind\Exception\HttpDownloadException;
use VuFind\Http\CachingDownloader;
use VuFind\Http\GuzzleService;
use VuFindTest\Feature\ConfigRelatedServicesTrait;

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
    use ConfigRelatedServicesTrait;

    /**
     * Data provider for testDownload
     *
     * @return array
     */
    public static function downloadProvider(): array
    {
        return [
            'cache enabled' => [true],
            'cache disabled' => [false],
        ];
    }

    /**
     * Test a download
     *
     * @param bool $cacheEnabled Is the cache enabled?
     *
     * @return void
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('downloadProvider')]
    public function testDownload(bool $cacheEnabled): void
    {
        $container = new \VuFindTest\Container\MockContainer($this);

        $testUrl = 'https://dummyjson.com/products/1';
        $testBody = '{"id":1,"title":"iPhone 9"}';
        $testCacheKey = md5($testUrl);

        // GuzzleService
        $service = $this->createMock(GuzzleService::class);

        $stream = $this->createMock(StreamInterface::class);
        $stream->expects($this->any())->method('getContents')->willReturn($testBody);
        $stream->expects($this->once())->method('rewind');

        $response = $this->createMock(ResponseInterface::class);
        $response->method('getStatusCode')->willReturn(200);
        $response->expects($this->exactly(2))->method('getBody')->willReturn($stream);

        $service->expects($this->once())->method('get')->with($testUrl)->willReturn($response);

        // cacheManager
        $storage = $this->createMock(\Laminas\Cache\Storage\StorageInterface::class);
        $cacheManagerMock = $container->createMock(\VuFind\Cache\Manager::class);

        if ($cacheEnabled) {
            $storage->expects($this->once())->method('hasItem')->with($testCacheKey)->willReturn(false);
            $storage->expects($this->once())->method('addItem')->with($testCacheKey, $testBody);

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
        } else {
            $storage->expects($this->never())->method('hasItem');
            $storage->expects($this->never())->method('addItem');

            $cacheManagerMock
                ->expects($this->never())
                ->method('addDownloaderCache');
            $cacheManagerMock
                ->expects($this->never())
                ->method('getCache');
        }

        // configManager
        $configManagerMock = $this->getMockConfigManager();

        // downloader
        $downloader = new CachingDownloader($cacheManagerMock, $configManagerMock, $cacheEnabled);
        $downloader->setGuzzleService($service);

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
    public function testException(): void
    {
        $this->expectException(HttpDownloadException::class);

        $container = new \VuFindTest\Container\MockContainer($this);

        $testUrl = 'https://mock.codes/404';
        $testCacheKey = md5($testUrl);

        // GuzzleService
        $service = $this->createMock(GuzzleService::class);
        $service->expects($this->once())
            ->method('get')
            ->with($testUrl)
            ->willThrowException(new \Exception('Download failed (404): ' . $testUrl));

        // cacheManager
        $storage = $this->createMock(\Laminas\Cache\Storage\StorageInterface::class);

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
        $configManagerMock = $this->getMockConfigManager();

        // downloader
        $downloader = new CachingDownloader($cacheManagerMock, $configManagerMock, true);
        $downloader->setGuzzleService($service);

        $downloader->download(
            $testUrl
        );
    }
}
