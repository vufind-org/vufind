<?php

/**
 * Mink test class for responsive behavior.
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2024.
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
 * Mink test class for responsive behavior.
 *
 * Class must be final due to use of "new static()" by LiveDatabaseTrait.
 *
 * @category VuFind
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */
final class ResponsivenessTest extends \VuFindTest\Integration\MinkTestCase
{
    use \VuFindTest\Feature\LiveDatabaseTrait;
    use \VuFindTest\Feature\UserCreationTrait;

    /**
     * Data provider for testing elements that should be hidden in mobile, visible
     * on desktop.
     *
     * @return array
     */
    public static function windowDimensionProvider(): array
    {
        return [
            'mobile' => [500, 500, ['bulk' => false, 'offcanvas' => true]],
            'desktop' => [1280, 768, ['bulk' => true, 'offcanvas' => false]],
        ];
    }

    /**
     * Test that bulk controls are hidden in mobile view and visible in desktop
     *
     * @param int   $windowWidth       Window width
     * @param int   $windowHeight      Window height
     * @param array $controlVisibility Expected visibility of controls
     *
     * @return void
     *
     * @dataProvider windowDimensionProvider
     */
    public function testBulkControls(int $windowWidth, int $windowHeight, array $controlVisibility): void
    {
        // Activate the bulk options:
        $this->changeConfigs(
            ['config' =>
                [
                    'Site' => ['showBulkOptions' => true],
                ],
            ]
        );
        $session = $this->getMinkSession();
        $session->resizeWindow($windowWidth, $windowHeight, 'current');
        $session->visit($this->getVuFindUrl() . '/Search/Results?lookfor=id:testbug2');
        $page = $session->getPage();

        // Test visibility of search result bulk items and checkbox:
        $shouldBeVisible = $controlVisibility['bulk'];
        $this->assertEquals(
            $shouldBeVisible,
            $this->findCss($page, '#search-cart-form .bulkActionButtons')->isVisible()
        );
        $this->assertEquals(
            $shouldBeVisible,
            $this->findCss($page, '.mainbody > .bulkActionButtons')->isVisible()
        );
        $this->assertEquals($shouldBeVisible, $this->findCss($page, '.checkbox-select-item')->isVisible());

        // Add a favorite:
        $this->clickCss($page, '.save-record');
        $this->clickCss($page, '.modal-body .createAccountLink');
        $this->waitForPageLoad($page);
        $this->fillInAccountForm($page);
        $this->clickCss($page, '.modal-body .btn.btn-primary');
        $this->waitForPageLoad($page);
        $this->clickCss($page, '.modal-body .btn.btn-primary');
        $this->findCss($page, '.modal .alert.alert-success');
        $this->clickCss($page, '.modal-body .btn.btn-default');
        $this->waitForLightboxHidden();

        // Test visibility of favorites bulk items and checkbox:
        $this->assertEquals($shouldBeVisible, $this->findCss($page, '.bulkActionButtons')->isVisible());
        $this->assertEquals($shouldBeVisible, $this->findCss($page, '.checkbox-select-item')->isVisible());

        // Clear out user for next run:
        static::removeUsers(['username1']);
    }

    /**
     * Test that offcanvas controls are visible in mobile and hidden in desktop
     *
     * @param int   $windowWidth       Window width
     * @param int   $windowHeight      Window height
     * @param array $controlVisibility Expected visibility of controls
     *
     * @return void
     *
     * @dataProvider windowDimensionProvider
     */
    public function testOffcanvas(int $windowWidth, int $windowHeight, array $controlVisibility): void
    {
        // Activate offcanvas:
        $this->changeConfigs(
            ['config' =>
                [
                    'Site' => ['offcanvas' => true],
                ],
            ]
        );
        $session = $this->getMinkSession();
        $session->resizeWindow($windowWidth, $windowHeight, 'current');
        $session->visit($this->getVuFindUrl() . '/Search/Results?lookfor=id:testbug2');
        $page = $session->getPage();

        // Test search sidebar:
        $shouldBeVisible = $controlVisibility['offcanvas'];
        $this->assertEquals($shouldBeVisible, $this->findCss($page, '.search-filter-toggle')->isVisible());
        if ($shouldBeVisible) {
            $this->clickCss($page, '.search-filter-toggle');
        }
        $this->assertEquals($shouldBeVisible, $this->findCss($page, '#search-sidebar .close-offcanvas')->isVisible());

        // Log in:
        $session->visit($this->getVuFindUrl() . '/MyResearch/Home');
        $this->clickCss($page, '.createAccountLink');
        $this->waitForPageLoad($page);
        $this->fillInAccountForm($page, ['username' => 'username2']);
        $this->clickCss($page, '#accountForm .btn.btn-primary');
        $this->waitForPageLoad($page);

        // Test account menu:
        $this->assertEquals($shouldBeVisible, $this->findCss($page, '.search-filter-toggle')->isVisible());
        if ($shouldBeVisible) {
            $this->clickCss($page, '.search-filter-toggle');
        }
        $this->assertEquals(
            $shouldBeVisible,
            $this->findCss($page, '#myresearch-sidebar .close-offcanvas')->isVisible()
        );

        // Clear out user for next run:
        static::removeUsers(['username2']);
    }

    /**
     * Standard teardown method.
     *
     * @return void
     */
    public static function tearDownAfterClass(): void
    {
        static::removeUsers(['username1', 'username2']);
    }
}
