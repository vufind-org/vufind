<?php

/**
 * Custom link test class.
 *
 * PHP version 8
 *
 * Copyright (C) Hebis Verbundzentrale 2026.
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
 * @author   Thomas Wagener <wagener@hebis.uni-frankfurt.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */

namespace VuFindTest\Mink;

use Throwable;
use VuFind\Db\Service\ContentPageServiceInterface;
use VuFind\Db\Service\CustomLinkServiceInterface;
use VuFindTest\Feature\CacheManagementTrait;
use VuFindTest\Feature\LiveDatabaseTrait;
use VuFindTest\Feature\UserCreationTrait;

/**
 * Custom link test class.
 *
 * @category VuFind
 * @package  Tests
 * @author   Thomas Wagener <wagener@hebis.uni-frankfurt.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */
final class CustomLinkTest extends \VuFindTest\Integration\MinkTestCase
{
    use CacheManagementTrait;
    use LiveDatabaseTrait;
    use UserCreationTrait;

    /**
     * Standard setup method.
     *
     * @return void
     */
    public static function setUpBeforeClass(): void
    {
        static::failIfDataExists();
    }

    /**
     * Test adding custom links and content pages in admin module.
     *
     * @return void
     */
    public function testAddingCustomLinkInAdminModule(): void
    {
        $this->changeConfigs($this->getAdminPermissionConfig() + ['config' => ['Site' => ['admin_enabled' => 1]]]);
        $session = $this->getMinkSession();
        $session->visit($this->getVuFindUrl() . '/Admin/CustomLinks');
        $page = $session->getPage();
        $this->waitForPageLoad($page);

        // test canceling
        $this->clickCss($page, 'a[href*="CustomLinks/Add"]');
        $this->waitForPageLoad($page);

        $this->findCssAndSetValue(
            $page,
            'input[name="translations[en][title]"]',
            'Test URL'
        );

        $this->clickCss($page, 'button[name="cancel"]');
        $this->waitForPageLoad($page);

        // test that only one header menu exists, i.e. no link was added to the left header
        $this->unFindCss($page, 'nav.banner .header-menu', index: 1);

        // add custom link
        $this->clickCss($page, 'a[href*="CustomLinks/Add"]');
        $this->waitForPageLoad($page);

        $this->findCssAndSetValue(
            $page,
            'input[name="translations[en][title]"]',
            'Test URL'
        );
        $this->unFindCss($page, 'input[name="translations[en][url]"].hidden');
        $this->findCssAndSetValue(
            $page,
            'input[name="translations[en][url]"]',
            'https://vufind.org/wiki'
        );
        $this->findCss($page, 'input[name="translations[en][headline]"].hidden');
        $this->findCss($page, 'textarea[name="translations[en][content]"].hidden');

        $this->clickCss($page, 'button[name="submit"]');
        $this->waitForPageLoad($page);

        $this->clickCss($page, 'a[href*="CustomLinks/Add"]');
        $this->waitForPageLoad($page);

        // change context to footer_left
        $this->clickCss($page, 'fieldset[name="context_fieldset"] input[value="footer_left"]');

        // change type to content page
        $this->clickCss($page, 'fieldset[name="type_fieldset"] input[value="page"]');

        $this->findCssAndSetValue(
            $page,
            'input[name="translations[en][title]"]',
            'Test Page'
        );
        $this->unFindCss($page, 'input[name="translations[en][headline]"].hidden');
        $this->findCssAndSetValue(
            $page,
            'input[name="translations[en][headline]"]',
            'Test Headline'
        );
        $this->unFindCss($page, 'textarea[name="translations[en][content]"].hidden');
        $this->findCssAndSetValue(
            $page,
            'textarea[name="translations[en][content]"]',
            'Test Content'
        );
        $this->findCss($page, 'input[name="translations[en][url]"].hidden');

        $this->clickCss($page, '#tab-button-de');

        $this->findCssAndSetValue(
            $page,
            'input[name="translations[de][title]"]',
            'German Title'
        );
        $this->unFindCss($page, 'input[name="translations[de][headline]"].hidden');
        $this->findCssAndSetValue(
            $page,
            'input[name="translations[de][headline]"]',
            'German Headline'
        );
        $this->unFindCss($page, 'textarea[name="translations[de][content]"].hidden');
        $this->findCssAndSetValue(
            $page,
            'textarea[name="translations[de][content]"]',
            'German Content'
        );
        $this->findCss($page, 'input[name="translations[de][url]"].hidden');

        $this->clickCss($page, 'button[name="submit"]');
        $this->waitForPageLoad($page);

        // test custom links exist
        $link1 = $this->findCss($page, 'nav.banner .header-menu li')->findLink('Test URL');
        $this->assertInstanceOf(\Behat\Mink\Element\NodeElement::class, $link1);
        $this->assertSame('https://vufind.org/wiki', $link1->getAttribute('href'));
        $this->assertInstanceOf(\Behat\Mink\Element\NodeElement::class, $link1);
        $this->assertSame('_blank', $link1->getAttribute('target'));

        $link2 = $this->findCss($page, '.footer-column')->findLink('Test Page');
        $this->assertInstanceOf(\Behat\Mink\Element\NodeElement::class, $link2);
        $this->assertStringEndsWith('/Content/Test-Headline', $link2->getAttribute('href'));
        $this->assertInstanceOf(\Behat\Mink\Element\NodeElement::class, $link2);

        $link2->click();
        $this->waitForPageLoad($page);

        $pageHeadline =  $this->findCssAndGetText($page, '#content > h1');
        $this->assertSame('Test Headline', $pageHeadline);

        $pageContent =  $this->findCssAndGetText($page, '#content > p');
        $this->assertSame('Test Content', $pageContent);

        // test switching language
        $session->visit($this->getVuFindUrl() . '?lng=de');
        $this->waitForPageLoad($page);

        $link1 = $this->findCss($page, 'nav.banner .header-menu li')->findLink('Test URL');
        $this->assertInstanceOf(\Behat\Mink\Element\NodeElement::class, $link1);
        $this->assertSame('https://vufind.org/wiki', $link1->getAttribute('href'));
        $this->assertInstanceOf(\Behat\Mink\Element\NodeElement::class, $link1);
        $this->assertSame('_blank', $link1->getAttribute('target'));

        $link2 = $this->findCss($page, '.footer-column')->findLink('German Title');
        $this->assertInstanceOf(\Behat\Mink\Element\NodeElement::class, $link2);
        $this->assertStringEndsWith('/Content/Test-Headline', $link2->getAttribute('href'));
        $this->assertInstanceOf(\Behat\Mink\Element\NodeElement::class, $link2);

        $link2->click();
        $this->waitForPageLoad($page);

        $pageHeadline =  $this->findCssAndGetText($page, '#content > h1');
        $this->assertSame('German Headline', $pageHeadline);

        $pageContent =  $this->findCssAndGetText($page, '#content > p');
        $this->assertSame('German Content', $pageContent);
    }

