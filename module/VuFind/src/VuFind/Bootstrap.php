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
use VuFind\Config\Reader as ConfigReader,
    VuFind\Theme,
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

    /**
     * Constructor
     *
     * @param MvcEvent $event Zend MVC Event object
     */
    public function __construct(MvcEvent $event)
    {
        $this->config = ConfigReader::getConfig();
        $this->event = $event;
    }

    /**
     * Bootstrap all necessary resources.
     *
     * @return void
     */
    public function bootstrap()
    {
        $this->initTheme();
    }

    /**
     * Set up theme handling.
     *
     * @return void
     */
    protected function initTheme()
    {
        $events = $this->event->getApplication()->events();

        // Attach template injection configuration to the route event:
        $events->attach('route', array('VuFind\Theme', 'configureTemplateInjection'));

        // Attach remaining theme configuration to the dispatch event:
        $config =& $this->config;
        $events->attach('dispatch', function($event) use ($config) {
            $theme = new Theme($config, $event);
            $theme->init();
        });
    }
}