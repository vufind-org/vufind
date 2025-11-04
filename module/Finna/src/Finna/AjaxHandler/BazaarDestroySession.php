<?php

/**
 * AJAX handler for destroying a Bazaar session.
 *
 * PHP version 8
 *
 * Copyright (C) The National Library of Finland 2023.
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
 * @author   Aleksi Peebles <aleksi.peebles@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */

namespace Finna\AjaxHandler;

use Finna\Service\BazaarService;
use Laminas\Mvc\Controller\Plugin\Params;

/**
 * AJAX handler for destroying a Bazaar session.
 *
 * @category VuFind
 * @package  AJAX
 * @author   Aleksi Peebles <aleksi.peebles@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
class BazaarDestroySession extends \VuFind\AjaxHandler\AbstractBase
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
     * Handle a request.
     *
     * @param Params $params Parameter helper from controller
     *
     * @return array [response data, HTTP status code]
     */
    public function handleRequest(Params $params)
    {
        $this->bazaarService->destroySession();
        return $this->formatResponse('');
    }
}
