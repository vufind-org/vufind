<?php

/**
 * Mink test class for basic collection functionality.
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2017-2026.
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
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Mink test class for basic collection functionality.
 *
 * @category VuFind
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */
class CollectionsTest extends \VuFindTest\Integration\MinkTestCase
{
    use \VuFindTest\Feature\RetryClickTrait;

    /**
     * Go to a collection page.
     *
     * @param string $basePath Record base path to use
     *
     * @return Element
     */
    protected function goToCollection(string $basePath = '/Collection'): Element
    {
        $session = $this->getMinkSession();
        $path = "$basePath/topcollection1";
        $session->visit($this->getVuFindUrl() . $path);
        return $session->getPage();
    }

    /**
     * Go to a collection's hierarchy tab.
     *
     * @param string $basePath Record base path to use
     *
     * @return Element
     */
    protected function goToCollectionHierarchy(string $basePath = '/Collection'): Element
    {
        $session = $this->getMinkSession();
        $path = "$basePath/subcollection1/HierarchyTree";
        $session->visit($this->getVuFindUrl() . $path);
        return $session->getPage();
    }

    /**
     * Data provider for testing both Collection and Search2Collection actions.
     *
     * @return \Generator
     */
    public static function basePathProvider(): \Iterator
    {
        yield 'Collection' => ['/Collection'];
        yield 'Search2Collection' => ['/Search2Collection'];
    }

    /**
     * Test that a collection contains records.
     *
     * @param string $basePath Base path
     *
     * @return void
     */
    #[DataProvider('basePathProvider')]
    public function testBasic(string $basePath): void
    {
        $this->changeConfigs(
            [
            'config' => [
                'Collections' => [
                    'collections' => true,
                ],
            ],
            'HierarchyDefault' => [
                'Collections' => [
                    'link_type' => 'Top',
                ],
            ],
            ]
        );
        $page = $this->goToCollection($basePath);
        $results = $page->findAll('css', '.result');
        $this->assertCount(7, $results);
    }

    /**
     * Data provider for testPubDateVisAjax.
     *
     * @return \Iterator
     */
    public static function recommendationPositionProvider(): \Iterator
    {
        // Top:
        yield ['top'];
        // Bottom:
        yield ['bottom'];
    }

    /**
     * Test that a collection can use the PubDateVisAjax recommendation module.
     *
     * @param string $recommendationPosition Position of PubDateVisAjax recommendation
     *
     * @return void
     */
    #[DataProvider('recommendationPositionProvider')]
    public function testPubDateVisAjax(string $recommendationPosition): void
    {
        $this->changeConfigs(
            [
                'config' => [
                    'Collections' => [
                        'collections' => true,
                    ],
                ],
                'Collection' => [
                    'Recommend' => [$recommendationPosition => 'PubDateVisAjax:true:publishDate'],
                ],
            ]
        );
        $page = $this->goToCollection();
        $this->waitForPageLoad($page);
        $results = $page->findAll('css', '.result');
        $this->assertCount(7, $results);
        $this->waitStatement(
            'document.querySelector(".datevis-input-from") '
            . '&& document.querySelector(".datevis-input-from").value === "1957"'
        );
        $this->assertEquals('1957', $this->findCssAndGetValue($page, '.datevis-input-from'));
        $this->assertEquals('1985', $this->findCssAndGetValue($page, '.datevis-input-to'));
    }

    /**
     * Test that the keyword filter feature works correctly.
     *
     * @param string $basePath Base path
     *
     * @return void
     */
    #[DataProvider('basePathProvider')]
    public function testKeywordFilter(string $basePath): void
    {
        $this->changeConfigs(
            [
                'config' => [
                    'Collections' => [
                        'collections' => true,
                    ],
                ],
                'HierarchyDefault' => [
                    'Collections' => [
                        'link_type' => 'Top',
                    ],
                ],
            ]
        );
        $page = $this->goToCollection($basePath);
        $input = $this->findCss($page, '#keywordFilter_lookfor');
        $input->setValue('Subcollection 2');
        $this->findCss($page, '#keywordFilterForm .btn')->press();

        $this->waitStatement('$(".result").length === 2');
    }

    /**
     * Test that the collection hierarchy tab interface works.
     *
     * @param string $basePath Base path
     *
     * @return void
     */
    #[DataProvider('basePathProvider')]
    public function testContextLinks(string $basePath): void
    {
        // link_type => 'All'
        $this->changeConfigs(
            [
                'config' => [
                    'Hierarchy' => [
                        'showTree' => true,
                    ],
                    'Collections' => [
                        'collections' => true,
                    ],
                ],
                'HierarchyDefault' => [
                    'Collections' => [
                        'link_type' => 'All',
                    ],
                ],
            ]
        );
        $page = $this->goToCollection($basePath);
        $this->findCss($page, '.hierarchyTreeLink');

        $page = $this->goToCollectionHierarchy();
        $this->waitForPageLoad($page);
        $this->assertSame(
            'Subcollection 1',
            trim($this->findCssAndGetText($page, '#tree-preview h2'))
        );
        $this->clickCss($page, 'a[data-record-id="colitem2"]');

        $this->waitStatement('$("#tree-preview h2").text().trim() === "Collection item 2"');

        $this->assertEquals(
            $this->getVuFindUrl() . '/Collection/subcollection1/HierarchyTree',
            $this->getMinkSession()->getCurrentUrl()
        );
    }

    /**
     * Test that bulk actions open in a lightbox when enabled.
     *
     * @param string $basePath Base path
     *
     * @return void
     */
    #[DataProvider('basePathProvider')]
    public function testBulkExportLightbox(string $basePath): void
    {
        $this->changeConfigs(
            [
                'config' => [
                    'Site' => ['showBulkOptions' => true],
                    'Collections' => ['collections' => true],
                ],
            ]
        );
        $page = $this->goToCollection($basePath);

        // Confirm that an appropriate message appears if no items are checked:
        $buttonSelector = '#ribbon-export';
        $alert = $this->openLightboxAndFindCss($page, $buttonSelector, '.modal-body .alert-danger');
        $this->assertSame(
            'No items were selected. '
            . 'Please click on a checkbox next to an item and try again.',
            $alert->getText()
        );

        // Now do it for real -- we should get a lightbox prompt.
        $page->find('css', '#addFormCheckboxSelectAll')->check();
        $this->waitStatement('$("input.checkbox-select-item:checked").length === 7');
        $this->clickCss($page, $buttonSelector);

        // Select EndNote option
        try {
            // We don't want to wait the full default timeout here since that wastes a lot
            // of time if a click failed to register; however, we shouldn't wait for too
            // short of a time, or else a slow response can break the test by causing a
            // double form submission.
            $select = $this->findCss($page, '#format', 1500);
        } catch (\Exception $e) {
            $this->retryClickWithResizedWindow($this->getMinkSession(), $page, $buttonSelector);
            $select = $this->findCss($page, '#format');
        }
        $select->selectOption('EndNote');

        // Do the export:
        $this->clickCss($page, '.form-cart-export input[name=submitButton]');
        $buttonText = $this->findCssAndGetText($page, '.alert .text-center .btn');
        $this->assertSame('Download File', $buttonText);
    }
}
