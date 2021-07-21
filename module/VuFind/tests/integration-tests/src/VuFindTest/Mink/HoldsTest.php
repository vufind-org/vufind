<?php
/**
 * Test class for holds-related functionality.
 *
 * PHP version 7
 *
 * Copyright (C) Villanova University 2021.
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

use Behat\Mink\Element\DocumentElement;
use Behat\Mink\Element\Element;

/**
 * Test class for holds-related functionality.
 *
 * Class must be final due to use of "new static()" by LiveDatabaseTrait.
 *
 * @category VuFind
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 * @retry    4
 */
final class HoldsTest extends \VuFindTest\Integration\MinkTestCase
{
    use \VuFindTest\Feature\DemoDriverTestTrait;
    use \VuFindTest\Feature\LiveDatabaseTrait;
    use \VuFindTest\Feature\UserCreationTrait;

    /**
     * Standard setup method.
     *
     * @return void
     */
    public static function setUpBeforeClass(): void
    {
        static::failIfUsersExist();
    }

    /**
     * Standard setup method.
     *
     * @return void
     */
    public function setUp(): void
    {
        // Give up if we're not running in CI:
        if (!$this->continuousIntegrationRunning()) {
            $this->markTestSkipped('Continuous integration not running.');
            return;
        }
    }

    /**
     * Get config.ini override settings for testing ILS functions.
     *
     * @return array
     */
    public function getConfigIniOverrides(): array
    {
        return [
            'Catalog' => [
                'driver' => 'Demo',
                'holds_mode' => 'driver',
                'title_level_holds_mode' => 'driver',
            ]
        ];
    }

    /**
     * Move the current page to a record by performing a search.
     *
     * @param string $id ID of record to access.
     *
     * @return DocumentElement
     */
    protected function gotoRecordById(string $id = 'testsample1'): DocumentElement
    {
        $session = $this->getMinkSession();
        $session->visit($this->getVuFindUrl() . '/Record/' . urlencode($id));
        return $session->getPage();
    }

    /**
     * Fill in and submit the catalog login form with the provided credentials.
     *
     * @param Element $page     Page element.
     * @param string  $username Username
     * @param string  $password Password
     *
     * @return void
     */
    protected function submitCatalogLoginForm(Element $page, string $username,
        string $password
    ): void {
        $this->findCss($page, '#profile_cat_username')->setValue($username);
        $this->findCss($page, '#profile_cat_password')->setValue($password);
        $this->clickCss($page, 'input.btn.btn-primary');
        $this->snooze();
    }

    /**
     * Support method to place a hold and click through to "Your Holds and Recalls."
     *
     * @param Element $page   Page element.
     * @param array   $extras Associative array of selector => value for additional
     * form values to set.
     *
     * @return void
     */
    protected function placeHoldAndGoToHoldsScreen(Element $page, array $extras = []
    ): void {
        // Open the "place hold" dialog
        $this->clickCss($page, 'a.placehold');
        $this->snooze();

        // Set pickup location to a non-default value so we can confirm that
        // the element is being passed through correctly, then submit form:
        if (!isset($extras['#pickUpLocation'])) {
            $extras['#pickUpLocation'] = 'B';
        }
        // Add extra form field values:
        foreach ($extras as $selector => $value) {
            $this->findCss($page, $selector)->setValue($value);
        }
        $this->clickCss($page, '.modal-body .btn.btn-primary');
        $this->snooze();

        // If successful, we should now have a link to review the hold:
        $link = $this->findCss($page, '.modal-body a');
        $this->assertEquals('Your Holds and Recalls', $link->getText());
        $link->click();
        $this->snooze();

        // Make sure we arrived where we expected to:
        $this->assertEquals(
            'Your Holds and Recalls', $this->findCss($page, 'h2')->getText()
        );
    }

    /**
     * Test placing a hold
     *
     * @retryCallback tearDownAfterClass
     *
     * @return void
     */
    public function testPlaceHold(): void
    {
        $this->changeConfigs(
            [
                'config' => $this->getConfigIniOverrides(),
                'Demo' => $this->getDemoIniOverrides(),
            ]
        );
        $page = $this->gotoRecordById();
        $element = $this->findCss($page, '.alert.alert-info a');
        $this->assertEquals('Login for hold and recall information', $element->getText());
        $element->click();
        $this->snooze();
        $this->clickCss($page, '.createAccountLink');
        $this->snooze();
        $this->fillInAccountForm($page);
        $this->clickCss($page, 'input.btn.btn-primary');
        $this->snooze();

        // Test invalid patron login
        $this->submitCatalogLoginForm($page, 'bad', 'incorrect');
        $this->assertEquals(
            'Invalid Patron Login',
            $this->findCss($page, '.alert.alert-danger')->getText()
        );

        // Test valid patron login
        $this->submitCatalogLoginForm($page, 'catuser', 'catpass');
        $this->snooze();

        // Create the hold and go to the holds screen:
        $this->placeHoldAndGoToHoldsScreen($page);

        // Verify the hold is correct:
        $this->assertEquals(
            'Journal of rational emotive therapy :'
            . ' the journal of the Institute for Rational-Emotive Therapy.',
            $this->findCss($page, 'a.title')->getText()
        );
        $this->assertTrue(false !== strstr($page->getContent(), 'Campus B'));

        // Confirm that no cancel buttons appear, since they are not configured:
        $this->assertNull($page->find('css', '#cancelSelected'));
        $this->assertNull($page->find('css', '#cancelAll'));
    }

