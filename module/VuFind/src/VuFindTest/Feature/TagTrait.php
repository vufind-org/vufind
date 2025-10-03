<?php

/**
 * Trait for working with tags in Mink tests.
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2025.
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

namespace VuFindTest\Feature;

use Behat\Mink\Element\Element;

/**
 * Trait for working with tags in Mink tests.
 *
 * @category VuFind
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */
trait TagTrait
{
    /**
     * Add tags to a record
     *
     * @param Element $page Page object
     * @param string  $tags Tag(s) to add (space delimited string formatted for the tag input box)
     * @param ?string $user Username to log in with (null if already logged in)
     * @param ?string $pass Password to log in with (null if already logged in)
     *
     * @return void
     */
    protected function addTagsToRecord(
        Element $page,
        string $tags,
        ?string $user = null,
        ?string $pass = null
    ): void {
        $this->clickCss($page, '.tag-record');
        // Login if necessary
        if (!empty($user) && !empty($pass)) {
            $this->fillInLoginForm($page, $user, $pass);
            $this->submitLoginForm($page);
        }
        // Add tags
        $this->findCssAndSetValue($page, '.modal #addtag_tag', $tags);
        $this->clickCss($page, '.modal-body .btn.btn-primary');
        $this->waitForPageLoad($page);
        $this->assertEquals('Tags Saved', $this->findCssAndGetText($page, '.modal-body .alert-success'));
        $this->closeLightbox($page);
    }

    /**
     * Extract user tags from a page element.
     *
     * @param Element $page Page containing tags
     *
     * @return string[]
     */
    protected function getTagsFromPage(Element $page): array
    {
        $tags = $page->findAll('css', '.tagList .tag');
        $tvals = [];
        foreach ($tags as $t) {
            $tvals[] = $this->findCssAndGetText($t, 'a');
        }
        sort($tvals);
        return $tvals;
    }

    /**
     * Set up and access the Tag Admin page.
     *
     * @param string $subPage The tag admin sub-page (optional)
     *
     * @return Element
     */
    protected function goToTagAdmin(string $subPage = ''): Element
    {
        $this->changeConfigs(
            [
                'config' => [
                    'Site' => ['admin_enabled' => 1],
                    'Social' => ['case_sensitive_tags' => 'true'],
                ],
            ],
        );
        $session = $this->getMinkSession();
        $session->visit($this->getVuFindUrl('/Admin/Tags' . $subPage));
        return $session->getPage();
    }
}
