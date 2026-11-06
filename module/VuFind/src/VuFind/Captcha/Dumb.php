<?php

/**
 * A simple captcha that asks for a reversed string.
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2023.
 * Copyright (C) The National Library of Finland 2026.
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
 * @package  CAPTCHA
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */

namespace VuFind\Captcha;

use Laminas\Session\Container;
use VuFind\ServiceManager\Factory\Autowire;
use VuFind\Session\SessionContainerRegistry;

/**
 * A simple captcha that asks for a reversed string.
 *
 * @category VuFind
 * @package  CAPTCHA
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
class Dumb extends AbstractInputCaptcha
{
    /**
     * Constructor.
     *
     * @param Container $sessionContainer Session container for captcha string
     */
    public function __construct(
        #[Autowire(container: SessionContainerRegistry::class, service: 'DumbCaptcha')]
        protected Container $sessionContainer,
    ) {
        parent::__construct();
        $this->sessionContainer->setExpirationHops(1, null);
        $this->sessionContainer->setExpirationSeconds($this->timeout);
    }

    /**
     * Get the captcha word.
     *
     * @return string
     */
    public function getWord(): string
    {
        $str = substr(str_shuffle(md5(microtime(true))), 0, 8);
        $this->sessionContainer->phrase = $str;
        return $str;
    }

    /**
     * Pull the captcha field from request params and check it.
     *
     * @param array $postParams  POST params
     * @param array $queryParams Query params
     *
     * @return bool
     */
    public function verify(array $postParams, array $queryParams): bool
    {
        $param = $this->getHtmlInputId();
        $str = $postParams[$param] ?? $queryParams[$param] ?? null;
        return $str === $this->sessionContainer->phrase;
    }
}
