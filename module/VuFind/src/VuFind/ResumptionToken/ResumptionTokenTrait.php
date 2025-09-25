<?php

/**
 * Resumption token trait.
 *
 * PHP version 8
 *
 * Copyright (C) The National Library 2024.
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
 * @package  Controller
 * @author   Juha Luoma <juha.luoma@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:controllers Wiki
 */

namespace VuFind\ResumptionToken;

use VuFind\Db\Entity\OaiResumptionEntityInterface;
use VuFind\Db\Service\OaiResumptionServiceInterface;

/**
 * Resumption token trait.
 *
 * @category VuFind
 * @package  Controller
 * @author   Juha Luoma <juha.luoma@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:controllers Wiki
 */
trait ResumptionTokenTrait
{
    /**
     * Resumption service for managing resumption tokens.
     *
     * @var OaiResumptionServiceInterface
     */
    protected OaiResumptionServiceInterface $resumptionService;

    /**
     * Set resumption service
     *
     * @param OaiResumptionServiceInterface $resumptionService Resumption service
     *
     * @return void
     */
    public function setResumptionService(OaiResumptionServiceInterface $resumptionService): void
    {
        $this->resumptionService = $resumptionService;
    }

    /**
     * Generate and return a new resumption token
     *
     * @param array  $params     Params to be saved for the resumption token
     * @param int    $cursor     Cursor to be saved for the resumption token
     * @param string $cursorMark Cursor mark to be saved for the resumption token
     * @param int    $lifetime   [Optional] How many seconds until token is expired. Default is 86400.
     *
     * @return OaiResumptionEntityInterface
     */
    public function createResumptionToken(
        array $params,
        int $cursor,
        string $cursorMark,
        int $lifetime = 86400
    ): OaiResumptionEntityInterface {
        $params['cursor'] = $cursor;
        $params['cursorMark'] = $cursorMark;
        $expire = time() + $lifetime;
        return $this->resumptionService->createAndPersistToken($params, $expire);
    }

    /**
     * Load parameters associated with a resumption token.
     *
     * @param string $token The resumption token to look up
     *
     * @return ?array Parameters associated with token or null if invalid or expired
     */
    protected function loadResumptionToken(string $token): ?array
    {
        // Clean up expired records before doing our search:
        $this->resumptionService->removeExpired();

        // Load the requested token if it still exists:
        if ($row = $this->resumptionService->findWithTokenOrLegacyIdToken($token)) {
            parse_str($row->getResumptionParameters(), $params);
            return $params;
        }

        // If we got this far, the token is invalid or expired:
        return null;
    }
}
