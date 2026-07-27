<?php

/**
 * Interlibrary Loan tab.
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
 * @package  RecordTabs
 * @author   Stefan Weil <sw@weilnetz.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:record_tabs Wiki
 */

namespace VuFind\RecordTab;

/**
 * Interlibrary Loan tab.
 *
 * @category VuFind
 * @package  RecordTabs
 * @author   Stefan Weil <sw@weilnetz.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:record_tabs Wiki
 */
class InterlibraryLoan extends AbstractBase
{
    /**
     * Get the on-screen description for this tab.
     *
     * @return string
     */
    public function getDescription()
    {
        return 'GVI::InterlibraryLoan';
    }

    /**
     * Is this tab active?
     *
     * @return bool
     */
    public function isActive()
    {
        return true;
    }

    /**
     * Get tab content for the template.
     *
     * @return array
     */
    public function getContent(): array
    {
        $hasLocal = $this->driver->hasLocalHoldings();
        $auth = $this->getAuthorizationService();
        $user = $auth?->getIdentity();
        $baseUrl = $this->driver->mainConfig->ILL->ill_base_url ?? '';
        $illUrl = ($user && !empty($baseUrl))
            ? $this->driver->getIllUrl($baseUrl) : '';
        return [
            'hasLocalHoldings' => $hasLocal,
            'localMessage' => $hasLocal ? 'ill_local_holdings' : null,
            'isLoggedIn' => $user !== null,
            'illUrl' => $illUrl,
        ];
    }
}
