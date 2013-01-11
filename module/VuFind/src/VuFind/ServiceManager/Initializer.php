<?php
/**
 * VuFind Service Initializer
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
 * @package  ServiceManager
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:developer_manual Wiki
 */
namespace VuFind\ServiceManager;
use Zend\ServiceManager\ServiceManager;

/**
 * VuFind Service Initializer
 *
 * @category VuFind2
 * @package  ServiceManager
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:developer_manual Wiki
 */
class Initializer
{
    /**
     * Given an instance and a Service Manager, initialize the instance.
     *
     * @param object         $instance Instance to initialize
     * @param ServiceManager $sm       Service manager
     *
     * @return object
     */
    public static function initInstance($instance, ServiceManager $sm)
    {
        if ($instance instanceof \VuFind\Db\Table\DbTableAwareInterface) {
            $instance->setDbTableManager($sm->get('VuFind\DbTablePluginManager'));
        }
        if ($instance instanceof \Zend\Log\LoggerAwareInterface) {
            $instance->setLogger($sm->get('VuFind\Logger'));
        }
        if ($instance instanceof \VuFind\I18n\Translator\TranslatorAwareInterface) {
            $instance->setTranslator($sm->get('VuFind\Translator'));
        }
        if ($instance instanceof \VuFindHttp\HttpServiceAwareInterface) {
            $instance->setHttpService($sm->get('VuFind\Http'));
        }
        return $instance;
    }

    /**
     * Given a Zend Framework Plugin Manager, initialize the instance.
     *
     * @param object                                     $instance Instance to
     * initialize
     * @param \Zend\ServiceManager\AbstractPluginManager $manager  Plugin manager
     *
     * @return object
     */
    public static function initZendPlugin($instance,
        \Zend\ServiceManager\AbstractPluginManager $manager
    ) {
        $sm = $manager->getServiceLocator();
        if (null !== $sm) {
            static::initInstance($instance, $sm);
        }
        return $instance;
    }

    /**
     * Given an instance and a Plugin Manager, initialize the instance.
     *
     * @param object                $instance Instance to initialize
     * @param AbstractPluginManager $manager  Plugin manager
     *
     * @return object
     */
    public static function initPlugin($instance, AbstractPluginManager $manager)
    {
        static::initZendPlugin($instance, $manager);
        if (method_exists($instance, 'setPluginManager')) {
            $instance->setPluginManager($manager);
        }
        return $instance;
    }
}