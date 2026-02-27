<?php

/**
 * Condition manager test
 *
 * PHP version 8
 *
 * Copyright (C) Hebis Verbundzentrale 2026.
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
 * @author   Thomas Wagener <wagener@hebis.uni-frankfurt.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */

namespace VuFindTest\Condition;

use VuFind\Condition\Handler\StringHandler;
use VuFind\Condition\Manager as ConditionManager;

/**
 * Condition manager test
 *
 * @category VuFind
 * @package  Tests
 * @author   Thomas Wagener <wagener@hebis.uni-frankfurt.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */
class ManagerTest extends \PHPUnit\Framework\TestCase
{
    /**
     * Get condition manager.
     *
     * @return ConditionManager
     */
    protected function getManager(): ConditionManager
    {
        $handlerManager = $this->createMock(\VuFind\Condition\Handler\PluginManager::class);
        $handlerManager->method('get')->willReturn(new StringHandler());
        return new ConditionManager($handlerManager);
    }

    /**
     * Data provider for testEvaluateConditions.
     *
     * @return \Iterator<(int | string), mixed>
     */
    public static function evaluateConditionsProvider(): \Iterator
    {
        yield 'empty' => [[], true];
        yield 'single' => [
            [
                [
                    'type' => 'string',
                    'comparator' => '=',
                    'checkedValues' => 'test',
                    'string' => 'test',
                ],
            ],
            true,
        ];
        yield 'multiple-values-true' => [
            [
                [
                    'type' => 'string',
                    'comparator' => '=',
                    'checkedValues' => ['foo', 'test', 'bar'],
                    'string' => 'test',
                ],
            ],
            true,
        ];
        yield 'multiple-values-false' => [
            [
                [
                    'type' => 'string',
                    'comparator' => '=',
                    'checkedValues' => ['foo', 'bar'],
                    'string' => 'test',
                ],
            ],
            false,
        ];
        yield 'multiple-conditions-true' => [
            [
                [
                    'type' => 'string',
                    'comparator' => '=',
                    'checkedValues' => 'test',
                    'string' => 'test',
                ],
                [
                    'type' => 'string',
                    'comparator' => '=',
                    'checkedValues' => 'test2',
                    'string' => 'test2',
                ],
                [
                    'type' => 'string',
                    'comparator' => '=',
                    'checkedValues' => 'test3',
                    'string' => 'test3',
                ],
            ],
            true,
        ];
        yield 'multiple-conditions-false' => [
            [
                [
                    'type' => 'string',
                    'comparator' => '=',
                    'checkedValues' => 'test',
                    'string' => 'other',
                ],
                [
                    'type' => 'string',
                    'comparator' => '=',
                    'checkedValues' => 'test2',
                    'string' => 'test2',
                ],
                [
                    'type' => 'string',
                    'comparator' => '=',
                    'checkedValues' => 'test3',
                    'string' => 'test3',
                ],
            ],
            false,
        ];
    }

