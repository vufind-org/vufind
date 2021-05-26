<?php
/**
 * Mink ILS actions test class.
 *
 * PHP version 7
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

use Behat\Mink\Element\Element;

/**
 * Mink ILS actions test class.
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
final class IlsActionsTest extends \VuFindTest\Integration\MinkTestCase
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
                'renewals_enabled' => true,
            ]
        ];
    }

    /**
     * Move the current page to a record by performing a search.
     *
     * @param string $id ID of record to access.
     *
     * @return Element
     */
    protected function gotoRecordById(string $id = 'testsample1'): Element
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
     * @param Element $page Page element.
     *
     * @return void
     */
    protected function placeHoldAndGoToHoldsScreen(Element $page): void
    {
        // Open the "place hold" dialog
        $this->clickCss($page, 'a.placehold');
        $this->snooze();

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
     * Support method to place an ILL request and end up on the ILL screen.
     *
     * @param Element $page Page element.
     *
     * @return void
     */
    protected function placeIllRequestAndGoToIllScreen(Element $page): void
    {
        // Open the "place hold" dialog
        $this->snooze();
        $this->clickCss($page, 'a.placeILLRequest');
        $this->snooze();

        // Set pickup location to a non-default value so we can confirm that
        // the element is being passed through correctly, then submit form:
        $this->findCss($page, '#pickupLibrary')->setValue('2');
        $this->snooze();
        $this->findCss($page, '#pickupLibraryLocation')->setValue('3');
        $this->clickCss($page, '.modal-body .btn.btn-primary');
        $this->snooze();

        // If successful, we should now have a link to review the hold:
        $link = $this->findCss($page, '.modal-body a');
        $this->assertEquals('Interlibrary Loan Requests', $link->getText());
        $link->click();
        $this->snooze();

        // Make sure we arrived where we expected to:
        $this->assertEquals(
            'Interlibrary Loan Requests', $this->findCss($page, 'h2')->getText()
        );
    }

    /**
     * Support method to place a storage retrieval request and end up on the SRR
     * screen.
     *
     * @param Element $page Page element.
     *
     * @return void
     */
    protected function placeStorageRetrievalRequestAndGoToSRRScreen(
        Element $page
    ): void {
        // Open the "place hold" dialog
        $this->snooze();
        $this->clickCss($page, 'a.placeStorageRetrievalRequest');
        $this->snooze();

        // Set pickup location to a non-default value so we can confirm that
        // the element is being passed through correctly, then submit form:
        $this->findCss($page, '.modal-body select')->setValue('C');
        $this->clickCss($page, '.modal-body .btn.btn-primary');
        $this->snooze();

        // If successful, we should now have a link to review the hold:
        $link = $this->findCss($page, '.modal-body a');
        $this->assertEquals('Storage Retrieval Requests', $link->getText());
        $link->click();
        $this->snooze();

        // Make sure we arrived where we expected to:
        $this->assertEquals(
            'Storage Retrieval Requests', $this->findCss($page, 'h2')->getText()
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
        $this->snooze(1);

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
        // Test empty selection
        $this->clickCss($page, '#cancelSelected');
        $this->clickButtonGroupLink($page, 'Yes');
        $this->snooze();
        $this->assertEquals(
            'No ' . $type . ' were selected',
            $this->findCss($page, '.alert.alert-danger')->getText()
        );

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
     * Routine to place an ILL request
     *
     * @param Element $page Page element.
     *
     * @return void
     */
    protected function illRequestProcedure(Element $page): void
    {
        $element = $this->findCss($page, '.alert.alert-info a');
        $this->assertEquals('Login for hold and recall information', $element->getText());
        $element->click();
        $this->snooze();
        $this->fillInLoginForm($page, 'username1', 'test', false);
        $this->submitLoginForm($page, false);

        // Place the request:
        $this->placeIllRequestAndGoToIllScreen($page);

        // Verify the request is correct:
        $this->assertEquals(
            'Journal of rational emotive therapy :'
            . ' the journal of the Institute for Rational-Emotive Therapy.',
            $this->findCss($page, 'a.title')->getText()
        );
        $this->assertTrue(false !== strstr($page->getContent(), 'Main Desk'));
    }

    /**
     * Routine to place a storage retrieval request
     *
     * @param Element $page Page element.
     *
     * @return void
     */
    protected function storageRetrievalRequestProcedure(Element $page): void
    {
        $element = $this->findCss($page, '.alert.alert-info a');
        $this->assertEquals('Login for hold and recall information', $element->getText());
        $element->click();
        $this->snooze();
        $this->fillInLoginForm($page, 'username1', 'test', false);
        $this->submitLoginForm($page, false);

        // Place the request:
        $this->placeStorageRetrievalRequestAndGoToSRRScreen($page);

        // Verify the request is correct:
        $this->assertEquals(
            'Journal of rational emotive therapy :'
            . ' the journal of the Institute for Rational-Emotive Therapy.',
            $this->findCss($page, 'a.title')->getText()
        );
        $this->assertTrue(false !== strstr($page->getContent(), 'Campus C'));
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
     * Test ILL requests.
     *
     * @depends testPlaceHold
     *
     * @return void
     */
    public function testIllRequest(): void
    {
        $this->changeConfigs(
            [
                'config' => $this->getConfigIniOverrides(),
                'Demo' => $this->getDemoIniOverrides(),
            ]
        );

        // Log in the user on the record page:
        $page = $this->gotoRecordById();
        $this->illRequestProcedure($page);

        // Confirm that no cancel buttons appear, since they are not configured:
        $this->assertNull($page->find('css', '#cancelSelected'));
        $this->assertNull($page->find('css', '#cancelAll'));
    }

    /**
     * Test canceling an ILL request.
     *
     * @depends testPlaceHold
     *
     * @return void
     */
    public function testCancelIllRequest(): void
    {
        // Turn on "cancel ILL requests" in addition to normal defaults:
        $config = $this->getConfigIniOverrides();
        $config['Catalog']['cancel_ill_requests_enabled'] = 1;
        $this->changeConfigs(
            [
                'config' => $config,
                'Demo' => $this->getDemoIniOverrides(),
            ]
        );

        // Log in the user on the record page:
        $page = $this->gotoRecordById();
        $this->illRequestProcedure($page);

        // Test canceling the request:
        $this->cancelProcedure($page, 'interlibrary loan requests');
    }

    /**
     * Test storage retrieval requests.
     *
     * @depends testPlaceHold
     *
     * @return void
     */
    public function testStorageRetrievalRequest(): void
    {
        $this->changeConfigs(
            [
                'config' => $this->getConfigIniOverrides(),
                'Demo' => $this->getDemoIniOverrides(),
            ]
        );

        // Log in the user on the record page:
        $page = $this->gotoRecordById();
        $this->storageRetrievalRequestProcedure($page);

        // Confirm that no cancel buttons appear, since they are not configured:
        $this->assertNull($page->find('css', '#cancelSelected'));
        $this->assertNull($page->find('css', '#cancelAll'));
    }

    /**
     * Test canceling storage retrieval requests.
     *
     * @depends testPlaceHold
     *
     * @return void
     */
    public function testCancelStorageRetrievalRequest(): void
    {
        // Turn on "cancel storage requests" in addition to normal defaults:
        $config = $this->getConfigIniOverrides();
        $config['Catalog']['cancel_storage_retrieval_requests_enabled'] = 1;
        $this->changeConfigs(
            [
                'config' => $config,
                'Demo' => $this->getDemoIniOverrides(),
            ]
        );

        // Log in the user on the record page:
        $page = $this->gotoRecordById();
        $this->storageRetrievalRequestProcedure($page);

        // Test canceling the request:
        $this->cancelProcedure($page, 'storage retrieval requests');
    }

    /**
     * Test user profile action.
     *
     * @depends testPlaceHold
     *
     * @return void
     */
    public function testProfile(): void
    {
        $this->changeConfigs(
            [
                'config' => $this->getConfigIniOverrides(),
                'Demo' => $this->getDemoIniOverrides(),
            ]
        );

        // Go to user profile screen:
        $session = $this->getMinkSession();
        $session->visit($this->getVuFindUrl() . '/MyResearch/Profile');
        $page = $session->getPage();

        // Log in
        $this->fillInLoginForm($page, 'username1', 'test', false);
        $this->submitLoginForm($page, false);

        // Confirm that demo driver expected values are present:
        $texts = [
            'Lib-catuser', 'Somewhere...', 'Over the Rainbow'
        ];
        foreach ($texts as $text) {
            $this->assertTrue($this->hasElementsMatchingText($page, 'td', $text));
        }
    }

    /**
     * Test renewal action.
     *
     * @depends testPlaceHold
     *
     * @return void
     */
    public function testRenewal(): void
    {
        $this->changeConfigs(
            [
                'config' => $this->getConfigIniOverrides(),
                'Demo' => $this->getDemoIniOverrides(),
            ]
        );

        // Go to user profile screen:
        $session = $this->getMinkSession();
        $session->visit($this->getVuFindUrl() . '/MyResearch/CheckedOut');
        $page = $session->getPage();

        // Log in
        $this->fillInLoginForm($page, 'username1', 'test', false);
        $this->submitLoginForm($page, false);

        // Test submitting with no selected checkboxes:
        $this->clickCss($page, '#renewSelected');
        $this->snooze();
        $this->assertEquals(
            'No items were selected',
            $this->findCss($page, '.alert.alert-danger')->getText()
        );

        // Test "renew all":
        $this->clickCss($page, '#renewAll');
        $this->snooze();
        $this->assertEquals(
            'Renewal Successful',
            $this->findCss($page, '.alert.alert-success')->getText()
        );
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
