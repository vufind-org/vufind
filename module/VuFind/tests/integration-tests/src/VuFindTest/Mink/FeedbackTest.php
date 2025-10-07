<?php

/**
 * Mink Feedback module test class.
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2016.
 * Copyright (C) The National Library of Finland 2024.
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
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */

namespace VuFindTest\Mink;

use Behat\Mink\Element\Element;

/**
 * Mink Feedback module test class.
 *
 * @category VuFind
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */
class FeedbackTest extends \VuFindTest\Integration\MinkTestCase
{
    use \VuFindTest\Feature\EmailTrait;

    /**
     * Get config.ini override settings for testing feedback.
     *
     * @return array
     */
    public function getConfigIniOverrides(): array
    {
        return [
            'Mail' => [
                'testOnly' => '1',
                'message_log' => $this->getEmailLogPath(),
                'message_log_format' => $this->getEmailLogFormat(),
            ],
            'Feedback' => [
                'tab_enabled' => '1',
                'recipient_email' => 'fake@fake.com',
                'blocked_senders' => [
                    '@blockeddomain.com',
                    '/^bar@bad/',
                ],
                'ignored_senders' => [
                    '@ignoreddomain.com',
                    '/^bar@spam/',
                ],
            ],
        ];
    }

    /**
     * Set up the page for testing.
     *
     * @param array $extraConfigs Top-level config.ini overrides
     *
     * @return Element
     */
    protected function setupPage(array $extraConfigs = []): Element
    {
        // Set up configs
        $this->changeConfigs(
            [
                'config' => $extraConfigs + $this->getConfigIniOverrides(),
            ]
        );

        $session = $this->getMinkSession();
        $session->visit($this->getVuFindUrl());
        return $session->getPage();
    }

    /**
     * Fill in the feedback form.
     *
     * @param Element $page  Page element
     * @param string  $email Email to fill in
     * @param string  $msg   Message to fill in
     *
     * @return void
     */
    protected function fillInAndSubmitFeedbackForm(
        Element $page,
        string $email = 'test@test.com',
        string $msg = 'test test test'
    ): void {
        $this->clickCss($page, '#feedbackLink');
        $this->findCssAndSetValue($page, '#modal .form-control[name="name"]', 'Me');
        $this->findCss($page, '#modal .form-control[name="email"]')->setValue($email);
        $this->findCss($page, '#modal #form_FeedbackSite_message')->setValue($msg);
        $this->clickCss($page, '#modal input[type="submit"]');
    }

    /**
     * Data provider for testFeedbackForm
     *
     * @return array
     */
    public static function feedbackFormProvider(): array
    {
        return [
            ['test@test.com', true, true],
            ['foobar@spam.com', true, true],
            ['test@blockeddomain.com', false, false],
            ['bar@bad.com', false, false],
            ['test@ignoreddomain.com', true, false],
            ['bar@spam.com', true, false],
        ];
    }

    /**
     * Test that feedback form can be successfully populated and submitted.
     *
     * @param string $sender        Sender email address
     * @param bool   $expectSuccess Expect successful send?
     * @param bool   $expectEmail   Expect email to be received?
     *
     * @dataProvider feedbackFormProvider
     *
     * @return void
     */
    public function testFeedbackForm(string $sender, bool $expectSuccess, bool $expectEmail): void
    {
        $this->resetEmailLog();
        $page = $this->setupPage();
        $this->fillInAndSubmitFeedbackForm($page, $sender);
        if ($expectSuccess) {
            $this->assertEquals(
                'Thank you for your feedback.',
                $this->findCssAndGetText($page, '#modal .alert-success')
            );
        } else {
            $this->assertEquals(
                'Could not process your feedback. Please try again later.',
                $this->findCssAndGetText($page, '#modal .alert-danger')
            );
        }
        if ($expectEmail) {
            $this->assertStringContainsString(
                'Comments',
                $this->getLoggedEmail()->getBody()->getBody()
            );
        } else {
            $this->expectExceptionMessage('No serialized email message data found');
            $this->getLoggedEmail();
        }
    }

    /**
     * Test that feedback form can save to the database.
     *
     * @return void
     */
    public function testFeedbackFormDatabaseStorage(): void
    {
        $this->changeYamlConfigs(
            [
                'FeedbackForms' => [
                    'forms' => [
                        'FeedbackSite' => [
                            'primaryHandler' => 'database',
                        ],
                    ],
                ],
            ]
        );
        $page = $this->setupPage();
        $feedbackEntries = [
            ['user1@test.com', 'first message'],
            ['user1@test.com', 'second message'],
            ['user2@test.com', 'message from user2'],
        ];
        foreach ($feedbackEntries as $feedbackParams) {
            $this->fillInAndSubmitFeedbackForm($page, ...$feedbackParams);
            $this->assertEquals(
                'Thank you for your feedback.',
                $this->findCssAndGetText($page, '#modal .alert-success')
            );
            $this->clickCss($page, 'button.close');
        }
    }

