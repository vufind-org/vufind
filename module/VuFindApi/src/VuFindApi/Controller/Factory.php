<?php
/**
 * Factory for controllers.
 *
 * PHP version 5
 *
 * Copyright (C) The National Library of Finland 2016.
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
 * @category VuFind
 * @package  Controller
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:controllers Wiki
 */
namespace VuFindApi\Controller;
use VuFindApi\Formatter\FacetFormatter;
use VuFindApi\Formatter\RecordFormatter;
use Zend\ServiceManager\ServiceManager;

/**
 * Factory for controllers.
 *
 * @category VuFind
 * @package  Controller
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:controllers Wiki
 *
 * @codeCoverageIgnore
 */
class Factory
{
    /**
     * Construct the ApiController.
     *
     * @param ServiceManager $sm Service manager.
     *
     * @return ApiController
     */
    public static function getApiController(ServiceManager $sm)
    {
        $controller = new ApiController();
        $controller->addApi($sm->get('SearchApi'));
        return $controller;
    }

    /**
     * Construct the SearchApiController.
     *
     * @param ServiceManager $sm Service manager.
     *
     * @return SearchApiController
     */
    public static function getSearchApiController(ServiceManager $sm)
    {
        $recordFields = $sm->getServiceLocator()
            ->get('VuFind\YamlReader')->get('SearchApiRecordFields.yaml');
        $helperManager = $sm->getServiceLocator()->get('ViewHelperManager');
        $rf = new RecordFormatter($recordFields, $helperManager);
        $controller = new SearchApiController($rf, new FacetFormatter());
        return $controller;
    }
}