    /**
     * Click a link inside a drop down button group.
     *
     * @param Element $page Page element.
     * @param string  $text Text to match on link.
     *
     * @return void
     */
    protected function clickButtonGroupLink(Element $page, string $text): void
    {
        $link = $this->findCss($page, '.btn-group.open')->findLink($text);
        $this->assertTrue(is_object($link));
        $link->click();
    }

    /**
     * Test canceling a request.
     *
     * @param Element $page Page element.
     * @param string  $type Request type being tested.
     *
     * @return void
     */
    protected function cancelProcedure(Element $page, string $type): void
    {
        // Test that control is disabled upon empty selection
        $this->clickCss($page, '#cancelSelected');
        $this->assertNull($page->find('css', '.btn-group.open'));

        // Test "cancel all" button -- first make sure item is there before
        // cancel is pushed:
        $this->assertEquals(
            'Journal of rational emotive therapy :'
            . ' the journal of the Institute for Rational-Emotive Therapy.',
            $this->findCss($page, 'a.title')->getText()
        );

        // Click cancel but bail out with no... item should still be there.
        $this->clickCss($page, '#cancelAll');
        $this->clickButtonGroupLink($page, 'No');
        $this->snooze();
        $this->assertEquals(
            'Journal of rational emotive therapy :'
            . ' the journal of the Institute for Rational-Emotive Therapy.',
            $this->findCss($page, 'a.title')->getText()
        );

        // Now cancel for real:
        $this->clickCss($page, '#cancelAll');
        $this->clickButtonGroupLink($page, 'Yes');
        $this->snooze();
        $this->assertEquals(
            '1 request(s) were successfully canceled',
            $this->findCss($page, '.alert.alert-success')->getText()
        );
        $this->assertNull($page->find('css', 'a.title'));
    }

    /**
     * Test canceling a hold.
     *
     * @depends testPlaceHold
     *
     * @return void
     */
    public function testCancelHold(): void
    {
        // Turn on "cancel holds" in addition to normal defaults:
        $config = $this->getConfigIniOverrides();
        $config['Catalog']['cancel_holds_enabled'] = 1;
        $this->changeConfigs(
            [
                'config' => $config,
                'Demo' => $this->getDemoIniOverrides(),
            ]
        );

        // Log in the user on the record page:
        $page = $this->gotoRecordById();
        $element = $this->findCss($page, '.alert.alert-info a');
        $this->assertEquals('Login for hold and recall information', $element->getText());
        $element->click();
        $this->snooze();
        $this->fillInLoginForm($page, 'username1', 'test', false);
        $this->submitLoginForm($page, false);

        // Place the hold:
        $this->placeHoldAndGoToHoldsScreen($page);

        // Test canceling the hold:
        $this->cancelProcedure($page, 'holds');
    }

    /**
     * Create a frozen hold and navigate to the holds list. Return the page
     * object that was set up during the process.
     *
     * @return DocumentElement
     */
    protected function setUpFrozenHold(): DocumentElement
    {
        // Log in the user on the record page:
        $page = $this->gotoRecordById();
        $element = $this->findCss($page, '.alert.alert-info a');
        $this->assertEquals('Login for hold and recall information', $element->getText());
        $element->click();
        $this->snooze();
        $this->fillInLoginForm($page, 'username1', 'test', false);
        $this->submitLoginForm($page, false);

        // Place the hold:
        $futureDate = date("m-d-Y", strtotime("+2 days"));
        $expectedDate = date("m-d-Y", strtotime("+1 day"));
        $this->placeHoldAndGoToHoldsScreen($page, ['#startDate' => $futureDate]);

        // Confirm that the hold is frozen, as expected (note that the expected
        // date differs from the date entered, because the date entered indicates
        // when the hold will start, and the freeze ends on the previous day):
        $expected = "Frozen (temporarily suspended) through $expectedDate";
        $elementText = $this->findCss($page, ".media-body")->getText();
        $this->assertTrue(
            false !== strstr($elementText, $expected),
            "Missing expected text: $expected in $elementText"
        );

        return $page;
    }

