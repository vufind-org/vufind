<?php

/**
 * Short link controller
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2019.
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
 * @package  Controller
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */

namespace VuFind\Controller;

use Laminas\Diactoros\ServerRequestFactory;
use Laminas\Psr7Bridge\Psr7Response;
use Psr\Http\Message\ResponseInterface;
use VuFind\Action\PluginManager;

use function is_array;

/**
 * Short link controller
 *
 * @category VuFind
 * @package  Controller
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */
class ShortlinkController extends AbstractBase
{
    /**
     * Resolve full version of shortlink & redirect to target.
     *
     * @return mixed
     */
    public function redirectAction()
    {
        $actionManager = $this->serviceLocator->get(PluginManager::class);
        $action = $actionManager->getActionHandler('shortlink', 'redirect');
        $request = ServerRequestFactory::fromGlobals();
        foreach ($this->params()->fromRoute() as $routeParam => $value) {
            $request = $request->withAttribute($routeParam, $value);
        }
        $result = $action->redirectAction($request);
        if (is_array($result)) {
            return $this->createViewModel($result);
        }
        if ($result instanceof ResponseInterface) {
            return Psr7Response::toLaminas($result);
        }
        throw new \Exception('Unexpected state reached.');
    }
}
