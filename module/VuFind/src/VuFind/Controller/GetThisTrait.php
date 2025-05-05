<?php

/**
 * GetThis trait
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
 * @author   MSUL <LIB.DL.pubcat@msu.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */

namespace VuFind\Controller;

use Laminas\View\Model\ViewModel;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;
use VuFind\GetThis\GetThisLoader;

/**
 * GetThis trait
 *
 * @category VuFind
 * @package  Controller
 * @author   MSUL <LIB.DL.pubcat@msu.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */
trait GetThisTrait
{
    /**
     * Display the "Get this" dialog content.
     *
     * @return ViewModel
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     */
    public function getThisAction(): ViewModel
    {
        $view = $this->createViewModel();

        $items = $this->getILS()->getHolding($this->params()->fromRoute('id'));
        $itemId = $this->params()->fromQuery('item_id');
        $getThis = $this->serviceLocator->get(GetThisLoader::class);
        $getThis->setItemId($itemId);
        if (isset($view->driver)) {
            $getThis->setRecord($view->driver);
        }
        $getThis->setItems($items['holdings']);

        $view->setVariable('getThis', $getThis);
        $view->setTemplate('record/get-this');
        return $view;
    }
}
