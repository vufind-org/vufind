<?php

/**
 * Abstract base condition handler.
 *
 * PHP version 8
 *
 * Copyright (C) Michigan State University 2023.
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
 * @package  Condition_Handler
 * @author   Nathan Collins <colli372@msu.edu>
 * @author   Thomas Wagener <wagener@hebis.uni-frankfurt.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */

namespace VuFind\Condition\Handler;

use VuFind\Exception\ConditionException;

use function is_array;

/**
 * Abstract base condition handler.
 *
 * @category VuFind
 * @package  Condition_Handler
 * @author   Nathan Collins <colli372@msu.edu>
 * @author   Thomas Wagener <wagener@hebis.uni-frankfurt.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
abstract class AbstractBase implements ConditionHandlerInterface
{
    /**
     * Check if a condition is met.
     *
     * Conditions are represented as an associative array with the following required keys:
     * - type: identifier of the condition handler (is mainly used to select
     *         the handler and can be ignored at this point)
     * - comparator: identifier of the type of comparison
     * - checkedValues: values that are checked against with the comparator
     *
     * Additional handler specific keys can can be added.
     *
     * @param array $condition Condition
     *
     * @return bool
     * @throws ConditionException
     */
    public function checkCondition(array $condition): bool
    {
        $baseValue = $this->getBaseValue($condition);
        $checkedValues = $condition['checkedValues'] ?? [];
        if (!is_array($checkedValues)) {
            $checkedValues = [$checkedValues];
        }
        return $this->handleComparison(
            $condition['comparator'] ?? '',
            $baseValue,
            $checkedValues
        );
    }

    /**
     * Get base value to check.
     *
     * @param array $condition Optionally used for handler specific parameters
     *
     * @return string
     * @throws ConditionException
     */
    abstract protected function getBaseValue(array $condition): string;

    /**
     * Evaluate a single set of comparison conditions.
     *
     * @param string   $comparator    Identifier of the type of comparison
     * @param string   $baseValue     The value to validate
     * @param string[] $checkedValues Values that are checked against with the comparator
     *
     * @return bool
     * @throws ConditionException
     */
    protected function handleComparison(
        string $comparator,
        string $baseValue,
        array $checkedValues
    ): bool {
        try {
            return array_any($checkedValues, fn ($checkedValue) => match ($comparator) {
                '=' => $baseValue == $checkedValue,
                '!=' => $baseValue != $checkedValue,
                '<' => $baseValue < $checkedValue,
                '<=' => $baseValue <= $checkedValue,
                '>' => $baseValue > $checkedValue,
                '>=' => $baseValue >= $checkedValue,
                'starts_with' => str_starts_with($baseValue, $checkedValue),
                'ends_with' => str_ends_with($baseValue, $checkedValue),
                'regex' => preg_match($checkedValue, $baseValue),
                default => throw new ConditionException("Unknown comparison type '{$comparator}'")
            });
        } catch (\Exception $e) {
            throw new ConditionException($e->getMessage());
        }
    }
}
