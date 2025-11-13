<?php

/**
 * Stream Handler Test Class
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
 * @author   Sambhav Pokharel <sambhavpokharel@gmail.com>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */

namespace VuFindTest\Log\Handler;

use Monolog\Level;
use PHPUnit\Framework\TestCase;
use VuFind\Log\Handler\StreamHandler;
use VuFind\Log\Logger;

/**
 * Stream Log Handler Test Class
 *
 * @category VuFind
 * @package  Tests
 * @author   Sambhav Pokharel <sambhavpokharel@gmail.com>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */
class StreamHandlerTest extends TestCase
{
    /**
     * Temporary log file path
     *
     * @var string
     */
    protected $tempLogFile;

    /**
     * Stream handler instance
     *
     * @var StreamHandler
     */
    protected $handler;

    /**
     * Logger instance
     *
     * @var Logger
     */
    protected $logger;

    /**
     * Set up test environment
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->tempLogFile = tempnam(sys_get_temp_dir(), 'vufind_log_test_');
        $this->handler = new StreamHandler($this->tempLogFile, Level::Debug);
        $mockIpReader = $this->getMockBuilder(\VuFind\Net\UserIpReader::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getUserIp'])
            ->getMock();
        $mockIpReader->method('getUserIp')->willReturn('127.0.0.1');

        $monologLogger = new \Monolog\Logger('test');
        $monologLogger->pushHandler($this->handler);
        $this->logger = new Logger($mockIpReader, $monologLogger);
    }

    /**
     * Clean up test environment
     *
     * @return void
     */
    protected function tearDown(): void
    {
        parent::tearDown();
        if (file_exists($this->tempLogFile)) {
            unlink($this->tempLogFile);
        }
    }

    /**
     * Test that the handler can be instantiated
     *
     * @return void
     */
    public function testHandlerInstantiation(): void
    {
        $handler = new StreamHandler($this->tempLogFile);
        $this->assertInstanceOf(StreamHandler::class, $handler);
    }

    /**
     * Test basic log writing functionality
     *
     * @return void
     */
    public function testBasicLogWriting(): void
    {
        $message = 'Test log message';
        $this->logger->info($message);

        $this->assertFileExists($this->tempLogFile);
        $logContent = file_get_contents($this->tempLogFile);
        $this->assertStringContainsString($message, $logContent);
        $this->assertStringContainsString('INFO', $logContent);
    }

    /**
     * Test all log levels are handled correctly
     *
     * @return void
     */
    public function testAllLogLevels(): void
    {
        $levels = [
            'emergency' => 'Emergency message',
            'alert'     => 'Alert message',
            'critical'  => 'Critical message',
            'error'     => 'Error message',
            'warning'   => 'Warning message',
            'notice'    => 'Notice message',
            'info'      => 'Info message',
            'debug'     => 'Debug message',
        ];

        foreach ($levels as $level => $message) {
            $this->logger->$level($message);
        }

        $logContent = file_get_contents($this->tempLogFile);

        foreach ($levels as $level => $message) {
            $this->assertStringContainsString($message, $logContent);
            $this->assertStringContainsString(strtoupper($level), $logContent);
        }
    }

    /**
     * Test log level filtering
     *
     * @return void
     */
    public function testLogLevelFiltering(): void
    {
        // Handler that only logs WARNING and above
        $handler = new StreamHandler($this->tempLogFile, Level::Warning);

        $mockIpReader = $this->getMockBuilder(\VuFind\Net\UserIpReader::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getUserIp'])
            ->getMock();
        $mockIpReader->method('getUserIp')->willReturn('127.0.0.1');

        $monologLogger = new \Monolog\Logger('test');
        $monologLogger->pushHandler($handler);

        $logger = new Logger($mockIpReader, $monologLogger);
        $logger->debug('Debug message - should not appear');
        $logger->info('Info message - should not appear');
        $logger->notice('Notice message - should not appear');
        $logger->warning('Warning message - should appear');
        $logger->error('Error message - should appear');
        $logger->critical('Critical message - should appear');

        $logContent = file_get_contents($this->tempLogFile);

        // Verify only WARNING and above appear in log
        $this->assertStringNotContainsString('Debug message', $logContent);
        $this->assertStringNotContainsString('Info message', $logContent);
        $this->assertStringNotContainsString('Notice message', $logContent);
        $this->assertStringContainsString('Warning message', $logContent);
        $this->assertStringContainsString('Error message', $logContent);
        $this->assertStringContainsString('Critical message', $logContent);
    }

    /**
     * Test logging with context data
     *
     * @return void
     */
    public function testLoggingWithContext(): void
    {
        $message = 'User action performed';
        $context = [
            'user_id'    => 12345,
            'action'     => 'search',
            'query'      => 'test query',
            'ip_address' => '192.168.1.1',
        ];

        $this->logger->info($message, $context);

        $logContent = file_get_contents($this->tempLogFile);
        $this->assertStringContainsString($message, $logContent);

        foreach ($context as $value) {
            $this->assertStringContainsString((string)$value, $logContent);
        }
    }

    /**
     * Test concurrent logging
     *
     * @return void
     */
    public function testConcurrentLogging(): void
    {
        $messages = [];
        for ($i = 1; $i <= 10; $i++) {
            $message    = "Concurrent log message $i";
            $messages[] = $message;
            $this->logger->info($message);
        }

        $logContent = file_get_contents($this->tempLogFile);
        foreach ($messages as $message) {
            $this->assertStringContainsString($message, $logContent);
        }

        $lines = explode("\n", trim($logContent));
        $this->assertCount(10, $lines);
    }

    /**
     * Test file permissions and accessibility
     *
     * @return void
     */
    public function testFilePermissions(): void
    {
        $this->logger->info('Test message');

        $this->assertFileExists($this->tempLogFile);
        $this->assertIsReadable($this->tempLogFile);
        $this->assertIsWritable($this->tempLogFile);
    }

    /**
     * Test that the handler properly closes resources
     *
     * @return void
     */
    public function testResourceCleanup(): void
    {
        $this->logger->info('Test message');

        // Force cleanup
        $this->handler->close();
        $this->assertFileExists($this->tempLogFile);
        $logContent = file_get_contents($this->tempLogFile);
        $this->assertStringContainsString('Test message', $logContent);
    }
}
