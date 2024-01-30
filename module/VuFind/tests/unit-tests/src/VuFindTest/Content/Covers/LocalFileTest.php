<?php

/**
 * Unit tests for LocalFile cover loader.
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2024.
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

use VuFind\Content\Covers\LocalFile;
use VuFindCode\ISBN;

/**
 * Unit tests for LocalFile cover loader.
 *
 * @category VuFind
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org
 */
class LocalFileTest extends \PHPUnit\Framework\TestCase
{
    use \VuFindTest\Feature\FixtureTrait;

    /**
     * Base path to image fixtures.
     *
     * @var string
     */
    protected $fixtureBase;

    /**
     * Standard setup method.
     *
     * @return void
     */
    protected function setUp(): void
    {
        $this->fixtureBase = realpath($this->getFixtureDir() . '/content/covers/localfile');
    }

    /**
     * Data provider for testValidCoverLoading().
     *
     * @return array
     */
    public static function validCoverProvider(): array
    {
        return [
            'source gif in size folder' => ['small/x.gif', '%size%/%source%.gif', ['source' => 'x']],
            'hard-coded path in vufind-home' => [
                'invalidsize/x.gif',
                '%vufind-home%/module/VuFind/tests/fixtures/content/covers/localfile/invalidsize/x.gif',
                [],
                'small',
                false,
            ],
            'isbn10 gif via anyimage' => ['0739313126.gif', '%isbn10%.%anyimage%', ['isbn' => new ISBN('0739313126')]],
            'isbn13 jpg via anyimage' => [
                '9780739313121.jpg',
                '%isbn13%.%anyimage%',
                ['isbn' => new ISBN('0739313126')],
            ],
        ];
    }

    /**
     * Run a single test.
     *
     * @param string $keyPattern         Match pattern to use in key
     * @param array  $imageParams        Image parameters
     * @param string $size               Size value to use
     * @param bool   $includeFixturePath Include fixture path in key pattern?
     *
     * @return string|false
     */
    protected function runSingleLoaderTest(
        string $keyPattern,
        array $imageParams,
        string $size = 'small',
        bool $includeFixturePath = true
    ) {
        $loader = new LocalFile();
        return $loader->getUrl(
            ($includeFixturePath ? "$this->fixtureBase/" : '') . $keyPattern,
            $size,
            $imageParams
        );
    }

    /**
     * Test cover loading
     *
     * @param string $expectedFilename   Fixture file matching key
     * @param string $keyPattern         Match pattern to use in key
     * @param array  $imageParams        Image parameters
     * @param string $size               Size value to use
     * @param bool   $includeFixturePath Include fixture path in key pattern?
     *
     * @return void
     *
     * @dataProvider validCoverProvider
     */
    public function testValidCoverLoading(
        string $expectedFilename,
        string $keyPattern,
        array $imageParams,
        string $size = 'small',
        bool $includeFixturePath = true
    ): void {
        $this->assertEquals(
            "file://$this->fixtureBase/$expectedFilename",
            $this->runSingleLoaderTest($keyPattern, $imageParams, $size, $includeFixturePath)
        );
    }

    /**
     * Data provider for testInvalidCover()
     *
     * @return array
     */
    public static function invalidCoverProvider(): array
    {
        return [
            'missing ISBN' => ['%isbn10%.%anyimage%'],
            'invalid size' => ['%size%/%source%.gif', ['source' => 'x'], 'invalidsize'],
            'non-image file' => ['%vufind-home%/README.md'],
        ];
    }

    /**
     * Test bad parameter/key combinations.
     *
     * @param string $keyPattern         Match pattern to use in key
     * @param array  $imageParams        Image parameters
     * @param string $size               Size value to use
     * @param bool   $includeFixturePath Include fixture path in key pattern?
     *
     * @return void
     *
     * @dataProvider invalidCoverProvider
     */
    public function testInvalidCover(
        string $keyPattern,
        array $imageParams = [],
        string $size = 'small',
        bool $includeFixturePath = true
    ): void {
        $this->assertFalse(
            $this->runSingleLoaderTest($keyPattern, $imageParams, $size, $includeFixturePath)
        );
    }
}
