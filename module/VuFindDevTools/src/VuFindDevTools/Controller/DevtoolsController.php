<?php
/**
 * Development Tools Controller
 *
 * PHP Version 5
 *
 * Copyright (C) Villanova University 2011.
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License version 2,
 * as published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.    See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA    02111-1307    USA
 *
 * @category VuFind
 * @package  Controller
 * @author   Mark Triggs <vufind-tech@lists.sourceforge.net>
 * @author   Chris Hallberg <challber@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/indexing:alphabetical_heading_browse Wiki
 */
namespace VuFindDevTools\Controller;
use VuFind\I18n\Translator\Loader\ExtendedIni;
use VuFindDevTools\LanguageHelper;

/**
 * Development Tools Controller
 *
 * @category VuFind
 * @package  Controller
 * @author   Mark Triggs <vufind-tech@lists.sourceforge.net>
 * @author   Chris Hallberg <challber@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/indexing:alphabetical_heading_browse Wiki
 */
class DevtoolsController extends \VuFind\Controller\AbstractBase
{
    /**
     * Deminify action
     *
     * @return \Zend\View\Model\ViewModel
     */
    public function deminifyAction()
    {
        $min = trim($this->params()->fromPost('min'));
        $view = $this->createViewModel();
        if (!empty($min)) {
            $view->min = unserialize($min);
        }
        if (isset($view->min) && $view->min) {
            $view->results = $view->min->deminify(
                $this->getServiceLocator()->get('VuFind\SearchResultsPluginManager')
            );
        }
        if (isset($view->results) && $view->results) {
            $params = $view->results->getParams();
            $view->query = $params->getQuery();
            if (is_callable([$params, 'getBackendParameters'])) {
                $view->backendParams = $params->getBackendParameters()
                    ->getArrayCopy();
            }
            try {
                $backend = $this->getServiceLocator()
                    ->get('VuFind\Search\BackendManager')
                    ->get($params->getSearchClassId());
            } catch (\Exception $e) {
                $backend = false;
            }
            if ($backend && is_callable([$backend, 'getQueryBuilder'])) {
                $builder = $backend->getQueryBuilder();
                $view->queryParams = $builder->build($view->query)->getArrayCopy();
            }
        }
        return $view;
    }

    /**
     * Language action
     *
     * @return array
     */
    public function languageAction()
    {
        // Test languages with no local overrides and no fallback:
        $loader = new ExtendedIni([APPLICATION_PATH . '/languages']);
        $helper = new LanguageHelper($loader, $this->getConfig());
        return $helper->getAllDetails($this->params()->fromQuery('main', 'en'));
    }
}
