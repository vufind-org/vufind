<?php
/**
 * RetryTrait Test Class
 *
 * PHP version 7
 *
 * Copyright (C) The National Library of Finland 2023.
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
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */
namespace VuFindTest\Service\Feature;

use VuFind\Service\Feature\RetryTrait;

/**
 * RetryTrait Test Class
 *
 * @category VuFind
 * @package  Tests
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */
class RetryTraitTest extends \PHPUnit\Framework\TestCase
{
    /**
     * Test retry with an eventually successful method
     *
     * @return void
     */
    public function testRetrySuccess()
    {
        $testClass = new MockRetryTestClass();

        $counter = 0;
        $result = $testClass->call(
            function () use (&$counter) {
                ++$counter;
                if ($counter > 2) {
                    return 'success';
                }
                throw new \Exception("Fail attempt $counter");
            }
        );
        $this->assertEquals('success', $result);
    }

    /**
     * Test the trait with a failing method
     *
     * @return void
     */
    public function testRetryFail()
    {
        $testClass = new MockRetryTestClass();

        $counter = 0;
        $startTime = microtime(true);
        $this->expectExceptionMessage('Fail attempt 1');
        $testClass->call(
            function () use (&$counter, $startTime) {
                ++$counter;
                // Check timing:
                if ($counter > 1) {
                    $backoff = 0.050 * ($counter - 2);
                    $this->assertGreaterThanOrEqual(
                        $backoff,
                        microtime(true) - $startTime
                    );
                    $this->assertLessThan(
                        $backoff + 0.2, // given some slack
                        microtime(true) - $startTime
                    );
                }
                throw new \Exception("Fail attempt $counter");
            },
            function ($attempt, $success) use (&$counter) {
                $this->assertEquals($counter + 1, $attempt);
                $this->assertFalse($success);
            },
            [
                'subsequentBackoff' => 50
            ]
        );
    }
}

/**
 * Mock test class for RetryTrait
 */
class MockRetryTestClass
{
    use RetryTrait;

    /**
     * Call a method and retry the call if an exception is thrown
     *
     * @param callable  $callback       Method to call
     * @param ?callable $statusCallback Status callback called before retry and after
     * a successful retry
     * @param array     $options        Optional options to override defaults in
     * $this->retryOptions
     *
     * @return mixed
     */
    public function call(
        callable $callback,
        ?callable $statusCallback = null,
        array $options = []
    ) {
        return $this->callWithRetry($callback, $statusCallback, $options);
    }
}
