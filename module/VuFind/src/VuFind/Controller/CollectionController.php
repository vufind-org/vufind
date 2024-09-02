<?php

/**
 * Collection Controller
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

namespace VuFind\Controller;

use Laminas\Config\Config;
use Laminas\ServiceManager\ServiceLocatorInterface;

/**
 * Collection Controller
 *
 * @category VuFind
 * @package  Controller
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */
class CollectionController extends AbstractRecord
{
    /**
     * Constructor
     *
     * @param ServiceLocatorInterface $sm     Service manager
     * @param Config                  $config VuFind configuration
     */
    public function __construct(ServiceLocatorInterface $sm, Config $config)
    {
        // Call standard record controller initialization:
        parent::__construct($sm);

        // Set default tab, if specified:
        if (isset($config->Collections->defaultTab)) {
            $this->fallbackDefaultTab = $config->Collections->defaultTab;
        }
    }

    /**
     * Get the tab configuration for this controller.
     *
     * @return \VuFind\RecordTab\TabManager
     */
    protected function getRecordTabManager()
    {
        $manager = parent::getRecordTabManager();
        $manager->setContext('collection');
        return $manager;
    }

    /**
     * Display a particular tab.
     *
     * @param string $tab  Name of tab to display
     * @param bool   $ajax Are we in AJAX mode?
     *
     * @return mixed
     */
    protected function showTab($tab, $ajax = false)
    {
        // Check that collections are enabled and redirect if necessary
        $config = $this->getConfig();
        if (empty($config->Collections->collections)) {
            return $this->redirectToRecord();
        }

        $result = parent::showTab($tab, $ajax);
        if (
            !$ajax && $result instanceof \Laminas\View\Model\ViewModel
            && $result->getTemplate() !== 'myresearch/login'
        ) {
            $result->setTemplate('collection/view');
        }
        return $result;
    }

    /**
     * Is the result scroller active?
     *
     * @return bool
     */
    protected function resultScrollerActive()
    {
        $config = $this->getService(\VuFind\Config\PluginManager::class)->get('config');
        return $config->Record->next_prev_navigation ?? false;
    }
}
