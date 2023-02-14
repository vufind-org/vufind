<?php
/**
 * Trait that provides support for calling a method with configurable retries
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
        'maximumBackoff' => 1000      // maximum backoff (milliseconds)
    ];

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
    protected function callWithRetry(
        callable $callback,
        ?callable $statusCallback = null,
        array $options = []
    ) {
        $attempt = 0;
        $exception = null;
        $options = array_merge($this->retryOptions, $options);
        do {
            if (++$attempt > 1 && $statusCallback) {
                $statusCallback($attempt, false);
            }
            try {
                $result = $callback();
                if ($attempt > 1 && $statusCallback) {
                    $statusCallback($attempt, true);
                }
                return $result;
            } catch (\Exception $e) {
                if (null === $exception) {
                    $exception = $e;
                }
                if ($checkCallback = $options['retryableExceptionCallback'] ?? '') {
                    if (!$checkCallback($e)) {
                        break;
                    }
                }
            }
        } while ($this->checkRetryAndSleep($attempt, $options));
        // No success, re-throw the original exception:
        throw $exception;
    }

    /**
     * Check if the call needs to be retried and sleep before the retry
     *
     * @param int   $attempt Failed attempt number
     * @param array $options Current options
     *
     * @return bool
     */
    protected function checkRetryAndSleep(int $attempt, array $options): bool
    {
        if ($attempt > $options['retryCount']) {
            return false;
        }
        if (1 === $attempt) {
            usleep($options['firstBackoff'] * 1000);
        } else {
            $backoff = $options['subsequentBackoff'];
            if ($options['exponentialBackoff']) {
                $backoff = min(
                    2 ** ($attempt - 2) * $backoff,
                    $options['maximumBackoff']
                );
            }
            usleep($backoff * 1000);
        }
        return true;
    }
}
