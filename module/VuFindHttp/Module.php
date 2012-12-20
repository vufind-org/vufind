<?php

/**
 * ZF2 module definition for the VF2 proxy service
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
 * @category Proxy
 * @package  Service
 * @author   David Maus <maus@hab.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://github.com/dmj/vf2-proxy
 */

namespace VuFindHttp;

/**
 * ZF2 module definition for the VF2 HTTP service.
 *
 * @category Proxy
 * @package  Service
 * @author   David Maus <maus@hab.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://github.com/dmj/vf2-proxy
 */

class Module
{

    /**
     * Relative path to search service configuration.
     *
     * @var string
     */
    protected $configPath = 'config/http.conf.php';

    /**
     * Return autoloader configuration.
     *
     * @return array
     */
    public function getAutoloaderConfig ()
    {
        return array(
            'Zend\Loader\StandardAutoloader' => array(
                'namespaces' => array(
                    __NAMESPACE__ => __DIR__ . '/src/' . __NAMESPACE__,
                ),
            ),
        );
    }

    /**
     * Return module configuration.
     *
     * @return array
     */
    public function getConfig ()
    {
        return array();
    }

    /**
     * Initialize module.
     *
     * @return void
     */
    public function init ()
    {
    }

    /**
     * Return service configuration.
     *
     * @return array
     */
    public function getServiceConfig ()
    {
        return array(
            'factories' => array(
                'VuFind\Http' => array($this, 'setup'),
            )
        );
    }

    /**
     * Return configured http service to superior service manager.
     *
     * @return \VuFind\Service\Search
     */
    public function setup ()
    {
        $config = include realpath(__DIR__ . '/' . $this->configPath);

        $di = new \Zend\Di\Di();
        $di->configure(new \Zend\Di\Config($config));
        return $di->get('VuFindHttp\HttpService');
    }

}