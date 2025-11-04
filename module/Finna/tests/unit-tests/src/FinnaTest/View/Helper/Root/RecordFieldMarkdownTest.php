<?php

/**
 * RecordFieldMarkdown Test Class
 *
 * PHP version 8
 *
 * Copyright (C) The National Library of Finland 2022.
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
 * @author   Aleksi Peebles <aleksi.peebles@helsinki.fi>
 * @license  https://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */

namespace FinnaTest\View\Helper\Root;

use Finna\View\Helper\Root\RecordFieldMarkdown;

/**
 * RecordFieldMarkdown Test Class
 *
 * @category VuFind
 * @package  Tests
 * @author   Aleksi Peebles <aleksi.peebles@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */
class RecordFieldMarkdownTest extends \PHPUnit\Framework\TestCase
{
    use \VuFindTest\Feature\ViewTrait;
    use \FinnaTest\Traits\ViewTrait;

    /**
     * Get view helper to test.
     *
     * @return RecordFieldMarkdown
     */
    protected function getHelper(): RecordFieldMarkdown
    {
        $view = $this->getPhpRenderer(
            [
                'cleanHtml' => $this->getCleanHtml([]),
            ],
            'finna2'
        );
        $markdown = new RecordFieldMarkdown(
            new \Finna\Service\RecordFieldMarkdown()
        );
        $markdown->setView($view);
        return $markdown;
    }

    /**
     * Test basic markdown conversion with the default soft break
     *
     * @return void
     */
    public function testRecordFieldMarkdown(): void
    {
        $converted = $this->getHelper()->toHtml($this->getTestMarkdown());
        $expected = <<<EOT
            <p># Heading<br>**bold**<br>[link](https://vufind.org/vufind/)<br>&lt;h1&gt;HTML heading&lt;/h1&gt;</p>
            <p>Another markdown paragraph<br>containing a line break</p>

            EOT;
        $this->assertEquals($expected, $converted);
    }

    /**
     * Test basic markdown conversion with a provided soft break
     *
     * @return void
     */
    public function testRecordFieldMarkdownWithProvidedSoftBreak(): void
    {
        $converted = $this->getHelper()->toHtml($this->getTestMarkdown(), "\n");
        $expected = <<<EOT
            <p># Heading
            **bold**
            [link](https://vufind.org/vufind/)
            &lt;h1&gt;HTML heading&lt;/h1&gt;</p>
            <p>Another markdown paragraph
            containing a line break</p>

            EOT;
        $this->assertEquals($expected, $converted);
    }

    /**
     * Return test Markdown input
     *
     * @return string
     */
    protected function getTestMarkdown(): string
    {
        return <<<EOT
            # Heading
            **bold**
            [link](https://vufind.org/vufind/)
            <h1>HTML heading</h1>

            Another markdown paragraph
            containing a line break
            EOT;
    }
}
