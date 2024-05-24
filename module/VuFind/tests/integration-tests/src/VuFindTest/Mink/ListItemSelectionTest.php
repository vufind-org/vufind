<?php

/**
 * Mink list item selection test class.
 *
 * PHP version 8
 *
 * Copyright (C) Hebis Verbundzentrale 2024.
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
 * @author   Thomas Wagener <wagener@hebis.uni-frankfurt.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */

namespace VuFindTest\Mink;

use Behat\Mink\Element\Element;

/**
 * Mink list item selection test class.
 *
 * Class must be final due to use of "new static()" by LiveDatabaseTrait.
 *
 * @category VuFind
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Thomas Wagener <wagener@hebis.uni-frankfurt.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */
final class ListItemSelectionTest extends \VuFindTest\Integration\MinkTestCase
{
    use \VuFindTest\Feature\LiveDatabaseTrait;
    use \VuFindTest\Feature\UserCreationTrait;

    /**
     * Checkbox states
     */
    public const NONE = 0;
    public const UNCHECKED = 1;
    public const CHECKED = 2;

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
     * Login and go to account home.
     *
     * @return \Behat\Mink\Element\DocumentElement
     */
    protected function gotoUserAccount()
    {
        $session = $this->getMinkSession();
        $path = '/Search/Home';
        $session->visit($this->getVuFindUrl() . $path);
        $page = $session->getPage();
        // Login
        $this->clickCss($page, '#loginOptions a');
        $this->fillInLoginForm($page, 'username1', 'test');
        $this->submitLoginForm($page);
        $this->waitForPageLoad($page);
        // Go to saved lists
        $path = '/MyResearch/Home';
        $session->visit($this->getVuFindUrl() . $path);
        $this->waitForPageLoad($page);
        return $page;
    }

    /**
     * Adjust configs for testing, then go to user account.
     *
     * @param $config array Config
     *
     * @return \Behat\Mink\Element\DocumentElement
     */
    protected function setupTest($config)
    {
        $this->changeConfigs(['config' => ['Social' => $config]]);
        return $this->gotoUserAccount();
    }

    /**
     * Check that the number of selected elements on the page is correct.
     *
     * @param Element $page           Page element
     * @param int     $expectedNumber Expected number of selected elements
     *
     * @return void
     */
    protected function checkNumberOfSelectedOnPage($page, $expectedNumber)
    {
        $elements = $page->findAll('css', '.checkbox-select-item');
        $clickedElements = array_filter($elements, function ($element) {
            return $element->isChecked();
        });
        $this->assertCount($expectedNumber, $clickedElements, 'Wrong number of selected on page');
    }

    /**
     * Check that the number of selected elements globally is correct.
     *
     * @param Element $page           Page element
     * @param int     $expectedNumber Expected number of selected elements
     *
     * @return void
     */
    protected function checkNumberOfSelectedGlobal($page, $expectedNumber)
    {
        $this->clickCss($page, '[name=bulkActionForm] [name=email]');
        if ($expectedNumber == 0) {
            $warning = $this->findCss($page, '.modal-body .alert');
            $this->assertEquals(
                'No items were selected. Please click on a checkbox next to an item and try again.',
                $warning->getText(),
                'Wrong number of selected global'
            );
        } elseif ($expectedNumber == 1) {
            $this->unFindCss($page, '.modal-body .btn-default');
            $this->findCss($page, '.modal-body .form-control-static');
        } else {
            $numberButton = $this->findCss($page, '.modal-body .btn-default');
            $number = (int)filter_var($numberButton->getText(), FILTER_SANITIZE_NUMBER_INT);
            $this->assertEquals($expectedNumber, $number, 'Wrong number of selected global');
        }
        $this->closeLightbox($page);
    }

