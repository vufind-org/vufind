<?php
/**
 * Factory for Root view helpers.
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
 * @package  View_Helpers
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:developer_manual Wiki
 */
namespace Finna\View\Helper\Root;
use Zend\ServiceManager\ServiceManager;

/**
 * Factory for Root view helpers.
 *
 * @category VuFind2
 * @package  View_Helpers
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:developer_manual Wiki
 * @codeCoverageIgnore
 */
class Factory extends \VuFind\View\Helper\Root\Factory
{
    /**
     * Construct the Record helper.
     *
     * @param ServiceManager $sm Service manager.
     *
     * @return Record
     */
    public static function getRecord(ServiceManager $sm)
    {
        return new Record(
            $sm->getServiceLocator()->get('VuFind\Config')->get('config')
        );
    }

    /**
     * Construct the Header view helper.
     *
     * @param ServiceManager $sm Service manager.
     *
     * @return Header
     */
    public static function getHeader(ServiceManager $sm)
    {
        $locator = $sm->getServiceLocator();
        $menuConfig = $locator->get('VuFind\Config')->get('navibar');

        return new Header($menuConfig);
    }

    /**
     * Construct content page view helper.
     *
     * @param ServiceManager $sm Service manager.
     *
     * @return Header
     */
    public static function getContent(ServiceManager $sm)
    {
        return new Content();
    }

    /**
     * Construct record image view helper.
     *
     * @param ServiceManager $sm Service manager.
     *
     * @return Header
     */
    public static function getRecordImage(ServiceManager $sm)
    {
        return new RecordImage();
    }

}
