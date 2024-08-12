<?php

/**
 * Admin Tag Controller
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

namespace VuFindAdmin\Controller;

/**
 * Class controls distribution of tags and resource tags.
 *
 * @category VuFind
 * @package  Controller
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */
class OverdriveController extends AbstractAdmin
{
    /**
     * Params
     *
     * @var array
     */
    protected $params;

    /**
     * Get the url parameters
     *
     * @param string $param A key to check the url params for
     *
     * @return string
     */
    protected function getParam($param)
    {
        return $this->params[$param] ?? $this->params()->fromPost(
            $param,
            $this->params()->fromQuery($param, null)
        );
    }

    /**
     * Tag Details
     *
     * @return \Laminas\View\Model\ViewModel
     */
    public function homeAction()
    {
        $connector  = $this->getService(\VuFind\DigitalContent\OverdriveConnector::class);

        $view = $this->createViewModel();
        $view->setTemplate('admin/overdrive/home');
        $view->productsKey = $connector->getCollectionToken();
        $view->overdriveConfig = $connector->getConfig();
        $view->hasAccess = $connector->getAccess();
        return $view;
    }
}
