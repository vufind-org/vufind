<?php

/**
 * Logging integration test.
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
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, see
 * <https://www.gnu.org/licenses/>.
 *
 * @category VuFind
 * @package  Tests
 * @author   Sambhav Pokharel <sambhavpokharel@gmail.com>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */

namespace VuFindTest\Mink;

use PHPUnit\Framework\ExpectationFailedException;
use PHPUnit\Framework\GeneratorNotSupportedException;
use VuFind\Db\Connection;
use VuFindTest\Integration\MinkTestCase;

use function count;

/**
 * Logging integration test.
 *
 * Class must be final due to use of "new static()" by LiveDatabaseTrait.
 *
 * @category VuFind
 * @package  Tests
 * @author   Sambhav Pokharel <sambhavpokharel@gmail.com>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */
final class LoggingTest extends MinkTestCase
{
    use \VuFindTest\Feature\EmailTrait;
    use \VuFindTest\Feature\LiveDatabaseTrait;
    use \VuFindTest\Feature\SolrPortTrait;

    protected const CRITICAL_LEVEL_REGEX = '/CRIT/';
    protected const DEBUG_LEVEL_REGEX = '/DEBUG/';
    protected const INFO_LEVEL_REGEX = '/INFO/';

    /**
     * Data provider for email logging test scenarios
     *
     * @return array
     */
    public static function emailLoggingScenarioProvider(): array
    {
        return [
            'debug_error_and_alert_logging' => [
                'emailConfig'        => 'alerts@myuniversity.edu:debug-5,alert-5,error-5',
                'expectedPatterns'   => [
                    self::CRITICAL_LEVEL_REGEX,
                    '/404 Not Found/',
                    '/RequestErrorException/',
                    '/VuFindSearch\\\\Backend\\\\Exception/',
                    '/Search\/Results.*lookfor.*test/',
                    self::DEBUG_LEVEL_REGEX,
                ],
                'unexpectedPatterns' => [
                ],
                'minEmails'          => 2,
                'description'         => 'Should log critical errors when Solr connection fails',
            ],
            'error_and_alert_logging_only' => [
                'emailConfig'        => 'alerts@myuniversity.edu:alert-5,error-5',
                'expectedPatterns'   => [
                    self::CRITICAL_LEVEL_REGEX,
                    '/404 Not Found/',
                    '/RequestErrorException/',
                    '/VuFindSearch\\\\Backend\\\\Exception/',
                    '/Search\/Results.*lookfor.*test/',
                ],
                'unexpectedPatterns' => [
                    self::DEBUG_LEVEL_REGEX,
                    self::INFO_LEVEL_REGEX,
                ],
                'minEmails'          => 1,
                'description'         => 'Should log critical errors when Solr connection fails',
            ],
            'debug_logging_only'      => [
                'emailConfig'        => 'debug@myuniversity.edu:debug-5',
                'expectedPatterns'   => [
                    self::DEBUG_LEVEL_REGEX,
                ],
                'unexpectedPatterns' => [
                    self::CRITICAL_LEVEL_REGEX,
                ],
                'minEmails'          => 1,
                'description'         => 'Should capture debug messages when debug logging is enabled',
            ],
            'minimal_detail_level'    => [
                'emailConfig'        => 'alerts@myuniversity.edu:error-1',
                'expectedPatterns'   => [
                    self::CRITICAL_LEVEL_REGEX,
                    '/404 Not Found/',
                ],
                'unexpectedPatterns' => [
                    '/Backtrace:/',
                    '/\(Server: IP =/',
                    '/Server Context:/',
                    '/Array/',
                    '/args:/',
                ],
                'minEmails'          => 1,
                'description'         => 'Should provide minimal detail at level 1',
            ],
            'detail_level_2'    => [
                'emailConfig'        => 'alerts@myuniversity.edu:error-2',
                'expectedPatterns'   => [
                    self::CRITICAL_LEVEL_REGEX,
                    '/404 Not Found/',
                    '/\(Server: IP =/',
                ],
                'unexpectedPatterns' => [
                    '/Backtrace:/',
                    '/Server Context:/',
                    '/Array/',
                    '/args:/',
                ],
                'minEmails'          => 1,
                'description'         => 'Should provide appropriate detail at level 2',
            ],
            'detail_level_3'    => [
                'emailConfig'        => 'alerts@myuniversity.edu:error-3',
                'expectedPatterns'   => [
                    self::CRITICAL_LEVEL_REGEX,
                    '/404 Not Found/',
                    '/\(Server: IP =/',
                    '/Backtrace:/',
                ],
                'unexpectedPatterns' => [
                    '/Server Context:/',
                    '/Array/',
                    '/args:/',
                ],
                'minEmails'          => 1,
                'description'         => 'Should provide appropriate detail at level 3',
            ],
            'detail_level_4'    => [
                'emailConfig'        => 'alerts@myuniversity.edu:error-4',
                'expectedPatterns'   => [
                    self::CRITICAL_LEVEL_REGEX,
                    '/404 Not Found/',
                    '/Server Context:/',
                    '/Backtrace:/',
                ],
                'unexpectedPatterns' => [
                    '/\(Server: IP =/',
                    '/args:/',
                ],
                'minEmails'          => 1,
                'description'         => 'Should provide appropriate detail at level 4',
            ],
            'maximum_detail_level'    => [
                'emailConfig'        => 'alerts@myuniversity.edu:error-5',
                'expectedPatterns'   => [
                    self::CRITICAL_LEVEL_REGEX,
                    '/404 Not Found/',
                    '/Backtrace:/',
                    '/Server Context:/',
                    '/HTTP_USER_AGENT/',
                    '/REQUEST_URI/',
                    '/args:/',
                ],
                'unexpectedPatterns' => [
                    '/\(Server: IP =/',
                ],
                'minEmails'          => 1,
                'description'         => 'Should provide maximum detail at level 5',
            ],
        ];
    }

