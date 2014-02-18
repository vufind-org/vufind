<?php
/**
 * Factory for authentication services.
 *
 * PHP version 5
 *
 * Copyright (C) Villanova University 2014.
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
 * @package  Authentication
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:developer_manual Wiki
 */
namespace VuFind\Auth;
use Zend\ServiceManager\ServiceManager;

/**
 * Factory for authentication services.
 *
 * @category VuFind2
 * @package  Authentication
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:developer_manual Wiki
 */
class Factory
{
    /**
     * Construct the ILS plugin.
     *
     * @param ServiceManager $sm Service manager.
     *
     * @return ILS
     */
    public static function getILS(ServiceManager $sm)
    {
        return new ILS($sm->getServiceLocator()->get('VuFind\ILSConnection'));
    }

    /**
     * Construct the authentication manager.
     *
     * @param ServiceManager $sm Service manager.
     *
     * @return Manager
     */
    public static function getManager(ServiceManager $sm)
    {
        return new Manager($sm->get('VuFind\Config')->get('config'));
    }

    /**
     * Construct the MultiILS plugin.
     *
     * @param ServiceManager $sm Service manager.
     *
     * @return MultiILS
     */
    public static function getMultiILS(ServiceManager $sm)
    {
        return new MultiILS($sm->getServiceLocator()->get('VuFind\ILSConnection'));
    }
}