    /**
     * Test creating a frozen hold.
     *
     * @depends testPlaceHold
     *
     * @return void
     */
    public function testFrozenHoldCreation(): void
    {
        $this->changeConfigs(
            [
                'config' => $this->getConfigIniOverrides(),
                'Demo' => $this->getDemoIniOverrides(),
            ]
        );

        $page = $this->setUpFrozenHold();

        // Confirm that no cancel buttons appear, since they are not configured:
        $this->assertNull($page->find('css', '#cancelSelected'));
        $this->assertNull($page->find('css', '#cancelAll'));

        // Confirm that there are no edit controls on the page, since we didn't
        // enable them:
        $this->assertNull($page->find('css', '#update_selected'));
        $this->assertNull($page->find('css', '.hold-edit'));
    }

    /**
     * Test creating, and then editing, a frozen hold.
     *
     * @depends testPlaceHold
     *
     * @return void
     */
    public function testFrozenHoldEditing(): void
    {
        $demoConfig = $this->getDemoIniOverrides();
        $demoConfig['Holds'] = ['updateFields' => 'frozen:frozenThrough:pickUpLocation'];
        $this->changeConfigs(
            [
                'config' => $this->getConfigIniOverrides(),
                'Demo' => $demoConfig,
            ]
        );

        $page = $this->setUpFrozenHold();

        // Confirm that no cancel buttons appear, since they are not configured:
        $this->assertNull($page->find('css', '#cancelSelected'));
        $this->assertNull($page->find('css', '#cancelAll'));

        // Confirm that there are edit controls on the page:
        $this->assertNotNull($page->find('css', '#update_selected'));

        // Open the edit dialog box using the link:
        $this->clickCss($page, '.hold-edit');
        $this->snooze();

        // Release the hold and change the pickup location
        $this->findCss($page, '#frozen')->setValue('0');
        $this->findCss($page, '#pickup_location')->setValue('A');
        $this->findCss($page, '#modal .btn.btn-primary')->click();
        $this->snooze();

        // Confirm that the values have changed
        $this->assertTrue(false !== strstr($page->getContent(), 'Campus A'));
        $this->assertFalse(
            strstr($page->getContent(), 'Frozen (temporarily suspended) through ')
        );
    }

    /**
     * Test editing two holds with different pickup locations.
     *
     * @depends testPlaceHold
     *
     * @return void
     */
    public function testEditingDifferentPickupLocations()
    {
        $demoConfig = $this->getDemoIniOverrides();
        $demoConfig['Holds'] = ['updateFields' => 'frozen:frozenThrough:pickUpLocation'];
        $this->changeConfigs(
            [
                'config' => $this->getConfigIniOverrides(),
                'Demo' => $demoConfig,
            ]
        );

        // Place the first hold:
        $this->setUpFrozenHold();

        // Place the second hold:
        $page = $this->gotoRecordById('dollar$ign/slashcombo');
        $this->placeHoldAndGoToHoldsScreen($page, ['#pickUpLocation' => 'C']);

        // Open the edit dialog box using the button:
        $this->clickCss($page, '.checkbox-select-all');
        $this->clickCss($page, '#update_selected');
        $this->snooze();

        // Confirm that the popup contains a warning message about mismatched
        // pickup locations:
        $expectedMsg = "Selected holds have different options for pick up location. "
            . "Edit a single hold to change its pick up location.";
        $this->assertStringContainsString(
            $expectedMsg,
            $this->findCss($page, '#modal .hold-pickup-location')->getText()
        );

        // Close the modal:
        $this->findCss($page, '#modal .btn.btn-primary')->click();
        $this->snooze();

        // Change the first hold to location C so it matches the second one:
        $this->clickCss($page, '.hold-edit');
        $this->snooze();
        $this->findCss($page, '#pickup_location')->setValue('C');
        $this->findCss($page, '#modal .btn.btn-primary')->click();
        $this->snooze();

        // Locations should now match:
        $this->assertTrue(false !== strstr($page->getContent(), 'Campus C'));
        $this->assertFalse(strstr($page->getContent(), 'Campus B'));

        // Place a third hold:
        $page = $this->gotoRecordById('dollar$ign/slashcombo');
        $this->placeHoldAndGoToHoldsScreen($page, ['#pickUpLocation' => 'A']);

        // Update the two holds that have same possible pick up locations to
        // change the pickup locations to campus C:
        $this->clickCss($page, '.checkbox-select-item', 1000, 0);
        $this->clickCss($page, '.checkbox-select-item', 1000, 2);
        $this->clickCss($page, '#update_selected');
        $this->snooze();
        $this->findCss($page, '#pickup_location')->setValue('C');
        $this->findCss($page, '#modal .btn.btn-primary')->click();
        $this->snooze();

        // Confirm that it worked:
        $this->assertTrue(false !== strstr($page->getContent(), 'Campus C'));
        $this->assertFalse(strstr($page->getContent(), 'Campus A'));
        $this->assertFalse(strstr($page->getContent(), 'Campus B'));
    }

