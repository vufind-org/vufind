<?php

/**
 * Decorator for Laminas CSRF validator to add token counting/clearing functions.
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2018.
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
 * @package  Validator
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */

namespace VuFind\Validator;

use InvalidArgumentException;
use Laminas\Session\Container;
use Laminas\Session\Validator\Csrf;

use function count;

/**
 * Decorator for Laminas CSRF validator to add token counting/clearing functions.
 *
 * @category VuFind
 * @package  Validator
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
class SessionCsrf implements CsrfInterface
{
    /**
     * Laminas CSRF class.
     *
     * @var Csrf
     */
    protected Csrf $csrf;

    /**
     * Session container.
     *
     * @var Container
     */
    protected Container $sessionContainer;

    /**
     * Constructor.
     *
     * @param array $options Options to pass to CSRF validator
     */
    public function __construct(protected array $options = [])
    {
        if (!isset($options['session'])) {
            throw new InvalidArgumentException('Options must include session');
        }
        $this->csrf = new Csrf($options);
        $this->sessionContainer = $options['session'];
    }

    /**
     * Keep only the most recent N tokens.
     *
     * @param int $limit Number of tokens to keep.
     *
     * @return void
     */
    public function trimTokenList($limit)
    {
        // This method only exists for back-compatibility.
    }

    /**
     * How many tokens are currently stored in the session?
     *
     * @return int
     */
    public function getTokenCount()
    {
        // This method only exists for back-compatibility.
        return count($this->sessionContainer->tokenList ?? []);
    }

    /**
     * Retrieve CSRF token.
     *
     * If no CSRF token currently exists, or should be regenerated,
     * generates one.
     *
     * @param bool $regenerate regenerate hash, default false
     *
     * @return string
     */
    public function getHash($regenerate = false)
    {
        if ($regenerate) {
            throw new InvalidArgumentException('The regenerate parameter is no longer supported');
        }
        return $this->csrf->getHash();
    }

    /**
     * Returns true if the CSRF token is valid.
     *
     * @param mixed $value Token to validate
     *
     * @return bool
     */
    public function isValid(mixed $value): bool
    {
        return $this->csrf->isValid($value);
    }

    /**
     * Returns an array of messages that explain why the most recent isValid()
     * call returned false. The array keys are validation failure message identifiers,
     * and the array values are the corresponding human-readable message strings.
     *
     * If isValid() was never called or if the most recent isValid() call
     * returned true, then this method returns an empty array.
     *
     * @return array<string, string>
     */
    public function getMessages()
    {
        return $this->csrf->getMessages();
    }
}
