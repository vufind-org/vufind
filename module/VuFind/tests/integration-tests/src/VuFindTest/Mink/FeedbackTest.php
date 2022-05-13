<?php
/**
 * Mink Feedback module test class.
 *
 * PHP version 7
 *
 * Copyright (C) Villanova University 2016.
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
 * Mink Feedback module test class.
 *
 * @category VuFind
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 * @retry    4
 */
class FeedbackTest extends \VuFindTest\Integration\MinkTestCase
{
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
     * Get config.ini override settings for testing feedback.
     *
     * @return array
     */
    public function getConfigIniOverrides()
    {
        return [
            'Mail' => [
                'testOnly' => '1',
            ],
            'Feedback' => [
                'tab_enabled' => '1',
                'recipient_email' => 'fake@fake.com',
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
    protected function setupPage($extraConfigs = [])
    {
        // Set up configs
        $this->changeConfigs(
            [
                'config' => $extraConfigs + $this->getConfigIniOverrides(),
            ]
        );

        $session = $this->getMinkSession();
        $session->visit($this->getVuFindUrl());
        $this->snooze();
        return $session->getPage();
    }

    /**
     * Fill in the feedback form.
     *
     * @param Element $page Page element
     *
     * @return void
     */
    protected function fillInAndSubmitFeedbackForm($page)
    {
        $this->clickCss($page, '#feedbackLink');
        $this->snooze();
        $this->findCss($page, '#modal .form-control[name="name"]')->setValue('Me');
        $this->findCss($page, '#modal .form-control[name="email"]')
            ->setValue('test@test.com');
        $this->findCss($page, "#modal #message")->setValue('test test test');
        $this->clickCss($page, '#modal input[type="submit"]');
        $this->snooze();
    }

    /**
     * Test that feedback form can be successfully populated and submitted.
     *
     * @return void
     */
    public function testFeedbackForm()
    {
        // By default, no OpenURL on record page:
        $page = $this->setupPage();
        $this->fillInAndSubmitFeedbackForm($page);
        $this->assertEquals(
            'Thank you for your feedback.',
            $this->findCss($page, '#modal .alert-success')->getText()
        );
    }

    /**
     * Test that feedback form can be successfully populated and submitted.
     *
     * @return void
     */
    public function testFeedbackFormWithCaptcha()
    {
        // By default, no OpenURL on record page:
        $page = $this->setupPage(
            [
                'Captcha' => ['types' => ['demo'], 'forms' => 'feedback']
            ]
        );
        $this->fillInAndSubmitFeedbackForm($page);
        // CAPTCHA should have failed...
        $this->assertEquals(
            'CAPTCHA not passed',
            $this->findCss($page, '.modal-body .alert-danger')->getText()
        );
        // Now fix the CAPTCHA
        $this->findCss($page, 'form [name="demo_captcha"]')
            ->setValue('demo');
        $this->clickCss($page, '#modal input[type="submit"]');
        $this->snooze();
        $this->assertEquals(
            'Thank you for your feedback.',
            $this->findCss($page, '#modal .alert-success')->getText()
        );
    }
}
