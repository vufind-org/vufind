<?php

/**
 * Class MarkdownTest
 *
 * PHP version 8
 *
 * Copyright (C) Moravian Library 2020.
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
 * @package  VuFindTest\View\Helper\Root
 * @author   Josef Moravec <moravec@mzk.cz>
 * @license  https://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://knihovny.cz Main Page
 */

namespace VuFindTest\View\Helper\Root;

use League\CommonMark\GithubFlavoredMarkdownConverter;
use VuFind\View\Helper\Root\Markdown;

/**
 * Markdown Test Class
 *
 * @category VuFind
 * @package  Tests
 * @author   Josef Moravec <moravec@mzk.cz>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */
class MarkdownTest extends \PHPUnit\Framework\TestCase
{
    use \VuFindTest\Feature\ViewTrait;

    /**
     * Get view helper to test.
     *
     * @return Markdown
     */
    protected function getHelper()
    {
        $view = $this->getPhpRenderer();
        $markdown = new Markdown(
            new GithubFlavoredMarkdownConverter(
                [
                    'html_input' => 'strip',
                    'allow_unsafe_links' => false,
                ]
            )
        );
        $markdown->setView($view);
        return $markdown;
    }

    /**
     * Test basic markdown conversion
     *
     * @return void
     */
    public function testMarkdown()
    {
        $markdown = "# Main heading\n## Second heading";
        $html = "<h1>Main heading</h1>\n<h2>Second heading</h2>\n";
        $this->assertEquals($html, ($this->getHelper())($markdown));
    }
}
