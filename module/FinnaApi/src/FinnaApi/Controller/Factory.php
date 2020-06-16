<?php
/**
 * Factory for controllers.
 *
 * PHP version 7
 *
 * Copyright (C) The National Library of Finland 2017.
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
 * @package  Controller
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:controllers Wiki
 */
namespace FinnaApi\Controller;

use FinnaApi\Formatter\RecordFormatter;
use Laminas\ServiceManager\ServiceManager;
use VuFindApi\Controller\SearchApiController;
use VuFindApi\Formatter\FacetFormatter;

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
     * Construct the AdminApiController.
     *
     * @param ServiceManager $sm Service manager.
     *
     * @return AdminApiController
     */
    public static function getAdminApiController(ServiceManager $sm)
    {
        return new AdminApiController($sm);
    }

    /**
     * Construct the ApiController.
     *
     * @param ServiceManager $sm Service manager.
     *
     * @return ApiController
     */
    public static function getApiController(ServiceManager $sm)
    {
        $controller = new \VuFindApi\Controller\ApiController($sm);
        $controllerManager = $sm->get('ControllerManager');
        $controller->addApi($controllerManager->get('AdminApi'));
        $controller->addApi($controllerManager->get('SearchApi'));
        $controller->addApi($controllerManager->get('AuthApi'));
        return $controller;
    }

    /**
     * Construct the AuthApiController.
     *
     * @param ServiceManager $sm Service manager.
     *
     * @return AuthApiController
     */
    public static function getAuthApiController(ServiceManager $sm)
    {
        $result = new AuthApiController($sm);
        $result->setLogger($sm->get(\VuFind\Log\Logger::class));
        return $result;
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
        $recordFields = $sm->get('VuFind\YamlReader')
            ->get('SearchApiRecordFields.yaml');
        $helperManager = $sm->get('ViewHelperManager');
        $translator = $sm->get(\Laminas\Mvc\I18n\Translator::class);
        $rf = new RecordFormatter($recordFields, $helperManager, $translator);
        $controller = new SearchApiController($sm, $rf, new FacetFormatter());
        return $controller;
    }
}