    /**
     * Check that the number of selected elements globally is shown on the clear selection button.
     *
     * @param Element $page           Page element
     * @param int     $expectedNumber Expected number of selected elements
     *
     * @return void
     */
    protected function checkClearSelectionButton($page, $expectedNumber)
    {
        $button = $this->findCss($page, '.clear-selection');
        if ($expectedNumber == 0) {
            $this->assertTrue(
                $button->hasClass('hidden'),
                'Clear selection button is not hidden'
            );
        } else {
            $number = (int)filter_var($button->getText(), FILTER_SANITIZE_NUMBER_INT);
            $this->assertEquals($expectedNumber, $number, 'Wrong number of selected on clear selection button');
        }
    }

    /**
     * Check if the select all on page checkbox exits.
     *
     * @param Element $page       Page element
     * @param bool    $expectTrue If the checkbox is expected to exist or not
     *
     * @return void
     */
    protected function checkSelectAllOnPageDoesExists($page, $expectTrue = true)
    {
        if ($expectTrue) {
            $this->findCss($page, '[name=bulkActionForm] .checkbox-select-all');
        } else {
            $this->unFindCss($page, '[name=bulkActionForm] .checkbox-select-all');
        }
    }

    /**
     * Check if the select all on page checkbox is checked.
     *
     * @param Element $page       Page element
     * @param bool    $expectTrue If the checkbox is expected to be checked or not
     *
     * @return void
     */
    protected function checkSelectAllOnPageIsClicked($page, $expectTrue = true)
    {
        $this->assertEquals(
            $expectTrue,
            $this->findCss($page, '[name=bulkActionForm] .checkbox-select-all')->isChecked()
        );
    }

    /**
     * Check if the select all global checkbox exits.
     *
     * @param Element $page       Page element
     * @param bool    $expectTrue If the checkbox is expected to exist or not
     *
     * @return void
     */
    protected function checkSelectAllGlobalDoesExists($page, $expectTrue = true)
    {
        if ($expectTrue) {
            $this->findCss($page, '[name=bulkActionForm] .checkbox-select-all-global');
        } else {
            $this->unFindCss($page, '[name=bulkActionForm] .checkbox-select-all-global');
        }
    }

    /**
     * Check if the select all global checkbox is checked.
     *
     * @param Element $page       Page element
     * @param bool    $expectTrue If the checkbox is expected to be checked or not
     *
     * @return void
     */
    protected function checkSelectAllGlobalIsClicked($page, $expectTrue = true)
    {
        $this->assertEquals(
            $expectTrue,
            $this->findCss($page, '[name=bulkActionForm] .checkbox-select-all-global')->isChecked()
        );
    }

    /**
     * Check the complete status of the checkboxes and selection.
     *
     * @param Element $page                      Page element
     * @param int     $selectAllOnPageCheckbox   Expected state of the select all on page checkbox
     * @param int     $selectAllGlobalCheckbox   Expected state of the select all global checkbox
     * @param int     $numberOfSelectedOnPage    Expected number of selected elements on page
     * @param int     $numberOfSelectedGlobal    Expected number of globally selected elements
     * @param boolean $multiPageSelectionEnabled If multi page selection is enabled
     *
     * @return void
     */
    protected function checkStatus(
        $page,
        $selectAllOnPageCheckbox,
        $selectAllGlobalCheckbox,
        $numberOfSelectedOnPage,
        $numberOfSelectedGlobal,
        $multiPageSelectionEnabled = false
    ) {
        $this->waitForPageLoad($page);
        switch ($selectAllOnPageCheckbox) {
            case self::NONE:
                $this->checkSelectAllOnPageDoesExists($page, false);
                break;
            case self::UNCHECKED:
                $this->checkSelectAllOnPageDoesExists($page);
                $this->checkSelectAllOnPageIsClicked($page, false);
                break;
            case self::CHECKED:
                $this->checkSelectAllOnPageDoesExists($page);
                $this->checkSelectAllOnPageIsClicked($page);
                break;
        }
        switch ($selectAllGlobalCheckbox) {
            case self::NONE:
                $this->checkSelectAllGlobalDoesExists($page, false);
                break;
            case self::UNCHECKED:
                $this->checkSelectAllGlobalDoesExists($page);
                $this->checkSelectAllGlobalIsClicked($page, false);
                break;
            case self::CHECKED:
                $this->checkSelectAllGlobalDoesExists($page);
                $this->checkSelectAllGlobalIsClicked($page);
                break;
        }
        $this->checkNumberOfSelectedOnPage($page, $numberOfSelectedOnPage);
        $this->checkNumberOfSelectedGlobal($page, $numberOfSelectedGlobal);
        if ($multiPageSelectionEnabled) {
            $this->checkClearSelectionButton($page, $numberOfSelectedGlobal);
        }
    }

