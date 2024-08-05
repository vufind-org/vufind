<?php

/**
 * Trait for setting consecutive test expectations.
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2024.
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

namespace VuFindTest\Feature;

use PHPUnit\Framework\Constraint\Constraint;
use PHPUnit\Framework\MockObject\Builder\InvocationStubber;
use PHPUnit\Framework\MockObject\MockObject;

use function count;
use function func_get_args;
use function is_array;

/**
 * Trait for setting consecutive test expectations.
 *
 * @category VuFind
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */
trait WithConsecutiveTrait
{
    /**
     * Expect consecutive calls to a mock.
     *
     * @param MockObject $mock          Mock object
     * @param string     $method        Method expecting calls
     * @param array      $expectedCalls Expected input parameters
     * @param mixed      $returnValues  Return values to mock (either an array indexed parallel to
     * $expectedCalls to return different values, or a single value to always return the same thing)
     *
     * @return InvocationStubber
     */
    protected function expectConsecutiveCalls(
        MockObject $mock,
        string $method,
        array $expectedCalls,
        mixed $returnValues = null
    ): InvocationStubber {
        $matcher = $this->exactly(count($expectedCalls));
        $callback = function () use ($matcher, $expectedCalls, $returnValues) {
            $index = $matcher->numberOfInvocations() - 1;
            $expectedArgs = $expectedCalls[$index] ?? [];
            $actualArgs = func_get_args();
            foreach ($expectedArgs as $i => $expected) {
                if ($expected instanceof Constraint) {
                    $expected->evaluate($actualArgs[$i] ?? null);
                } else {
                    $this->assertEquals($expected, $actualArgs[$i] ?? null);
                }
            }
            return is_array($returnValues) ? $returnValues[$index] ?? null : $returnValues;
        };
        return $mock->expects($matcher)
            ->method($method)
            ->willReturnCallback($callback);
    }
}
