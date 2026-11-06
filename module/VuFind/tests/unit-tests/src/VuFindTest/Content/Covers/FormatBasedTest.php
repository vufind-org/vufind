<?php

/**
 * FormatBased cover loader unit tests.
 *
 * PHP version 8
 *
 * Copyright (C) Universitätsbibliothek Mannheim 2026.
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
 * @author   Stefan Weil <sw@weilnetz.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org
 */

namespace VuFindTest\Content\Covers;

use VuFind\Content\Covers\FormatBased;
use VuFind\Record\Loader as RecordLoader;

use function dirname;

/**
 * Unit tests for FormatBased cover loader.
 *
 * @category VuFind
 * @package  Tests
 * @author   Stefan Weil <sw@weilnetz.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org
 */
class FormatBasedTest extends \PHPUnit\Framework\TestCase
{
    /**
     * Directory holding test images.
     *
     * @var string
     */
    protected $imageDir;

    /**
     * Standard setup method.
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->imageDir = sys_get_temp_dir() . '/vufind_formatbased_test_' . uniqid();
        mkdir($this->imageDir, 0o777, true);
        foreach (['book.png', 'journal.jpg', 'default.png'] as $file) {
            file_put_contents($this->imageDir . '/' . $file, 'fake image data');
        }
    }

    /**
     * Standard teardown method.
     *
     * @return void
     */
    protected function tearDown(): void
    {
        foreach (glob($this->imageDir . '/*') ?: [] as $file) {
            unlink($file);
        }
        rmdir($this->imageDir);
        parent::tearDown();
    }

    /**
     * Create a cover loader with a mocked record loader returning the
     * given formats.
     *
     * @param array $formats Formats reported by the record driver
     * @param array $args    Constructor arguments after the record loader
     *
     * @return FormatBased
     */
    protected function createLoader(array $formats, array $args = []): FormatBased
    {
        $driver = new class ($formats) {
            /**
             * Constructor.
             *
             * @param array $formats Formats reported by the record driver
             *
             * @return void
             */
            public function __construct(protected array $formats)
            {
            }

            /**
             * Get the formats reported by the record driver.
             *
             * @return array
             */
            public function getFormats(): array
            {
                return $this->formats;
            }
        };
        $recordLoader = $this->createMock(RecordLoader::class);
        $recordLoader
            ->method('load')
            ->willReturn($driver);
        return new FormatBased($recordLoader, ...$args);
    }

    /**
     * Test that the loader only supports requests with a recordid.
     *
     * @return void
     */
    public function testSupports(): void
    {
        $loader = $this->createLoader([], [$this->imageDir]);
        $this->assertTrue($loader->supports(['recordid' => '1']));
        $this->assertFalse($loader->supports(['isbn' => new \VuFindCode\ISBN('123')]));
        $this->assertFalse($loader->supports([]));
    }

    /**
     * Test that the loader allows caching.
     *
     * @return void
     */
    public function testCacheAllowed(): void
    {
        $loader = $this->createLoader([], [$this->imageDir]);
        $this->assertTrue($loader->isCacheAllowed());
    }

    /**
     * Test that an image from image_dir is returned for a known format.
     *
     * @return void
     */
    public function testImageDirLookup(): void
    {
        $loader = $this->createLoader(['book'], [$this->imageDir]);
        $this->assertSame(
            'file://' . $this->imageDir . '/book.png',
            $loader->getUrl(null, 'small', ['recordid' => '1', 'source' => 'Solr'])
        );
    }

    /**
     * Test that jpg files in image_dir are found, too.
     *
     * @return void
     */
    public function testImageDirLookupJpg(): void
    {
        $loader = $this->createLoader(['journal'], [$this->imageDir]);
        $this->assertSame(
            'file://' . $this->imageDir . '/journal.jpg',
            $loader->getUrl(null, 'small', ['recordid' => '1', 'source' => 'Solr'])
        );
    }

    /**
     * Test that an explicit mapping takes precedence over image_dir.
     *
     * @return void
     */
    public function testMappingPrecedence(): void
    {
        $other = $this->imageDir . '/book.png';
        $loader = $this->createLoader(['book'], [$this->imageDir, ['book' => $other . '.copy']]);
        file_put_contents($other . '.copy', 'fake image data');
        $this->assertSame(
            'file://' . $other . '.copy',
            $loader->getUrl(null, 'small', ['recordid' => '1', 'source' => 'Solr'])
        );
    }

    /**
     * Test that http(s) URLs are passed through unchanged.
     *
     * @return void
     */
    public function testHttpUrlPassthrough(): void
    {
        $loader = $this->createLoader(
            ['book'],
            ['', ['book' => 'https://example.com/book.png']]
        );
        $this->assertSame(
            'https://example.com/book.png',
            $loader->getUrl(null, 'small', ['recordid' => '1', 'source' => 'Solr'])
        );
    }

    /**
     * Test that unknown formats fall back to the default image.
     *
     * @return void
     */
    public function testDefaultImage(): void
    {
        $loader = $this->createLoader(['Map'], [$this->imageDir]);
        $this->assertSame(
            'file://' . $this->imageDir . '/default.png',
            $loader->getUrl(null, 'small', ['recordid' => '1', 'source' => 'Solr'])
        );
    }

    /**
     * Test that an explicit default setting is used.
     *
     * @return void
     */
    public function testExplicitDefault(): void
    {
        $other = $this->imageDir . '/default.png';
        $loader = $this->createLoader(['Map'], ['', [], $other]);
        $this->assertSame(
            'file://' . $other,
            $loader->getUrl(null, 'small', ['recordid' => '1', 'source' => 'Solr'])
        );
    }

    /**
     * Test that false is returned when no image is configured.
     *
     * @return void
     */
    public function testNoConfiguredImage(): void
    {
        $loader = $this->createLoader(['Map']);
        $this->assertFalse($loader->getUrl(null, 'small', ['recordid' => '1', 'source' => 'Solr']));
    }

    /**
     * Test that format values containing path separators cannot escape
     * the image directory.
     *
     * @return void
     */
    public function testPathTraversalSanitization(): void
    {
        // If sanitization were broken, "../evil" would resolve to
        // <imageDir>/../evil.png:
        file_put_contents(dirname($this->imageDir) . '/evil.png', 'sentinel');
        $loader = $this->createLoader(['../evil'], [$this->imageDir]);
        $this->assertSame(
            'file://' . $this->imageDir . '/default.png',
            $loader->getUrl(null, 'small', ['recordid' => '1', 'source' => 'Solr'])
        );
        unlink(dirname($this->imageDir) . '/evil.png');
    }

    /**
     * Test that records without a format fall back to the default image.
     *
     * @return void
     */
    public function testEmptyFormat(): void
    {
        $loader = $this->createLoader([], [$this->imageDir]);
        $this->assertSame(
            'file://' . $this->imageDir . '/default.png',
            $loader->getUrl(null, 'small', ['recordid' => '1', 'source' => 'Solr'])
        );
    }
}
