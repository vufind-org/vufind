<?php

/**
 * VuFind Condition Manager.
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
 * @package  Condition
 * @author   Nathan Collins <colli372@msu.edu>
 * @author   Thomas Wagener <wagener@hebis.uni-frankfurt.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */

namespace VuFind\Condition;

use Psr\Log\LoggerAwareInterface;
use VuFind\Condition\Handler\PluginManager as HandlerPluginManager;
use VuFind\Exception\ConditionException;
use VuFind\Log\LoggerAwareTrait;
use VuFind\ServiceManager\Factory\Autowire;

/**
 * VuFind Condition Manager.
 *
 * @category VuFind
 * @package  Condition
 * @author   Nathan Collins <colli372@msu.edu>
 * @author   Thomas Wagener <wagener@hebis.uni-frankfurt.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */
class Manager implements LoggerAwareInterface
{
    use LoggerAwareTrait;

    /**
     * Constructor.
     *
     * @param HandlerPluginManager $handlerPluginManager Handler plugin manager
     */
    public function __construct(
        #[Autowire(service: HandlerPluginManager::class)]
        protected HandlerPluginManager $handlerPluginManager,
    ) {
    }

    /**
     * Evaluate a set of conditions.
     *
     * @param array $conditions Conditions
     *
     * @return bool
     */
    public function evaluateConditions(array $conditions): bool
    {
        foreach ($conditions as $condition) {
            $conditionHandler = $this->handlerPluginManager->get($condition['type'] ?? '');
            try {
                if (!$conditionHandler->checkCondition($condition)) {
                    return false;
                }
            } catch (ConditionException $e) {
                $this->logWarning('Condition check failed: ' . $e->getMessage());
                return false;
            }
        }
        return true;
    }
}