    /**
     * Click select all on page checkbox.
     *
     * @param $page Element element
     *
     * @return void
     */
    protected function clickSelectAllOnPage($page)
    {
        $this->clickCss($page, '[name=bulkActionForm] .checkbox-select-all');
    }

    /**
     * Click select all global checkbox.
     *
     * @param $page Element element
     *
     * @return void
     */
    protected function clickSelectAllGlobal(Element $page)
    {
        $this->clickCss($page, '[name=bulkActionForm] .checkbox-select-all-global');
    }

    /**
     * Click clear selection button.
     *
     * @param $page Element element
     *
     * @return void
     */
    protected function clickClearSelection(Element $page)
    {
        $this->clickCss($page, '[name=bulkActionForm] .clear-selection');
    }

    /**
     * Click a specific element checkbox.
     *
     * @param Element $page  Page element
     * @param int     $index Index of the element to select
     *
     * @return void
     */
    protected function clickSelectSingleElement($page, $index)
    {
        $this->clickCss($page, '.checkbox-select-item', null, $index);
    }

    /**
     * Go to the previous page.
     *
     * @param Element $page Page element
     *
     * @return void
     */
    protected function gotoPrevPage($page)
    {
        $this->clickCss($page, $this->pagePrevSelector);
    }

    /**
     * Go to the next page.
     *
     * @param Element $page Page element
     *
     * @return void
     */
    protected function gotoNextPage(Element $page)
    {
        $this->clickCss($page, $this->pageNextSelector);
    }

    /**
     * Sets up a favorite for the following tests.
     * Creates a user and adds 100 item to the favorite list.
     *
     * @return void
     */
    public function testPrepareFavoriteList()
    {
        $this->changeConfigs(
            [
                'config' =>
                    ['Site' => ['showBulkOptions' => true]],
                'searches' =>
                    ['General' => ['default_limit' => '100']],
            ]
        );
        // Go to search
        $session = $this->getMinkSession();
        $session->visit($this->getVuFindUrl() . '/Search/Home');
        $page = $session->getPage();
        $this->findCssAndSetValue($page, '#searchForm_lookfor', 'test');
        $this->clickCss($page, '.btn.btn-primary');
        $this->waitForPageLoad($page);

        // Start adding results to favorites
        $this->findCss($page, '#addFormCheckboxSelectAll')->check();
        $this->clickCss($page, '#ribbon-save');

        // Create account
        $this->clickCss($page, '.createAccountLink');
        $this->fillInAccountForm($page, ['email' => 'username1@ignore.com']);
        $this->fillInLoginForm($page, 'username1', 'test');
        $this->submitLoginForm($page);
        $this->waitForPageLoad($page);

        // Finish adding results to favorites
        $this->clickCss($page, '.modal-body .btn.btn-primary');
        $this->waitForPageLoad($page);
    }

