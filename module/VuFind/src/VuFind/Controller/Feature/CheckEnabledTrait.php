<?php

/**
 * Check Enabled Trait
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
 * along with this program; if not, see
 * <https://www.gnu.org/licenses/>.
 *
 * @category VuFind
 * @package  Controller_Plugins
 * @author   Maccabee Levine <msl321@lehigh.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */

namespace VuFind\Controller\Feature;

use Laminas\Mvc\MvcEvent;
use VuFind\Exception\Forbidden as ForbiddenException;

/**
 * Check Enabled Trait
 *
 * @category VuFind
 * @package  Controller_Plugins
 * @author   Maccabee Levine <msl321@lehigh.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */
trait CheckEnabledTrait
{
    /**
     * Check whether the controller is enabled
     *
     * @return void
     *
     * @throws ForbiddenException if the controller is not enabled
     */
    protected function checkEnabled()
    {
        $configId = $this->searchClassId ?? $this->sourceId ?? null;
        if (!$configId) {
            return;
        }

        $config = $this->getConfig($configId);
        if (!($config['General']['enabled'] ?? false)) {
            throw new ForbiddenException($configId . ' is disabled');
        }
    }

    /**
     * Add to event listeners a check that the controller is enabled
     *
     * @return void
     */
    protected function attachDefaultListeners()
    {
        parent::attachDefaultListeners();
        $events = $this->getEventManager();
        $events->attach(
            MvcEvent::EVENT_DISPATCH,
            fn () => $this->checkEnabled(),
            1000
        );
    }
}