    /**
     * Test editing custom links and content pages in admin module.
     *
     * @return void
     */
    #[\PHPUnit\Framework\Attributes\Depends('testAddingCustomLinkInAdminModule')]
    public function testEditingCustomLinkInAdminModule(): void
    {
        $this->changeConfigs($this->getAdminPermissionConfig() + ['config' => ['Site' => ['admin_enabled' => 1]]]);
        $session = $this->getMinkSession();
        $session->visit($this->getVuFindUrl() . '/Admin/CustomLinks');
        $page = $session->getPage();
        $this->waitForPageLoad($page);

        // verify custom links exist
        $link1 = $this->findCss($page, 'nav.banner .header-menu li')->findLink('Test URL');
        $this->assertInstanceOf(\Behat\Mink\Element\NodeElement::class, $link1);
        $this->assertSame('https://vufind.org/wiki', $link1->getAttribute('href'));
        $this->assertInstanceOf(\Behat\Mink\Element\NodeElement::class, $link1);
        $this->assertSame('_blank', $link1->getAttribute('target'));

        $link2 = $this->findCss($page, '.footer-column')->findLink('Test Page');
        $this->assertInstanceOf(\Behat\Mink\Element\NodeElement::class, $link2);
        $this->assertStringEndsWith('/Content/Test-Headline', $link2->getAttribute('href'));

        // test cancel
        $this->clickCss($page, '.custom-link-list a[title="Edit"]');
        $this->waitForPageLoad($page);

        $this->findCssAndSetValue(
            $page,
            'input[name="translations[en][title]"]',
            'Changed Title'
        );

        $this->clickCss($page, 'button[name="cancel"]');
        $this->waitForPageLoad($page);

        // edit custom links
        $this->clickCss($page, '.custom-link-list a[title="Edit"]');
        $this->waitForPageLoad($page);

        // change context to account_menu
        $this->clickCss($page, 'fieldset[name="context_fieldset"] input[value="account_menu"]');

        $this->clickCss($page, 'fieldset[name="type_fieldset"] input[value="page"]');

        $this->findCssAndSetValue(
            $page,
            'input[name="translations[en][title]"]',
            'Changed Title'
        );
        $this->unFindCss($page, 'input[name="translations[en][headline]"].hidden');

        // set same headline to test duplicated HRIDs
        $this->findCssAndSetValue(
            $page,
            'input[name="translations[en][headline]"]',
            'Test Headline'
        );
        $this->unFindCss($page, 'textarea[name="translations[en][content]"].hidden');
        $this->findCssAndSetValue(
            $page,
            'textarea[name="translations[en][content]"]',
            'Test Content2'
        );
        $this->findCss($page, 'input[name="translations[en][url]"].hidden');

        $this->clickCss($page, 'button[name="submit"]');
        $this->waitForPageLoad($page);

        $this->clickCss($page, '.custom-link-list a[title="Edit"]', index: 1);
        $this->waitForPageLoad($page);

        // check context footer_left is preselected and change context to footer_right
        $this->findCss($page, 'fieldset[name="context_fieldset"] input[value="footer_left"]:checked');
        $this->clickCss($page, 'fieldset[name="context_fieldset"] input[value="footer_right"]');

        // check type page is preselected and change to url
        $this->findCss($page, 'fieldset[name="type_fieldset"] input[value="page"]:checked');
        $this->clickCss($page, 'fieldset[name="type_fieldset"] input[value="url"]');

        $this->unFindCss($page, 'input[name="translations[en][url]"].hidden');
        $this->findCssAndSetValue(
            $page,
            'input[name="translations[en][url]"]',
            'https://vufind.org/wiki'
        );
        $this->findCss($page, 'input[name="translations[en][headline]"].hidden');
        $this->findCss($page, 'textarea[name="translations[en][content]"].hidden');

        $this->clickCss($page, 'button[name="submit"]');
        $this->waitForPageLoad($page);

        // test custom links changed

        // test that only one header menu exists, i.e. no link is present in the left header
        $this->unFindCss($page, 'nav.banner .header-menu', index: 1);

        $session->visit($this->getVuFindUrl() . '/SearchHistory/List');
        $this->waitForPageLoad($page);

        $link1 = $this->findCss($page, '.account-menu')->findLink('Changed Title');
        $this->assertInstanceOf(\Behat\Mink\Element\NodeElement::class, $link1);
        $this->assertStringEndsWith('/Content/Test-Headline-2', $link1->getAttribute('href'));
        $this->assertNull($link1->getAttribute('target'));

        $link2 = $this->findCss($page, '.footer-column', index: 2)->findLink('Test Page');
        $this->assertInstanceOf(\Behat\Mink\Element\NodeElement::class, $link2);
        $this->assertSame('https://vufind.org/wiki', $link2->getAttribute('href'));
        $this->assertInstanceOf(\Behat\Mink\Element\NodeElement::class, $link2);
        $this->assertSame('_blank', $link2->getAttribute('target'));

        // test switching language
        $session->visit($this->getVuFindUrl() . '/SearchHistory/List?lng=de');
        $this->waitForPageLoad($page);

        $link1 = $this->findCss($page, '.account-menu')->findLink('Changed Title');
        $this->assertInstanceOf(\Behat\Mink\Element\NodeElement::class, $link1);
        $this->assertStringEndsWith('/Content/Test-Headline-2', $link1->getAttribute('href'));
        $this->assertInstanceOf(\Behat\Mink\Element\NodeElement::class, $link1);
        $this->assertNull($link1->getAttribute('target'));

        $link2 = $this->findCss($page, '.footer-column', index: 2)->findLink('German Title');
        $this->assertInstanceOf(\Behat\Mink\Element\NodeElement::class, $link2);
        $this->assertSame('https://vufind.org/wiki', $link2->getAttribute('href'));
        $this->assertInstanceOf(\Behat\Mink\Element\NodeElement::class, $link2);
        $this->assertSame('_blank', $link2->getAttribute('target'));
        $this->assertInstanceOf(\Behat\Mink\Element\NodeElement::class, $link1);

        $link1->click();
        $this->waitForPageLoad($page);

        $pageHeadline =  $this->findCssAndGetText($page, '#content > h1');
        $this->assertSame('Test Headline', $pageHeadline);

        $pageContent =  $this->findCssAndGetText($page, '#content > p');
        $this->assertSame('Test Content2', $pageContent);
    }

