<?php

/**
 * Trait that provides support for calling a method with configurable retries
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
 * @package  Service
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development
 */

namespace VuFind\Service\Feature;

/**
 * Trait that provides support for calling a method with configurable retries
 *
 * @category VuFind
 * @package  Service
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development
 */
trait RetryTrait
{
    /**
     * Retry options
     *
     * @var array
     */
    protected $retryOptions = [
        'retryCount' => 5,            // number of retries (set to 0 to disable)
        'firstBackoff' => 0,          // backoff (delay) before first retry
                                      // (milliseconds)
        'subsequentBackoff' => 200,   // backoff (delay) before subsequent retries
                                      // (milliseconds)
        'exponentialBackoff' => true, // whether to use exponential backoff
        'maximumBackoff' => 1000,      // maximum backoff (milliseconds)
    ];

    /**
     * Call a method and retry the call if an exception is thrown
     *
     * @param callable  $callback       Method to call
     * @param ?callable $statusCallback Status callback called before retry and after
     * a successful retry. The callback gets the attempt number and either an
     * exception if an error occurred or null if the request succeeded after retries.
     * @param array     $options        Optional options to override defaults in
     * $this->retryOptions. Options can also include a retryableExceptionCallback
     * for a callback that gets the attempt number and exception as parameters and
     * returns true if the call can be retried or false if not.
     *
     * @return mixed
     */
    protected function callWithRetry(
        callable $callback,
        ?callable $statusCallback = null,
        array $options = []
    ) {
        $attempt = 0;
        $firstException = null;
        $lastException = null;
        $options = array_merge($this->retryOptions, $options);
        do {
            ++$attempt;
            if ($delay = $this->getBackoffDuration($attempt, $options)) {
                usleep($delay * 1000);
            }
            if ($lastException && $statusCallback) {
                $statusCallback($attempt, $lastException);
            }
            try {
                $result = $callback();
                if ($attempt > 1 && $statusCallback) {
                    $statusCallback($attempt, null);
                }
                return $result;
            } catch (\Exception $e) {
                $lastException = $e;
                if (null === $firstException) {
                    $firstException = $e;
                }
                if ($checkCallback = $options['retryableExceptionCallback'] ?? '') {
                    if (!$checkCallback($attempt, $e)) {
                        break;
                    }
                }
            }
        } while ($this->shouldRetry($attempt, $options));
        // No success, re-throw the first exception:
        throw $firstException;
    }

    /**
     * Check if the call needs to be retried
     *
     * @param int   $attempt Failed attempt number
     * @param array $options Current options
     *
     * @return bool
     */
    protected function shouldRetry(int $attempt, array $options): bool
    {
        return $attempt <= $options['retryCount'];
    }

    /**
     * Get the delay before a try
     *
     * @param int   $attempt Attempt number
     * @param array $options Current options
     *
     * @return int milliseconds
     */
    protected function getBackoffDuration(int $attempt, array $options): int
    {
        if ($attempt < 2) {
            return 0;
        }
        if (2 === $attempt) {
            return $options['firstBackoff'];
        }
        $backoff = $options['subsequentBackoff'];
        if ($options['exponentialBackoff']) {
            $backoff = min(
                2 ** ($attempt - 3) * $backoff,
                $options['maximumBackoff']
            );
        }
        return $backoff;
    }
}
