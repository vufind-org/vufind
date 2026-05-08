<?php

/**
 * Logged in condition handler.
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

use VuFind\Auth\Manager as AuthManager;
use VuFind\ServiceManager\Factory\Autowire;

/**
 * Logged in condition handler.
 *
 * @category VuFind
 * @package  Condition_Handler
 * @author   Thomas Wagener <wagener@hebis.uni-frankfurt.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:condition_handlers Wiki
 */
class LoggedIn extends AbstractBase
{
    /**
     * Constructor.
     *
     * @param AuthManager $authManager Authentication manager
     */
    public function __construct(
        #[Autowire(service: AuthManager::class)]
        protected AuthManager $authManager,
    ) {
    }

    /**
     * Get base value to check.
     *
     * @param array $condition Optionally used for handler specific parameters
     *
     * @return string
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    protected function getBaseValue(array $condition): string
    {
        return ($this->authManager->getIdentity() === null) ? 'false' : 'true';
    }
}