    /**
     * Test deleting custom links and content pages in admin module.
     *
     * @return void
     */
    #[\PHPUnit\Framework\Attributes\Depends('testEditingCustomLinkInAdminModule')]
    public function testDeletingCustomLinkInAdminModule(): void
    {
        $this->changeConfigs($this->getAdminPermissionConfig() + ['config' => ['Site' => ['admin_enabled' => 1]]]);
        $session = $this->getMinkSession();
        $page = $session->getPage();

        // verify custom links exist
        $session->visit($this->getVuFindUrl() . '/SearchHistory/List');
        $this->waitForPageLoad($page);

        $link1 = $this->findCss($page, '.account-menu')->findLink('Changed Title');
        $this->assertInstanceOf(\Behat\Mink\Element\NodeElement::class, $link1);
        $this->assertStringEndsWith('/Content/Test-Headline-2', $link1->getAttribute('href'));
        $this->assertInstanceOf(\Behat\Mink\Element\NodeElement::class, $link1);
        $this->assertNull($link1->getAttribute('target'));

        $link2 = $this->findCss($page, '.footer-column', index: 2)->findLink('Test Page');
        $this->assertInstanceOf(\Behat\Mink\Element\NodeElement::class, $link2);
        $this->assertSame('https://vufind.org/wiki', $link2->getAttribute('href'));
        $this->assertInstanceOf(\Behat\Mink\Element\NodeElement::class, $link2);
        $this->assertSame('_blank', $link2->getAttribute('target'));

        // test cancel
        $session->visit($this->getVuFindUrl() . '/Admin/CustomLinks');
        $this->waitForPageLoad($page);

        $this->clickCss($page, '.custom-link-list a[title="Delete"]');
        $this->waitForPageLoad($page);

        $this->clickCss($page, 'button[name="cancel"]');
        $this->waitForPageLoad($page);

        // verify custom links exist
        $session->visit($this->getVuFindUrl() . '/SearchHistory/List');
        $this->waitForPageLoad($page);

        $link1 = $this->findCss($page, '.account-menu')->findLink('Changed Title');
        $this->assertInstanceOf(\Behat\Mink\Element\NodeElement::class, $link1);
        $this->assertStringEndsWith('/Content/Test-Headline-2', $link1->getAttribute('href'));
        $this->assertInstanceOf(\Behat\Mink\Element\NodeElement::class, $link1);
        $this->assertNull($link1->getAttribute('target'));

        $link2 = $this->findCss($page, '.footer-column', index: 2)->findLink('Test Page');
        $this->assertInstanceOf(\Behat\Mink\Element\NodeElement::class, $link2);
        $this->assertSame('https://vufind.org/wiki', $link2->getAttribute('href'));
        $this->assertSame('_blank', $link2->getAttribute('target'));

        // test delete first
        $session->visit($this->getVuFindUrl() . '/Admin/CustomLinks');
        $this->waitForPageLoad($page);

        $this->clickCss($page, '.custom-link-list a[title="Delete"]');
        $this->waitForPageLoad($page);

        $this->clickCss($page, 'button[name="confirm"]');
        $this->waitForPageLoad($page);

        $session->visit($this->getVuFindUrl() . '/SearchHistory/List');
        $this->waitForPageLoad($page);

        $this->assertNull($this->findCss($page, '.account-menu')->findLink('Changed Title'));

        $link2 = $this->findCss($page, '.footer-column', index: 2)->findLink('Test Page');
        $this->assertInstanceOf(\Behat\Mink\Element\NodeElement::class, $link2);
        $this->assertSame('https://vufind.org/wiki', $link2->getAttribute('href'));
        $this->assertInstanceOf(\Behat\Mink\Element\NodeElement::class, $link2);
        $this->assertSame('_blank', $link2->getAttribute('target'));

        // test delete second
        $session->visit($this->getVuFindUrl() . '/Admin/CustomLinks');
        $this->waitForPageLoad($page);

        $this->clickCss($page, '.custom-link-list a[title="Delete"]');
        $this->waitForPageLoad($page);

        $this->clickCss($page, 'button[name="confirm"]');
        $this->waitForPageLoad($page);

        $this->assertNull($this->findCss($page, '.footer-column', index: 2)->findLink('Test Page'));
    }

    /**
     * Standard teardown method.
     *
     * @return void
     */
    public static function tearDownAfterClass(): void
    {
        static::removeUsers(['username1', 'catuser']);

        // Delete all custom links and content pages
        try {
            $test = new static('');   // create instance of current class
            // If CI is not running, all tests were skipped, so no work is necessary:
            if (!$test->continuousIntegrationRunning()) {
                return;
            }
            // Delete custom links
            $customLinkService = $test->getDbService(CustomLinkServiceInterface::class);
            $customLinks = $customLinkService->getCustomLinks();
            foreach ($customLinks as $customLink) {
                $customLinkService->delete($customLink->getId());
            }
            // Delete content pages
            $contentPageService = $test->getDbService(ContentPageServiceInterface::class);
            $contentPages = $contentPageService->getContentPages();
            foreach ($contentPages as $contentPage) {
                $contentPageService->deleteByHrid($contentPage->getHrid());
            }
            $test->tearDownLiveDatabaseContainer();
        } catch (Throwable $t) {
            echo "\n\nError in removing custom links and content pages: " . (string)$t . "\n";
        }
    }
}
