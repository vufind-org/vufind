<?php

/**
 * EscapeOrCleanHtml view helper Test Class
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
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301  USA
 *
 * @category VuFind
 * @package  Tests
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */

namespace VuFindTest\View\Helper\Root;

use VuFind\Escaper\Escaper;
use VuFind\String\PropertyString;
use VuFind\View\Helper\Root\EscapeOrCleanHtml;
use VuFindTest\Feature\ViewTrait;

/**
 * EscapeOrCleanHtml view helper Test Class
 *
 * @category VuFind
 * @package  Tests
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */
class EscapeOrCleanHtmlTest extends \PHPUnit\Framework\TestCase
{
    use ViewTrait;

    /**
     * Data provider for testEscapeOrCleanHtml
     *
     * @return array
     */
    public static function escapeOrCleanHtmlProvider(): array
    {
        $link = '<a href="https://vufind.org/">VuFind</a>';
        $div = '<div>Div</div>';
        $dnd = '<i>Dungeons &amp; Dragons</i>';
        return [
            'plain string' => ['plain string', null, null, 'default', [], 'plain string'],
            'link' => [$link, null, null, 'default', [], htmlentities($link)],
            'link as PropertyString' => [PropertyString::fromHtml($link), null, null, 'default', [], 'VuFind'],
            'link as PropertyString, allow HTML' => [
                PropertyString::fromHtml($link), null, true, 'default', [], $link,
            ],
            'link as PropertyString, allow by config, proper context' => [
                PropertyString::fromHtml($link), 'title', null, 'default', ['title' => true], $link,
            ],
            'link as PropertyString, allow by config, wrong context' => [
                PropertyString::fromHtml($link), null, null, 'default', ['title' => true], 'VuFind',
            ],
            'div as PropertyString, allow HTML' => [
                PropertyString::fromHtml($div), null, true, 'default', [], $div,
            ],
            'div as PropertyString, allow HTML, rendered in heading' => [
                PropertyString::fromHtml($div), null, true, 'heading', [], 'Div',
            ],
            'HTML containing entity, disallow HTML' => [
                PropertyString::fromHtml($dnd), null, false, 'heading', [], 'Dungeons &amp; Dragons',
            ],
        ];
    }

    /**
     * Test escapeOrCleanHtml
     *
     * @param string|PropertyString $input            Input string
     * @param ?string               $dataContext      Data context
     * @param ?bool                 $allowHtml        Allow HTML at all?
     * @param string                $renderingContext Rendering context
     * @param array                 $config           Data context configuration
     * @param string                $expected         Expected result
     *
     * @return void
     *
     * @dataProvider escapeOrCleanHtmlProvider
     */
    public function testEscapeOrCleanHtml(
        $input,
        ?string $dataContext,
        ?bool $allowHtml,
        string $renderingContext,
        array $config,
        string $expected
    ): void {
        $escaper = new Escaper(false);
        $cleanHtml = $this->createCleanHtmlHelper();
        $escapeOrCleanHtml = new EscapeOrCleanHtml($escaper, $cleanHtml, ['Allowed_HTML_Contexts' => $config]);
        $result = $escapeOrCleanHtml($input, $dataContext, $allowHtml, $renderingContext);
        $this->assertEquals($expected, $result);
    }

    /**
     * Test invoking without parameters
     *
     * @return void
     */
    public function testInvokeWithoutParameters(): void
    {
        $escaper = new Escaper(false);
        $cleanHtml = $this->createCleanHtmlHelper();
        $escapeOrCleanHtml = new EscapeOrCleanHtml($escaper, $cleanHtml, []);
        $this->assertEquals($escapeOrCleanHtml, $escapeOrCleanHtml());
    }
}
