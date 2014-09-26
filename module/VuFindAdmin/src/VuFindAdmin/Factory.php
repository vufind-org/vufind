<?php
/**
 * Factory for VuFindAdmin services.
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
 * @package  Service
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:developer_manual Wiki
 */
namespace VuFindAdmin;
use Zend\ServiceManager\ServiceManager;

/**
 * Factory for VuFindAdmin services.
 *
 * @category VuFind2
 * @package  Service
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:developer_manual Wiki
 * @codeCoverageIgnore
 */
class Factory
{
    /**
     * Construct the Admin module's HasAccessAssertion.
     *
     * @param ServiceManager $sm Service manager.
     *
     * @return \VuFind\Assertion\HasAccessAssertion
     */
    public static function getHasAccessAssertion(ServiceManager $sm)
    {
        $rawConfig = $sm->getServiceLocator()->get('VuFind\Config')->get('config');
        $config = isset($rawConfig->AdminAuth)
            ? $rawConfig->AdminAuth->toArray() : array();
        $request = $sm->getServiceLocator()->get('Request');
        return new \VuFind\Assertion\HasAccessAssertion($config, $request);
    }
}