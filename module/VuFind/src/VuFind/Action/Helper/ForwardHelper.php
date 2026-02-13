<?php

/**
 * Action helper for forwarding requests.
 *
 * PHP version 8
 *
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
 * @package  Action_Helper
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:hierarchy_components Wiki
 */

namespace VuFind\Action\Helper;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use VuFind\Action\PluginManager as ActionPluginManager;
use VuFind\ServiceManager\Factory\Autowire;

/**
 * Action helper for forwarding requests.
 *
 * @category VuFind
 * @package  Action_Helper
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:hierarchy_components Wiki
 */
class ForwardHelper implements HelperInterface
{
    /**
     * Constructor
     *
     * @param ActionPluginManager $actionPluginManager Action plugin manager
     */
    #[Autowire()]
    public function __construct(
        protected ActionPluginManager $actionPluginManager,
    ) {
    }

    /**
     * Forward the request to another action.
     *
     * @param ServerRequestInterface $request  Request
     * @param ResponseInterface      $response Response
     * @param string                 $actionId Action ('category/action')
     *
     * @return mixed
     */
    public function forwardTo(ServerRequestInterface $request, ResponseInterface $response, string $actionId)
    {
        if (!$this->actionPluginManager->has($actionId)) {
            throw new \InvalidArgumentException("Unknown action '$actionId'");
        }
        $action = $this->actionPluginManager->get($actionId);
        return $action(
            $request->withAttribute('action-id', $actionId),
            $response
        );
    }
}
