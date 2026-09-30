<?php

/**
 * Mink cookie consent test class.
 *
 * PHP version 8
 *
 * Copyright (C) The National Library of Finland 2022-2026.
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
     * Test cookie consent disabled.
     *
     * @return void
     */
    public function testCookieConsentDisabled(): void
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
     * Test cookie consent.
     *
     * @return void
     */
    public function testCookieConsent(): void
    {
        $this->setupConfigs();

        $page = $this->getStartPage('/Content/privacy');
        $html = $page->getHtml();

        $this->assertStringContainsString('VuFind.cookie.setupConsent', $html);
        // Check that the missing consent is properly reflected:
        $this->verifyCurrentAllowStatus($page, false, false);

        $this->waitForCookieConsentOverlay($page);
        $this->assertCount(2, $page->findAll('css', '.cookie-consent .cookie-consent__category'));
        $this->assertSame(
            'Essential Cookies',
            $this->findCssAndGetText($page, '.cookie-consent .cookie-consent__category-checkbox')
        );
        $this->assertSame(
            'Analytics Cookies',
            $this->findCssAndGetText($page, '.cookie-consent .cookie-consent__category-checkbox', index: 1)
        );

        // Save without allowing analytics:
        $this->clickAcceptEssential($page);
        // Verify that there's no Matomo consent:
        $this->verifyCurrentAllowStatus($page, true, false);

        // Open settings again and accept only essential cookies:
        $this->clickSettings($page);
        $this->waitForCookieConsentOverlay($page);
        $this->clickCss($page, '.cookie-consent .cookie-consent__settings-toggle');
        $this->clickSave($page);
        // Verify again that there's no Matomo consent:
        $this->verifyCurrentAllowStatus($page, true, false);

        // Open settings again and toggle analytics:
        $this->clickSettings($page);
        $this->waitForCookieConsentOverlay($page);
        // Show details:
        $this->clickCss($page, '.cookie-consent .cookie-consent__settings-toggle');
        // Allow analytics cookies:
        $this->clickCss($page, '.cookie-consent .cookie-consent__category-checkbox input', index: 1);
        $this->clickSave($page);
        // Verify that there's Matomo consent:
        $this->verifyCurrentAllowStatus($page, true, true);

        // Open settings again and accept only essential cookies:
        $this->clickSettings($page);
        $this->waitForCookieConsentOverlay($page);
        $this->clickAcceptEssential($page);
        // Verify that there's no Matomo consent:
        $this->verifyCurrentAllowStatus($page, true, false);

        // Open settings again and accept all cookies:
        $this->clickSettings($page);
        $this->waitForCookieConsentOverlay($page);
        $this->clickAcceptAll($page);
        // Verify that there's Matomo consent:
        $this->verifyCurrentAllowStatus($page, true, true);
    }

    /**
     * Test cookie consent with only essential categories.
     *
     * @return void
     */
    public function testEssentialOnly(): void
    {
        $this->setupConfigs('essential');

        $page = $this->getStartPage('/Content/privacy');
        $this->waitForCookieConsentOverlay($page);
        $this->assertCount(1, $page->findAll('css', '.cookie-consent .cookie-consent__category'));
        $this->assertSame(
            'Essential Cookies',
            $this->findCssAndGetText($page, '.cookie-consent .cookie-consent__category-checkbox')
        );
        // Check that there's no Save Settings button:
        $this->unFindCss($page, '.cookie-consent .cookie-consent__save-settings');

        // Toggle settings and click the Accept All button that should still be visible:
        $this->clickCss($page, '.cookie-consent .cookie-consent__settings-toggle');
        $this->clickAcceptAll($page);

        // Verify that there's no Matomo consent:
        $this->verifyCurrentAllowStatus($page, true, false);
    }

    /**
     * Setup configs for cookie consent.
     *
     * @param string $categories Enabled categories
     *
     * @return void
     */
    protected function setupConfigs(?string $categories = null): void
    {
        // Activate the cookie consent and Matomo:
        $this->changeConfigs(
            [
                'config' => [
                    'Cookies' => [
                        'consent' => true,
                        'consentCategories' => $categories ?? 'essential,matomo',
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
    }

    /**
     * Get start page.
     *
     * @param string $path Path to load
     *
     * @return Element
     */
    protected function getStartPage(string $path = ''): Element
    {
        $session = $this->getMinkSession();
        $session->visit($this->getVuFindUrl($path));
        $page = $session->getPage();
        $this->waitForPageLoad($page);
        return $page;
    }

    /**
     * Wait for the cookie consent overlay to be displayed.
     *
     * @param Element $page Page
     *
     * @return void
     */
    protected function waitForCookieConsentOverlay(Element $page): void
    {
        $this->assertSame(
            'This site uses essential cookies to ensure its proper operation, and tracking cookies to understand how '
            . 'you interact with it.',
            $this->findCssAndGetText($page, '.cookie-consent .cookie-consent__title')
        );
    }

    /**
     * Assert that cookies are allowed as they should.
     *
     * @param Element $page             Page
     * @param bool    $consentGiven     Has consent been given?
     * @param bool    $analyticsAllowed Should analytics cookies be allowed?
     *
     * @return void
     */
    protected function verifyCurrentAllowStatus(Element $page, bool $consentGiven, bool $analyticsAllowed): void
    {
        $notAllowedInPaq = "_paq.push(['requireCookieConsent']);";

        // Verify that essential cookies are allowed if consent is given, or proper status is displayed if not:
        if ($consentGiven) {
            $this->waitStatement('VuFind.cookie.isCategoryAccepted("essential")');
        }

        if ($analyticsAllowed) {
            $this->assertTrue($consentGiven);
            $this->waitStatement('VuFind.cookie.isServiceAllowed("matomo")');
            $this->assertWithTimeout(
                $notAllowedInPaq,
                fn () => $page->getHtml(),
                fn ($expected, $result) => !str_contains($result, $expected),
                [$this, 'assertStringNotContainsString']
            );

            $this->assertStringContainsString(
                'State: Allow (Essential Cookies, Analytics Cookies)',
                $this->findCssAndGetText($page, '#content')
            );
        } else {
            $this->waitStatement('!VuFind.cookie.isServiceAllowed("matomo")');
            $this->assertWithTimeout(
                $notAllowedInPaq,
                fn () => $page->getHtml(),
                fn ($expected, $result) => str_contains($result, $expected),
                [$this, 'assertStringContainsString']
            );

            if ($consentGiven) {
                $this->assertStringContainsString(
                    'State: Allow (Essential Cookies)',
                    $this->findCssAndGetText($page, '#content')
                );
            } else {
                $this->assertWithTimeout(
                    'You have not yet given consent.',
                    fn () => $this->findCssAndGetText($page, '#content'),
                    fn ($expected, $result) => str_contains($result, $expected),
                    [$this, 'assertStringContainsString']
                );
            }
        }
    }

    /**
     * Click the "Accept All Cookies" button.
     *
     * @param Element $page Page
     *
     * @return void
     */
    protected function clickAcceptAll(Element $page): void
    {
        $this->clickCss($page, '.cookie-consent .cookie-consent__accept-all');
    }

    /**
     * Click the "Accept Only Essential Cookies" button.
     *
     * @param Element $page Page
     *
     * @return void
     */
    protected function clickAcceptEssential(Element $page): void
    {
        $this->clickCss($page, '.cookie-consent .cookie-consent__accept-essential');
    }

    /**
     * Click the Cookie Settings button.
     *
     * @param Element $page Page
     *
     * @return void
     */
    protected function clickSettings(Element $page): void
    {
        $this->clickCss($page, 'a[data-cc=show-preferencesModal]');
    }

    /**
     * Click the Save button.
     *
     * @param Element $page Page
     *
     * @return void
     */
    protected function clickSave(Element $page): void
    {
        $this->clickCss($page, '.cookie-consent .cookie-consent__save-settings');
    }
}