    /**
     * Test with disabled multi page selection and no select all checkboxes
     *
     * @depends testPrepareFavoriteList
     *
     * @return void
     */
    public function testDisabledMultiPageSelectionCheckboxTypeNone()
    {

        $page = $this->setupTest([
            'multi_page_favorites_selection' => false,
            'checkbox_select_all_favorites_type' => 'none',
        ]);
        $this->checkStatus($page, self::NONE, self::NONE, 0, 0);

        $this->clickSelectSingleElement($page, 0);
        $this->checkStatus($page, self::NONE, self::NONE, 1, 1);

        $this->clickSelectSingleElement($page, 1);
        $this->clickSelectSingleElement($page, 2);
        $this->checkStatus($page, self::NONE, self::NONE, 3, 3);

        $this->clickSelectSingleElement($page, 0);
        $this->checkStatus($page, self::NONE, self::NONE, 2, 2);

        $this->gotoNextPage($page);
        $this->checkStatus($page, self::NONE, self::NONE, 0, 0);

        $this->gotoPrevPage($page);
        $this->checkStatus($page, self::NONE, self::NONE, 0, 0);
    }

    /**
     * Test with disabled multi page selection and select all on page checkbox
     *
     * @depends testPrepareFavoriteList
     *
     * @return void
     */
    public function testDisabledMultiPageSelectionCheckboxTypeOnPage()
    {

        $page = $this->setupTest([
            'multi_page_favorites_selection' => false,
            'checkbox_select_all_favorites_type' => 'on_page',
        ]);
        $this->checkStatus($page, self::UNCHECKED, self::NONE, 0, 0);

        $this->clickSelectSingleElement($page, 0);
        $this->checkStatus($page, self::UNCHECKED, self::NONE, 1, 1);

        $this->clickSelectSingleElement($page, 1);
        $this->clickSelectSingleElement($page, 2);
        $this->checkStatus($page, self::UNCHECKED, self::NONE, 3, 3);

        $this->clickSelectSingleElement($page, 0);
        $this->checkStatus($page, self::UNCHECKED, self::NONE, 2, 2);

        $this->clickSelectAllOnPage($page);
        $this->checkStatus($page, self::CHECKED, self::NONE, 20, 20);

        $this->clickSelectSingleElement($page, 0);
        $this->checkStatus($page, self::UNCHECKED, self::NONE, 19, 19);

        $this->clickSelectSingleElement($page, 0);
        $this->checkStatus($page, self::CHECKED, self::NONE, 20, 20);

        $this->gotoNextPage($page);
        $this->checkStatus($page, self::UNCHECKED, self::NONE, 0, 0);

        $this->clickSelectSingleElement($page, 0);
        $this->checkStatus($page, self::UNCHECKED, self::NONE, 1, 1);

        $this->clickSelectAllOnPage($page);
        $this->checkStatus($page, self::CHECKED, self::NONE, 20, 20);

        $this->clickSelectAllOnPage($page);
        $this->checkStatus($page, self::UNCHECKED, self::NONE, 0, 0);

        $this->gotoPrevPage($page);
        $this->checkStatus($page, self::UNCHECKED, self::NONE, 0, 0);
    }

    /**
     * Test with disabled multi page selection and select all global checkbox
     *
     * @depends testPrepareFavoriteList
     *
     * @return void
     */
    public function testDisabledMultiPageSelectionCheckboxTypeGlobal()
    {

        $page = $this->setupTest([
            'multi_page_favorites_selection' => false,
            'checkbox_select_all_favorites_type' => 'global',
        ]);
        // the select all global checkbox should not be shown
        // if multi page selection is disabled
        $this->checkStatus($page, self::NONE, self::NONE, 0, 0);
    }

    /**
     * Test with disabled multi page selection and both select all checkboxes
     *
     * @depends testPrepareFavoriteList
     *
     * @return void
     */
    public function testDisabledMultiPageSelectionCheckboxTypeBoth()
    {

        $page = $this->setupTest([
            'multi_page_favorites_selection' => false,
            'checkbox_select_all_favorites_type' => 'both',
        ]);
        // the select all global checkbox should not be shown
        // if multi page selection is disabled
        $this->checkStatus($page, self::UNCHECKED, self::NONE, 0, 0);
    }

