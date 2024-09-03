<?php

/**
 * Mink URL shortener test class.
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
 * Mink URL shortener test class.
 *
 * @category VuFind
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */
class UrlShortenerTest extends \VuFindTest\Integration\MinkTestCase
{
    use \VuFindTest\Feature\EmailTrait;

    /**
     * Test database-driven URL shortening.
     *
     * @return void
     */
    public function testDatabaseDrivenShortening(): void
    {
        // Set up configs, session and message logging:
        $this->changeConfigs(
            [
                'config' => [
                    'Mail' => [
                        'email_action' => 'enabled',
                        'testOnly' => true,
                        'message_log' => $this->getEmailLogPath(),
                        'url_shortener' => 'database',
                    ],
                ],
            ]
        );
        $session = $this->getMinkSession();
        $searchUrl = $this->getVuFindUrl('/Search/Results?lookfor=test');
        $session->visit($searchUrl);
        $page = $session->getPage();
        $this->resetEmailLog();

        // Click login and request email:
        $this->clickCss($page, '.mailSearch');
        $this->findCssAndSetValue($page, '#email_from', 'username1@ignore.com');
        $this->findCssAndSetValue($page, '#email_to', 'username2@ignore.com');
        $this->clickCss($page, '.modal input[type="submit"]');
        $this->assertEquals('Message Sent', $this->findCssAndGetText($page, '.modal .alert-success'));

        // Extract the link from the provided message:
        $email = file_get_contents($this->getEmailLogPath());
        preg_match('/Link: <(http.*)>/', $email, $matches);
        $shortLink = $matches[1];
        $this->assertNotEquals($searchUrl, $shortLink);
        $this->assertStringContainsString('/short', $shortLink);

        // Now confirm that this sends us back to the correct set of search results:
        $session->visit($shortLink);
        $this->assertEquals($searchUrl, $session->getCurrentUrl());

        // Clean up the email log:
        $this->resetEmailLog();
    }
}
