<?php

/**
 * Trait introducing an annotation that can be used to auto-retry tests that may
 * fail intermittently due to timing issues (e.g. Mink integration tests).
 *
 * Inspired by discussion here at https://stackoverflow.com/questions/7705169
 *
 * PHP version 7
 *
 * Copyright (C) Villanova University 2019.
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
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */
namespace VuFindTest\Unit;

use PHPUnit\Framework\SkippedTestError;

/**
 * Trait introducing an annotation that can be used to auto-retry tests that may
 * fail intermittently due to timing issues (e.g. Mink integration tests).
 *
 * @category VuFind
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */
trait AutoRetryTrait
{
    /**
     * Flag whether we ran out of retries on a prior test.
     *
     * @var bool
     */
    protected static $failedAfterRetries = false;

    /**
     * Override PHPUnit's main run method, introducing annotation-based retry
     * behavior.
     *
     * @return void
     */
    public function runBare(): void
    {
        // Fetch retry count from annotations, but make sure it's a sane number;
        // default to a single attempt with no retries unless told otherwise.
        // Also skip retries if a past test has failed after running out of
        // retries -- one failed test is likely to have a knock-on effect on
        // subsequent tests, and retrying will just waste time before showing
        // the cause of the initial error. We only really want to retry if it
        // will prevent ANY failures from occurring.
        $annotations = $this->getAnnotations();
        $retryCountAnnotation = $annotations['method']['retry'][0]
            ?? $annotations['class']['retry'][0] ?? 0;
        $retryCount = !self::$failedAfterRetries && $retryCountAnnotation > 0
            ? $retryCountAnnotation : 0;

        // Also fetch retry callbacks, if any, from annotations; always include
        // standard 'tearDown' method:
        $retryCallbacks = $annotations['method']['retryCallback'] ?? [];
        $retryCallbacks[] = 'tearDown';

        // Run through all of the attempts... Note that even if retryCount is 0,
        // we still need to run the test once (single attempt, no retries)...
        // hence the $retryCount + 1 below.
        for ($i = 0; $i < $retryCount + 1; $i++) {
            try {
                parent::runBare();
                // No exception thrown? We can return as normal.
                return;
            } catch (\Exception $e) {
                // Don't retry skipped tests!
                if (get_class($e) == SkippedTestError::class) {
                    throw $e;
                }
                // Execute callbacks for interrupted test.
                foreach ($retryCallbacks as $callback) {
                    if (is_callable([$this, $callback])) {
                        $this->{$callback}();
                    }
                }
            }
        }

        // If we got this far, something went wrong... under healthy circumstances,
        // we should have returned from inside the loop above. $e should be set from
        // within the catch above, so if it's unset, something weird has occurred.
        self::$failedAfterRetries = true;
        throw $e ?? new \Exception('Unexpected state reached');
    }
}
