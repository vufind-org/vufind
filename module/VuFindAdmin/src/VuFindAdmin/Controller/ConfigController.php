<?php

/**
 * Admin Configuration Controller
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
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */

namespace VuFindAdmin\Controller;

/**
 * Class controls VuFind administration.
 *
 * @category VuFind
 * @package  Controller
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */
class ConfigController extends AbstractAdmin
{
    /**
     * Configuration management
     *
     * @return \Laminas\View\Model\ViewModel
     */
    public function homeAction()
    {
        $view = $this->createViewModel();
        $view->setTemplate('admin/config/home');
        $resolver = $this->getService(\VuFind\Config\PathResolver::class);
        $view->baseConfigPath = $resolver->getBaseConfigPath('');
        $conf = $this->getConfig();
        $view->showInstallLink
            = isset($conf->System->autoConfigure) && $conf->System->autoConfigure;
        return $view;
    }

    /**
     * Support action for config -- attempt to enable auto configuration.
     *
     * @return mixed
     */
    public function enableautoconfigAction()
    {
        $resolver = $this->getService(\VuFind\Config\PathResolver::class);
        if (!($configFile = $resolver->getLocalConfigPath('config.ini'))) {
            $this->flashMessenger()->addErrorMessage(
                'Could not enable auto-configuration; local '
                . $configFile . ' not found.'
            );
            return $this->forwardTo('AdminConfig', 'Home');
        }
        $writer = new \VuFind\Config\Writer($configFile);
        $writer->set('System', 'autoConfigure', 1);
        $success = false;
        try {
            $success = $writer->save();
        } catch (\Exception $e) {
            // Failure -- leave $success set to false.
        }
        if ($success) {
            $this->flashMessenger()->addSuccessMessage('Auto-configuration enabled.');

            // Reload config now that it has been edited (otherwise, old setting
            // will persist in cache):
            $this->getService(\VuFind\Config\PluginManager::class)->reload('config');
        } else {
            $this->flashMessenger()->addErrorMessage(
                'Could not enable auto-configuration; check permissions on '
                . $configFile . '.'
            );
        }
        return $this->forwardTo('AdminConfig', 'Home');
    }
}
