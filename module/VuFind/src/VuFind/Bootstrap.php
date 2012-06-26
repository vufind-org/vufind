<?php
/**
 * VuFind Bootstrapper
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
 * @package  Support_Classes
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org   Main Site
 */
namespace VuFind;
use VuFind\Account\Manager as AccountManager,
    VuFind\Config\Reader as ConfigReader,
    VuFind\Theme\Initializer as ThemeInitializer,
    Zend\Mvc\MvcEvent;
/**
 * VuFind Bootstrapper
 *
 * @category VuFind2
 * @package  Support_Classes
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org   Main Site
 */
class Bootstrap
{
    protected $config;
    protected $event;
    protected $events;

    /**
     * Constructor
     *
     * @param MvcEvent $event Zend MVC Event object
     */
    public function __construct(MvcEvent $event)
    {
        $this->config = ConfigReader::getConfig();
        $this->event = $event;
        $this->events = $event->getApplication()->events();
    }

    /**
     * Bootstrap all necessary resources.
     *
     * @return void
     */
    public function bootstrap()
    {
        $this->initAccount();
        $this->initContext();
        $this->initHeadTitle();
        $this->initTheme();
    }

    /**
     * Make account manager available to views.
     *
     * @return void
     */
    protected function initAccount()
    {
        $callback = function($event) {
            $serviceManager = $event->getApplication()->getServiceManager();
            $viewModel = $serviceManager->get('viewmanager')->getViewModel();
            $viewModel->setVariable('account', AccountManager::getInstance());
        };
        $this->events->attach('dispatch', $callback);
    }

    /**
     * Set view variables representing the current context.
     *
     * @return void
     */
    protected function initContext()
    {
        $callback = function($event) {
            $serviceManager = $event->getApplication()->getServiceManager();
            $viewModel = $serviceManager->get('viewmanager')->getViewModel();

            // Grab the template name from the first child -- we can use this to
            // figure out the current template context.
            $children = $viewModel->getChildren();
            $parts = explode('/', $children[0]->getTemplate());
            $viewModel->setVariable('templateDir', $parts[0]);
            $viewModel->setVariable(
                'templateName', isset($parts[1]) ? $parts[1] : null
            );
        };
        $this->events->attach('dispatch', $callback);
    }

    /**
     * Set up headTitle view helper -- we always want to set, not append, titles.
     *
     * @return void
     */
    protected function initHeadTitle()
    {
        $callback = function($event) {
            $serviceManager = $event->getApplication()->getServiceManager();
            $renderer = $serviceManager->get('viewmanager')->getRenderer();
            $headTitle = $renderer->plugin('headtitle');
            $headTitle->setDefaultAttachOrder(
                \Zend\View\Helper\Placeholder\Container\AbstractContainer::SET
            );
        };
        $this->events->attach('dispatch', $callback);
    }

    /**
     * Set up theme handling.
     *
     * @return void
     */
    protected function initTheme()
    {
        // Attach template injection configuration to the route event:
        $this->events->attach(
            'route', array('VuFind\Theme\Initializer', 'configureTemplateInjection')
        );

        // Attach remaining theme configuration to the dispatch event:
        $config =& $this->config;
        $callback = function($event) use ($config) {
            $theme = new ThemeInitializer($config, $event);
            $theme->init();
        };
        $this->events->attach('dispatch', $callback);
    }
}