    /**
     * Test evaluation of conditions.
     *
     * @param array $conditions     Conditions
     * @param bool  $expectedResult The expected result
     *
     * @return void
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('evaluateConditionsProvider')]
    public function testEvaluateConditions(array $conditions, bool $expectedResult): void
    {
        $conditionManager = $this->getManager();
        $this->assertSame($expectedResult, $conditionManager->evaluateConditions($conditions));
    }

    /**
     * Data provider for testComparators.
     *
     * @return \Iterator<(int | string), mixed>
     */
    public static function comparatorsTestProvider(): \Iterator
    {
        yield 'equal-true' => [
            [
                'type' => 'string',
                'comparator' => '=',
                'checkedValues' => 'test',
                'string' => 'test',
            ],
            true,
        ];
        yield 'equal-false' => [
            [
                'type' => 'string',
                'comparator' => '=',
                'checkedValues' => 'other',
                'string' => 'test',
            ],
            false,
        ];
        yield 'not-equal-true' => [
            [
                'type' => 'string',
                'comparator' => '!=',
                'checkedValues' => 'other',
                'string' => 'test',
            ],
            true,
        ];
        yield 'not-equal-false' => [
            [
                'type' => 'string',
                'comparator' => '!=',
                'checkedValues' => 'test',
                'string' => 'test',
            ],
            false,
        ];
        yield 'smaller-true' => [
            [
                'type' => 'string',
                'comparator' => '<',
                'checkedValues' => '2',
                'string' => '1',
            ],
            true,
        ];
        yield 'smaller-false' => [
            [
                'type' => 'string',
                'comparator' => '<',
                'checkedValues' => '1',
                'string' => '2',
            ],
            false,
        ];
        yield 'smaller-false2' => [
            [
                'type' => 'string',
                'comparator' => '<',
                'checkedValues' => '1',
                'string' => '1',
            ],
            false,
        ];
        yield 'smaller-equal-true' => [
            [
                'type' => 'string',
                'comparator' => '<=',
                'checkedValues' => '2',
                'string' => '1',
            ],
            true,
        ];
        yield 'smaller-equal-true2' => [
            [
                'type' => 'string',
                'comparator' => '<=',
                'checkedValues' => '1',
                'string' => '1',
            ],
            true,
        ];
        yield 'smaller-equal-false' => [
            [
                'type' => 'string',
                'comparator' => '<=',
                'checkedValues' => '1',
                'string' => '2',
            ],
            false,
        ];
        yield 'greater-true' => [
            [
                'type' => 'string',
                'comparator' => '>',
                'checkedValues' => '1',
                'string' => '2',
            ],
            true,
        ];
        yield 'greater-false' => [
            [
                'type' => 'string',
                'comparator' => '>',
                'checkedValues' => '2',
                'string' => '1',
            ],
            false,
        ];
        yield 'greater-false2' => [
            [
                'type' => 'string',
                'comparator' => '>',
                'checkedValues' => '1',
                'string' => '1',
            ],
            false,
        ];
        yield 'greater-equal-true' => [
            [
                'type' => 'string',
                'comparator' => '>=',
                'checkedValues' => '1',
                'string' => '2',
            ],
            true,
        ];
        yield 'greater-equal-true2' => [
            [
                'type' => 'string',
                'comparator' => '>=',
                'checkedValues' => '1',
                'string' => '1',
            ],
            true,
        ];
        yield 'greater-equal-false' => [
            [
                'type' => 'string',
                'comparator' => '>=',
                'checkedValues' => '2',
                'string' => '1',
            ],
            false,
        ];
        yield 'starts-with-true' => [
            [
                'type' => 'string',
                'comparator' => 'starts_with',
                'checkedValues' => 'test',
                'string' => 'test_end',
            ],
            true,
        ];
        yield 'starts-with-false' => [
            [
                'type' => 'string',
                'comparator' => 'starts_with',
                'checkedValues' => 'test',
                'string' => 'other',
            ],
            false,
        ];
        yield 'ends-with-true' => [
            [
                'type' => 'string',
                'comparator' => 'ends_with',
                'checkedValues' => 'test',
                'string' => 'start_test',
            ],
            true,
        ];
        yield 'ends-with-false' => [
            [
                'type' => 'string',
                'comparator' => 'ends_with',
                'checkedValues' => 'test',
                'string' => 'other',
            ],
            false,
        ];
        yield 'regex-true' => [
            [
                'type' => 'string',
                'comparator' => 'regex',
                'checkedValues' => '/\d\s[a-z]*/',
                'string' => '1 test',
            ],
            true,
        ];
        yield 'regex-false' => [
            [
                'type' => 'string',
                'comparator' => 'regex',
                'checkedValues' => '/\d\s[a-z]*/',
                'string' => 'test',
            ],
            false,
        ];
    }

    /**
     * Test comparators.
     *
     * @param array $condition      Condition
     * @param bool  $expectedResult The expected result
     *
     * @return void
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('comparatorsTestProvider')]
    public function testComparators(array $condition, bool $expectedResult): void
    {
        $conditionManager = $this->getManager();
        $this->assertSame($expectedResult, $conditionManager->evaluateConditions([$condition]));
    }
}
