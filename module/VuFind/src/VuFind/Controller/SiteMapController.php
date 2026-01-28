<?php

/**
 * Site map controller.
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
 * @package  Controller
 * @author   Aleksi Peebles <aleksi.peebles@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */

namespace VuFind\Controller;

use Laminas\ServiceManager\ServiceLocatorInterface;
use Laminas\View\Model\ViewModel;
use VuFind\Navigation\SiteMap;

/**
 * Site map controller.
 *
 * @category VuFind
 * @package  Controller
 * @author   Aleksi Peebles <aleksi.peebles@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */
class SiteMapController extends AbstractBase
{
    /**
     * Constructor.
     *
     * @param ServiceLocatorInterface $sm      Service locator
     * @param SiteMap                 $siteMap Site map navigation plugin
     */
    public function __construct(
        ServiceLocatorInterface $sm,
        protected SiteMap $siteMap,
    ) {
        parent::__construct($sm);
    }

    /**
     *  Generates a site map page as specified in WCAG 2.2 Technique G63.
     *
     * @return ViewModel
     */
    public function homeAction()
    {
        $viewModel = $this->createViewModel([
            'siteMap' => $this->siteMap,
        ]);
        $viewModel->setTemplate('Section/SiteMap.phtml');

        return $viewModel;
    }
}
