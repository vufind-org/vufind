<?php

/**
 * RetryTrait Test Class
 *
 * PHP version 8
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
     * Get a test harness for the trait.
     *
     * @return object
     */
    protected function getMockRetryTestClass()
    {
        return new class () {
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

            /**
             * Get the delay before a try
             *
             * @param int   $attempt Attempt number
             * @param array $options Current options
             *
             * @return int milliseconds
             */
            public function getBackoff(int $attempt, array $options): int
            {
                $options = array_merge($this->retryOptions, $options);
                return $this->getBackoffDuration($attempt, $options);
            }
        };
    }

    /**
     * Test retry with an eventually successful method
     *
     * @return void
     */
    public function testRetrySuccess()
    {
        $testClass = $this->getMockRetryTestClass();

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
        $testClass = $this->getMockRetryTestClass();

        $counter = 0;
        $this->expectExceptionMessage('Fail attempt 1');
        $testClass->call(
            function () use (&$counter) {
                ++$counter;
                throw new \Exception("Fail attempt $counter");
            },
            function ($attempt, $exception) use (&$counter) {
                $this->assertEquals($counter + 1, $attempt);
                $this->assertInstanceOf(\Exception::class, $exception);
            },
            [
                'firstRetryBackoff' => 0,
                'subsequentBackoff' => 0,
            ]
        );
    }

    /**
     * Test the trait with retryableExceptionCallback
     *
     * @return void
     */
    public function testRetryableExceptionCallback()
    {
        $testClass = $this->getMockRetryTestClass();

        $counter = 0;
        $retries = 0;
        try {
            $testClass->call(
                function () use (&$counter) {
                    ++$counter;
                    throw new \Exception("Fail attempt $counter");
                },
                function ($attempt, $exception) use (&$counter, &$retries) {
                    $this->assertEquals($counter + 1, $attempt);
                    $this->assertInstanceOf(\Exception::class, $exception);
                    ++$retries;
                },
                [
                    'firstRetryBackoff' => 0,
                    'subsequentBackoff' => 0,
                    'retryableExceptionCallback' => function ($attempt, $exception) {
                        $this->assertInstanceOf(\Exception::class, $exception);
                        return $exception->getMessage() !== 'Fail attempt 3';
                    },
                ]
            );
        } catch (\Exception $e) {
            // Do nothing
        }
        $this->assertEquals(2, $retries);
    }

    /**
     * Data provider for testBackoff
     *
     * @return array
     */
    public static function backoffDataProvider(): array
    {
        return [
            [0, 0],
            [0, 1],
            [0, 2],
            [200, 3],
            [400, 4],
            [800, 5],
            [1000, 6],
            [1000, 7],
            [1600, 6, ['maximumBackoff' => 2000]],
            [1500, 6, ['maximumBackoff' => 1500]],
            [200, 2, ['firstBackoff' => 200]],
            [300, 3, ['subsequentBackoff' => 300]],
            [200, 7, ['exponentialBackoff' => false]],
        ];
    }

    /**
     * Test the backoff duration handling
     *
     * @param int   $expected Expected result
     * @param int   $attempt  Attempt number
     * @param array $options  Current options
     *
     * @dataProvider backoffDataProvider
     *
     * @return void
     */
    public function testBackoff(int $expected, int $attempt, array $options = [])
    {
        $testClass = $this->getMockRetryTestClass();

        $this->assertEquals($expected, $testClass->getBackoff($attempt, $options));
    }
}
