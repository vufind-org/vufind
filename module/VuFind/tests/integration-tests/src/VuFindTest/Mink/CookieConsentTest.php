<?php

/**
 * Mink cookie consent test class.
 *
 * PHP version 8
 *
 * Copyright (C) The National Library of Finland 2022-2023.
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
 * @link     https://vufind.org Main Page
 */

namespace VuFindTest\Mink;

use Behat\Mink\Element\Element;

/**
 * Mink cookie consent test class.
 *
 * Class must be final due to use of "new static()" by LiveDatabaseTrait.
 *
 * @category VuFind
 * @package  Tests
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */
final class CookieConsentTest extends \VuFindTest\Integration\MinkTestCase
{
    /**
     * Test cookie consent disabled
     *
     * @return void
     */
    public function testCookieConsentDisabled()
    {
        // Activate Matomo:
        $this->changeConfigs(
            [
                'config' => [
                    'Matomo' => [
                        'url' => $this->getVuFindUrl() . '/Content/faq',
                    ],
                ],
            ]
        );
        $page = $this->getStartPage();
        $html = $page->getHtml();
        $this->assertStringNotContainsString('VuFind.cookie.setupConsent', $html);
        $this->assertStringNotContainsString(
            "_paq.push(['requireCookieConsent']);",
            $html
        );
    }

    /**
     * Test cookie consent
     *
     * @return void
     */
    public function testCookieConsent()
    {
        // Activate the cookie consent and Matomo:
        $this->changeConfigs(
            [
                'config' => [
                    'Cookies' => [
                        'consent' => true,
                        'consentCategories' => 'essential,matomo',
                    ],
                    'Matomo' => [
                        'url' => $this->getVuFindUrl() . '/Content/faq',
                    ],
                ],
            ]
        );
        // Make sure the cookie dialog is not hidden from a headless client:
        $this->changeYamlConfigs(
            [
                'CookieConsent' => [
                    'CookieConsent' => [
                        'HideFromBots' => false,
                    ],
                ],
            ]
        );

        $page = $this->getStartPage();
        $html = $page->getHtml();
        $this->assertStringContainsString('VuFind.cookie.setupConsent', $html);

        $this->assertStringContainsString(
            "_paq.push(['requireCookieConsent']);",
            $html
        );

        // Open settings:
        $this->clickSettings($page);
        $this->waitStatement('$(".pm .pm__title").text() === "Cookie Settings"');
        $this->waitStatement('$(".pm__section-title").length === 2');
        $this->waitStatement(
            '$(".pm__section-title")[0].innerText === "Essential Cookies"'
        );
        $this->waitStatement(
            '$(".pm__section-title")[1].innerText === "Analytics Cookies"'
        );

        // Save without allowing analytics:
        $this->clickAcceptEssential($page);
        // Verify that there's no Matomo consent:
        $this->waitStatement(
            "window._paq[window._paq.length-1][0] !== 'setCookieConsentGiven'"
        );
        $this->waitStatement('!VuFind.cookie.isServiceAllowed("matomo")');
        // Verify that essential cookies are allowed:
        $this->waitStatement('VuFind.cookie.isCategoryAccepted("essential")');

        // Open settings again and accept only essential cookies:
        $this->clickSettings($page);
        $this->waitStatement('$(".pm .pm__title").text() === "Cookie Settings"');
        $this->clickSave($page);

        // Verify that there's no Matomo consent:
        $this->waitStatement(
            "window._paq[window._paq.length-1][0] !== 'setCookieConsentGiven'"
        );
        $this->waitStatement('!VuFind.cookie.isServiceAllowed("matomo")');

        // Open settings again and toggle analytics:
        $this->clickSettings($page);
        $this->waitStatement('$(".pm .pm__title").text() === "Cookie Settings"');
        $this->clickCss($page, '.section__toggle', null, 1);
        $this->clickSave($page);
        // Verify that there's Matomo consent:
        $this->waitStatement(
            "window._paq[window._paq.length-1][0] === 'setCookieConsentGiven'"
        );
        $this->waitStatement('VuFind.cookie.isServiceAllowed("matomo")');
        $this->waitStatement('window._paq.pop()');

        // Open settings again and accept only essential cookies:
        $this->clickSettings($page);
        $this->waitStatement('$(".pm .pm__title").text() === "Cookie Settings"');
        $this->clickAcceptEssential($page);
        $this->waitStatement(
            "window._paq[window._paq.length-1][0] !== 'setCookieConsentGiven'"
        );
        $this->waitStatement('!VuFind.cookie.isServiceAllowed("matomo")');
        $this->waitStatement('window._paq.pop()');

        // Open settings again and accept all cookies:
        $this->clickSettings($page);
        $this->waitStatement('$(".pm .pm__title").text() === "Cookie Settings"');
        $this->clickAcceptAll($page);
        $this->waitStatement(
            "window._paq[window._paq.length-1][0] === 'setCookieConsentGiven'"
        );
        $this->waitStatement('VuFind.cookie.isServiceAllowed("matomo")');
    }

    /**
     * Get start page
     *
     * @return Element
     */
    protected function getStartPage(): Element
    {
        $session = $this->getMinkSession();
        $session->visit($this->getVuFindUrl());
        $page = $session->getPage();
        $this->waitForPageLoad($page);
        return $page;
    }

    /**
     * Click the "Accept All Cookies" button
     *
     * @param Element $page Page
     *
     * @return void
     */
    protected function clickAcceptAll(Element $page): void
    {
        $this->clickCss($page, '.pm__btn');
    }

    /**
     * Click the "Accept Only Essential Cookies" button
     *
     * @param Element $page Page
     *
     * @return void
     */
    protected function clickAcceptEssential(Element $page): void
    {
        $this->clickCss($page, '.pm__btn', null, 1);
    }

    /**
     * Click the Settings button
     *
     * @param Element $page Page
     *
     * @return void
     */
    protected function clickSettings(Element $page): void
    {
        $this->clickCss($page, '#cm__desc a');
    }

    /**
     * Click the Save button
     *
     * @param Element $page Page
     *
     * @return void
     */
    protected function clickSave(Element $page): void
    {
        $this->clickCss($page, '.pm__btn.pm__btn--secondary');
    }
}
