<?php

/**
 * Search action for course reserves.
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2010.
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
 * @package  Action
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */

namespace VuFind\Action\Search;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use VuFind\Action\AbstractTemplateRenderingAction;
use VuFind\ActionHelper\ForwardHelper;
use VuFind\ILS\Connection;
use VuFind\Search\Base\Results;
use VuFind\Search\ReservesHelper;
use VuFind\ServiceManager\Factory\Autowire;

/**
 * Search action for course reserves.
 *
 * @category VuFind
 * @package  Action
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */
class ReservesAction extends AbstractTemplateRenderingAction
{
    /**
     * Constructor.
     *
     * @param ReservesHelper $reservesHelper Reserves helper
     * @param Connection     $ilsConnection  ILS connection
     */
    #[Autowire]
    public function __construct(
        protected ReservesHelper $reservesHelper,
        protected Connection $ilsConnection,
    ) {
        parent::__construct();
    }

    /**
     * Display reserves search form.
     *
     * @param ServerRequestInterface $request  Server request
     * @param ResponseInterface      $response Response
     *
     * @return ResponseInterface
     */
    public function action(
        ServerRequestInterface $request,
        ResponseInterface $response,
    ): ResponseInterface {
        // Search parameters set?  Process results.
        if (
            $this->getQueryParam('inst') !== null
            || $this->getQueryParam('course') !== null
            || $this->getQueryParam('dept') !== null
        ) {
            return $this->getHelper(ForwardHelper::class)->forwardTo($request, $response, 'Search/ReservesResults');
        }

        // No params?  Show appropriate form (varies depending on whether we're using ILS-based or Solr-based
        // reserves searching).
        if ($this->reservesHelper->useIndex()) {
            return $this->getHelper(ForwardHelper::class)->forwardTo($request, $response, 'Search/ReservesSearch');
        }

        // If we got this far, we're using driver-based searching and need to send options to the view (but we should
        // tolerate drivers that do not define all of the department/instructor/courses getters):
        try {
            $deptList = $this->ilsConnection->getDepartments();
        } catch (\VuFind\Exception\ILS $e) {
            $deptList = [];
        }
        try {
            $instList = $this->ilsConnection->getInstructors();
        } catch (\VuFind\Exception\ILS $e) {
            $instList = [];
        }
        try {
            $courseList =  $this->ilsConnection->getCourses();
        } catch (\VuFind\Exception\ILS $e) {
            $courseList = [];
        }
        return $this->renderTemplate($request, $response, compact('deptList', 'instList', 'courseList'));
    }
}
