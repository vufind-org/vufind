<?php

/**
 * Error Controller
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2010.
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
 * @package  Controller
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */

namespace VuFind\Controller;

use Laminas\Mvc\Controller\AbstractActionController;

/**
 * Error Controller
 *
 * @category VuFind
 * @package  Controller
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */
class ErrorController extends AbstractActionController
{
    /**
     * Display unavailable message.
     *
     * @return mixed
     */
    public function unavailableAction()
    {
        $this->getResponse()->setStatusCode(503);
        return new \Laminas\View\Model\ViewModel();
    }

    /**
     * Display permission denied message.
     *
     * @return mixed
     */
    public function permissionDeniedAction()
    {
        $this->getResponse()->setStatusCode(403);
        return new \Laminas\View\Model\ViewModel(
            ['msg' => $this->params()->fromQuery('msg')]
        );
    }
}