    /**
     * Test that the feedback admin module works.
     *
     * @return void
     *
     * @depends testFeedbackFormDatabaseStorage
     */
    public function testFeedbackAdmin(): void
    {
        // Go to admin page:
        $this->changeConfigs(['config' => ['Site' => ['admin_enabled' => 1]]]);
        $session = $this->getMinkSession();
        $session->visit($this->getVuFindUrl('/Admin/Feedback'));
        $page = $session->getPage();

        // We expect the three feedback entries created by the previous test:
        $this->assertCount(3, $page->findAll('css', 'input[name="ids[]"]'));

        // We expect specific form name and site URL values:
        $this->assertEquals('All FeedbackSite', $this->findCss($page, '#form_name')->getText());
        $this->assertEquals("All {$this->getVuFindUrl()}/", $this->findCss($page, '#site_url')->getText());

        // Set the first message to "in progress" status:
        $this->findCss($page, '.form-feedback-list .status_update')->setValue('in progress');
        $this->waitForPageLoad($page);
        $this->assertEquals(
            'Feedback status updated successfully',
            $this->findCss($page, '.alert-success')->getText()
        );

        // Apply a filter to see just the "in progress" item:
        $this->findCss($page, '#status')->setValue('in progress');
        $this->clickCss($page, '#feedbacksubmit');
        $this->waitForPageLoad($page);
        $this->assertCount(1, $page->findAll('css', 'input[name="ids[]"]'));

        // Now delete the item and confirm that it is gone:
        $this->clickCss($page, 'input[name="deletePage"]');
        $this->waitForPageLoad($page);
        $this->assertEquals(
            'Warning! You are about to delete 1 feedback messages',
            $this->findCss($page, '.alert-info')->getText()
        );
        $this->clickCss($page, 'input[value="Yes"]');
        $this->waitForPageLoad($page);
        $this->assertEquals(
            '1 feedback responses deleted.',
            $this->findCss($page, '.alert-success')->getText()
        );
        $this->assertCount(0, $page->findAll('css', 'input[name="ids[]"]'));

        // Clear the filter; there should be two items left:
        $page->clickLink('Clear Filter');
        $this->waitForPageLoad($page);
        $this->assertCount(2, $page->findAll('css', 'input[name="ids[]"]'));

        // Clean up the remaining data:
        $this->clickCss($page, 'input[name="deletePage"]');
        $this->waitForPageLoad($page);
        $this->assertEquals(
            'Warning! You are about to delete 2 feedback messages',
            $this->findCss($page, '.alert-info')->getText()
        );
        $this->clickCss($page, 'input[value="Yes"]');
        $this->waitForPageLoad($page);
        $this->assertEquals(
            '2 feedback responses deleted.',
            $this->findCss($page, '.alert-success')->getText()
        );
        $this->assertCount(0, $page->findAll('css', 'input[name="ids[]"]'));
    }

    /**
     * Test that feedback form can be successfully populated and submitted.
     *
     * @return void
     */
    public function testFeedbackFormWithCaptcha(): void
    {
        // By default, no OpenURL on record page:
        $page = $this->setupPage(
            [
                'Captcha' => ['types' => ['demo'], 'forms' => 'feedback'],
            ]
        );
        $this->fillInAndSubmitFeedbackForm($page);
        // CAPTCHA should have failed...
        $this->assertEquals(
            'CAPTCHA not passed',
            $this->findCssAndGetText($page, '.modal-body .alert-danger')
        );
        // Now fix the CAPTCHA
        $this->findCss($page, 'form [name="demo_captcha"]')
            ->setValue('demo');
        $this->clickCss($page, '#modal input[type="submit"]');
        $this->assertEquals(
            'Thank you for your feedback.',
            $this->findCssAndGetText($page, '#modal .alert-success')
        );
    }

    /**
     * Test feedback form with the interval captcha.
     *
     * @return void
     */
    public function testIntervalCaptcha(): void
    {
        $page = $this->setupPage(
            [
                'Captcha' => [
                    'types' => ['interval'],
                    'forms' => 'feedback',
                    'action_interval' => 60,
                ],
            ]
        );
        // Test that submission is blocked:
        $this->fillInAndSubmitFeedbackForm($page);
        $this->assertMatchesRegularExpression(
            '/This action can only be performed after (\d+) seconds/',
            $this->findCssAndGetText($page, '#modal .alert-danger'),
        );

        // Set up with no real delay and test that submission is passed:
        $page = $this->setupPage(
            [
                'Captcha' => [
                    'types' => ['interval'],
                    'forms' => 'feedback',
                    'action_interval' => 0,
                ],
            ]
        );
        $this->fillInAndSubmitFeedbackForm($page);
        $this->assertEquals(
            'Thank you for your feedback.',
            $this->findCssAndGetText($page, '#modal .alert-success')
        );
    }
}
