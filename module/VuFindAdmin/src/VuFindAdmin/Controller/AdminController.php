<?php

/**
 * Admin Controller
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
 * Class controls VuFind administration.
 *
 * @category VuFind
 * @package  Controller
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */
class AdminController extends AbstractAdmin
{
    /**
     * Display disabled message.
     *
     * @return \Laminas\View\Model\ViewModel
     */
    public function disabledAction()
    {
        return $this->createViewModel();
    }

    /**
     * Admin home.
     *
     * @return \Laminas\View\Model\ViewModel
     */
    public function homeAction()
    {
        $config = $this->getConfig();
        $xml = false;
        if (isset($config->Index->url)) {
            $response = $this->getService(\VuFindHttp\HttpService::class)
                ->get($config->Index->url . '/admin/cores?wt=xml');
            $xml = $response->isSuccess() ? $response->getBody() : false;
        }
        $view = $this->createViewModel();
        $view->xml = $xml ? simplexml_load_string($xml) : false;
        return $view;
    }
}
