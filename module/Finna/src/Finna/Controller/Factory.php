<?php
/**
 * Factory for controllers.
 *
 * PHP version 5
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
 * Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
 *
 * @category VuFind2
 * @package  Controller
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:developer_manual Wiki
 */
namespace Finna\Controller;
use Zend\ServiceManager\ServiceManager;

/**
 * Factory for controllers.
 *
 * @category VuFind2
 * @package  Controller
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:developer_manual Wiki
 *
 * @codeCoverageIgnore
 */
class Factory
{
    /**
     * Construct the BrowseController.
     *
     * @param ServiceManager $sm Service manager.
     *
     * @return BrowseController
     */
    public static function getBrowseController(ServiceManager $sm)
    {
        return new BrowseController(
            $sm->getServiceLocator()->get('VuFind\Config')->get('config')
        );
    }

    /**
     * Construct the RecordController.
     *
     * @param ServiceManager $sm Service manager.
     *
     * @return RecordController
     */
    public static function getRecordController(ServiceManager $sm)
    {
        return new RecordController(
            $sm->getServiceLocator()->get('VuFind\Config')->get('config')
        );
    }

    /**
     * Construct the MetaLib RecordController.
     *
     * @param ServiceManager $sm Service manager.
     *
     * @return RecordController
     */
    public static function getMetaLibrecordController(ServiceManager $sm)
    {
        return new MetaLibrecordController(
            $sm->getServiceLocator()->get('VuFind\Config')->get('config')
        );
    }
}
