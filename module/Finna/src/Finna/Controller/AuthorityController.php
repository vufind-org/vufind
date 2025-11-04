<?php

/**
 * Authority Controller
 *
 * PHP version 8
 *
 * Copyright (C) The National Library of Finland 2019-2020.
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
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org   Main Site
 */

namespace Finna\Controller;

/**
 * Authority Record Controller
 *
 * @category VuFind
 * @package  Controller
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org   Main Site
 */
class AuthorityController extends \Finna\Controller\SearchController
{
    use FinnaSearchControllerTrait;
    use FinnaAuthorityControllerTrait;

    protected $searchClassId = 'SolrAuth';

    /**
     * Home action
     *
     * @return mixed
     */
    public function homeAction()
    {
        $this->layout()->searchClassId = $this->searchClassId;
        return parent::homeAction();
    }

    /**
     * Record action -- display a record
     *
     * @return \Laminas\View\Model\ViewModel
     */
    public function recordAction()
    {
        return $this->redirect()->toRoute(
            'authorityrecord',
            ['id' => $this->params()->fromQuery('id')]
        );
    }

    /**
     * Search action -- call standard results action
     *
     * @return mixed
     */
    public function searchAction()
    {
        return $this->resultsAction();
    }
}
