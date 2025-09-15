<?php

/**
 * Logging integration test.
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
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301 USA
 *
 * @category VuFind
 * @package  Tests
 * @author   Sambhav Pokharel <sambhavpokharel@gmail.com>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */

namespace VuFindTest\Mink;

use VuFindTest\Integration\MinkTestCase;

/**
 * Logging integration test.
 *
 * @category VuFind
 * @package  Tests
 * @author   Sambhav Pokharel <sambhavpokharel@gmail.com>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */
class LoggingTest extends MinkTestCase
{
    use \VuFindTest\Feature\EmailTrait;

    /**
     * Data provider for logging test scenarios
     *
     * @return array
     */
    public static function loggingScenarioProvider(): array
    {
        return [
            'error_and_alert_logging' => [
                'email_config'        => 'alerts@myuniversity.edu:alert-5,error-5',
                'expected_patterns'   => [
                    '/VuFind Log Alert/',
                    '/CRITICAL:/',
                    '/404 Not Found/',
                    '/RequestErrorException/',
                    '/VuFindSearch\\\\Backend\\\\Exception/',
                    // More flexible URL pattern
                    '/Search\/Results.*lookfor.*test/',
                ],
                'unexpected_patterns' => [
                    '/DEBUG:/',
                    '/INFO:/',
                ],
                'min_emails'          => 1,
                'description'         => 'Should log critical errors when Solr connection fails',
            ],
            'debug_logging_only'      => [
                'email_config'        => 'debug@myuniversity.edu:debug-5',
                'expected_patterns'   => [
                    '/VuFind Log Alert/',
                    '/DEBUG:/',
                ],
                'unexpected_patterns' => [
                ],
                'min_emails'          => 1,
                'description'         => 'Should capture debug messages when debug logging is enabled',
            ],
            'minimal_detail_level'    => [
                'email_config'        => 'alerts@myuniversity.edu:error-1',
                'expected_patterns'   => [
                    '/VuFind Log Alert/',
                    '/CRITICAL:/',
                    '/404 Not Found/',
                ],
                'unexpected_patterns' => [
                    '/Backtrace:/',
                    '/Server Context:/',
                    '/Array/',
                ],
                'min_emails'          => 1,
                'description'         => 'Should provide minimal detail at level 1',
            ],
            'maximum_detail_level'    => [
                'email_config'        => 'alerts@myuniversity.edu:error-5',
                'expected_patterns'   => [
                    '/VuFind Log Alert/',
                    '/CRITICAL:/',
                    '/404 Not Found/',
                    '/Backtrace:/',
                    '/Server Context:/',
                    '/HTTP_USER_AGENT/',
                    '/REQUEST_URI/',
                ],
                'unexpected_patterns' => [],
                'min_emails'          => 1,
                'description'         => 'Should provide maximum detail at level 5',
            ],
        ];
    }

    /**
     * Test email logging functionality with various configurations
     *
     * @param string $emailConfig        Email configuration string
     * @param array  $expectedPatterns   Patterns that should be found in log
     * @param array  $unexpectedPatterns Patterns that should NOT be found in log
     * @param int    $minEmails          Minimum number of emails expected
     * @param string $description        Test scenario description
     *
     * @return void
     *
     * @dataProvider loggingScenarioProvider
     */
    public function testLogging(
        string $emailConfig,
        array $expectedPatterns,
        array $unexpectedPatterns,
        int $minEmails,
        string $description
    ): void {
        $this->changeConfigs([
            'config' => [
                'Index'   => [
                    'url' => 'http://localhost:8983/not-solr',
                ],
                'Mail'    => [
                    'testOnly'           => true,
                    'message_log'        => $this->getEmailLogPath(),
                    'message_log_format' => $this->getEmailLogFormat(),
                ],
                'Logging' => [
                    'email' => $emailConfig,
                ],
            ],
        ]);

        $this->resetEmailLog();

        $session = $this->getMinkSession();
        $session->visit($this->getVuFindUrl() . '/Search/Results?lookfor=test');
        $page = $session->getPage();

        // Wait for logging to complete
        $this->findCss($page, 'body');

        $loggedEmails = $this->getLoggedEmails();
        $allEmailContent = implode('', array_map(fn ($email) => $email->toString(), $loggedEmails));
        $allEmailSubjects = implode('', array_map(fn ($email) => $email->getSubject(), $loggedEmails));
        $allEmailBodies = implode('', array_map(fn ($email) => $email->getBody()->getBody(), $loggedEmails));

        // Basic assertions
        $this->assertNotEmpty(
            $allEmailContent,
            $description . ': Expected to receive log email'
        );

        foreach ($expectedPatterns as $pattern) {
            $this->assertMatchesRegularExpression(
                $pattern,
                $allEmailContent,
                $description . ': Expected pattern not found: ' . $pattern
            );
        }

        foreach ($unexpectedPatterns as $pattern) {
            $this->assertDoesNotMatchRegularExpression(
                $pattern,
                $allEmailContent,
                $description . ': Unexpected pattern found: ' . $pattern
            );
        }

        // Email subject assertion
        $this->assertStringContainsString(
            'VuFind Log Message',
            $allEmailSubjects,
            'Email subject should contain "VuFind Log Message"'
        );

        // Conditional assertions based on log level/type
        if (strpos($emailConfig, 'debug') !== false) {
            $this->assertStringContainsString(
                'DEBUG:',
                $allEmailBodies,
                'Email body should contain debug messages'
            );
            
            $this->assertStringContainsString(
                'not-solr',
                $allEmailBodies,
                'Email body should contain the Solr URL that failed'
            );
        } else {
            $this->assertStringContainsString(
                'RequestErrorException',
                $allEmailBodies,
                'Email body should contain the specific exception type'
            );
        }
        
        $this->assertStringContainsString(
            '404 Not Found',
            $allEmailBodies,
            'Email body should contain the HTTP error'
        );
    }

    /**
     * Test that no emails are sent when logging is disabled
     *
     * @return void
     */
    public function testNoLoggingWhenDisabled(): void
    {
        $this->changeConfigs([
            'config' => [
                'Index' => [
                    'url' => 'http://localhost:8983/not-solr',
                ],
                'Mail'  => [
                    'testOnly'           => true,
                    'message_log'        => $this->getEmailLogPath(),
                    'message_log_format' => $this->getEmailLogFormat(),
                ],
                'Logging' => [
                    'email' => '',
                ],
            ],
        ]);

        $this->resetEmailLog();

        $session = $this->getMinkSession();
        $session->visit($this->getVuFindUrl() . '/Search/Results?lookfor=test');
        $page = $session->getPage();

        // Wait for logging to complete
        $this->findCss($page, 'body');

        $emailLogPath = $this->getEmailLogPath();
        if (file_exists($emailLogPath)) {
            $loggedEmails = trim(file_get_contents($emailLogPath));
            print_r($loggedEmails);
            $this->assertEmpty(
                $loggedEmails,
                'No emails should be sent when email logging is not configured'
            );
        } else {
            $this->assertTrue(true, 'Email log file does not exist, which is expected when logging is disabled');
        }
    }
}