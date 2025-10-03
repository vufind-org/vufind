<?php

/**
 * Zotero OAuth TokenCredentials
 *
 * PHP version 8
 *
 * Copyright (C) The National Library of Finland 2025.
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
 * @package  Zotero
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */

declare(strict_types=1);

namespace VuFind\Export\Zotero;

use League\OAuth1\Client\Credentials\TokenCredentials;

/**
 * Zotero OAuth TokenCredentials
 *
 * Class for authenticating with Zotero using OAuth.
 *
 * @category VuFind
 * @package  Zotero
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
class ZoteroTokenCredentials extends TokenCredentials
{
    /**
     * User ID
     *
     * @var ?string
     */
    protected ?string $userId = null;

    /**
     * Get user ID.
     *
     * @return ?string
     */
    public function getUserId(): ?string
    {
        return $this->userId;
    }

    /**
     * Set user ID.
     *
     * @param ?string $id User ID
     *
     * @return void
     */
    public function setUserId(?string $id): void
    {
        $this->userId = $id;
    }
}
