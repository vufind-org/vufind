<?php

/**
 * Very simple Mink test class.
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2011.
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

/**
 * Very simple Mink test class.
 *
 * @category VuFind
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */
class BasicTest extends \VuFindTest\Integration\MinkTestCase
{
    /**
     * Test that the home page is available.
     *
     * @return void
     */
    public function testHomePage(): void
    {
        $page = $this->getSearchHomePage();
        $this->assertTrue(false !== strstr($page->getContent(), 'VuFind'));
    }

    /**
     * Test that AJAX availability status is working.
     *
     * @return void
     */
    public function testAjaxStatus(): void
    {
        // Search for a known record:
        $page = $this->getSearchHomePage();
        $this->findCss($page, '#searchForm_lookfor')
            ->setValue('id:testsample1');
        $this->clickCss($page, '.btn.btn-primary');
        $this->waitForPageLoad($page);

        // Check for sample driver location/call number in output (this will
        // only appear after AJAX returns):
        $this->unFindCss($page, '.callnumber.ajax-availability');
        $this->unFindCss($page, '.location.ajax-availability');
        $this->assertEquals(
            'A1234.567',
            $this->findCssAndGetText($page, '.callnumber')
        );
        $this->assertEquals(
            '3rd Floor Main Library',
            $this->findCssAndGetText($page, '.location')
        );
    }

    /**
     * Test language switching by checking a link in the footer
     *
     * @return void
     */
    public function testLanguage(): void
    {
        $page = $this->getSearchHomePage();
        // Check footer help-link
        $this->assertEquals(
            'Search Tips',
            $this->findCssAndGetHtml($page, 'footer .help-link')
        );
        // Change the language:
        $this->clickCss($page, '.language.dropdown');
        $this->clickCss($page, '.language.dropdown li:not(.active) a');
        $this->waitForPageLoad($page);
        // Check footer help-link
        $this->assertNotEquals(
            'Search Tips',
            $this->findCssAndGetHtml($page, 'footer .help-link')
        );
    }

    /**
     * Test theme switching by checking for a phrase from the example theme
     *
     * @return void
     */
    public function testThemeSwitcher(): void
    {
        // Turn on theme switcher
        $themeList = 'sandal:sandal,example:local_theme_example';
        $this->changeConfigs(
            [
                'config' => [
                    'Site' => [
                        'theme' => 'sandal',
                        'alternate_themes' => $themeList,
                        'selectable_themes' => $themeList,
                    ],
                ],
            ]
        );

        $page = $this->getSearchHomePage();
        $this->waitForPageLoad($page);

        // Default theme does not have an h1:
        $this->unfindCss($page, 'h1');

        // Change the theme:
        $this->clickCss($page, '.theme-selector.dropdown');
        $this->clickCss($page, '.theme-selector.dropdown li:not(.active) a');
        $this->waitForPageLoad($page);

        // Check h1 again -- it should exist now
        $this->assertEquals(
            'Welcome to your custom theme!',
            $this->findCssAndGetHtml($page, 'h1')
        );
    }

    /**
     * Test lightbox jump links
     *
     * @return void
     */
    public function testLightboxJumps(): void
    {
        $page = $this->getSearchHomePage();
        // Open Search tips lightbox
        $this->clickCss($page, 'footer .help-link');
        $this->waitForPageLoad($page);
        // Click a jump link
        $this->clickCss($page, '.modal-body .HelpMenu a');
        // Make sure we're still in the Search Tips
        $this->waitForPageLoad($page);
        $this->findCss($page, '.modal-body .HelpMenu');
    }
}
