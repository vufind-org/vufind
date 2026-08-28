<?php

/**
 * Tests covering embedded content in record tabs.
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2026.
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
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */

namespace VuFindTest\Mink;

use Behat\Mink\Element\Element;
use Generator;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Tests covering embedded content in record tabs.
 *
 * @category VuFind
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Juha Luoma <juha.luoma@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */
class RecordContentTest extends \VuFindTest\Integration\MinkTestCase
{
    /**
     * Load a record page for testing.
     *
     * @param string $id ID of record to load.
     *
     * @return Element
     */
    protected function gotoRecord(string $id = '0001732009-0'): Element
    {
        $session = $this->getMinkSession();
        $session->visit($this->getVuFindUrl() . '/Record/' . urlencode($id));
        return $session->getPage();
    }

    /**
     * Data provider for testContent().
     *
     * @return Generator<string, array>
     */
    public static function contentProvider(): Generator
    {
        yield 'author notes' => ['authorNotes', 'description', 'Demo author note ISBN: 9780822307020'];
        yield 'excerpt' => ['excerpts', 'excerpt', 'Demo excerpt ISBN: 9780822307020'];
        yield 'review' => ['reviews', 'reviews', 'Demo review ISBN: 9780822307020'];
        yield 'summary' => ['summaries', 'description', 'Demo summary ISBN: 9780822307020'];
        yield 'table of contents' => ['toc', 'toc', 'Demo TOC ISBN: 9780822307020'];
    }

    /**
     * Test content embedding using demo plugins.
     *
     * @param string $contentType Type of content to test (key in Content config)
     * @param string $tab         Tab to click on to find embedded content
     * @param string $expected    Text expected in tab
     *
     * @return void
     */
    #[DataProvider('contentProvider')]
    public function testContent(string $contentType, string $tab, string $expected): void
    {
        // Set up configuration:
        $this->changeConfigs(
            ['config' => ['Content' => [$contentType => 'demo']]]
        );
        // Go to a record view
        $page = $this->gotoRecord();
        $this->clickCss($page, '#tab-button-' . $tab);
        $this->waitForPageLoad($page);
        $this->assertStringContainsString(
            $expected,
            $this->findCssAndGetText($page, '#tab-pane-' . $tab)
        );
    }
}
