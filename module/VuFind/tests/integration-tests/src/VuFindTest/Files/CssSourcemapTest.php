<?php

/**
 * Check for non-standard paths in CSS source maps.
 *
 * PHP version 8
 *
 * Copyright (C) Hebis Verbundzentrale 2026.
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
 * @author   Thomas Wagener <wagener@hebis.uni-frankfurt.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */

namespace VuFindTest\Files;

/**
 * Check for non-standard paths in CSS source maps.
 *
 * @category VuFind
 * @package  Tests
 * @author   Thomas Wagener <wagener@hebis.uni-frankfurt.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */
class CssSourcemapTest extends \PHPUnit\Framework\TestCase
{
    /**
     * Data provider for source map test.
     *
     * @return \Iterator
     */
    public static function fileProvider(): \Iterator
    {
        yield 'bootstrap5' => ['themes/bootstrap5/css/compiled.css.map'];
        yield 'local_theme_example' => ['themes/local_theme_example/css/compiled.css.map'];
        yield 'sandal5' => ['themes/sandal5/css/compiled.css.map'];
    }

    /**
     * Test for non-standard paths in source map files.
     *
     * @param string $sourceMapFile Source map file to check
     *
     * @return void
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('fileProvider')]
    public function testForNonStandardSourceMapPaths(string $sourceMapFile): void
    {
        $fileContents = file_get_contents($sourceMapFile);

        $this->assertNotFalse($fileContents, 'Could not read file ' . $sourceMapFile);

        $jsonContent = json_decode($fileContents, true);

        foreach ($jsonContent['sources'] as $source) {
            if (str_starts_with($source, 'file://')) {
                $this->assertStringStartsWith(
                    'file:///usr/local/vufind/',
                    $source,
                    'Source does not start with standard path!'
                );
            }
        }
    }
}
