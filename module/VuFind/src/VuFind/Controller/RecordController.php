<?php

/**
 * Record Controller.
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

use Laminas\ServiceManager\ServiceLocatorInterface;
use Laminas\View\Model\ViewModel;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;
use VuFind\Config\Config;
use VuFind\GetThis\GetThisLoader;

/**
 * Record Controller.
 *
 * @category VuFind
 * @package  Controller
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */
class RecordController extends AbstractRecord
{
    use HoldsTrait;
    use ILLRequestsTrait;
    use StorageRetrievalRequestsTrait;

    /**
     * Constructor.
     *
     * @param ServiceLocatorInterface $sm     Service manager
     * @param Config                  $config VuFind configuration
     */
    public function __construct(ServiceLocatorInterface $sm, Config $config)
    {
        // Call standard record controller initialization:
        parent::__construct($sm);

        // Load default tab setting:
        $this->fallbackDefaultTab = $config->Site->defaultRecordTab ?? 'Holdings';
    }

    /**
     * Display the "Get This" dialog content.
     *
     * @return ViewModel
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     */
    public function getThisAction(): ViewModel
    {
        $view = $this->createViewModel();
        $id = $this->params()->fromRoute('id');
        $itemId = $this->params()->fromQuery('item_id');
        $items = $this->getILS()->getStatus($id);
        $getThisLoader = $this->getService(GetThisLoader::class);
        if (isset($view->driver)) {
            $getThisLoader->setRecordDriver($view->driver);
        }
        $getThisLoader->setItems($items);
        $getThisLoader->setDefaultItemId($itemId);

        $view->getThisLoader = $getThisLoader;
        $view->setTemplate('record/get-this');
        return $view;
    }
}
