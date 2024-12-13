<?php

/**
 * Decorator for Laminas\Validator\Csrf with token counting/clearing functions added.
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
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301  USA
 *
 * @category VuFind
 * @package  Validator
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */

namespace VuFind\Validator;

use Laminas\Validator\Csrf;
use Laminas\Validator\Translator\TranslatorAwareInterface;
use Laminas\Validator\Translator\TranslatorInterface;
use Laminas\Validator\ValidatorInterface;

use function array_slice;
use function count;
use function is_callable;

/**
 * Decorator for Laminas\Validator\Csrf with token counting/clearing functions added.
 *
 * @category VuFind
 * @package  Solr
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
class SessionCsrf implements CsrfInterface, ValidatorInterface, TranslatorAwareInterface
{
    /**
     * Laminas CSRF class.
     *
     * @var Csrf
     */
    protected Csrf $csrf;

    /**
     * Constructor
     *
     * @param array $options Options to pass to CSRF validator
     */
    public function __construct(array $options = [])
    {
        $this->csrf = new Csrf($options);
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
        $session = $this->csrf->getSession();
        if ($limit < 1) {
            // Reset the array if necessary:
            $session->tokenList = [];
        } elseif ($limit < $this->getTokenCount()) {
            // Trim the array if necessary:
            $session->tokenList
                = array_slice($session->tokenList, -1 * $limit, null, true);
        }
    }

    /**
     * How many tokens are currently stored in the session?
     *
     * @return int
     */
    public function getTokenCount()
    {
        return count($this->csrf->getSession()->tokenList ?? []);
    }

    /**
     * Retrieve CSRF token
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
        return $this->csrf->getHash($regenerate);
    }

    /**
     * Sets translator to use in helper
     *
     * @param TranslatorInterface $translator [optional] translator.
     * Default is null, which sets no translator.
     * @param string              $textDomain [optional] text domain
     * Default is null, which skips setTranslatorTextDomain
     *
     * @return self
     */
    public function setTranslator(?TranslatorInterface $translator = null, $textDomain = null)
    {
        return $this->csrf->setTranslator($translator, $textDomain);
    }

    /**
     * Returns translator used in object
     *
     * @return TranslatorInterface|null
     */
    public function getTranslator()
    {
        return $this->csrf->getTranslator();
    }

    /**
     * Checks if the object has a translator
     *
     * @return bool
     */
    public function hasTranslator()
    {
        return $this->csrf->hasTranslator();
    }

    /**
     * Sets whether translator is enabled and should be used
     *
     * @param bool $enabled [optional] whether translator should be used.
     * Default is true.
     *
     * @return self
     */
    public function setTranslatorEnabled($enabled = true)
    {
        return $this->csrf->setTranslatorEnabled($enabled);
    }

    /**
     * Returns whether translator is enabled and should be used
     *
     * @return bool
     */
    public function isTranslatorEnabled()
    {
        return $this->csrf->isTranslatorEnabled();
    }

    /**
     * Set translation text domain
     *
     * @param string $textDomain New text domain
     *
     * @return TranslatorAwareInterface
     */
    public function setTranslatorTextDomain($textDomain = 'default')
    {
        return $this->csrf->setTranslatorTextDomain($textDomain);
    }

    /**
     * Return the translation text domain
     *
     * @return string
     */
    public function getTranslatorTextDomain()
    {
        return $this->csrf->getTranslatorTextDomain();
    }

    /**
     * Returns true if and only if $value meets the validation requirements
     *
     * If $value fails validation, then this method returns false, and
     * getMessages() will return an array of messages that explain why the
     * validation failed.
     *
     * @param mixed $value Value to validate
     *
     * @return bool
     * @throws Exception\RuntimeException If validation of $value is impossible.
     */
    public function isValid($value)
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

    /**
     * Proxy all other calls to the CSRF object.
     *
     * @param string $method Method being called
     * @param array  $args   Argument list
     *
     * @return mixed
     * @throws \Exception
     */
    public function __call(string $method, array $args = []): mixed
    {
        if (is_callable([$this->csrf, 'method'])) {
            return ($this->csrf->$method)(...$args);
        }
        throw new \Exception("Undefined method: $method");
    }
}
