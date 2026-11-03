<?php

/**
 * ContentBlock test class.
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

/**
 * ContentBlock test class.
 *
 * @category VuFind
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */
class ContentBlockTest extends \VuFindTest\Integration\MinkTestCase
{
    /**
     * Test the Recommend ContentBlock.
     *
     * @return void
     */
    public function testRecommend(): void
    {
        $this->changeConfigs(
            [
                'searches' => [
                    'HomePage' => ['content' => ['Recommend:Solr:CatalogResults']],
                ],
            ]
        );
        // If everything is working, the heading from the CatalogResults module should show on the home page:
        $page = $this->getSearchHomePage();
        $this->assertSame('Catalog Results', $this->findCssAndGetText($page, 'h2'));
    }
}
