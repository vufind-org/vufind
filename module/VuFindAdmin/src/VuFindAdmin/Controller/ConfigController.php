<?php
/**
 * Admin Configuration Controller
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
namespace VuFindAdmin\Controller;

/**
 * Class controls VuFind administration.
 *
 * @category VuFind2
 * @package  Controller
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org   Main Site
 */
class ConfigController extends AbstractAdmin
{
    /**
     * Configuration management
     *
     * @return \Zend\View\Model\ViewModel
     */
    public function homeAction()
    {
        $view = $this->createViewModel();
        $view->setTemplate('admin/config/home');
        $view->baseConfigPath = \VuFind\Config\Locator::getBaseConfigPath('');
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
        $configFile = \VuFind\Config\Locator::getConfigPath('config.ini');
        $writer = new \VuFind\Config\Writer($configFile);
        $writer->set('System', 'autoConfigure', 1);
        if ($writer->save()) {
            $this->flashMessenger()
                ->addMessage('Auto-configuration enabled.', 'success');

            // Reload config now that it has been edited (otherwise, old setting
            // will persist in cache):
            $this->getServiceLocator()->get('VuFind\Config')->reload('config');
        } else {
            $this->flashMessenger()->addMessage(
                'Could not enable auto-configuration; check permissions on '
                . $configFile . '.', 'error'
            );
        }
        return $this->forwardTo('AdminConfig', 'Home');
    }

}