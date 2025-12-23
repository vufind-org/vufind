<?php

/**
 * CleanHtml view helper Test Class
 *
 * PHP version 8
 *
 * Copyright (C) The National Library of Finland 2025.
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
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */

namespace VuFindTest\View\Helper\Root;

use VuFindTest\Feature\ViewTrait;

/**
 * CleanHtml view helper Test Class
 *
 * @category VuFind
 * @package  Tests
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */
class CleanHtmlTest extends \PHPUnit\Framework\TestCase
{
    use ViewTrait;

    /**
     * Data provider for testCleanHtml
     *
     * @return \Iterator
     */
    public static function cleanHtmlProvider(): \Iterator
    {
        $link = '<a href="https://vufind.org/">VuFind</a>';
        $linkTargetBlank
            = '<a href="https://vufind.org/" rel="nofollow noreferrer noopener" target="_blank">VuFind</a>';
        $script = '<script>console.log("foo");</script>';
        $summaryDetails = '<summary>Summary</summary> <details>Details</details>';
        yield 'plain string' => ['plain string', null, null, 'plain string'];
        yield 'link' => [$link, null, null, $link];
        yield 'link + script' => [$link . $script, null, null, $link];
        yield 'link + script + link' => [$link . $script . $link, null, null, $link . $link];
        yield 'link with target="_blank"' => [$link, true, null, $linkTargetBlank];
        yield 'link in heading' => [$link, null, 'heading', $link];
        yield 'summary and details in default context' => [$summaryDetails, null, null, $summaryDetails];
        yield 'summary and details in heading' => [$summaryDetails, null, 'heading', 'Summary Details'];
    }

    /**
     * Test cleanHtml
     *
     * @param string  $input       Input string
     * @param ?bool   $targetBlank Add target="_blank" to external links?
     * @param ?string $context     Rendering context or null for default
     * @param string  $expected    Expected result
     *
     * @return void
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('cleanHtmlProvider')]
    public function testCleanHtml(string $input, ?bool $targetBlank, ?string $context, $expected): void
    {
        $cleanHtml = $this->createCleanHtmlHelper();
        $extraParams = [];
        if (null !== $targetBlank) {
            $extraParams['targetBlank'] = $targetBlank;
        }
        if (null !== $context) {
            $extraParams['context'] = $context;
        }
        $result = $cleanHtml($input, ...$extraParams);
        $this->assertEquals($expected, $result);
    }
}
