<?php
/**
 * Combined Search Controller
 *
 * PHP version 7
 *
 * Copyright (C) The National Library of Finland 2015.
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
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org   Main Site
 */
namespace Finna\Controller;

/**
 * Redirects the user to the appropriate default VuFind action.
 *
 * @category VuFind
 * @package  Controller
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org   Main Site
 */
class CombinedController extends \VuFind\Controller\CombinedController
{
    use FinnaSearchControllerTrait;

    /**
     * Handle onDispatch event
     *
     * @param \Laminas\Mvc\MvcEvent $e Event
     *
     * @return mixed
     */
    public function onDispatch(\Laminas\Mvc\MvcEvent $e)
    {
        $combinedHelper = $this->getViewRenderer()->plugin('combined');
        if (!$combinedHelper->isAvailable()) {
            throw new \Exception('Combined view is disabled');
        }

        return parent::onDispatch($e);
    }

    /**
     * Results action
     *
     * @return mixed
     */
    public function resultsAction()
    {
        $view = parent::resultsAction();
        if ($saved = $this->getCombinedSearches()) {
            $view->params->setCombinedSearchIds($saved);
        }
        return $view;
    }

    /**
     * Convenience method to make invocation of forward() helper less verbose.
     *
     * @param string $controller Controller to invoke
     * @param string $action     Action to invoke
     * @param array  $params     Extra parameters for the RouteMatch object (no
     * need to provide action here, since $action takes care of that)
     *
     * @return mixed
     */
    public function forwardTo($controller, $action, $params = [])
    {
        $this->getRequest()->getQuery()->set('combined', 1);
        return parent::forwardTo($controller, $action, $params);
    }

    /**
     * Get tab configuration based on the full combined results configuration.
     *
     * @param array $config Combined results configuration
     *
     * @return array
     */
    protected function getTabConfig($config)
    {
        $config = parent::getTabConfig($config);

        // Strip out non-tab sections of the configuration:
        unset($config['General']);

        return $config;
    }
}
