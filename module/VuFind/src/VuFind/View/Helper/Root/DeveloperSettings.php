<?php

/**
 * Developer settings helper
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
 * @package  View_Helpers
 * @author   Juha Luoma <juha.luoma@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/ Wiki
 */

namespace VuFind\View\Helper\Root;

use Laminas\View\Helper\AbstractHelper;
use VuFind\DeveloperSettings\DeveloperSettingsService;

/**
 * Developer settings helper
 *
 * @category VuFind
 * @package  View_Helpers
 * @author   Juha Luoma <juha.luoma@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/ Wiki
 */
class DeveloperSettings extends AbstractHelper
{
    /**
     * Constructor
     *
     * @param DeveloperSettingsService $developerSettingsService Developer settings service
     */
    public function __construct(protected DeveloperSettingsService $developerSettingsService)
    {
    }

    /**
     * Are developer settings enabled? This includes in example: API keys
     *
     * @return bool
     */
    public function isEnabled(): bool
    {
        return $this->developerSettingsService->apiKeysEnabled();
    }
}