    /**
     * Test with multi page selection and no select all checkboxes
     *
     * @depends testPrepareFavoriteList
     *
     * @return void
     */
    public function testMultiPageSelectionCheckboxTypeNone()
    {

        $page = $this->setupTest([
            'multi_page_favorites_selection' => true,
            'checkbox_select_all_favorites_type' => 'none',
        ]);
        $this->checkStatus($page, self::NONE, self::NONE, 0, 0, true);

        $this->clickSelectSingleElement($page, 0);
        $this->checkStatus($page, self::NONE, self::NONE, 1, 1, true);

        $this->clickSelectSingleElement($page, 1);
        $this->clickSelectSingleElement($page, 2);
        $this->checkStatus($page, self::NONE, self::NONE, 3, 3, true);

        $this->clickSelectSingleElement($page, 0);
        $this->checkStatus($page, self::NONE, self::NONE, 2, 2, true);

        $this->gotoNextPage($page);
        $this->checkStatus($page, self::NONE, self::NONE, 0, 2, true);

        $this->clickSelectSingleElement($page, 0);
        $this->checkStatus($page, self::NONE, self::NONE, 1, 3, true);

        $this->gotoPrevPage($page);
        $this->checkStatus($page, self::NONE, self::NONE, 2, 3, true);

        $this->clickSelectSingleElement($page, 1);
        $this->checkStatus($page, self::NONE, self::NONE, 1, 2, true);

        $this->clickClearSelection($page);
        $this->checkStatus($page, self::NONE, self::NONE, 0, 0, true);
    }

    /**
     * Test with multi page selection and select all on page checkbox
     *
     * @depends testPrepareFavoriteList
     *
     * @return void
     */
    public function testMultiPageSelectionCheckboxTypeOnPage()
    {

        $page = $this->setupTest([
            'multi_page_favorites_selection' => true,
            'checkbox_select_all_favorites_type' => 'on_page',
        ]);
        $this->checkStatus($page, self::UNCHECKED, self::NONE, 0, 0, true);

        $this->clickSelectSingleElement($page, 0);
        $this->checkStatus($page, self::UNCHECKED, self::NONE, 1, 1, true);

        $this->clickSelectSingleElement($page, 1);
        $this->clickSelectSingleElement($page, 2);
        $this->checkStatus($page, self::UNCHECKED, self::NONE, 3, 3, true);

        $this->clickSelectSingleElement($page, 0);
        $this->checkStatus($page, self::UNCHECKED, self::NONE, 2, 2, true);

        $this->clickSelectAllOnPage($page);
        $this->checkStatus($page, self::CHECKED, self::NONE, 20, 20, true);

        $this->gotoNextPage($page);
        $this->checkStatus($page, self::UNCHECKED, self::NONE, 0, 20, true);

        $this->clickSelectSingleElement($page, 0);
        $this->checkStatus($page, self::UNCHECKED, self::NONE, 1, 21, true);

        $this->clickSelectAllOnPage($page);
        $this->checkStatus($page, self::CHECKED, self::NONE, 20, 40, true);

        $this->clickSelectSingleElement($page, 0);
        $this->checkStatus($page, self::UNCHECKED, self::NONE, 19, 39, true);

        $this->clickSelectAllOnPage($page);
        $this->checkStatus($page, self::CHECKED, self::NONE, 20, 40, true);

        $this->clickSelectAllOnPage($page);
        $this->checkStatus($page, self::UNCHECKED, self::NONE, 0, 20, true);

        $this->gotoPrevPage($page);
        $this->checkStatus($page, self::CHECKED, self::NONE, 20, 20, true);

        $this->clickClearSelection($page);
        $this->checkStatus($page, self::UNCHECKED, self::NONE, 0, 0, true);
    }

