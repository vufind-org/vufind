<?php

/**
 * Bazaar session view helper
 *
 * PHP version 8
 *
 * Copyright (C) The National Library of Finland 2022-2023.
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
 * @author   Aida Luuppala <aida.luuppala@helsinki.fi>
 * @author   Aleksi Peebles <aleksi.peebles@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */

namespace Finna\View\Helper\Root;

use Finna\Service\BazaarService;
use Laminas\View\Helper\AbstractHelper;

/**
 * Bazaar session view helper
 *
 * @category VuFind
 * @package  View_Helpers
 * @author   Aida Luuppala <aida.luuppala@helsinki.fi>
 * @author   Aleksi Peebles <aleksi.peebles@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
class BazaarSession extends AbstractHelper
{
    /**
     * Bazaar service.
     *
     * @var BazaarService
     */
    protected BazaarService $bazaarService;

    /**
     * Constructor
     *
     * @param BazaarService $bazaarService Bazaar service
     */
    public function __construct(BazaarService $bazaarService)
    {
        $this->bazaarService = $bazaarService;
    }

    /**
     * Return whether a Bazaar session is active.
     *
     * @return bool
     */
    public function isActive(): bool
    {
        return $this->bazaarService->isSessionActive();
    }

    /**
     * Sets selection data if a Bazaar session is active.
     *
     * @param string $uid  UID
     * @param string $name Name
     *
     * @return bool Whether the data was set or not
     */
    public function setSelectionData(string $uid, string $name): bool
    {
        return $this->bazaarService->setSelectionData($uid, $name);
    }

    /**
     * Returns an add resource callback payload, or null if a Bazaar session is not
     * active or payload data has not been set.
     *
     * @return ?string
     */
    public function getAddResourceCallbackPayload(): ?string
    {
        return $this->bazaarService->getAddResourceCallbackPayload();
    }

    /**
     * Returns the add resource callback URL, or null if it is not set or a Bazaar
     * session is not active.
     *
     * @return ?string
     */
    public function getAddResourceCallbackUrl(): ?string
    {
        return $this->bazaarService->getAddResourceCallbackUrl();
    }

    /**
     * Returns the cancel URL.
     *
     * @return ?string
     */
    public function getCancelUrl(): ?string
    {
        return $this->getView()->plugin('url')('bazaar-cancel');
    }
}
