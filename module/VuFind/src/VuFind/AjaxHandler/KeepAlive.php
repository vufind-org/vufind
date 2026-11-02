<?php

/**
 * "Keep Alive" AJAX handler.
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
 * @package  AJAX
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */

namespace VuFind\AjaxHandler;

use Laminas\Session\SessionManager;
use Psr\Http\Message\ServerRequestInterface;

/**
 * "Keep Alive" AJAX handler.
 *
 * This is responsible for keeping the session alive whenever called
 * (via JavaScript)
 *
 * @category VuFind
 * @package  AJAX
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
class KeepAlive extends AbstractBase
{
    /**
     * Session Manager.
     *
     * @var SessionManager
     */
    protected $sessionManager;

    /**
     * Constructor.
     *
     * @param SessionManager $sm Session manager
     */
    public function __construct(SessionManager $sm)
    {
        parent::__construct(null);
        $this->sessionManager = $sm;
    }

    /**
     * Handle a request.
     *
     * @param ServerRequestInterface $request Request
     *
     * @return array [response data, HTTP status code]
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function handleRequest(ServerRequestInterface $request): array
    {
        // Request ID from session to mark it active
        $this->sessionManager->getId();
        return $this->formatResponse(true);
    }
}
