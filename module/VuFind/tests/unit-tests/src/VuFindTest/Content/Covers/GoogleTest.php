<?php

/**
 * Unit tests for Google cover loader.
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2023.
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
 * @link     https://vufind.org
 */

namespace VuFindTest\Content\Covers;

use VuFind\Content\Covers\Google;
use VuFind\Http\CachingDownloader;
use VuFindCode\ISBN;

/**
 * Unit tests for Google cover loader.
 *
 * @category VuFind
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org
 */
class GoogleTest extends \PHPUnit\Framework\TestCase
{
    use \VuFindTest\Feature\ConfigPluginManagerTrait;
    use \VuFindTest\Feature\FixtureTrait;

    /**
     * Get a callback to check the download function call.
     *
     * @param string          $body        Body for mock to return
     * @param string|string[] $expectedIds Identifier(s) expected in request URL
     *
     * @return callable
     */
    protected function getDownloadCallback(
        string $body,
        string|array $expectedIds,
    ): callable {
        return function ($url, $params, $callback) use ($body, $expectedIds) {
            $this->assertEquals(
                'https://books.google.com/books?jscmd=viewapi'
                . '&bibkeys=' . urlencode(implode(',', (array)$expectedIds)) . '&callback=addTheCover',
                $url
            );
            $this->assertEquals([], $params);
            $response = $this->createMock(\Laminas\Http\Response::class);
            $response->expects($this->any())->method('getBody')->willReturn($body);
            return $callback($response, $url);
        };
    }

    /**
     * Test cover loading with a single ISBN
     *
     * @return void
     */
    public function testValidCoverLoading(): void
    {
        $loader = new Google();

        $mockDownloader = $this->createMock(CachingDownloader::class);
        $downloadCallback = $this->getDownloadCallback(
            $this->getFixture('content/covers/google-cover.js'),
            'ISBN:9781612917986'
        );
        $mockDownloader->expects($this->once())->method('download')->willReturnCallback($downloadCallback);
        $loader->setCachingDownloader($mockDownloader);

        $this->assertEquals(
            'https://books.google.com/books/content'
            . '?id=dEMBBAAAQBAJ&printsec=frontcover&img=1&zoom=5&edge=curl',
            $loader->getUrl(
                '',
                'small',
                ['isbn' => new ISBN('1612917984')]
            )
        );
    }

    /**
     * Test cover loading with multiple IDs
     *
     * @return void
     */
    public function testValidCoverLoadingWithMultipleIds(): void
    {
        $loader = new Google();

        $mockDownloader = $this->createMock(CachingDownloader::class);
        $downloadCallback = $this->getDownloadCallback(
            $this->getFixture('content/covers/google-cover.js'),
            ['ISBN:9781612917986', 'ISBN:9780123456786', 'OCLC:1234']
        );
        $mockDownloader->expects($this->once())->method('download')->willReturnCallback($downloadCallback);
        $loader->setCachingDownloader($mockDownloader);

        $this->assertEquals(
            'https://books.google.com/books/content'
            . '?id=dEMBBAAAQBAJ&printsec=frontcover&img=1&zoom=5&edge=curl',
            $loader->getUrl(
                '',
                'small',
                ['isbns' => [new ISBN('1612917984'), new ISBN('0123456789')], 'oclc' => '1234']
            )
        );
    }

    /**
     * Test cover loading at a larger size
     *
     * @return void
     */
    public function testValidLargeCoverLoading(): void
    {
        $loader = new Google();

        $mockDownloader = $this->createMock(CachingDownloader::class);
        $downloadCallback = $this->getDownloadCallback(
            $this->getFixture('content/covers/google-cover.js'),
            'ISBN:9781612917986'
        );
        $mockDownloader->expects($this->once())->method('download')->willReturnCallback($downloadCallback);
        $loader->setCachingDownloader($mockDownloader);

        $this->assertEquals(
            'https://books.google.com/books/content'
            . '?id=dEMBBAAAQBAJ&printsec=frontcover&img=1&zoom=1&edge=curl',
            $loader->getUrl(
                '',
                'large',
                ['isbn' => new ISBN('1612917984')]
            )
        );
    }

    /**
     * Test successful transaction containing no thumbnails.
     *
     * @return void
     */
    public function testNoAvailableThumbnailLoading(): void
    {
        $loader = new Google();

        $mockDownloader = $this->createMock(CachingDownloader::class);
        $downloadCallback = $this->getDownloadCallback(
            $this->getFixture('content/covers/google-cover-no-thumbnail.js'),
            'ISBN:9781612917986'
        );
        $mockDownloader->expects($this->once())->method('download')->willReturnCallback($downloadCallback);
        $loader->setCachingDownloader($mockDownloader);

        $this->assertFalse(
            $loader->getUrl(
                '',
                'small',
                ['isbn' => new ISBN('1612917984')]
            )
        );
    }

    /**
     * Test successful transaction using OCLC number.
     *
     * @return void
     */
    public function testOCLCLoading(): void
    {
        $loader = new Google();

        $mockDownloader = $this->createMock(CachingDownloader::class);
        $downloadCallback = $this->getDownloadCallback(
            $this->getFixture('content/covers/google-cover-no-thumbnail.js'),
            'OCLC:1234'
        );
        $mockDownloader->expects($this->once())->method('download')->willReturnCallback($downloadCallback);
        $loader->setCachingDownloader($mockDownloader);

        $this->assertFalse(
            $loader->getUrl(
                '',
                'small',
                ['oclc' => '1234']
            )
        );
    }

    /**
     * Test invalid (empty) response
     *
     * @return void
     */
    public function testEmptyResponse(): void
    {
        $loader = new Google();

        $mockDownloader = $this->createMock(CachingDownloader::class);
        $downloadCallback = $this->getDownloadCallback('', 'ISBN:9781612917986');
        $mockDownloader->expects($this->once())->method('download')->willReturnCallback($downloadCallback);
        $loader->setCachingDownloader($mockDownloader);
        $this->expectExceptionMessage('Invalid response body (raw)');
        $loader->getUrl(
            '',
            'small',
            ['isbn' => new ISBN('1612917984')]
        );
    }

    /**
     * Test invalid (non-empty, non-parseable) response
     *
     * @return void
     */
    public function testInvalidNonEmptyResponse(): void
    {
        $loader = new Google();

        $mockDownloader = $this->createMock(CachingDownloader::class);
        $downloadCallback = $this->getDownloadCallback(
            $this->getFixture('content/covers/google-cover-invalid.js'),
            'ISBN:9781612917986'
        );
        $mockDownloader->expects($this->once())->method('download')->willReturnCallback($downloadCallback);
        $loader->setCachingDownloader($mockDownloader);
        $this->expectExceptionMessage('Invalid response body (json)');
        $loader->getUrl(
            '',
            'small',
            ['isbn' => new ISBN('1612917984')]
        );
    }

    /**
     * Test missing downloader
     *
     * @return void
     */
    public function testMissingDownloader(): void
    {
        $loader = new Google();
        $this->expectExceptionMessage('CachingDownloader initialization failed.');
        $loader->getUrl(
            '',
            'small',
            ['isbn' => new ISBN('0123456789')]
        );
    }

    /**
     * Test missing ISBN
     *
     * @return void
     */
    public function testMissingIsbn(): void
    {
        $loader = new Google();
        $this->assertFalse($loader->getUrl('', 'small', []));
    }
}
