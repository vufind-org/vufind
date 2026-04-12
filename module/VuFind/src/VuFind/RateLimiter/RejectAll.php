<?php

/**
 * RejectAll policy for RateLimiter.
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
 * @package  Service
 * @author   Maccabee Levine <msl321@lehigh.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */

namespace VuFind\RateLimiter;

use Symfony\Component\RateLimiter\Exception\ReserveNotSupportedException;
use Symfony\Component\RateLimiter\LimiterInterface;
use Symfony\Component\RateLimiter\RateLimit;
use Symfony\Component\RateLimiter\Reservation;

/**
 * RejectAll policy for RateLimiter.
 *
 * @category VuFind
 * @package  RateLimiter
 * @author   Maccabee Levine <msl321@lehigh.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
class RejectAll implements LimiterInterface
{
    /**
     * Waits until the required number of tokens is available.
     *
     * In this implementation, just throws an exception since reserving is not supported.
     *
     * @param int        $tokens  the number of tokens required
     * @param float|null $maxTime maximum accepted waiting time in seconds
     *
     * @return Reservation The reservation
     *
     * @throws MaxWaitDurationExceededException if $maxTime is set and the process needs to wait longer than its value
     *                                          (in seconds)
     * @throws ReserveNotSupportedException     if this limiter implementation doesn't support reserving tokens
     * @throws \InvalidArgumentException        if $tokens is larger than the maximum burst size
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function reserve(int $tokens = 1, ?float $maxTime = null): Reservation
    {
        throw new ReserveNotSupportedException(__CLASS__);
    }

    /**
     * Use this method if you intend to drop if the required number
     * of tokens is unavailable.
     *
     * @param int $tokens the number of tokens required
     *
     * @return RateLimit The limit counter
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function consume(int $tokens = 1): RateLimit
    {
        return new RateLimit(0, new \DateTimeImmutable(), false, 0);
    }

    /**
     * Resets the limit.
     *
     * No-op in this implementation.
     *
     * @return void
     */
    public function reset(): void
    {
    }
}
