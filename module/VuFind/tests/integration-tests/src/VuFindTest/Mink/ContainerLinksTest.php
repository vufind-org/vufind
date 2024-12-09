<?php

/**
 * Mink test class for simple container links functionality (including collection routing).
 *
 * PHP version 8
 *
 * Copyright (C) The National Library of Finland 2023.
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
 * @link     https://vufind.org/wiki/indexing:hierarchies_and_collections Hierarchies and Collections
 */

namespace VuFindTest\Mink;

use Behat\Mink\Element\Element;

/**
 * Mink test class for simple container links functionality (including collection routing).
 *
 * @category VuFind
 * @package  Tests
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/indexing:hierarchies_and_collections Hierarchies and Collections
 */
class ContainerLinksTest extends \VuFindTest\Integration\MinkTestCase
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
     * Test default behavior of container links.
     *
     * Test handling of container fields when simpleContainerLinks is false
     * (default behavior).
     *
     * @return void
     */
    public function testDefaultContainerLinks(): void
    {
        $page = $this->performSearch('id:jnl1-1');
        $url = $this->findCss($page, '.result-body a.container-link')->getAttribute('href');
        $this->assertMatchesRegularExpression(
            '{.*/Search/Results}',
            parse_url($url, PHP_URL_PATH)
        );
        parse_str(parse_url($url, PHP_URL_QUERY), $query);
        $this->assertEquals('JournalTitle', $query['type']);
        $this->assertEquals('"Arithmetic Facts"', $query['lookfor']);
    }

    /**
     * Test simple container linking with ID.
     *
     * @return void
     */
    public function testSimpleContainerLinksWithID(): void
    {
        $this->changeConfigs(
            [
            'config' => [
                'Collections' => [
                    'collections' => true,
                ],
                'Hierarchy' => [
                    'simpleContainerLinks' => true,
                ],
            ],
            'HierarchyDefault' => [
                'Collections' => [
                    'link_type' => 'All',
                ],
            ],
            ]
        );
        $page = $this->performSearch('id:jnl1-1');
        // Check parent link:
        $parentLink = $this->findCss($page, '.result-body a.container-link');
        $this->assertMatchesRegularExpression(
            '{.*/Record/jnl1\?checkRoute=1&sid=\d+}',
            $parentLink->getAttribute('href')
        );
        // Go to parent and check proper routing to collection with sid included:
        $parentLink->click();
        $this->waitForPageLoad($page);
        $this->assertMatchesRegularExpression(
            '{/Collection/jnl1\?sid=\d+}',
            $this->getMinkSession()->getCurrentUrl()
        );
    }
}