    /**
     * Test creating, and then editing, and then canceling, a frozen hold.
     *
     * @depends testPlaceHold
     *
     * @return void
     */
    public function testFrozenHoldEditingWithCancellation(): void
    {
        $config = $this->getConfigIniOverrides();
        $config['Catalog']['cancel_holds_enabled'] = 1;
        $demoConfig = $this->getDemoIniOverrides();
        $demoConfig['Holds'] = ['updateFields' => 'frozen:frozenThrough:pickUpLocation'];
        $this->changeConfigs(['config' => $config, 'Demo' => $demoConfig]);

        $page = $this->setUpFrozenHold();

        // Open the edit dialog box using the button:
        $this->clickCss($page, '.checkbox-select-all');
        $this->clickCss($page, '#update_selected');
        $this->snooze();

        // Revise the freeze date:
        $futureDate = date('m-d-Y', strtotime('+3 days'));
        $this->findCss($page, '#frozen')->setValue('1');
        $this->findCss($page, '#frozen_through')->setValue($futureDate);
        $this->findCss($page, '#modal .btn.btn-primary')->click();
        $this->snooze();

        // Confirm that the values have changed
        $expected = "Frozen (temporarily suspended) through $futureDate";
        $elementText = $this->findCss($page, ".media-body")->getText();
        $this->assertTrue(
            false !== strstr($elementText, $expected),
            "Missing expected text: $expected in $elementText"
        );

        // Now cancel the hold (using the checkboxes, to test a variation on
        // what we do in the cancelProcedure method)
        $this->clickCss($page, '.checkbox-select-all');
        $this->clickCss($page, '#cancelSelected');
        $this->clickButtonGroupLink($page, 'Yes');
        $this->snooze();
        $this->assertEquals(
            '1 request(s) were successfully canceled',
            $this->findCss($page, '.alert.alert-success')->getText()
        );
        $this->assertNull($page->find('css', 'a.title'));
    }

    /**
     * Test create account path when in holds_mode = "all"
     *
     * IMPORTANT: this test uses an ID with a slash in it; if it fails, ensure
     * that Apache is configured with "AllowEncodedSlashes on" inside the
     * VirtualHost used for your VuFind test instance!
     *
     * @retryCallback removeUsername2
     *
     * @return void
     */
    public function testHoldsAll(): void
    {
        $config = $this->getConfigIniOverrides();
        $config['Catalog']['holds_mode'] = 'all';
        $config['Catalog']['title_level_holds_mode'] = 'always';
        $this->changeConfigs(
            [
                'config' => $config,
                'Demo' => $this->getDemoIniOverrides(),
            ]
        );
        $page = $this->gotoRecordById('dollar$ign/slashcombo');
        // No login at top
        $this->assertNull($page->find('css', '.alert.alert-info a'));
        // Hold links should be visible
        $element = $this->findCss($page, 'a.placehold');
        $element->click();
        $this->snooze();
        // Since we're not logged in...
        $this->clickCss($page, '.createAccountLink');
        $this->snooze();
        $this->fillInAccountForm(
            $page, ['username' => 'username2', 'email' => 'u2@vufind.org']
        );
        $this->clickCss($page, 'input.btn.btn-primary');
        $this->snooze();

        // Test valid patron login
        $this->submitCatalogLoginForm($page, 'catuser', 'catpass');

        // Go directly to holds screen
        // Set pickup location to a non-default value so we can confirm that
        // the element is being passed through correctly, then submit form:
        $this->findCss($page, '#pickUpLocation')->setValue('B');
        $this->clickCss($page, '.modal-body .btn.btn-primary');
        $this->snooze();

        // If successful, we should now have a link to review the hold:
        $link = $this->findCss($page, '.modal-body a');
        $this->assertEquals('Your Holds and Recalls', $link->getText());
        $link->click();
        $this->snooze();

        // Make sure we arrived where we expected to:
        $this->assertEquals(
            'Your Holds and Recalls', $this->findCss($page, 'h2')->getText()
        );
    }

    /**
     * Retry cleanup method in case of failure during testHoldsAll.
     *
     * @return void
     */
    protected function removeUsername2(): void
    {
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
