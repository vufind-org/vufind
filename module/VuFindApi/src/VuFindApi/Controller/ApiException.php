<?php

/**
 * API exception
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
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301  USA
 *
 * @category VuFind
 * @package  Exceptions
 * @author   Juha Luoma <juha.luoma@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:ils_drivers Wiki
 */

namespace VuFindApi\Controller;

use Exception;

/**
 * API exception class
 *
 * @category VuFind
 * @package  Exceptions
 * @author   Juha Luoma <juha.luoma@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
class ApiException extends Exception
{
    /**
     * Error if limit is out of bounds < 0 or > max
     *
     * @var string
     */
    public const INVALID_LIMIT = 'Invalid limit';

    /**
     * Error if search has encountered an error
     *
     * @var string
     */
    public const INVALID_SEARCH = 'Invalid search';

    /**
     * Error if token is invalid or expired
     *
     * @var string
     */
    public const INVALID_OR_EXPIRED_TOKEN = 'Invalid or expired token';

    /**
     * Error if invalid or no record fields were defined
     *
     * @var string
     */
    public const INVALID_RECORD_FIELDS = 'Invalid record fields defined';
}
