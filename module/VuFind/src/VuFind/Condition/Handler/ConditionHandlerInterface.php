<?php

/**
 * Interface for condition handlers.
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
 * @package  Condition_Handler
 * @author   Thomas Wagener <wagener@hebis.uni-frankfurt.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:condition_handlers Wiki
 */

namespace VuFind\Condition\Handler;

use VuFind\Exception\ConditionException;

/**
 * Interface for condition handlers.
 *
 * @category VuFind
 * @package  Condition_Handler
 * @author   Thomas Wagener <wagener@hebis.uni-frankfurt.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:condition_handlers Wiki
 */
interface ConditionHandlerInterface
{
    /**
     * Check if a condition is met.
     *
     * Conditions are represented as an associative array with the following required keys:
     * - type: identifier of the condition handler
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
    public function checkCondition(array $condition): bool;
}
