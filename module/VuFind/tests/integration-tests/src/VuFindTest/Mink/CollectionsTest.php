<?php

/**
 * Mink test class for basic collection functionality.
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2017.
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
 * @link     https://vufind.org Main Page
 */

namespace VuFindTest\Mink;

use Behat\Mink\Element\Element;

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
    /**
     * Go to a collection page.
     *
     * @return Element
     */
    protected function goToCollection()
    {
        $session = $this->getMinkSession();
        $path = '/Collection/topcollection1';
        $session->visit($this->getVuFindUrl() . $path);
        return $session->getPage();
    }

    /**
     * Go to a collection's hierarchy tab.
     *
     * @return Element
     */
    protected function goToCollectionHierarchy()
    {
        $session = $this->getMinkSession();
        $path = '/Collection/subcollection1/HierarchyTree';
        $session->visit($this->getVuFindUrl() . $path);
        return $session->getPage();
    }

    /**
     * Test that a collection contains records.
     *
     * @return void
     */
    public function testBasic()
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
        $page = $this->goToCollection();
        $results = $page->findAll('css', '.result');
        $this->assertCount(7, $results);
    }

    /**
     * Test that the keyword filter feature works correctly.
     *
     * @return void
     */
    public function testKeywordFilter()
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
        $page = $this->goToCollection();
        $input = $this->findCss($page, '#keywordFilter_lookfor');
        $input->setValue('Subcollection 2');
        $this->findCss($page, '#keywordFilterForm .btn')->press();

        $this->waitStatement('$(".result").length === 2');
    }

    /**
     * Test that the collection hierarchy tab interface works.
     *
     * @return void
     */
    public function testContextLinks()
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
        $page = $this->goToCollection();
        $this->findCss($page, '.hierarchyTreeLink');

        $page = $this->goToCollectionHierarchy();
        $this->waitForPageLoad($page);
        $this->assertEquals(
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
}
