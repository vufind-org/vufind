<?php

/**
 * Development Tools Controller
 *
 * PHP version 8
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
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301  USA
 *
 * @category VuFind
 * @package  Controller
 * @author   Mark Triggs <vufind-tech@lists.sourceforge.net>
 * @author   Chris Hallberg <challber@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/indexing:alphabetical_heading_browse Wiki
 */

namespace VuFindDevTools\Controller;

use VuFind\I18n\Locale\LocaleSettings;
use VuFind\I18n\Translator\Loader\ExtendedIni;
use VuFind\Role\PermissionManager;
use VuFind\Role\PermissionProvider\PluginManager as PermissionProviderPluginManager;
use VuFind\Role\PermissionProvider\SessionKey;
use VuFind\Search\Results\PluginManager as ResultsManager;
use VuFindDevTools\LanguageHelper;

use function is_callable;

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
     * Fetch the query builder for the specified search backend. Return null if
     * unavailable.
     *
     * @param string $id Backend ID
     *
     * @return object
     */
    protected function getQueryBuilder($id)
    {
        $command = new \VuFindSearch\Command\GetQueryBuilderCommand($id);
        try {
            $this->getService(\VuFindSearch\Service::class)->invoke($command);
        } catch (\Exception $e) {
            return null;
        }
        return $command->getResult();
    }

    /**
     * Deminify action
     *
     * @return \Laminas\View\Model\ViewModel
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
                $this->getService(ResultsManager::class)
            );
        }
        if (isset($view->results) && $view->results) {
            $params = $view->results->getParams();
            $view->query = $params->getQuery();
            if (is_callable([$params, 'getBackendParameters'])) {
                $view->backendParams = $params->getBackendParameters()
                    ->getArrayCopy();
            }
            if ($builder = $this->getQueryBuilder($params->getSearchClassId())) {
                $view->queryParams = $builder->build($view->query)->getArrayCopy();
            }
        }
        return $view;
    }

    /**
     * Home action
     *
     * @return \Laminas\View\Model\ViewModel
     */
    public function homeAction()
    {
        return $this->createViewModel();
    }

    /**
     * Icon action
     *
     * @return array
     */
    public function iconAction()
    {
        $config = $this->getService(\VuFindTheme\ThemeInfo::class)
            ->getMergedConfig('icons');
        $aliases = array_keys($config['aliases'] ?? []);
        sort($aliases);
        return compact('aliases');
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
        $langs = $this->getService(LocaleSettings::class)
            ->getEnabledLocales();
        $helper = new LanguageHelper($loader, $langs);
        return $helper->getAllDetails(
            $this->params()->fromQuery('main', 'en'),
            (bool)$this->params()->fromQuery('includeOptional', 1)
        );
    }

    /**
     * Permissions action
     *
     * @return array
     */
    public function permissionsAction()
    {
        // Handle demo session key setting/unsetting:
        $set = $this->params()->fromQuery('setSessionKey');
        $unset = $this->params()->fromQuery('unsetSessionKey');
        if ($set || $unset) {
            $provider = $this->getService(PermissionProviderPluginManager::class)->get(SessionKey::class);
            $method = $set ? 'setSessionValue' : 'unsetSessionValue';
            $provider->$method('demo_key');
            return $this->redirect()->toRoute('devtools-permissions');
        }

        // Retrieve full permission list:
        $manager = $this->getService(PermissionManager::class);
        $permissions = [];
        foreach ($manager->getAllConfiguredPermissions() as $permission) {
            $permissions[$permission] = $manager->isAuthorized($permission);
        }
        ksort($permissions);
        return compact('permissions');
    }
}
