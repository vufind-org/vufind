<?php

/**
 * Mail Exception
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2011-2023.
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
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */

namespace VuFind\Exception;

/**
 * Mail Exception
 *
 * @category VuFind
 * @package  Exceptions
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
class Mail extends \Exception
{
    /**
     * Default error message when the error is not known exactly.
     * Will return $defaultDisplayMessage if APPLICATION_ENV is not development.
     *
     * @var int
     */
    public const ERROR_UNKNOWN = 0;

    /**
     * Mail recipient address is invalid.
     *
     * @var int
     */
    public const ERROR_INVALID_RECIPIENT = 1;

    /**
     * Mail sender address is invalid.
     *
     * @var int
     */
    public const ERROR_INVALID_SENDER = 2;

    /**
     * Mail reply to address is invalid.
     *
     * @var int
     */
    public const ERROR_INVALID_REPLY_TO = 3;

    /**
     * Mail too many recipients.
     *
     * @var int
     */
    public const ERROR_TOO_MANY_RECIPIENTS = 4;

    /**
     * Safe error message to return
     *
     * @var string
     */
    protected $defaultDisplayMessage = 'email_failure';

    /**
     * Returns the error message, but excludes too technical messages.
     *
     * @return string
     */
    public function getDisplayMessage(): string
    {
        // If application env is development, we can display too technical messages
        if ('development' === APPLICATION_ENV || $this->getCode() !== self::ERROR_UNKNOWN) {
            return $this->getMessage();
        }
        return $this->defaultDisplayMessage;
    }
}
