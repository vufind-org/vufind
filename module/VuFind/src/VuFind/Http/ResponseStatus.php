<?php

/**
 * Commit the HTTP response status code to the PHP SAPI early.
 *
 * PHP version 8
 *
 * Copyright (C) Universitätsbibliothek Mannheim 2026.
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
 * @package  Http
 * @author   Stefan Weil <sw@weilnetz.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development
 */

namespace VuFind\Http;

/**
 * Commit the HTTP response status code to the PHP SAPI early.
 *
 * Laminas MVC writes the HTTP status line only when sending the response (at
 * the "finish" event). If a large response body causes PHP's output buffering
 * to flush to the SAPI while the body is being rendered, the status line is
 * committed at that point -- and it will be whatever status is currently set,
 * which may still be the default 200 even though the application has already
 * decided on a different status (e.g. 404 for a non-matching resource). This
 * results in non-matching resources being served with a misleading 200 status.
 *
 * Calling {@see commit()} right before the response body is rendered makes the
 * pending status code match the application's decision, so any such
 * auto-flush carries the correct status line.
 *
 * @category VuFind
 * @package  Http
 * @author   Stefan Weil <sw@weilnetz.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development
 */
class ResponseStatus
{
    /**
     * Commit the given HTTP status code to the PHP SAPI, if appropriate.
     *
     * This only sets the pending status code; it does not flush any output.
     * It is a no-op under the CLI SAPI, for codes outside the valid HTTP
     * range, or if the headers have already been sent to the client.
     *
     * @param int $statusCode The status code to commit
     *
     * @return void
     */
    public static function commit(int $statusCode): void
    {
        if (self::shouldCommit($statusCode)) {
            http_response_code($statusCode);
        }
    }

    /**
     * Decide whether the given status code should be committed to the SAPI.
     *
     * The $isCli and $headersSent parameters default to the real SAPI state,
     * but may be overridden for testing.
     *
     * @param int       $statusCode  Candidate status code
     * @param bool|null $isCli       Running under the CLI SAPI? (defaults to the real value)
     * @param bool|null $headersSent Have the headers already been sent? (defaults to the real value)
     *
     * @return bool
     */
    public static function shouldCommit(int $statusCode, ?bool $isCli = null, ?bool $headersSent = null): bool
    {
        $isCli ??= (PHP_SAPI === 'cli');
        $headersSent ??= headers_sent();
        return !$isCli
            && $statusCode >= 100
            && $statusCode <= 599
            && !$headersSent;
    }
}
