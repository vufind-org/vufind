<?php

/**
 * WorldCat Controller (legacy -- redirects to WorldCat v2 controller)
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2024.
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

/**
 * WorldCat Controller (legacy -- redirects to WorldCat v2 controller)
 *
 * @category VuFind
 * @package  Controller
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */
class WorldcatController extends AbstractBase
{
    /**
     * Home action -- redirect to WorldCat v2.
     *
     * @return mixed
     */
    public function homeAction()
    {
        return $this->redirect()->toRoute('worldcat2-home');
    }

    /**
     * Advanced search action -- redirect to WorldCat v2.
     *
     * @return mixed
     */
    public function advancedAction()
    {
        return $this->redirect()->toRoute('worldcat2-advanced');
    }

    /**
     * Search action -- transform search and redirect to WorldCat v2.
     *
     * @return mixed
     */
    public function searchAction()
    {
        $params = $this->params()->fromQuery();
        // v1 types are prefixed with "srw." but v2 types are not; convert!
        foreach ($params as $key => $value) {
            if (str_starts_with($key, 'type')) {
                $params[$key] = str_replace('srw.', '', $value);
            }
        }
        return $this->redirect()->toRoute('worldcat2-search', options: ['query' => $params]);
    }
}