    /**
     * Test with multi page selection and select all global checkbox
     *
     * @depends testPrepareFavoriteList
     *
     * @return void
     */
    public function testMultiPageSelectionCheckboxTypeGlobal()
    {

        $page = $this->setupTest([
            'multi_page_favorites_selection' => true,
            'checkbox_select_all_favorites_type' => 'global',
        ]);
        $this->checkStatus($page, self::NONE, self::UNCHECKED, 0, 0, true);

        $this->clickSelectSingleElement($page, 0);
        $this->checkStatus($page, self::NONE, self::UNCHECKED, 1, 1, true);

        $this->clickSelectSingleElement($page, 1);
        $this->clickSelectSingleElement($page, 2);
        $this->checkStatus($page, self::NONE, self::UNCHECKED, 3, 3, true);

        $this->clickSelectSingleElement($page, 0);
        $this->checkStatus($page, self::NONE, self::UNCHECKED, 2, 2, true);

        $this->clickSelectSingleElement($page, 0);
        for ($i = 3; $i < 20; $i++) {
            $this->clickSelectSingleElement($page, $i);
        }
        $this->checkStatus($page, self::NONE, self::UNCHECKED, 20, 20, true);

        $this->gotoNextPage($page);
        $this->checkStatus($page, self::NONE, self::UNCHECKED, 0, 20, true);

        $this->clickSelectSingleElement($page, 0);
        $this->checkStatus($page, self::NONE, self::UNCHECKED, 1, 21, true);

        $this->gotoPrevPage($page);
        $this->checkStatus($page, self::NONE, self::UNCHECKED, 20, 21, true);

        $this->clickSelectSingleElement($page, 10);
        $this->clickSelectSingleElement($page, 11);
        $this->checkStatus($page, self::NONE, self::UNCHECKED, 18, 19, true);

        $this->clickSelectAllGlobal($page);
        $this->checkStatus($page, self::NONE, self::CHECKED, 20, 100, true);

        $this->gotoNextPage($page);
        $this->checkStatus($page, self::NONE, self::CHECKED, 20, 100, true);

        $this->clickSelectSingleElement($page, 0);
        $this->checkStatus($page, self::NONE, self::UNCHECKED, 19, 99, true);

        $this->clickSelectSingleElement($page, 0);
        $this->checkStatus($page, self::NONE, self::CHECKED, 20, 100, true);

        $this->clickSelectSingleElement($page, 0);
        $this->checkStatus($page, self::NONE, self::UNCHECKED, 19, 99, true);

        $this->gotoNextPage($page);
        $this->checkStatus($page, self::NONE, self::UNCHECKED, 20, 99, true);

        $this->clickSelectAllGlobal($page);
        $this->checkStatus($page, self::NONE, self::CHECKED, 20, 100, true);

        $this->clickClearSelection($page);
        $this->checkStatus($page, self::NONE, self::UNCHECKED, 0, 0, true);
    }

