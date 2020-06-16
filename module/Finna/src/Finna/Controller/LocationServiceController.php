<?php
/**
 * Location Service Controller.
 *
 * PHP version 7
 *
 * Copyright (C) The National Library of Finland 2016.
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
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org   Main Site
 */
namespace Finna\Controller;

/**
 * Location Service Controller.
 *
 * @category VuFind
 * @package  Controller
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org   Main Site
 */
class LocationServiceController extends \VuFind\Controller\AbstractBase
{
    /**
     * Return HTML that loads the Location Service map to an iframe.
     *
     * @return string
     * @throws \Exception
     */
    public function modalAction()
    {
        $request = $this->getRequest()->getQuery()->toArray()
            + $this->getRequest()->getPost()->toArray();

        if (!isset($request['source'])) {
            throw new \Exception("Missing parameter 'source'");
        }
        if (!isset($request['callnumber'])) {
            throw new \Exception("Missing parameter 'callnumber'");
        }
        $source = $request['source'];
        $callnumber = $request['callnumber'];
        $collection = $request['collection'];
        $location = $request['location'];
        $title = $request['title'];

        $locationService = $this->serviceLocator
            ->get(\Finna\LocationService\LocationService::class);
        $language = $this->serviceLocator
            ->get(\Laminas\Mvc\I18n\Translator::class)->getLocale();

        $config = $locationService->getConfig(
            $source, $title, $callnumber, $collection, $location, $language
        );
        if ($config) {
            $view = $this->createViewModel();
            $view->url = $config['url'];
            return $view;
        }
        throw new \Exception("Invalid configuration (source $source)");
    }
}
