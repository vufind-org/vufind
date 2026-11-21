<?php

/**
 * ResponseStatus Test Class.
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
 * @package  Tests
 * @author   Stefan Weil <sw@weilnetz.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */

namespace VuFindTest\Http;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use VuFind\Http\ResponseStatus;

/**
 * ResponseStatus Test Class.
 *
 * @category VuFind
 * @package  Tests
 * @author   Stefan Weil <sw@weilnetz.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */
class ResponseStatusTest extends TestCase
{
    /**
     * Data provider for testShouldCommit.
     *
     * @return \Generator<string, array>
     */
    public static function shouldCommitProvider(): \Generator
    {
        yield 'valid status, web SAPI, headers not sent' => [404, false, false, true];
        yield '500 series status, web SAPI, headers not sent' => [500, false, false, true];
        yield '300 series status, web SAPI, headers not sent' => [302, false, false, true];
        yield 'lower boundary 100' => [100, false, false, true];
        yield 'upper boundary 599' => [599, false, false, true];
        yield 'CLI SAPI, even with valid status' => [404, true, false, false];
        yield 'headers already sent' => [404, false, true, false];
        yield 'below valid range' => [99, false, false, false];
        yield 'above valid range' => [600, false, false, false];
        yield 'zero' => [0, false, false, false];
        yield 'negative' => [-1, false, false, false];
    }

    /**
     * Test the shouldCommit() decision logic.
     *
     * @param int  $statusCode  Candidate status code
     * @param bool $isCli       Whether the CLI SAPI is active
     * @param bool $headersSent Whether the headers have already been sent
     * @param bool $expected    Expected result
     *
     * @return void
     */
    #[DataProvider('shouldCommitProvider')]
    public function testShouldCommit(int $statusCode, bool $isCli, bool $headersSent, bool $expected): void
    {
        $this->assertSame($expected, ResponseStatus::shouldCommit($statusCode, $isCli, $headersSent));
    }

    /**
     * Test that commit() is a no-op under the CLI SAPI (where the unit test
     * suite runs) and that it does not raise an error for a range of inputs.
     *
     * @return void
     */
    public function testCommitIsNoopUnderCli(): void
    {
        if (PHP_SAPI !== 'cli') {
            self::markTestSkipped('Only meaningful under the CLI SAPI.');
        }
        foreach ([0, 99, 200, 302, 404, 500, 599, 600] as $code) {
            ResponseStatus::commit($code);
        }
        $this->addToAssertionCount(1);
    }
}
