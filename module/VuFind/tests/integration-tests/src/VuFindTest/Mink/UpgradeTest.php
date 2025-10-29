<?php

/**
 * Mink upgrade controller test class.
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
use VuFind\Config\Version;

/**
 * Mink upgrade controller test class.
 *
 * Class must be final due to use of "new static()" by LiveDatabaseTrait.
 *
 * @category VuFind
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */
final class UpgradeTest extends \VuFindTest\Integration\MinkTestCase
{
    use \VuFindTest\Feature\LiveDatabaseTrait;
    use \VuFindTest\Feature\TagTrait;
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
     * Move the current page to a record by direct URL access.
     *
     * @param string $id Record ID to load.
     *
     * @return Element
     */
    protected function gotoRecord(string $id = 'testbug1'): Element
    {
        $session = $this->getMinkSession();
        $session->visit($this->getVuFindUrl('/Record/' . rawurlencode($id)));
        return $session->getPage();
    }

    /**
     * Test that we pick a reasonable default "from" version.
     *
     * @return void
     */
    public function testDefaultUpgradeFromVersion(): void
    {
        // Now go to the upgrade page:
        $session = $this->getMinkSession();
        $session->visit($this->getVuFindUrl('/Upgrade'));
        $page = $session->getPage();
        // Confirm that the source version defaults to the current version.
        $this->assertEquals(
            Version::getBuildVersion(),
            $this->findCssAndGetValue($page, 'input[name="sourceversion"]')
        );
    }

    /**
     * Test that the upgrade controller deduplicates tags.
     *
     * @return void
     */
    public function testTagDeduplication(): void
    {
        // Turn on case sensitive tags so we can create some duplicates:
        $this->changeConfigs(
            [
                'config' => [
                    'Social' => [
                        'case_sensitive_tags' => true,
                    ],
                ],
            ]
        );
        // Go to a record view
        $page = $this->gotoRecord();
        // Click to add tag
        $this->clickCss($page, '.tag-record');
        // Make account
        $this->makeAccount($page, 'username1');
        // Create tags to demonstrate case-insensitivity:
        $this->addTagsToRecord($page, 'foo foO fOo');
        // Count tags
        $this->waitForPageLoad($page);
        $this->assertEquals(['fOo', 'foO', 'foo'], $this->getTagsFromPage($page));
        // Now switch to case-insensitive tags:
        $this->changeConfigs(
            [
                'config' => [
                    'Social' => [
                        'case_sensitive_tags' => false,
                    ],
                ],
            ]
        );
        // Verify that there are duplicates on the page:
        $page = $this->gotoRecord();
        $this->assertEquals(['foo', 'foo', 'foo'], $this->getTagsFromPage($page));
        // Now go to the upgrade page:
        $this->getMinkSession()->visit($this->getVuFindUrl('/Upgrade'));
        // Upgrade the database only:
        $this->clickCss($page, '#skip-config');
        $this->clickCss($page, '.main input.btn-primary');
        $this->waitForPageLoad($page);
        // Skip the security warning:
        $this->assertEquals('Critical Issue: Insecure database settings', $this->findCssAndGetText($page, 'h2'));
        $ignoreSelector = '.main a.btn-default';
        $this->assertEquals('ignore the problem', $this->findCssAndGetText($page, $ignoreSelector));
        $this->clickCss($page, $ignoreSelector);
        $this->waitForPageLoad($page);
        // Now we should be on the duplicate tag screen; let's submit it:
        $this->assertStringContainsString('duplicate tags', $this->findCssAndGetText($page, 'p'));
        $this->clickCss($page, 'input[name="submitButton"]');
        $this->waitForPageLoad($page);
        $this->assertStringContainsString('Upgrade complete.', $this->findCssAndGetText($page, 'p'));
        // Upgrade should now be complete; verify that deduplication worked.
        $page = $this->gotoRecord();
        $this->assertEquals(['foo'], $this->getTagsFromPage($page));
        // Clean up the tag now that we're done by deleting in tag admin:
        $page = $this->goToTagAdmin('/Manage');
        $this->findCss($page, '#type')->setValue('tag');
        $this->clickCss($page, 'input[value="Submit"]');
        $this->waitForPageLoad($page);
        $this->clickCss($page, 'input[value="Delete Tags"]');
        $this->waitForPageLoad($page);
        $this->clickCss($page, 'input[value="Yes"]');
        $this->waitForPageLoad($page);
        $this->assertEquals(
            '1 tag(s) deleted',
            $this->findCss($page, '.alert-success')->getText()
        );
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
