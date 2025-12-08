<?php

/**
 * Mink fallback loader test class.
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

namespace VuFindTest\Mink;

use Behat\Mink\Element\Element;
use Exception;
use PHPUnit\Framework\ExpectationFailedException;
use VuFind\Db\Service\ResourceService;

/**
 * Mink fallback loader test class.
 *
 * Class must be final due to use of "new static()" by LiveDatabaseTrait.
 *
 * @category VuFind
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */
final class FallbackLoaderTest extends \VuFindTest\Integration\MinkTestCase
{
    use \VuFindTest\Feature\LiveDatabaseTrait;
    use \VuFindTest\Feature\RetryClickTrait;
    use \VuFindTest\Feature\UserCreationTrait;

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
     * Go to the specified record page.
     *
     * @param string $id Record to visit
     *
     * @return Element
     */
    protected function goToRecord(string $id): Element
    {
        $session = $this->getMinkSession();
        $session->visit($this->getVuFindUrl() . '/Record/' . $id);
        return $session->getPage();
    }

    /**
     * Add a favorite with a tag.
     *
     * @param Element $page          Page object
     * @param string  $tag           Tag to add
     * @param ?string $newListName   Name of new list to create (null to skip list creation)
     * @param bool    $createAccount Should we create an account?
     *
     * @return void
     */
    protected function addFavoriteWithTag(
        Element $page,
        string $tag,
        ?string $newListName = null,
        bool $createAccount = false
    ): void {
        $this->waitForPageLoad($page);
        $this->clickCss($page, '.save-record');
        if ($createAccount) {
            $this->clickCss($page, '.modal-body .createAccountLink');
            $this->fillInAccountForm($page);
            $this->clickCss($page, '.modal-body .btn.btn-primary');
        }
        $this->findCss($page, '#save_list');
        if ($newListName) {
            $this->clickCss($page, '#make-list');
            $this->waitForPageLoad($page);
            $this->findCssAndSetValue($page, '#list_title', $newListName);
            $this->clickCss($page, '.modal-body .btn.btn-primary');
            $this->assertEquals(
                $newListName,
                trim($this->findCssAndGetHtml($page, '#save_list option[selected]'))
            );
        }
        $this->findCssAndSetValue($page, '#add_mytags', $tag);
        $this->clickCss($page, '.modal-body .btn.btn-primary');
        $this->findCss($page, '.modal .alert.alert-success');
        $this->clickCss($page, '.modal-body .btn.btn-default');
        $this->waitForLightboxHidden();
    }

    /**
     * Add a comment (assumes you are on record page and logged in).
     *
     * @param Element $page    Page object
     * @param string  $comment Comment to add.
     *
     * @return void
     */
    protected function addComment(Element $page, string $comment): void
    {
        $this->clickCss($page, '.record-tabs .usercomments a');
        $this->findCss($page, '.comment-form');
        $this->findCssAndSetValue($page, 'form.comment-form [name="comment"]', $comment);
        $buttonSelector = 'form.comment-form .btn-primary';
        $this->clickCss($page, $buttonSelector);
        $commentSelector = '.comment-text';
        try {
            // We don't want to wait the full default timeout here since that wastes a lot
            // of time if a click failed to register; however, we shouldn't wait for too
            // short of a time, or else a slow response can break the test by causing a
            // double form submission.
            $this->findCss($page, $commentSelector, 1500);
        } catch (\Exception $e) {
            $this->retryClickWithResizedWindow($this->getMinkSession(), $page, $buttonSelector);
        }
        $this->assertEquals($comment, $this->findCssAndGetText($page, $commentSelector));
    }

    /**
     * Assert that the expected merged tags and comments are present on the page.
     *
     * @param Element $page Page object
     *
     * @return void
     * @throws Exception
     * @throws ExpectationFailedException
     */
    protected function assertMergedResults(Element $page): void
    {
        $this->assertEquals('new_tag 1 old_tag 1', $this->findCssAndGetText($page, '.tagList'));
        $this->assertEquals('old_list new_list', $this->findCssAndGetText($page, '.savedLists.loaded ul'));
        $this->clickCss($page, '.record-tabs .usercomments a');
        $this->assertEquals('old comment', $this->findCssAndGetText($page, '.comment-text', index: 0));
        $this->assertEquals('new comment', $this->findCssAndGetText($page, '.comment-text', index: 1));
    }

    /**
     * Test the fallback loader, based on manuial procedure documented at this wiki page:
     * https://vufind.org/wiki/development:testing:manual_testing#merging_record_data_when_ids_change
     *
     * @return void
     */
    public function testFallbackLoader(): void
    {
        $newId = 'vtls000000329';
        $oldId = '(IeDuNL)1048';

        // Create a user account and create a favorite, tag and comment to serve as "old data":
        $page = $this->gotoRecord($newId);
        $this->addFavoriteWithTag($page, 'old_tag', 'old_list', createAccount: true);
        $this->addComment($page, 'old comment');

        // We created the "old data" on the new ID, because the old ID doesn't really exist; our test
        // is just a simulation. Thus, we need to migrate the newly-created data to the old ID manually:
        $resourceService = $this->getDbService(ResourceService::class);
        $resource = $resourceService->getResourceByRecordId($newId);
        $resource->setRecordId($oldId);
        $resourceService->persistEntity($resource);

        // Now that the data has been moved away, let's create a new set of data on the new ID:
        $this->addFavoriteWithTag($page, 'new_tag', 'new_list');
        $this->addComment($page, 'new comment');

        // Now set up the Solr-based fallback loader to use the ctrlnum field as the fallback ID. This is
        // how we trick the test environment into thinking that $oldId is a previous identifier for $newId,
        // based on data in our existing test records.
        $this->changeConfigs(
            [
                'searches' => [
                    'General' => [
                        'fallback_id_field' => 'ctrlnum',
                    ],
                ],
            ]
        );

        // Now, try to access the old ID -- it should contain all of our data merged together:
        $this->assertMergedResults($this->goToRecord($oldId));

        // Finally, return to the new ID -- it should also contain the same information:
        $this->assertMergedResults($this->goToRecord($newId));
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
