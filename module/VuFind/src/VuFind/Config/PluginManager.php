<?php
/**
 * VuFind Config Manager
 *
 * PHP version 7
 *
 * Copyright (C) 2010 Villanova University,
 *               2018 Leipzig University Library <info@ub.uni-leipzig.de>
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
 * @package  ServiceManager
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Sebastian Kehr <kehr@ub.uni-leipzig.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
namespace VuFind\Config;

use Interop\Container\ContainerInterface;
use Zend\Config\Config;
use Zend\ServiceManager\AbstractPluginManager;

/**
 * VuFind Configuration Plugin Manager
 *
 * Please use {@see \VuFind\Config\Manager} instead as this class
 * only exists for backwards compatibility.
 *
 * @category   VuFind
 * @package    Config
 * @author     Demian Katz <demian.katz@villanova.edu>
 * @author     Sebastian Kehr <kehr@ub.uni-leipzig.de>
 * @license    http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link       https://vufind.org/wiki/development Wiki
 * @deprecated Deprecated since X.0.0
 */
class PluginManager extends AbstractPluginManager
{
    /**
     * @var Manager
     */
    protected $manager;

    /**
     * PluginManager constructor.
     *
     * @param ContainerInterface $container
     *
     * @throws \Psr\Container\ContainerExceptionInterface
     * @throws \Psr\Container\NotFoundExceptionInterface
     */
    public function __construct(ContainerInterface $container)
    {
        parent::__construct($container);
        $this->manager = $container->get('VuFind\Config\Manager');
    }

    /**
     * @param string     $name
     * @param array|null $options
     *
     * @return Config
     */
    public function get($name, array $options = null) : Config
    {
        return $this->manager->getConfig($name);
    }

    /**
     * Validate the plugin
     *
     * @param mixed $plugin Plugin to validate
     */
    public function validate($plugin)
    {
    }

    /**
     * Reload a configuration
     *
     * @param string $name
     *
     * @return \Zend\Config\Config
     */
    public function reload($name)
    {
        $this->manager->reset();
        return $this->get($name);
    }
}
