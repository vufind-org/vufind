<?php

/**
 * Unit tests for Syndetics cover loader.
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
 * @author   Damien Guillaume <damieng@msu.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org
 */

namespace VuFindTest\Content\Covers;

use Laminas\Config\Config;
use VuFind\Content\Covers\Syndetics;
use VuFind\Http\CachingDownloader;
use VuFindCode\ISBN;

/**
 * Unit tests for Syndetics cover loader.
 *
 * @category VuFind
 * @package  Tests
 * @author   Damien Guillaume <damieng@msu.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org
 */
class SyndeticsTest extends \PHPUnit\Framework\TestCase
{
    use \VuFindTest\Feature\FixtureTrait;

    /**
     * Get Syndetics object to test.
     *
     * @param ?string $fixtureFile                    Fixture file to return (null to skip downloader initialization)
     * @param ?string $isbn                           ISBN
     * @param ?bool   $useSyndeticsCoverImageFallback False to check metadata first
     *
     * @return Syndetics
     */
    protected function getLoader(
        ?string $fixtureFile = null,
        ?string $isbn = '',
        ?bool $useSyndeticsCoverImageFallback = true
    ): Syndetics {
        $loader = new Syndetics(new Config([
            'use_ssl' => false,
            'use_syndetics_cover_image_fallback' => $useSyndeticsCoverImageFallback,
        ]));
        if ($fixtureFile) {
            $mockDownloader = $this->getMockBuilder(CachingDownloader::class)
                ->disableOriginalConstructor()
                ->getMock();
            $fixture = $this->getFixture($fixtureFile);
            $mockDownloader->expects($this->once())->method('download')
                ->with($this->equalTo("http://syndetics.com/index.aspx?client=test&isbn=$isbn/index.xml"))
                ->will($this->returnValue($fixture));
            $loader->setCachingDownloader($mockDownloader);
        }
        return $loader;
    }

    /**
     * Get image URL with a check the image is in the Syndetics metadata.
     *
     * @return void
     */
    public function testValidCoverLoadingWhenCheckingMetadata(): void
    {
        $loader = $this->getLoader('content/covers/syndetics-metadata_with_images.xml', '9780520080607', false);
        $this->assertEquals(
            'http://syndetics.com/index.aspx?client=test&isbn=9780520080607/SC.GIF',
            $loader->getUrl(
                'test',
                'small',
                ['isbn' => new ISBN('9780520080607')]
            )
        );
    }

    /**
     * Get image URL without a check the image is in the Syndetics metadata.
     *
     * @return void
     */
    public function testValidCoverLoadingWithoutCheckingMetadata(): void
    {
        $loader = $this->getLoader(null, '9780709933847', true);
        $this->assertEquals(
            'http://syndetics.com/index.aspx?client=test&isbn=9780709933847/SC.GIF',
            $loader->getUrl(
                'test',
                'small',
                ['isbn' => new ISBN('9780709933847')]
            )
        );
    }

    /**
     * Not finding an image filename in the Syndetics metadata.
     *
     * @return void
     */
    public function testUnavailableCoverLoading(): void
    {
        $loader = $this->getLoader('content/covers/syndetics-metadata_without_images.xml', '9780709933847', false);
        $this->assertFalse(
            $loader->getUrl(
                'test',
                'small',
                ['isbn' => new ISBN('9780709933847')]
            )
        );
    }

    /**
     * Test missing ISBN
     *
     * @return void
     */
    public function testMissingIsbn(): void
    {
        $loader = $this->getLoader();
        $this->assertFalse($loader->getUrl(
            'test',
            'small',
            []
        ));
    }

    /**
     * Test unknown size without using metadata
     *
     * @return void
     */
    public function testUnknownSize(): void
    {
        $loader = $this->getLoader();
        $this->assertFalse($loader->getUrl(
            'test',
            'tiny',
            ['isbn' => new ISBN('9780520080607')]
        ));
    }

    /**
     * Test unknown size when using metadata
     *
     * @return void
     */
    public function testSizeNotFoundInMetadata(): void
    {
        $loader = $this->getLoader('content/covers/syndetics-metadata_with_images.xml', '9780520080607', false);
        $this->assertFalse($loader->getUrl(
            'test',
            'tiny',
            ['isbn' => new ISBN('9780520080607')]
        ));
    }
}
