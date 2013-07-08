<?php
/**
 * Combined Search Controller
 *
 * PHP version 5
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
 * Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
 *
 * @category VuFind2
 * @package  Controller
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org   Main Site
 */
namespace VuFind\Controller;
use Zend\Stdlib\Parameters;

/**
 * Redirects the user to the appropriate default VuFind action.
 *
 * @category VuFind2
 * @package  Controller
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org   Main Site
 */
class CombinedController extends AbstractSearch
{
    /**
     * Constructor
     */
    public function __construct()
    {
        $this->searchClassId = 'Combined';
        parent::__construct();
    }

    /**
     * Home action
     *
     * @return mixed
     */
    public function homeAction()
    {
        return $this->createViewModel();
    }

    /**
     * Results action
     *
     * @return mixed
     */
    public function resultsAction()
    {
        // Set up current request context:
        $results = $this->getResultsManager()->get('Combined');
        $params = $results->getParams();
        $params->initFromRequest(
            new Parameters(
                $this->getRequest()->getQuery()->toArray()
                + $this->getRequest()->getPost()->toArray()
            )
        );

        // Gather combined results:
        $combinedResults = array();
        $options = $this->getServiceLocator()
            ->get('VuFind\SearchOptionsPluginManager');
        $config = $this->getServiceLocator()->get('VuFind\Config')->get('combined')
            ->toArray();
        foreach ($config as $current => $settings) {
            $currentOptions = $options->get($current);
            list($controller, $action)
                = explode('-', $currentOptions->getSearchAction());
            $combinedResults[$current] = $settings;
            $combinedResults[$current]['view']
                = $this->forwardTo($controller, $action);
        }

        // Build view model:
        return $this->createViewModel(
            array(
                'results' => $results,
                'params' => $params,
                'combinedResults' => $combinedResults
            )
        );
    }
}