    /**
     * Test with multi page selection and both select all checkboxes
     *
     * @depends testPrepareFavoriteList
     *
     * @return void
     */
    public function testMultiPageSelectionCheckboxTypeBoth()
    {

        $page = $this->setupTest([
            'multi_page_favorites_selection' => true,
            'checkbox_select_all_favorites_type' => 'both',
        ]);
        $this->checkStatus($page, self::UNCHECKED, self::UNCHECKED, 0, 0, true);

        $this->clickSelectSingleElement($page, 0);
        $this->checkStatus($page, self::UNCHECKED, self::UNCHECKED, 1, 1, true);

        $this->clickSelectSingleElement($page, 1);
        $this->clickSelectSingleElement($page, 2);
        $this->checkStatus($page, self::UNCHECKED, self::UNCHECKED, 3, 3, true);

        $this->clickSelectSingleElement($page, 0);
        $this->checkStatus($page, self::UNCHECKED, self::UNCHECKED, 2, 2, true);

        $this->clickSelectSingleElement($page, 0);
        for ($i = 3; $i < 20; $i++) {
            $this->clickSelectSingleElement($page, $i);
        }
        $this->checkStatus($page, self::CHECKED, self::UNCHECKED, 20, 20, true);

        $this->gotoNextPage($page);
        $this->checkStatus($page, self::UNCHECKED, self::UNCHECKED, 0, 20, true);

        $this->clickSelectSingleElement($page, 0);
        $this->checkStatus($page, self::UNCHECKED, self::UNCHECKED, 1, 21, true);

        $this->gotoPrevPage($page);
        $this->checkStatus($page, self::CHECKED, self::UNCHECKED, 20, 21, true);

        $this->clickSelectSingleElement($page, 10);
        $this->clickSelectSingleElement($page, 11);
        $this->checkStatus($page, self::UNCHECKED, self::UNCHECKED, 18, 19, true);

        $this->clickSelectAllGlobal($page);
        $this->checkStatus($page, self::CHECKED, self::CHECKED, 20, 100, true);

        $this->gotoNextPage($page);
        $this->checkStatus($page, self::CHECKED, self::CHECKED, 20, 100, true);

        $this->clickSelectSingleElement($page, 0);
        $this->checkStatus($page, self::UNCHECKED, self::UNCHECKED, 19, 99, true);

        $this->clickSelectSingleElement($page, 0);
        $this->checkStatus($page, self::CHECKED, self::CHECKED, 20, 100, true);

        $this->clickSelectSingleElement($page, 0);
        $this->checkStatus($page, self::UNCHECKED, self::UNCHECKED, 19, 99, true);

        $this->gotoNextPage($page);
        $this->checkStatus($page, self::CHECKED, self::UNCHECKED, 20, 99, true);

        $this->clickSelectAllGlobal($page);
        $this->checkStatus($page, self::CHECKED, self::CHECKED, 20, 100, true);

        $this->clickSelectAllGlobal($page);
        $this->checkStatus($page, self::UNCHECKED, self::UNCHECKED, 0, 0, true);

        $this->clickSelectAllOnPage($page);
        $this->checkStatus($page, self::CHECKED, self::UNCHECKED, 20, 20, true);

        $this->gotoNextPage($page);
        $this->checkStatus($page, self::UNCHECKED, self::UNCHECKED, 0, 20, true);

        $this->clickSelectSingleElement($page, 0);
        $this->checkStatus($page, self::UNCHECKED, self::UNCHECKED, 1, 21, true);

        $this->clickSelectAllOnPage($page);
        $this->checkStatus($page, self::CHECKED, self::UNCHECKED, 20, 40, true);

        $this->clickSelectSingleElement($page, 0);
        $this->checkStatus($page, self::UNCHECKED, self::UNCHECKED, 19, 39, true);

        $this->clickSelectAllOnPage($page);
        $this->checkStatus($page, self::CHECKED, self::UNCHECKED, 20, 40, true);

        $this->clickSelectAllOnPage($page);
        $this->checkStatus($page, self::UNCHECKED, self::UNCHECKED, 0, 20, true);

        $this->gotoPrevPage($page);
        $this->checkStatus($page, self::CHECKED, self::UNCHECKED, 20, 20, true);

        $this->clickSelectAllGlobal($page);
        $this->checkStatus($page, self::CHECKED, self::CHECKED, 20, 100, true);

        $this->clickSelectAllOnPage($page);
        $this->checkStatus($page, self::UNCHECKED, self::UNCHECKED, 0, 80, true);

        $this->gotoNextPage($page);
        $this->checkStatus($page, self::CHECKED, self::UNCHECKED, 20, 80, true);

        $this->gotoPrevPage($page);
        $this->checkStatus($page, self::UNCHECKED, self::UNCHECKED, 0, 80, true);

        $this->clickSelectAllOnPage($page);
        $this->checkStatus($page, self::CHECKED, self::CHECKED, 20, 100, true);

        $this->clickClearSelection($page);
        $this->checkStatus($page, self::UNCHECKED, self::UNCHECKED, 0, 0, true);
    }

    /**
     * Standard teardown method.
     *
     * @return void
     */
    public static function tearDownAfterClass(): void
    {
        static::removeUsers(['username1']);
    }
}