    /**
     * Assert that the log content has all expected patterns and no unexpected patterns.
     *
     * @param string   $logContent         Log content
     * @param string[] $expectedPatterns   Array of expected regular expressions
     * @param string[] $unexpectedPatterns Array of unexpected regular expressions
     * @param string   $description        Description of current test scenario
     *
     * @return void
     * @throws ExpectationFailedException
     * @throws GeneratorNotSupportedException
     */
    protected function assertPatternsInLog(
        string $logContent,
        array $expectedPatterns,
        array $unexpectedPatterns,
        string $description
    ): void {
        $this->assertNotEmpty(
            $logContent,
            $description . ': Expected to receive log email'
        );

        foreach ($expectedPatterns as $pattern) {
            $this->assertMatchesRegularExpression(
                $pattern,
                $logContent,
                $description . ': Expected pattern not found: ' . $pattern
            );
        }

        foreach ($unexpectedPatterns as $pattern) {
            $this->assertDoesNotMatchRegularExpression(
                $pattern,
                $logContent,
                $description . ': Unexpected pattern found: ' . $pattern
            );
        }
    }

    /**
     * Wait for a minimum number of emails to be logged, with retry logic
     *
     * @param int $minEmails     Minimum number of emails expected
     * @param int $maxWaitSecs   Maximum time to wait in seconds
     * @param int $checkInterval Check interval in seconds
     *
     * @return array Array of logged emails
     */
    protected function waitForMinimumEmails(
        int $minEmails,
        int $maxWaitSecs = 10,
        int $checkInterval = 1
    ): array {
        $startTime = time();
        while (true) {
            try {
                $loggedEmails = $this->getLoggedEmails();
            } catch (\Exception $e) {
                $loggedEmails = [];
            }

            if (count($loggedEmails) >= $minEmails) {
                return $loggedEmails;
            }

            $elapsed = time() - $startTime;
            if ($elapsed >= $maxWaitSecs) {
                return $loggedEmails;
            }

            sleep($checkInterval);
        }
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
     * @dataProvider emailLoggingScenarioProvider
     */
    public function testEmailLogging(
        string $emailConfig,
        array $expectedPatterns,
        array $unexpectedPatterns,
        int $minEmails,
        string $description
    ): void {
        $port = $this->getSolrPort();
        $this->changeConfigs([
            'config' => [
                'Index'   => [
                    'url' => "http://localhost:$port/not-solr",
                ],
                'Mail'    => [
                    'testOnly'           => true,
                    'message_log'        => $this->getEmailLogPath(),
                    'message_log_format' => $this->getEmailLogFormat(),
                ],
                'Logging' => [
                    'email' => $emailConfig,
                    'file' => null,
                ],
            ],
        ]);

        $this->resetEmailLog();

        $session = $this->getMinkSession();
        $session->visit($this->getVuFindUrl() . '/Search/Results?lookfor=test');
        $page = $session->getPage();

        // Wait for logging to complete
        $this->findCss($page, 'body');

        $loggedEmails = $this->waitForMinimumEmails($minEmails);

        $this->assertGreaterThanOrEqual($minEmails, count($loggedEmails));
        $allEmailContent = preg_replace(
            '/=[\r\n]+/',
            '',
            implode('', array_map(fn ($email) => $email->toString(), $loggedEmails))
        );
        $allEmailSubjects = implode('', array_map(fn ($email) => $email->getSubject(), $loggedEmails));
        $allEmailBodies = implode('', array_map(fn ($email) => $email->getBody()->getBody(), $loggedEmails));

        // Basic assertions
        $this->assertPatternsInLog($allEmailContent, $expectedPatterns, $unexpectedPatterns, $description);

        // Email subject assertion
        $this->assertStringContainsString(
            'VuFind Log Message',
            $allEmailSubjects,
            'Email subject should contain "VuFind Log Message"'
        );

        // Conditional assertions based on log level/type
        if (str_contains($emailConfig, 'debug')) {
            $this->assertStringContainsString(
                trim(self::DEBUG_LEVEL_REGEX, '/'),
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
     * Data provider for file logging test scenarios
     *
     * @return array
     */
    public static function fileLoggingScenarioProvider(): array
    {
        // Transform the email test cases into file test cases:
        return array_map(
            function ($case) {
                $configParts = explode(':', $case['emailConfig']);
                $logSettings = array_pop($configParts);
                // Generate a random filename in the cache, to be sure the server has
                // permission to write there, and to keep each test's log distinct.
                $filename = LOCAL_CACHE_DIR . '/' . uniqid() . '.log';
                return [
                    'loggingConfig' => "$filename:$logSettings",
                    'expectedPatterns' => $case['expectedPatterns'],
                    'unexpectedPatterns' => $case['unexpectedPatterns'],
                    'description' => $case['description'],
                ];
            },
            static::emailLoggingScenarioProvider()
        );
    }

    /**
     * Test file logging functionality with various configurations
     *
     * @param string $loggingConfig      Logging configuration string
     * @param array  $expectedPatterns   Patterns that should be found in log
     * @param array  $unexpectedPatterns Patterns that should NOT be found in log
     * @param string $description        Test scenario description
     *
     * @return void
     *
     * @dataProvider fileLoggingScenarioProvider
     */
    public function testFileLogging(
        string $loggingConfig,
        array $expectedPatterns,
        array $unexpectedPatterns,
        string $description
    ): void {
        $port = $this->getSolrPort();
        $this->changeConfigs([
            'config' => [
                'Index'   => [
                    'url' => "http://localhost:$port/not-solr",
                ],
                'Logging' => [
                    'file' => $loggingConfig,
                ],
            ],
        ]);

        [$filename] = explode(':', $loggingConfig);

        $session = $this->getMinkSession();
        $session->visit($this->getVuFindUrl() . '/Search/Results?lookfor=test');
        $page = $session->getPage();

        // Wait for logging to complete
        $this->findCss($page, 'body');

        $logContent = file_get_contents($filename);

        // Basic assertions
        $this->assertPatternsInLog($logContent, $expectedPatterns, $unexpectedPatterns, $description);
    }

    /**
     * Test that no emails are sent when logging is disabled
     *
     * @return void
     */
    public function testNoEmailLoggingWhenDisabled(): void
    {
        $port = $this->getSolrPort();
        $this->changeConfigs([
            'config' => [
                'Index' => [
                    'url' => "http://localhost:$port/not-solr",
                ],
                'Mail'  => [
                    'testOnly'           => true,
                    'message_log'        => $this->getEmailLogPath(),
                    'message_log_format' => $this->getEmailLogFormat(),
                ],
                'Logging' => [
                    'email' => '',
                    'file' => null,
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
            $this->assertEmpty(
                $loggedEmails,
                'No emails should be sent when email logging is not configured'
            );
        } else {
            $this->assertTrue(true, 'Email log file does not exist, which is expected when logging is disabled');
        }
    }

    /**
     * Data provider for database logging test scenarios
     *
     * @return array
     */
    public static function databaseLoggingScenarioProvider(): array
    {
        // Transform the email test cases into database test cases:
        return array_map(
            function ($case) {
                $configParts = explode(':', $case['emailConfig']);
                $logSettings = array_pop($configParts);
                return [
                    'loggingConfig' => "log_table:$logSettings",
                    'expectedPatterns' => $case['expectedPatterns'],
                    'unexpectedPatterns' => $case['unexpectedPatterns'],
                    'description' => $case['description'],
                ];
            },
            static::emailLoggingScenarioProvider()
        );
    }

    /**
     * Test database logging functionality with various configurations
     *
     * @param string $loggingConfig      Logging configuration string
     * @param array  $expectedPatterns   Patterns that should be found in log
     * @param array  $unexpectedPatterns Patterns that should NOT be found in log
     * @param string $description        Test scenario description
     *
     * @return void
     *
     * @dataProvider DatabaseLoggingScenarioProvider
     */
    public function testDatabaseLogging(
        string $loggingConfig,
        array $expectedPatterns,
        array $unexpectedPatterns,
        string $description
    ): void {
        $port = $this->getSolrPort();
        $this->changeConfigs([
            'config' => [
                'Index'   => [
                    'url' => "http://localhost:$port/not-solr",
                ],
                'Logging' => [
                    'database' => $loggingConfig,
                    'file' => null,
                ],
            ],
        ]);

        $session = $this->getMinkSession();
        $session->visit($this->getVuFindUrl() . '/Search/Results?lookfor=test');
        $page = $session->getPage();

        // Wait for logging to complete
        $this->findCss($page, 'body');

        $connection = $this->getLiveDatabaseContainer()->get(Connection::class);
        $queryBuilder = $connection->createQueryBuilder();
        $queryBuilder->select('*')->from('log_table');
        $result = $connection->executeQuery($queryBuilder);
        $priorities = ['EMERGENCY', 'ALERT', 'CRITICAL', 'ERROR', 'WARNING', 'NOTICE', 'INFO', 'DEBUG'];
        $logContent = implode("\n", array_map(
            function ($row) use ($priorities) {
                $row['priority'] = $priorities[$row['priority']] ?? 'UNKNOWN-PRIORITY';
                return implode(' ', $row);
            },
            $result->fetchAllAssociative()
        ));

        // Basic assertions
        $unexpectedPatterns[] = '/UNKNOWN-PRIORITY/';
        $this->assertPatternsInLog($logContent, $expectedPatterns, $unexpectedPatterns, $description);

        // Clear data for the next test:
        $deleteQueryBuilder = $connection->createQueryBuilder();
        $deleteQueryBuilder->delete('log_table');
        $connection->executeQuery($deleteQueryBuilder);
    }
}
