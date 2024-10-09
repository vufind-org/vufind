<?php

/**
 * "Get User Favorites Status" AJAX handler
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2024.
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
 * @package  AJAX
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */

namespace VuFind\AjaxHandler;

use Laminas\Mvc\Controller\Plugin\Params;
use VuFind\Db\Entity\UserEntityInterface;
use VuFind\Db\Service\ResourceServiceInterface;
use VuFind\Session\Settings as SessionSettings;

use function count;

/**
 * "Get User Favorites Status" AJAX handler
 *
 * @category VuFind
 * @package  AJAX
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
class GetUserFavoritesStatus extends AbstractBase
{
    /**
     * Constructor
     *
     * @param SessionSettings          $ss              Session settings
     * @param ?UserEntityInterface     $user            Logged in user (or null)
     * @param ResourceServiceInterface $resourceService Resoruce database service
     */
    public function __construct(
        SessionSettings $ss,
        protected ?UserEntityInterface $user,
        protected ResourceServiceInterface $resourceService
    ) {
        $this->sessionSettings = $ss;
    }

    /**
     * Handle a request.
     *
     * @param Params $params Parameter helper from controller
     *
     * @return array [response data, internal status code, HTTP status code]
     */
    public function handleRequest(Params $params)
    {
        $this->disableSessionWrites();  // avoid session write timing bug
        $count = $this->user ? count($this->resourceService->getFavorites($this->user)) : 0;
        return $this->formatResponse(compact('count'));
    }
}
