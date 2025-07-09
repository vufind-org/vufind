<?php

/**
 * Finna Module Configuration
 *
 * PHP version 8
 *
 * Copyright (C) The National Library of Finland 2014-2025.
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
 * @package  Finna
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://github.com/KDK-Alli/NDL-VuFind2   NDL-VuFind2
 */

namespace Finna\Module\Configuration;

$config = [
    'router' => [
        'routes' => [
            'browse-database' => [
                'type' => 'Laminas\Router\Http\Literal',
                'options' => [
                    'route'    => '/Browse/Database',
                    'defaults' => [
                        'controller' => 'BrowseSearch',
                        'action'     => 'Database',
                    ],
                ],
            ],
            'browse-journal' => [
                'type' => 'Laminas\Router\Http\Literal',
                'options' => [
                    'route'    => '/Browse/Journal',
                    'defaults' => [
                        'controller' => 'BrowseSearch',
                        'action'     => 'Journal',
                    ],
                ],
            ],
            'comments-inappropriate' => [
                'type'    => 'Laminas\Router\Http\Segment',
                'options' => [
                    'route'    => '/Comments/Inappropriate/[:id]',
                    'constraints' => [
                        'controller' => '[a-zA-Z][a-zA-Z0-9_-]*',
                        'action'     => '[a-zA-Z][a-zA-Z0-9_-]*',
                    ],
                    'defaults' => [
                        'controller' => 'Comments',
                        'action'     => 'Inappropriate',
                    ],
                ],
            ],
            'feed-content-page' => [
                'type'    => 'Laminas\Router\Http\Segment',
                'options' => [
                    'route'    => '/FeedContent[/:page][/:element]',
                    'defaults' => [
                        'controller' => 'FeedContent',
                        'action'     => 'Content',
                    ],
                ],
            ],
            'feed-image' => [
                'type'    => 'Laminas\Router\Http\Segment',
                'options' => [
                    'route'    => '/FeedContent/Image/:page',
                    'defaults' => [
                        'controller' => 'FeedContent',
                        'action'     => 'Image',
                    ],
                ],
            ],
            'linked-events-image' => [
                'type'    => 'Laminas\Router\Http\Literal',
                'options' => [
                    'route'    => '/FeedContent/EventImage',
                    'defaults' => [
                        'controller' => 'FeedContent',
                        'action'     => 'EventImage',
                    ],
                ],
            ],
            'linked-events-content' => [
                'type'    => 'Laminas\Router\Http\Segment',
                'options' => [
                    'route'    => '/FeedContent/LinkedEvents[/:id]',
                    'constraints' => [
                        'controller' => '[a-zA-Z][a-zA-Z0-9_-]*',
                        'action'     => '[a-zA-Z][a-zA-Z0-9_-]*',
                    ],
                    'defaults' => [
                        'controller' => 'FeedContent',
                        'action'     => 'LinkedEvents',
                    ],
                ],
            ],
            'list-save' => [
                'type'    => 'Laminas\Router\Http\Segment',
                'options' => [
                    'route'    => '/List/[:id]/save',
                    'constraints' => [
                        'controller' => '[a-zA-Z][a-zA-Z0-9_-]*',
                        'action'     => '[a-zA-Z][a-zA-Z0-9_-]*',
                    ],
                    'defaults' => [
                        'controller' => 'ListPage',
                        'action'     => 'Save',
                    ],
                ],
            ],
            'list-page' => [
                'type'    => 'Laminas\Router\Http\Segment',
                'options' => [
                    'route'    => '/List[/:lid]',
                    'constraints' => [
                        'lid'     => '[0-9]+',
                    ],
                    'defaults' => [
                        'controller' => 'ListPage',
                        'action'     => 'List',
                    ],
                ],
            ],
            'myresearch-changemessagingsettings' => [
                'type' => 'Laminas\Router\Http\Literal',
                'options' => [
                    'route'    => '/MyResearch/ChangeMessagingSettings',
                    'defaults' => [
                        'controller' => 'MyResearch',
                        'action'     => 'ChangeMessagingSettings',
                    ],
                ],
            ],
            'myresearch-changeprofileaddress' => [
                'type' => 'Laminas\Router\Http\Literal',
                'options' => [
                    'route'    => '/MyResearch/ChangeProfileAddress',
                    'defaults' => [
                        'controller' => 'MyResearch',
                        'action'     => 'ChangeProfileAddress',
                    ],
                ],
            ],
            'myresearch-unsubscribe' => [
                'type' => 'Laminas\Router\Http\Literal',
                'options' => [
                    'route'    => '/MyResearch/Unsubscribe',
                    'defaults' => [
                        'controller' => 'MyResearch',
                        'action'     => 'Unsubscribe',
                    ],
                ],
            ],
            'myresearch-export' => [
                'type' => 'Laminas\Router\Http\Literal',
                'options' => [
                    'route'    => '/MyResearch/Export',
                    'defaults' => [
                        'controller' => 'MyResearch',
                        'action'     => 'Export',
                    ],
                ],
            ],
            'myresearch-import' => [
                'type' => 'Laminas\Router\Http\Literal',
                'options' => [
                    'route'    => '/MyResearch/Import',
                    'defaults' => [
                        'controller' => 'MyResearch',
                        'action'     => 'Import',
                    ],
                ],
            ],
            'organisation-info-image' => [
                'type'    => 'Laminas\Router\Http\Literal',
                'options' => [
                    'route'    => '/OrganisationInfo/Image',
                    'defaults' => [
                        'controller' => 'OrganisationInfo',
                        'action'     => 'Image',
                    ],
                ],
            ],
            'recordpreview' => [
                'type' => 'Laminas\Router\Http\Literal',
                'options' => [
                    'route'    => '/RecordPreview',
                    'defaults' => [
                        'controller' => 'RecordPreview',
                        'action'     => 'PreviewForm',
                    ],
                ],
            ],
            'recordpreview-validate' => [
                'type' => 'Laminas\Router\Http\Literal',
                'options' => [
                    'route'    => '/RecordPreview/Validate',
                    'defaults' => [
                        'controller' => 'RecordPreview',
                        'action'     => 'Validate',
                    ],
                ],
            ],
            'recordpreview-validationreport' => [
                'type' => 'Laminas\Router\Http\Literal',
                'options' => [
                    'route'    => '/RecordPreview/ValidationReport',
                    'defaults' => [
                        'controller' => 'RecordPreview',
                        'action'     => 'ValidationReport',
                    ],
                ],
            ],
            'cover-download' => [
                'type'    => 'Laminas\Router\Http\Literal',
                'options' => [
                    'route'    => '/Cover/Download',
                    'defaults' => [
                        'controller' => 'Record',
                        'action'     => 'DownloadFile',
                    ],
                ],
            ],
            'robots-txt' => [
                'type' => 'Laminas\Router\Http\Literal',
                'options' => [
                    'route'    => '/robots.txt',
                    'defaults' => [
                        'controller' => 'Robots',
                        'action'     => 'get',
                    ],
                ],
            ],
        ],
    ],
    'route_manager' => [
        'aliases' => [
            'Laminas\Mvc\Router\Http\Segment' => 'Laminas\Router\Http\Segment',
        ],
    ],
    'controllers' => [
        'factories' => [
            'Finna\Controller\AjaxController' => 'VuFind\Controller\AjaxControllerFactory',
            'Finna\Controller\AuthorityController' => 'VuFind\Controller\AbstractBaseFactory',
            'Finna\Controller\AuthorityRecordController' => 'Finna\Controller\AbstractBaseWithConfigFactory',
            'Finna\Controller\BarcodeController' => 'VuFind\Controller\AbstractBaseFactory',
            'Finna\Controller\BazaarController' => 'VuFind\Controller\AbstractBaseFactory',
            'Finna\Controller\BrowseSearchController' => 'VuFind\Controller\AbstractBaseFactory',
            'Finna\Controller\CartController' => 'VuFind\Controller\CartControllerFactory',
            'Finna\Controller\CollectionController' => 'VuFind\Controller\AbstractBaseWithConfigFactory',
            'Finna\Controller\CombinedController' => 'VuFind\Controller\AbstractBaseFactory',
            'Finna\Controller\CommentsController' => 'VuFind\Controller\AbstractBaseFactory',
            'Finna\Controller\ContentController' => 'VuFind\Controller\AbstractBaseFactory',
            'Finna\Controller\CoverController' => 'Finna\Controller\CoverControllerFactory',
            'Finna\Controller\EdsController' => 'Finna\Controller\AbstractBaseFactory',
            'Finna\Controller\EdsrecordController' => 'Finna\Controller\AbstractBaseFactory',
            'Finna\Controller\ErrorController' => 'VuFind\Controller\AbstractBaseFactory',
            'Finna\Controller\ExternalAuthController' => 'VuFind\Controller\AbstractBaseFactory',
            'Finna\Controller\FeedbackController' => 'VuFind\Controller\AbstractBaseFactory',
            'Finna\Controller\FeedContentController' => 'VuFind\Controller\AbstractBaseFactory',
            'Finna\Controller\HoldsController' => 'VuFind\Controller\HoldsControllerFactory',
            'Finna\Controller\LibraryCardsController' => 'Finna\Controller\LibraryCardsControllerFactory',
            'Finna\Controller\L1Controller' => 'VuFind\Controller\AbstractBaseFactory',
            'Finna\Controller\L1recordController' => 'Finna\Controller\AbstractBaseWithConfigFactory',
            'Finna\Controller\ListController' => 'Finna\Controller\ListControllerFactory',
            'Finna\Controller\LocationServiceController' => 'VuFind\Controller\AbstractBaseFactory',
            'Finna\Controller\MetaLibController' => 'VuFind\Controller\AbstractBaseFactory',
            'Finna\Controller\MetalibRecordController' => 'VuFind\Controller\AbstractBaseFactory',
            'Finna\Controller\MyResearchController' => 'VuFind\Controller\MyResearchControllerFactory',
            \Finna\Controller\OAuth2Controller::class => \VuFind\Controller\OAuth2ControllerFactory::class,
            'Finna\Controller\OrganisationInfoController' => 'VuFind\Controller\AbstractBaseFactory',
            'Finna\Controller\PCIController' => 'VuFind\Controller\AbstractBaseFactory',
            \Finna\Controller\RecordPreviewController::class => \VuFind\Controller\AbstractBaseFactory::class,
            'Finna\Controller\PrimoController' => 'VuFind\Controller\AbstractBaseFactory',
            'Finna\Controller\PrimorecordController' => 'Finna\Controller\AbstractBaseFactory',
            'Finna\Controller\RecordController' => 'Finna\Controller\AbstractBaseWithConfigFactory',
            \Finna\Controller\ReservationListController::class => \Finna\Controller\ReservationListControllerFactory::class,
            'Finna\Controller\RobotsController' => 'VuFind\Controller\AbstractBaseWithConfigFactory',
            'Finna\Controller\SearchController' => 'VuFind\Controller\AbstractBaseFactory',
        ],
        'aliases' => [
            'AuthorityRecord' => 'Finna\Controller\AuthorityRecordController',
            'Barcode' => 'Finna\Controller\BarcodeController',
            'barcode' => 'Finna\Controller\BarcodeController',
            'Bazaar' => 'Finna\Controller\BazaarController',
            'bazaar' => 'Finna\Controller\BazaarController',
            'BrowseSearch' => 'Finna\Controller\BrowseSearchController',
            // Alias for the browse record route (that must not clash with normal
            // record route for getMatchedRouteName to return correct value):
            'BrowseRecord' => 'Record',
            'Comments' => 'Finna\Controller\CommentsController',
            'comments' => 'Finna\Controller\CommentsController',
            'FeedContent' => 'Finna\Controller\FeedContentController',
            'feedcontent' => 'Finna\Controller\FeedContentController',
            'L1' => 'Finna\Controller\L1Controller',
            'l1' => 'Finna\Controller\L1Controller',
            'L1Record' => 'Finna\Controller\L1recordController',
            'l1record' => 'Finna\Controller\L1recordController',
            'ListPage' => 'Finna\Controller\ListController',
            'listpage' => 'Finna\Controller\ListController',
            'LocationService' => 'Finna\Controller\LocationServiceController',
            'locationservice' => 'Finna\Controller\LocationServiceController',
            'MetaLib' => 'Finna\Controller\MetaLibController',
            'metalib' => 'Finna\Controller\MetaLibController',
            'MetaLibRecord' => 'Finna\Controller\MetaLibrecordController',
            'metalibrecord' => 'Finna\Controller\MetaLibrecordController',
            'OrganisationInfo' => 'Finna\Controller\OrganisationInfoController',
            'RecordPreview' => \Finna\Controller\RecordPreviewController::class,
            'recordPreview' => \Finna\Controller\RecordPreviewController::class,
            'ReservationList' => \Finna\Controller\ReservationListController::class,
            'reservationList' => \Finna\Controller\ReservationListController::class,
            'reservationlist' => \Finna\Controller\ReservationListController::class,
            'Robots' => 'Finna\Controller\RobotsController',

            // Overrides:
            'VuFind\Controller\AuthorityController' => 'Finna\Controller\AuthorityController',
            'VuFind\Controller\AjaxController' => 'Finna\Controller\AjaxController',
            'VuFind\Controller\CartController' => 'Finna\Controller\CartController',
            'VuFind\Controller\CombinedController' => 'Finna\Controller\CombinedController',
            'VuFind\Controller\CollectionController' => 'Finna\Controller\CollectionController',
            'VuFind\Controller\ContentController' => 'Finna\Controller\ContentController',
            'VuFind\Controller\CoverController' => 'Finna\Controller\CoverController',
            'VuFind\Controller\EdsController' => 'Finna\Controller\EdsController',
            'VuFind\Controller\EdsrecordController' => 'Finna\Controller\EdsrecordController',
            'VuFind\Controller\ErrorController' => 'Finna\Controller\ErrorController',
            'VuFind\Controller\ExternalAuthController' => 'Finna\Controller\ExternalAuthController',
            'VuFind\Controller\FeedbackController' => 'Finna\Controller\FeedbackController',
            'VuFind\Controller\HoldsController' => 'Finna\Controller\HoldsController',
            'VuFind\Controller\LibraryCardsController' => 'Finna\Controller\LibraryCardsController',
            'VuFind\Controller\MyResearchController' => 'Finna\Controller\MyResearchController',
            \VuFind\Controller\OAuth2Controller::class => \Finna\Controller\OAuth2Controller::class,
            'VuFind\Controller\PrimoController' => 'Finna\Controller\PrimoController',
            'VuFind\Controller\PrimorecordController' => 'Finna\Controller\PrimorecordController',
            'VuFind\Controller\RecordController' => 'Finna\Controller\RecordController',
            'VuFind\Controller\SearchController' => 'Finna\Controller\SearchController',

            // Legacy:
            'PCI' => 'Finna\Controller\PrimoController',
            'pci' => 'Finna\Controller\PrimoController',
        ],
    ],
    'controller_plugins' => [
        'factories' => [
            'VuFind\Controller\Plugin\Captcha' => 'Finna\Controller\Plugin\CaptchaFactory',
            \Finna\Controller\Plugin\Preview::class => \Finna\Controller\Plugin\PreviewFactory::class,
        ],
        'aliases' => [
            'preview' => \Finna\Controller\Plugin\Preview::class,
        ],
    ],
    'service_manager' => [
        'allow_override' => true,
        'factories' => [
            'Finna\AppBootstrapListener' => 'Laminas\ServiceManager\Factory\InvokableFactory',
            'Finna\Autocomplete\PluginManager' => 'VuFind\ServiceManager\AbstractPluginManagerFactory',
            'Finna\Auth\ILSAuthenticator' => 'VuFind\Auth\ILSAuthenticatorFactory',
            'Finna\Auth\Manager' => 'VuFind\Auth\ManagerFactory',
            \Finna\Cart::class => \VuFind\CartFactory::class,
            'Finna\Cache\Manager' => 'VuFind\Cache\ManagerFactory',
            'Finna\Config\SearchSpecsReader' => 'VuFind\Config\YamlReaderFactory',
            'Finna\Config\YamlReader' => 'VuFind\Config\YamlReaderFactory',
            'Finna\Connection\Finto' => 'Finna\Connection\FintoFactory',
            'Finna\Content\Description\PluginManager' => 'VuFind\ServiceManager\AbstractPluginManagerFactory',
            'Finna\Cookie\RecommendationMemory' => 'Finna\Cookie\RecommendationMemoryFactory',
            'Finna\Cover\Loader' => 'VuFind\Cover\LoaderFactory',
            'Finna\Crypt\SecretCalculator' => 'VuFind\Crypt\SecretCalculatorFactory',
            'Finna\Export' => 'VuFind\ExportFactory',
            'Finna\Favorites\FavoritesService' => 'Finna\Favorites\FavoritesServiceFactory',
            'Finna\File\Loader' => 'Finna\File\LoaderFactory',
            'Finna\Feed\Feed' => 'Finna\Feed\FeedFactory',
            'Finna\Feed\LinkedEvents' => 'Finna\Feed\LinkedEventsFactory',
            'Finna\Form\Form' => 'Finna\Form\FormFactory',
            'Finna\ILS\Connection' => 'VuFind\ILS\ConnectionFactory',
            'Finna\LocationService\LocationService' => 'Finna\LocationService\LocationServiceFactory',
            'Finna\Mailer\Mailer' => 'VuFind\Mailer\Factory',
            'Finna\OAI\Server' => 'VuFind\OAI\ServerFactory',
            'Finna\OnlinePayment\Handler\PluginManager' => 'VuFind\ServiceManager\AbstractPluginManagerFactory',
            'Finna\OnlinePayment\OnlinePayment' => 'Finna\OnlinePayment\OnlinePaymentFactory',
            'Finna\OnlinePayment\Receipt' => 'Finna\OnlinePayment\ReceiptFactory',
            'Finna\OnlinePayment\Session' => 'Finna\OnlinePayment\OnlinePaymentSessionFactory',
            'Finna\OrganisationInfo\OrganisationInfo' => 'Finna\OrganisationInfo\OrganisationInfoFactory',
            'Finna\OrganisationInfo\Provider\Kirkanta' => 'Finna\OrganisationInfo\Provider\AbstractProviderFactory',
            'Finna\OrganisationInfo\Provider\MuseotFi' => 'Finna\OrganisationInfo\Provider\AbstractProviderFactory',
            'Finna\Record\Loader' => 'Finna\Record\LoaderFactory',
            'Finna\RecordDriver\PluginManager' => 'VuFind\ServiceManager\AbstractPluginManagerFactory',
            'Finna\RecordTab\TabManager' => 'VuFind\RecordTab\TabManagerFactory',
            'Finna\Role\PermissionManager' => 'VuFind\Role\PermissionManagerFactory',
            'Finna\Search\Solr\AuthorityHelper' => 'Finna\Search\Solr\AuthorityHelperFactory',
            'Finna\Search\Solr\HierarchicalFacetHelper' => 'VuFind\Search\Solr\HierarchicalFacetHelperFactory',
            'Finna\Service\BazaarService' => 'Finna\Service\BazaarServiceFactory',
            'Finna\Service\RecordFieldMarkdown' => 'Laminas\ServiceManager\Factory\InvokableFactory',
            'Finna\Service\UserPreferenceService' => 'Finna\Service\UserPreferenceServiceFactory',
            'Finna\Statistics\Driver\PluginManager' => 'VuFind\ServiceManager\AbstractPluginManagerFactory',
            'Finna\Statistics\EventHandler' => 'Finna\Statistics\EventHandlerFactory',
            \Finna\ReservationList\Form\Form::class => \Finna\Form\FormFactory::class,
            \Finna\ReservationList\ReservationListService::class => \Finna\ReservationList\ReservationListServiceFactory::class,
            \Finna\ReservationList\Handler\PluginManager::class => \VuFind\ServiceManager\AbstractPluginManagerFactory::class,
            \Finna\Util\CachingXmlEntityLoader::class => \Finna\Util\CachingXmlEntityLoaderFactory::class,
            'Finna\View\CustomElement\PluginManager' => 'VuFind\ServiceManager\AbstractPluginManagerFactory',
            'Finna\Video\Handler\PluginManager' => 'VuFind\ServiceManager\AbstractPluginManagerFactory',
            'Finna\Video\Video' => 'Finna\Video\VideoFactory',
            'Finna\Wayfinder\WayfinderService' => 'Finna\Wayfinder\WayfinderServiceFactory',
            'NatLibFi\FinnaCodeSets\FinnaCodeSets' => 'Finna\RecordDriver\CodeSetsFactory',

            // Factory overrides for non-Finna classes:
            'VuFind\Config\PathResolver' => 'Finna\Config\PathResolverFactory',

            \Laminas\Session\SessionManager::class => \Finna\Session\ManagerFactory::class,

            'League\CommonMark\ConverterInterface' => 'Finna\Service\MarkdownFactory',
        ],
        'aliases' => [
            'VuFind\Autocomplete\PluginManager' => 'Finna\Autocomplete\PluginManager',
            'VuFind\Auth\Manager' => 'Finna\Auth\Manager',
            'VuFind\Auth\ILSAuthenticator' => 'Finna\Auth\ILSAuthenticator',
            'VuFind\Cache\Manager' => 'Finna\Cache\Manager',
            \VuFind\Cart::class => \Finna\Cart::class,
            'VuFind\Config\SearchSpecsReader' => 'Finna\Config\SearchSpecsReader',
            'VuFind\Config\YamlReader' => 'Finna\Config\YamlReader',
            'VuFind\Cover\Loader' => 'Finna\Cover\Loader',
            'VuFind\Crypt\SecretCalculator' => 'Finna\Crypt\SecretCalculator',
            'VuFind\Export' => 'Finna\Export',
            'VuFind\Favorites\FavoritesService' => 'Finna\Favorites\FavoritesService',
            'VuFind\Form\Form' => 'Finna\Form\Form',
            'VuFind\ILS\Connection' => 'Finna\ILS\Connection',
            'VuFind\Mailer\Mailer' => 'Finna\Mailer\Mailer',
            'VuFind\OAI\Server' => 'Finna\OAI\Server',
            'VuFind\Record\Loader' => 'Finna\Record\Loader',
            'VuFind\RecordTab\TabManager' => 'Finna\RecordTab\TabManager',
            'VuFind\Role\PermissionManager' => 'Finna\Role\PermissionManager',
            'VuFind\Search\Solr\HierarchicalFacetHelper' => 'Finna\Search\Solr\HierarchicalFacetHelper',

            'Wayfinder' => 'Finna\Wayfinder\WayfinderService',
        ],
    ],
    'listeners' => [
        \Finna\AppBootstrapListener::class,
    ],
    // This section contains all VuFind-specific settings (i.e. configurations
    // unrelated to specific framework components).
    'vufind' => [
        'plugin_managers' => [
            'ajaxhandler' => [
                'factories' => [
                    'Finna\AjaxHandler\AddToList' =>
                        'Finna\AjaxHandler\AddToListFactory',
                    'Finna\AjaxHandler\BazaarDestroySession' =>
                        'Finna\AjaxHandler\BazaarDestroySessionFactory',
                    'Finna\AjaxHandler\CheckRequestsAreValid' =>
                        'VuFind\AjaxHandler\AbstractIlsAndUserActionFactory',
                    'Finna\AjaxHandler\CommentRecord' =>
                        'Finna\AjaxHandler\CommentRecordFactory',
                    'Finna\AjaxHandler\EditList' =>
                        'Finna\AjaxHandler\EditListFactory',
                    'Finna\AjaxHandler\EditListResource' =>
                        'Finna\AjaxHandler\EditListResourceFactory',
                    'Finna\AjaxHandler\GetAccountNotifications' =>
                        'VuFind\AjaxHandler\AbstractIlsAndUserActionFactory',
                    'Finna\AjaxHandler\GetAuthorityInfo' =>
                        'Finna\AjaxHandler\GetAuthorityInfoFactory',
                    'Finna\AjaxHandler\GetAuthorityFullInfo' =>
                        'Finna\AjaxHandler\GetAuthorityFullInfoFactory',
                    'Finna\AjaxHandler\GetACSuggestions' =>
                        'VuFind\AjaxHandler\GetACSuggestionsFactory',
                    'Finna\AjaxHandler\GetContentFeed' =>
                        'Finna\AjaxHandler\GetContentFeedFactory',
                    'Finna\AjaxHandler\GetDateRangeVisual' =>
                        'Finna\AjaxHandler\GetDateRangeVisualFactory',
                    'Finna\AjaxHandler\GetDescription' =>
                        'Finna\AjaxHandler\GetDescriptionFactory',
                    'Finna\AjaxHandler\GetModel' =>
                        'Finna\AjaxHandler\GetModelFactory',
                    'Finna\AjaxHandler\GetEncapsulatedRecords' =>
                        'Finna\AjaxHandler\GetEncapsulatedRecordsFactory',
                    'Finna\AjaxHandler\GetFeed' =>
                        'Finna\AjaxHandler\GetFeedFactory',
                    'Finna\AjaxHandler\GetFieldInfo' =>
                        'Finna\AjaxHandler\GetFieldInfoFactory',
                    'Finna\AjaxHandler\GetHoldingsDetails' =>
                        'Finna\AjaxHandler\GetHoldingsDetailsFactory',
                    'Finna\AjaxHandler\GetImageInformation' =>
                        'Finna\AjaxHandler\GetImageInformationFactory',
                    'Finna\AjaxHandler\GetLinkedEvents' =>
                        'Finna\AjaxHandler\GetLinkedEventsFactory',
                    'Finna\AjaxHandler\GetItemStatuses' =>
                        'VuFind\AjaxHandler\GetItemStatusesFactory',
                    'Finna\AjaxHandler\GetOrganisationInfo' =>
                        'Finna\AjaxHandler\GetOrganisationInfoFactory',
                    'Finna\AjaxHandler\GetOrganisationPageFeed' =>
                        'Finna\AjaxHandler\GetOrganisationPageFeedFactory',
                    'Finna\AjaxHandler\GetPiwikPopularSearches' =>
                        'Finna\AjaxHandler\GetPiwikPopularSearchesFactory',
                    'Finna\AjaxHandler\GetRecordData' =>
                        'Finna\AjaxHandler\GetRecordDataFactory',
                    'Finna\AjaxHandler\GetRecordDriverRelatedRecords' =>
                        'Finna\AjaxHandler\GetRecordDriverRelatedRecordsFactory',
                    'Finna\AjaxHandler\GetRecordInfoByAuthority' =>
                        'Finna\AjaxHandler\GetRecordInfoByAuthorityFactory',
                    'Finna\AjaxHandler\GetRequestGroupPickupLocations' =>
                        'VuFind\AjaxHandler\AbstractIlsAndUserActionFactory',
                    'Finna\AjaxHandler\GetSearchResults' => 'VuFind\AjaxHandler\GetSearchResultsFactory',
                    'Finna\AjaxHandler\GetSearchTabsRecommendations' =>
                        'Finna\AjaxHandler\GetSearchTabsRecommendationsFactory',
                    'Finna\AjaxHandler\GetSimilarRecords' =>
                        'Finna\AjaxHandler\GetSimilarRecordsFactory',
                    'Finna\AjaxHandler\GetUserList' =>
                        'Finna\AjaxHandler\GetUserListFactory',
                    'Finna\AjaxHandler\GetUserLists' =>
                        'Finna\AjaxHandler\GetUserListsFactory',
                    'Finna\AjaxHandler\GetCheckoutHistory' =>
                        'Finna\AjaxHandler\GetCheckoutHistoryFactory',
                    'Finna\AjaxHandler\GetCheckoutHistoryFile' =>
                        'Finna\AjaxHandler\GetCheckoutHistoryFactory',
                    'Finna\AjaxHandler\ReservationList' =>
                        'Finna\AjaxHandler\ReservationListFactory',
                    'Finna\AjaxHandler\ImportFavorites' =>
                        'Finna\AjaxHandler\ImportFavoritesFactory',
                    'Finna\AjaxHandler\OnlinePaymentNotify' =>
                        'Finna\AjaxHandler\AbstractOnlinePaymentActionFactory',
                    'Finna\AjaxHandler\RegisterOnlinePayment' =>
                        'Finna\AjaxHandler\AbstractOnlinePaymentActionFactory',
                    'Finna\AjaxHandler\SystemStatus' =>
                        'VuFind\AjaxHandler\SystemStatusFactory',
                    'Finna\AjaxHandler\WayfinderPlacementLinkLookup' =>
                        'Finna\AjaxHandler\WayfinderPlacementLinkLookupFactory',
                ],
                'aliases' => [
                    'addToList' => 'Finna\AjaxHandler\AddToList',
                    'bazaarDestroySession' => 'Finna\AjaxHandler\BazaarDestroySession',
                    'checkRequestsAreValid' => 'Finna\AjaxHandler\CheckRequestsAreValid',
                    'editList' => 'Finna\AjaxHandler\EditList',
                    'editListResource' => 'Finna\AjaxHandler\EditListResource',
                    'getAccountNotifications' => 'Finna\AjaxHandler\GetAccountNotifications',
                    'getAuthorityInfo' => 'Finna\AjaxHandler\GetAuthorityInfo',
                    'getAuthorityFullInfo' => 'Finna\AjaxHandler\GetAuthorityFullInfo',
                    'getCheckoutHistory' => 'Finna\AjaxHandler\GetCheckoutHistory',
                    'getCheckoutHistoryFile' => 'Finna\AjaxHandler\GetCheckoutHistoryFile',
                    'getContentFeed' => 'Finna\AjaxHandler\GetContentFeed',
                    'getDescription' => 'Finna\AjaxHandler\GetDescription',
                    'getModel' => 'Finna\AjaxHandler\GetModel',
                    'getDateRangeVisual' => 'Finna\AjaxHandler\GetDateRangeVisual',
                    'getEncapsulatedRecords' => 'Finna\AjaxHandler\GetEncapsulatedRecords',
                    'getFeed' => 'Finna\AjaxHandler\GetFeed',
                    'getFieldInfo' => 'Finna\AjaxHandler\GetFieldInfo',
                    'getHoldingsDetails' => 'Finna\AjaxHandler\GetHoldingsDetails',
                    'getImageInformation' => 'Finna\AjaxHandler\GetImageInformation',
                    'getLinkedEvents' => 'Finna\AjaxHandler\GetLinkedEvents',
                    'getOrganisationPageFeed' => 'Finna\AjaxHandler\GetOrganisationPageFeed',
                    'getMyLists' => 'Finna\AjaxHandler\GetUserLists',
                    'getOrganisationInfo' => 'Finna\AjaxHandler\GetOrganisationInfo',
                    'getPiwikPopularSearches' => 'Finna\AjaxHandler\GetPiwikPopularSearches',
                    'getRecordData' => 'Finna\AjaxHandler\GetRecordData',
                    'getRecordDriverRelatedRecords' => 'Finna\AjaxHandler\GetRecordDriverRelatedRecords',
                    'getRecordInfoByAuthority' => 'Finna\AjaxHandler\GetRecordInfoByAuthority',
                    'getSearchTabsRecommendations' => 'Finna\AjaxHandler\GetSearchTabsRecommendations',
                    'getSimilarRecords' => 'Finna\AjaxHandler\GetSimilarRecords',
                    'getUserList' => 'Finna\AjaxHandler\GetUserList',
                    'reservationList' => 'Finna\AjaxHandler\ReservationList',
                    'importFavorites' => 'Finna\AjaxHandler\ImportFavorites',
                    'onlinePaymentNotify' => 'Finna\AjaxHandler\OnlinePaymentNotify',
                    'registerOnlinePayment' => 'Finna\AjaxHandler\RegisterOnlinePayment',
                    'wayfinderPlacementLinkLookup' => 'Finna\AjaxHandler\WayfinderPlacementLinkLookup',

                    // Overrides:
                    'VuFind\AjaxHandler\CommentRecord' => 'Finna\AjaxHandler\CommentRecord',
                    'VuFind\AjaxHandler\GetACSuggestions' => 'Finna\AjaxHandler\GetACSuggestions',
                    'VuFind\AjaxHandler\GetItemStatuses' => 'Finna\AjaxHandler\GetItemStatuses',
                    'VuFind\AjaxHandler\GetRequestGroupPickupLocations' => 'Finna\AjaxHandler\GetRequestGroupPickupLocations',
                    'VuFind\AjaxHandler\GetSearchResults' => 'Finna\AjaxHandler\GetSearchResults',
                    'VuFind\AjaxHandler\SystemStatus' => 'Finna\AjaxHandler\SystemStatus',
                ],
            ],
            'auth' => [
                'factories' => [
                    'Finna\Auth\ILS' => 'VuFind\Auth\ILSFactory',
                    'Finna\Auth\MultiILS' => 'VuFind\Auth\ILSFactory',
                    'Finna\Auth\Shibboleth' => 'Finna\Auth\ShibbolethFactory',
                ],
                'aliases' => [
                    'VuFind\Auth\ILS' => 'Finna\Auth\ILS',
                    'VuFind\Auth\MultiILS' => 'Finna\Auth\MultiILS',
                    'VuFind\Auth\Shibboleth' => 'Finna\Auth\Shibboleth',
                ],
            ],
            'autocomplete' => [
                'factories' => [
                    'Finna\Autocomplete\Solr' => 'Finna\Autocomplete\SolrFactory',
                    \Finna\Autocomplete\SolrAuth::class => \Finna\Autocomplete\SolrAuthFactory::class,
                    'Finna\Autocomplete\L1' => 'Finna\Autocomplete\SolrFactory',
                ],
                'aliases' => [
                    'VuFind\Autocomplete\Solr' => 'Finna\Autocomplete\Solr',
                    \VuFind\Autocomplete\SolrAuth::class => \Finna\Autocomplete\SolrAuth::class,
                ],
            ],
            'content_description' => [],
            'db_row' => [
                'factories' => [
                    'Finna\Db\Row\Comments' => 'VuFind\Db\Row\RowGatewayFactory',
                    'Finna\Db\Row\CommentsInappropriate' => 'VuFind\Db\Row\RowGatewayFactory',
                    'Finna\Db\Row\CommentsRecord' => 'VuFind\Db\Row\RowGatewayFactory',
                    'Finna\Db\Row\DueDateReminder' => 'VuFind\Db\Row\RowGatewayFactory',
                    'Finna\Db\Row\Fee' => 'VuFind\Db\Row\RowGatewayFactory',
                    'Finna\Db\Row\FinnaCache' => 'VuFind\Db\Row\RowGatewayFactory',
                    'Finna\Db\Row\FinnaFeedback' => 'VuFind\Db\Row\RowGatewayFactory',
                    'Finna\Db\Row\FinnaPageViewStats' => 'VuFind\Db\Row\RowGatewayFactory',
                    'Finna\Db\Row\FinnaRecordStats' => 'VuFind\Db\Row\RowGatewayFactory',
                    'Finna\Db\Row\FinnaRecordStatsLog' => 'VuFind\Db\Row\RowGatewayFactory',
                    'Finna\Db\Row\FinnaRecordView' => 'VuFind\Db\Row\RowGatewayFactory',
                    'Finna\Db\Row\FinnaRecordViewInstView' => 'VuFind\Db\Row\RowGatewayFactory',
                    'Finna\Db\Row\FinnaRecordViewRecord' => 'VuFind\Db\Row\RowGatewayFactory',
                    'Finna\Db\Row\FinnaRecordViewRecordFormat' => 'VuFind\Db\Row\RowGatewayFactory',
                    'Finna\Db\Row\FinnaRecordViewRecordRights' => 'VuFind\Db\Row\RowGatewayFactory',
                    'Finna\Db\Row\FinnaSessionStats' => 'VuFind\Db\Row\RowGatewayFactory',
                    'Finna\Db\Row\PrivateUser' => 'Finna\Db\Row\UserFactory',
                    'Finna\Db\Row\Resource' => 'VuFind\Db\Row\RowGatewayFactory',
                    'Finna\Db\Row\Search' => 'VuFind\Db\Row\RowGatewayFactory',
                    'Finna\Db\Row\Session' => 'Finna\Db\Row\SessionFactory',
                    'Finna\Db\Row\Transaction' => 'VuFind\Db\Row\RowGatewayFactory',
                    'Finna\Db\Row\TransactionEventLog' => 'VuFind\Db\Row\RowGatewayFactory',
                    'Finna\Db\Row\User' => 'Finna\Db\Row\UserFactory',
                    'Finna\Db\Row\UserCard' => 'VuFind\Db\Row\RowGatewayFactory',
                    'Finna\Db\Row\UserList' => 'VuFind\Db\Row\UserListFactory',
                    \Finna\Db\Row\FinnaResourceList::class => \VuFind\Db\Row\RowGatewayFactory::class,
                    \Finna\Db\Row\FinnaResourceListResource::class => \VuFind\Db\Row\RowGatewayFactory::class,
                    'Finna\Db\Row\UserResource' => 'VuFind\Db\Row\RowGatewayFactory',
                ],
                'aliases' => [
                    'VuFind\Db\Row\Comments' => 'Finna\Db\Row\Comments',
                    'VuFind\Db\Row\PrivateUser' => 'Finna\Db\Row\PrivateUser',
                    'VuFind\Db\Row\Resource' => 'Finna\Db\Row\Resource',
                    'VuFind\Db\Row\Search' => 'Finna\Db\Row\Search',
                    'VuFind\Db\Row\Session' => 'Finna\Db\Row\Session',
                    'VuFind\Db\Row\Transaction' => 'Finna\Db\Row\Transaction',
                    'VuFind\Db\Row\User' => 'Finna\Db\Row\User',
                    'VuFind\Db\Row\UserCard' => 'Finna\Db\Row\UserCard',
                    'VuFind\Db\Row\UserList' => 'Finna\Db\Row\UserList',
                    'VuFind\Db\Row\UserResource' => 'Finna\Db\Row\UserResource',

                    'commentsinappropriate' => 'Finna\Db\Row\CommentsInappropriate',
                    'commentsrecord' => 'Finna\Db\Row\CommentsRecord',
                    'duedatereminder' => 'Finna\Db\Row\DueDateReminder',
                    'fee' => 'Finna\Db\Row\Fee',
                    'finnacache' => 'Finna\Db\Row\FinnaCache',
                    'transaction' => 'Finna\Db\Row\Transaction',
                ],
            ],
            'db_service' => [
                'factories' => [
                    \Finna\Db\Service\CommentsService::class => \VuFind\Db\Service\AbstractDbServiceFactory::class,
                    \Finna\Db\Service\AccessTokenService::class => \VuFind\Db\Service\AccessTokenServiceFactory::class,
                    \Finna\Db\Service\FinnaCacheService::class => \VuFind\Db\Service\AbstractDbServiceFactory::class,
                    \Finna\Db\Service\FinnaDueDateReminderService::class
                        => \VuFind\Db\Service\AbstractDbServiceFactory::class,
                    \Finna\Db\Service\FinnaFeeService::class => \VuFind\Db\Service\AbstractDbServiceFactory::class,
                    \Finna\Db\Service\FinnaFeedbackService::class => \VuFind\Db\Service\AbstractDbServiceFactory::class,
                    \Finna\Db\Service\FinnaStatisticsService::class
                        => \VuFind\Db\Service\AbstractDbServiceFactory::class,
                    \Finna\Db\Service\FinnaTransactionService::class
                        => \VuFind\Db\Service\AbstractDbServiceFactory::class,
                    \Finna\Db\Service\FinnaTransactionEventLogService::class
                        => \VuFind\Db\Service\AbstractDbServiceFactory::class,
                    \Finna\Db\Service\RatingsService::class => \VuFind\Db\Service\AbstractDbServiceFactory::class,
                    \Finna\Db\Service\RecordService::class => \VuFind\Db\Service\AbstractDbServiceFactory::class,
                    \Finna\Db\Service\FinnaResourceListService::class => \VuFind\Db\Service\AbstractDbServiceFactory::class,
                    \Finna\Db\Service\FinnaResourceListResourceService::class => \VuFind\Db\Service\AbstractDbServiceFactory::class,
                    \Finna\Db\Service\SearchService::class => \VuFind\Db\Service\AbstractDbServiceFactory::class,
                    \Finna\Db\Service\UserListService::class => \VuFind\Db\Service\AbstractDbServiceFactory::class,
                    \Finna\Db\Service\UserResourceService::class => \VuFind\Db\Service\AbstractDbServiceFactory::class,
                    \Finna\Db\Service\UserService::class => \Finna\Db\Service\UserServiceFactory::class,
                    \Finna\Db\Service\UserCardService::class => \Finna\Db\Service\UserCardServiceFactory::class,
                ],
                'aliases' => [
                    \VuFind\Db\Service\CommentsService::class => \Finna\Db\Service\CommentsService::class,
                    \VuFind\Db\Service\RatingsService::class => \Finna\Db\Service\RatingsService::class,
                    \VuFind\Db\Service\RecordService::class => \Finna\Db\Service\RecordService::class,
                    \VuFind\Db\Service\SearchService::class => \Finna\Db\Service\SearchService::class,
                    \VuFind\Db\Service\UserListService::class => \Finna\Db\Service\UserListService::class,
                    \VuFind\Db\Service\UserResourceService::class => \Finna\Db\Service\UserResourceService::class,
                    \VuFind\Db\Service\UserService::class => \Finna\Db\Service\UserService::class,
                    \VuFind\Db\Service\UserCardService::class => \Finna\Db\Service\UserCardService::class,
                    \VuFind\Db\Service\AccessTokenService::class => \Finna\Db\Service\AccessTokenService::class,

                    \Finna\Db\Service\FinnaCacheServiceInterface::class => \Finna\Db\Service\FinnaCacheService::class,
                    \Finna\Db\Service\FinnaDueDateReminderServiceInterface::class
                        => \Finna\Db\Service\FinnaDueDateReminderService::class,
                    \Finna\Db\Service\FinnaFeeServiceInterface::class => \Finna\Db\Service\FinnaFeeService::class,
                    \Finna\Db\Service\FinnaFeedbackServiceInterface::class
                        => \Finna\Db\Service\FinnaFeedbackService::class,
                    \Finna\Db\Service\FinnaRecordServiceInterface::class
                        => \Finna\Db\Service\RecordService::class,
                    \Finna\Db\Service\FinnaResourceListServiceInterface::class => \Finna\Db\Service\FinnaResourceListService::class,
                    \Finna\Db\Service\FinnaResourceListResourceServiceInterface::class => \Finna\Db\Service\FinnaResourceListResourceService::class,
                    \Finna\Db\Service\FinnaStatisticsServiceInterface::class
                        => \Finna\Db\Service\FinnaStatisticsService::class,
                    \Finna\Db\Service\FinnaTransactionServiceInterface::class
                        => \Finna\Db\Service\FinnaTransactionService::class,
                    \Finna\Db\Service\FinnaTransactionEventLogServiceInterface::class
                        => \Finna\Db\Service\FinnaTransactionEventLogService::class,
                ],
            ],
            'db_table' => [
                'factories' => [
                    'Finna\Db\Table\Comments' => 'VuFind\Db\Table\GatewayFactory',
                    'Finna\Db\Table\CommentsInappropriate' => 'Finna\Db\Table\CommentsInappropriateFactory',
                    'Finna\Db\Table\CommentsRecord' => 'VuFind\Db\Table\GatewayFactory',
                    'Finna\Db\Table\DueDateReminder' => 'VuFind\Db\Table\GatewayFactory',
                    'Finna\Db\Table\Fee' => 'VuFind\Db\Table\GatewayFactory',
                    'Finna\Db\Table\FinnaCache' => 'VuFind\Db\Table\GatewayFactory',
                    'Finna\Db\Table\FinnaFeedback' => 'VuFind\Db\Table\GatewayFactory',
                    'Finna\Db\Table\FinnaPageViewStats' => 'VuFind\Db\Table\GatewayFactory',
                    'Finna\Db\Table\FinnaRecordStats' => 'VuFind\Db\Table\GatewayFactory',
                    'Finna\Db\Table\FinnaRecordStatsLog' => 'VuFind\Db\Table\GatewayFactory',
                    'Finna\Db\Table\FinnaRecordView' => 'VuFind\Db\Table\GatewayFactory',
                    'Finna\Db\Table\FinnaRecordViewInstView' => 'VuFind\Db\Table\GatewayFactory',
                    'Finna\Db\Table\FinnaRecordViewRecord' => 'VuFind\Db\Table\GatewayFactory',
                    'Finna\Db\Table\FinnaRecordViewRecordFormat' => 'VuFind\Db\Table\GatewayFactory',
                    'Finna\Db\Table\FinnaRecordViewRecordRights' => 'VuFind\Db\Table\GatewayFactory',
                    'Finna\Db\Table\FinnaSessionStats' => 'VuFind\Db\Table\GatewayFactory',
                    'Finna\Db\Table\Resource' => 'VuFind\Db\Table\ResourceFactory',
                    'Finna\Db\Table\Search' => 'VuFind\Db\Table\GatewayFactory',
                    'Finna\Db\Table\Transaction' => 'VuFind\Db\Table\GatewayFactory',
                    'Finna\Db\Table\TransactionEventLog' => 'VuFind\Db\Table\GatewayFactory',
                    'Finna\Db\Table\User' => 'VuFind\Db\Table\UserFactory',
                    'Finna\Db\Table\UserList' => 'VuFind\Db\Table\GatewayFactory',
                    \Finna\Db\Table\FinnaResourceList::class => \VuFind\Db\Table\GatewayFactory::class,
                    \Finna\Db\Table\FinnaResourceListResource::class => \VuFind\Db\Table\GatewayFactory::class,
                    'Finna\Db\Table\UserResource' => 'VuFind\Db\Table\GatewayFactory',
                ],
                'aliases' => [
                    'VuFind\Db\Table\Comments' => 'Finna\Db\Table\Comments',
                    'VuFind\Db\Table\Resource' => 'Finna\Db\Table\Resource',
                    'VuFind\Db\Table\Search' => 'Finna\Db\Table\Search',
                    'VuFind\Db\Table\User' => 'Finna\Db\Table\User',
                    'VuFind\Db\Table\UserList' => 'Finna\Db\Table\UserList',
                    'VuFind\Db\Table\UserResource' => 'Finna\Db\Table\UserResource',

                    'commentsinappropriate' => 'Finna\Db\Table\CommentsInappropriate',
                    'commentsrecord' => 'Finna\Db\Table\CommentsRecord',
                    'duedatereminder' => 'Finna\Db\Table\DueDateReminder',
                    'fee' => 'Finna\Db\Table\Fee',
                    'finnafeedback' => 'Finna\Db\Table\FinnaFeedback',
                    'finnacache' => 'Finna\Db\Table\FinnaCache',
                    'finnapageviewstats' => 'Finna\Db\Table\FinnaPageViewStats',
                    'finnarecordstats' => 'Finna\Db\Table\FinnaRecordStats',
                    'finnarecordstatslog' => 'Finna\Db\Table\FinnaRecordStatsLog',
                    'finnasessionstats' => 'Finna\Db\Table\FinnaSessionStats',
                    'transaction' => 'Finna\Db\Table\Transaction',
                ],
            ],
            'form_handler' => [
                'factories' => [
                    'Finna\Form\Handler\Api' => 'Finna\Form\Handler\ApiFactory',
                    'Finna\Form\Handler\Database' => 'Finna\Form\Handler\DatabaseFactory',
                    'Finna\Form\Handler\Email' => 'VuFind\Form\Handler\EmailFactory',
                ],
                'aliases' => [
                    'api' => 'Finna\Form\Handler\Api',

                    'VuFind\Form\Handler\Database' => 'Finna\Form\Handler\Database',
                    'VuFind\Form\Handler\Email' => 'Finna\Form\Handler\Email',
                ],
            ],
            'ils_driver' => [
                'factories' => [
                    'Finna\ILS\Driver\Alma' => 'VuFind\ILS\Driver\DriverWithDateConverterFactory',
                    'Finna\ILS\Driver\AxiellWebServices' => 'Finna\ILS\Driver\AxiellWebServicesFactory',
                    'Finna\ILS\Driver\Demo' => 'VuFind\ILS\Driver\DemoFactory',
                    'Finna\ILS\Driver\KohaRest' => 'VuFind\ILS\Driver\KohaRestFactory',
                    'Finna\ILS\Driver\KohaRestSuomi' => 'Finna\ILS\Driver\KohaRestSuomiFactory',
                    'Finna\ILS\Driver\Mikromarc' => 'Finna\ILS\Driver\MikromarcFactory',
                    'Finna\ILS\Driver\MultiBackend' => 'VuFind\ILS\Driver\MultiBackendFactory',
                    'Finna\ILS\Driver\NoILS' => 'VuFind\ILS\Driver\NoILSFactory',
                    'Finna\ILS\Driver\Quria' => 'Finna\ILS\Driver\AxiellWebServicesFactory',
                    'Finna\ILS\Driver\SierraRest' => 'VuFind\ILS\Driver\SierraRestFactory',
                ],
                'aliases' => [
                    'axiellwebservices' => 'Finna\ILS\Driver\AxiellWebServices',
                    'mikromarc' => 'Finna\ILS\Driver\Mikromarc',
                    'koharestsuomi' => 'Finna\ILS\Driver\KohaRestSuomi',
                    'quria' => 'Finna\ILS\Driver\Quria',

                    'VuFind\ILS\Driver\Alma' => 'Finna\ILS\Driver\Alma',
                    'VuFind\ILS\Driver\Demo' => 'Finna\ILS\Driver\Demo',
                    'VuFind\ILS\Driver\KohaRest' => 'Finna\ILS\Driver\KohaRest',
                    'VuFind\ILS\Driver\MultiBackend' => 'Finna\ILS\Driver\MultiBackend',
                    'VuFind\ILS\Driver\NoILS' => 'Finna\ILS\Driver\NoILS',
                    'VuFind\ILS\Driver\SierraRest' => 'Finna\ILS\Driver\SierraRest',
                ],
            ],
            'onlinepayment_handler' => [ /* see Finna\OnlinePayment\Handler\PluginManager for defaults */ ],
            'video_handler' => [ /* see Finna\Video\Handler\PluginManager for defaults */ ],
            'reservationlist_handler' => [ /* see Finna\ReservationList\Handler\PluginManager for defaults */ ],
            'recommend' => [
                'factories' => [
                    'VuFind\Recommend\CollectionSideFacets' => 'Finna\Recommend\Factory::getCollectionSideFacets',
                    'VuFind\Recommend\SideFacets' => 'Finna\Recommend\Factory::getSideFacets',
                    'Finna\Recommend\AuthorityRecommend' => 'Finna\Recommend\AuthorityRecommendFactory',
                    'Finna\Recommend\Feedback' => 'Finna\Recommend\FeedbackFactory',
                    'Finna\Recommend\FinnaStaticHelp' => 'Laminas\ServiceManager\Factory\InvokableFactory',
                    'Finna\Recommend\FinnaSuggestions' => 'Finna\Recommend\FinnaSuggestionsFactory',
                    'Finna\Recommend\FinnaSuggestionsDeferred' => 'Finna\Recommend\FinnaSuggestionsDeferredFactory',
                    'Finna\Recommend\LearningMaterial' => 'Finna\Recommend\LearningMaterialFactory',
                    'Finna\Recommend\Ontology' => 'Finna\Recommend\OntologyFactory',
                    'Finna\Recommend\OntologyDeferred' => 'Finna\Recommend\OntologyDeferredFactory',
                    'Finna\Recommend\SideFacetsDeferred' => 'Finna\Recommend\Factory::getSideFacetsDeferred',
                ],
                'aliases' => [
                    'authorityrecommend' => 'Finna\Recommend\AuthorityRecommend',
                    'feedback' => 'Finna\Recommend\Feedback',
                    'finnastatichelp' => 'Finna\Recommend\FinnaStaticHelp',
                    'finnasuggestions' => 'Finna\Recommend\FinnaSuggestions',
                    'finnasuggestionsdeferred' => 'Finna\Recommend\FinnaSuggestionsDeferred',
                    'learningmaterial' => 'Finna\Recommend\LearningMaterial',
                    'ontology' => 'Finna\Recommend\Ontology',
                    'ontologydeferred' => 'Finna\Recommend\OntologyDeferred',
                    'sidefacetsdeferred' => 'Finna\Recommend\SideFacetsDeferred',
                ],
            ],
            'record_fallbackloader' => [
                'factories' => [
                    \Finna\Record\FallbackLoader\Solr::class => \Finna\Record\FallbackLoader\SolrFactory::class,
                    \Finna\Record\FallbackLoader\SolrAuth::class => \Finna\Record\FallbackLoader\SolrFactory::class,
                ],
                'aliases' => [
                    'solrauth' => \Finna\Record\FallbackLoader\SolrAuth::class,
                    \VuFind\Record\FallbackLoader\Solr::class => \Finna\Record\FallbackLoader\Solr::class,
                ],
            ],
            'recorddataformatter_specs' => [
                'factories' => [
                    \Finna\RecordDataFormatter\Specs\DefaultRecord::class
                        => \VuFind\RecordDataFormatter\Specs\DefaultRecordFactory::class,
                    \Finna\RecordDataFormatter\Specs\CollectionRecord::class
                        => \VuFind\RecordDataFormatter\Specs\DefaultRecordFactory::class,
                ],
                'aliases' => [
                    \VuFind\RecordDataFormatter\Specs\DefaultRecord::class
                        => \Finna\RecordDataFormatter\Specs\DefaultRecord::class,
                ],
            ],
            'resolver_driver' => [
                'factories' => [
                    'Finna\Resolver\Driver\Sfx' => 'VuFind\Resolver\Driver\DriverWithHttpClientFactory',
                    'Finna\Resolver\Driver\Alma' => 'VuFind\Resolver\Driver\DriverWithHttpClientFactory',
                ],
                'aliases' => [
                    'VuFind\Resolver\Driver\Sfx' => 'Finna\Resolver\Driver\Sfx',
                    'VuFind\Resolver\Driver\Alma' => 'Finna\Resolver\Driver\Alma',
                ],
            ],
            'search_backend' => [
                'factories' => [
                    'L1' => 'Finna\Search\Factory\L1BackendFactory',
                    'Primo' => 'Finna\Search\Factory\PrimoBackendFactory',
                    'Solr' => 'Finna\Search\Factory\SolrDefaultBackendFactory',
                    'SolrAuth' => 'Finna\Search\Factory\SolrAuthBackendFactory',
                    'SolrBrowse' => 'Finna\Search\Factory\SolrDefaultBackendFactory',
                ],
            ],
            'search_options' => [
                'factories' => [
                    'Finna\Search\Blender\Options' => 'VuFind\Search\Options\OptionsFactory',
                    'Finna\Search\Combined\Options' => 'VuFind\Search\Combined\OptionsFactory',
                    'Finna\Search\EDS\Options' => 'VuFind\Search\EDS\OptionsFactory',
                    \Finna\Search\ReservationList\Options::class => \VuFind\Search\Options\OptionsFactory::class,
                    'Finna\Search\Primo\Options' => 'VuFind\Search\Options\OptionsFactory',
                    'Finna\Search\Solr\Options' => 'VuFind\Search\Options\OptionsFactory',
                    'Finna\Search\SolrAuth\Options' => 'VuFind\Search\Options\OptionsFactory',
                    'Finna\Search\SolrBrowse\Options' => 'VuFind\Search\Options\OptionsFactory',
                    'Finna\Search\SolrCollection\Options' => 'VuFind\Search\Options\OptionsFactory',

                    'Finna\Search\L1\Options' => 'VuFind\Search\OptionsFactory',
                ],
                'aliases' => [
                    'VuFind\Search\Blender\Options' => 'Finna\Search\Blender\Options',
                    'VuFind\Search\Combined\Options' => 'Finna\Search\Combined\Options',
                    'VuFind\Search\EDS\Options' => 'Finna\Search\EDS\Options',
                    'VuFind\Search\Primo\Options' => 'Finna\Search\Primo\Options',
                    'VuFind\Search\Solr\Options' => 'Finna\Search\Solr\Options',
                    'VuFind\Search\SolrAuth\Options' => 'Finna\Search\SolrAuth\Options',
                    'VuFind\Search\SolrCollection\Options' => 'Finna\Search\SolrCollection\Options',

                    // Counterpart for EmptySet Params:
                    'Finna\Search\EmptySet\Options' => 'VuFind\Search\EmptySet\Options',
                    'Finna\Search\MixedList\Options' => 'VuFind\Search\MixedList\Options',
                    'ReservationList' => \Finna\Search\ReservationList\Params::class,
                    'SolrAuth' => 'Finna\Search\SolrAuth\Options',
                    'SolrBrowse' => 'Finna\Search\SolrBrowse\Options',
                    'L1' => 'Finna\Search\L1\Options',
                ],
            ],
            'search_params' => [
                'factories' => [
                    'Finna\Search\Blender\Params' => 'Finna\Search\Blender\ParamsFactory',
                    'Finna\Search\Combined\Params' => 'Finna\Search\Solr\ParamsFactory',
                    'Finna\Search\EDS\Params' => 'VuFind\Search\Params\ParamsFactory',
                    'Finna\Search\EmptySet\Params' => 'VuFind\Search\Params\ParamsFactory',
                    'Finna\Search\Favorites\Params' => 'VuFind\Search\Params\ParamsFactory',
                    \Finna\Search\ReservationList\Params::class => \Finna\Search\Solr\ParamsFactory::class,
                    'Finna\Search\MixedList\Params' => 'VuFind\Search\Params\ParamsFactory',
                    'Finna\Search\Solr\Params' => 'Finna\Search\Solr\ParamsFactory',
                    'Finna\Search\SolrAuth\Params' => 'Finna\Search\Solr\ParamsFactory',
                    'Finna\Search\SolrBrowse\Params' => 'Finna\Search\Solr\ParamsFactory',
                    'Finna\Search\SolrCollection\Params' => 'Finna\Search\SolrCollection\ParamsFactory',

                    'Finna\Search\L1\Params' => 'Finna\Search\Solr\ParamsFactory',
                ],
                'aliases' => [
                    'VuFind\Search\Blender\Params' => 'Finna\Search\Blender\Params',
                    'VuFind\Search\Combined\Params' => 'Finna\Search\Combined\Params',
                    'VuFind\Search\EDS\Params' => 'Finna\Search\EDS\Params',
                    'VuFind\Search\EmptySet\Params' => 'Finna\Search\EmptySet\Params',
                    'VuFind\Search\Favorites\Params' => 'Finna\Search\Favorites\Params',
                    'VuFind\Search\MixedList\Params' => 'Finna\Search\MixedList\Params',
                    'VuFind\Search\Solr\Params' => 'Finna\Search\Solr\Params',
                    'VuFind\Search\SolrCollection\Params' => 'Finna\Search\SolrCollection\Params',

                    'VuFind\Search\SolrAuth\Params' => 'Finna\Search\SolrAuth\Params',
                    'ReservationList' => \Finna\Search\ReservationList\Params::class,
                    'SolrAuth' => 'Finna\Search\SolrAuth\Params',
                    'L1' => 'Finna\Search\L1\Params',
                ],
            ],
            'search_results' => [
                'factories' => [
                    'Finna\Search\Blender\Results' => '\VuFind\Search\Solr\ResultsFactory',
                    'Finna\Search\Combined\Results' => 'VuFind\Search\Results\ResultsFactory',
                    'Finna\Search\EncapsulatedRecords\Results' => 'VuFind\Search\Results\ResultsFactory',
                    'Finna\Search\Favorites\Results' => 'VuFind\Search\Favorites\ResultsFactory',
                    \Finna\Search\ReservationList\Results::class => \Finna\Search\ReservationList\ResultsFactory::class,
                    'Finna\Search\Primo\Results' => 'VuFind\Search\Results\ResultsFactory',
                    'Finna\Search\Solr\Results' => 'VuFind\Search\Solr\ResultsFactory',
                    'Finna\Search\SolrAuth\Results' => 'VuFind\Search\Solr\ResultsFactory',
                    'Finna\Search\SolrBrowse\Results' => 'VuFind\Search\Solr\ResultsFactory',
                    'Finna\Search\SolrCollection\Results' => 'Finna\Search\SolrCollection\ResultsFactory',
                    'Finna\Search\L1\Results' => 'Finna\Search\L1\ResultsFactory',
                ],
                'aliases' => [
                    'VuFind\Search\Blender\Results' => 'Finna\Search\Blender\Results',
                    'VuFind\Search\Combined\Results' => 'Finna\Search\Combined\Results',
                    'VuFind\Search\Favorites\Results' => 'Finna\Search\Favorites\Results',
                    'VuFind\Search\Primo\Results' => 'Finna\Search\Primo\Results',
                    'VuFind\Search\Solr\Results' => 'Finna\Search\Solr\Results',
                    'VuFind\Search\SolrAuth\Results' => 'Finna\Search\SolrAuth\Results',
                    'VuFind\Search\SolrCollection\Results' => 'Finna\Search\SolrCollection\Results',

                    'EncapsulatedRecords' => 'Finna\Search\EncapsulatedRecords\Results',
                    'L1' => 'Finna\Search\L1\Results',
                    'ReservationList' => \Finna\Search\ReservationList\Results::class,
                    'SolrBrowse' => 'Finna\Search\SolrBrowse\Results',
                ],
            ],
            'session' => [
                'factories' => [
                    'Finna\Session\Redis' => 'Finna\Session\RedisFactory',
                ],
                'aliases' => [
                    'VuFind\Session\Redis' => 'Finna\Session\Redis',
                ],
            ],
            'statistics_driver' => [
                'factories' => [
                    'Finna\Statistics\Driver\Database' => 'Finna\Statistics\Driver\DatabaseFactory',
                    'Finna\Statistics\Driver\Redis' => 'Finna\Statistics\Driver\RedisFactory',
                ],
                'aliases' => [
                    'Database' => 'Finna\Statistics\Driver\Database',
                    'Redis' => 'Finna\Statistics\Driver\Redis',
                ],
            ],
            'content_covers' => [
                'factories' => [
                    'Finna\Content\Covers\BTJ' => 'Finna\Content\Covers\BTJFactory',
                    'Finna\Content\Covers\CoverArtArchive' => 'Finna\Content\Covers\CoverArtArchiveFactory',
                    'Finna\Content\Covers\Kirjavalitys' => 'Finna\Content\Covers\KirjavalitysFactory',
                ],
                'invokables' => [
                    'bookyfi' => 'Finna\Content\Covers\BookyFi',
                    'natlibfi' => 'Finna\Content\Covers\NatLibFi',
                ],
                'aliases' => [
                    'btj' => 'Finna\Content\Covers\BTJ',
                    'coverartarchive' => 'Finna\Content\Covers\CoverArtArchive',
                    'kirjavalitys' => 'Finna\Content\Covers\Kirjavalitys',
                ],
            ],
            'recorddriver' => [
                'factories' => [
                    'Finna\RecordDriver\AipaLrmi' =>
                        'Finna\RecordDriver\AipaLrmiFactory',
                    'Finna\RecordDriver\CuratedRecord' =>
                        'VuFind\RecordDriver\AbstractBaseFactory',
                    'Finna\RecordDriver\CuratedRecordList' =>
                        'Finna\RecordDriver\CuratedRecordListFactory',
                    'Finna\RecordDriver\EDS' =>
                        'VuFind\RecordDriver\NameBasedConfigFactory',
                    'Finna\RecordDriver\SolrDefault' =>
                        'Finna\RecordDriver\SolrDefaultFactory',
                    'Finna\RecordDriver\SolrAipa' =>
                        'Finna\RecordDriver\SolrAipaFactory',
                    'Finna\RecordDriver\SolrAuthEaccpf' =>
                        'Finna\RecordDriver\SolrDefaultFactory',
                    'Finna\RecordDriver\SolrAuthForward' =>
                        'Finna\RecordDriver\SolrDefaultFactory',
                    'Finna\RecordDriver\SolrAuthMarc' =>
                        'Finna\RecordDriver\SolrDefaultFactory',
                    'Finna\RecordDriver\SolrEad' =>
                        'Finna\RecordDriver\SolrDefaultFactory',
                    'Finna\RecordDriver\SolrEad3' =>
                        'Finna\RecordDriver\SolrDefaultFactory',
                    'Finna\RecordDriver\SolrForward' =>
                        'Finna\RecordDriver\SolrDefaultFactory',
                    'Finna\RecordDriver\SolrLido' =>
                        'Finna\RecordDriver\SolrDefaultFactory',
                    'Finna\RecordDriver\SolrLrmi' =>
                        'Finna\RecordDriver\SolrDefaultFactory',
                    'Finna\RecordDriver\SolrMarc' =>
                        'Finna\RecordDriver\SolrDefaultFactory',
                    'Finna\RecordDriver\SolrQdc' =>
                        'Finna\RecordDriver\SolrDefaultFactory',
                    'Finna\RecordDriver\Primo' =>
                        'VuFind\RecordDriver\NameBasedConfigFactory',
                ],
                'aliases' => [
                    'AipaLrmi' => 'Finna\RecordDriver\AipaLrmi',
                    'CuratedRecord' => 'Finna\RecordDriver\CuratedRecord',
                    'CuratedRecordList' => 'Finna\RecordDriver\CuratedRecordList',
                    'SolrAipa' => 'Finna\RecordDriver\SolrAipa',
                    'SolrAuthEaccpf' => 'Finna\RecordDriver\SolrAuthEaccpf',
                    'SolrAuthForwardAuthority' => 'Finna\RecordDriver\SolrAuthForward',
                    'SolrAuthMarcAuthority' => 'Finna\RecordDriver\SolrAuthMarc',
                    'SolrEad' => 'Finna\RecordDriver\SolrEad',
                    'SolrEad3' => 'Finna\RecordDriver\SolrEad3',
                    'SolrForward' => 'Finna\RecordDriver\SolrForward',
                    'SolrLido' => 'Finna\RecordDriver\SolrLido',
                    'SolrLrmi' => 'Finna\RecordDriver\SolrLrmi',
                    'SolrQdc' => 'Finna\RecordDriver\SolrQdc',

                    'VuFind\RecordDriver\EDS' => 'Finna\RecordDriver\EDS',
                    'VuFind\RecordDriver\SolrAuthDefault' => 'Finna\RecordDriver\SolrAuthDefault',
                    'VuFind\RecordDriver\SolrDefault' => 'Finna\RecordDriver\SolrDefault',
                    'VuFind\RecordDriver\SolrMarc' => 'Finna\RecordDriver\SolrMarc',
                    'VuFind\RecordDriver\Primo' => 'Finna\RecordDriver\Primo',
                ],
                'delegators' => [
                    'Finna\RecordDriver\SolrMarc' => [
                        'VuFind\RecordDriver\IlsAwareDelegatorFactory',
                    ],
                ],
            ],
            'recordtab' => [
                'factories' => [
                    'Finna\RecordTab\AuthorityRecordsAuthor' => 'Finna\RecordTab\AuthorityRecordsFactory',
                    'Finna\RecordTab\AuthorityRecordsTopic' => 'Finna\RecordTab\AuthorityRecordsFactory',
                    'Finna\RecordTab\CollectionHierarchyTree' => 'VuFind\RecordTab\CollectionHierarchyTreeFactory',
                    'Finna\RecordTab\CollectionList' => 'VuFind\RecordTab\CollectionListFactory',
                    'Finna\RecordTab\HoldingsArchive' => 'Finna\RecordTab\Factory::getHoldingsArchive',
                    'Finna\RecordTab\HierarchyTree' => 'VuFind\RecordTab\HierarchyTreeFactory',
                    'Finna\RecordTab\Map' => 'Finna\RecordTab\Factory::getMap',
                    'Finna\RecordTab\UserComments' => 'Finna\RecordTab\Factory::getUserComments',
                ],
                'invokables' => [
                    'componentparts' => 'Finna\RecordTab\ComponentParts',
                ],
                'aliases' => [
                    'authorityrecordsauthor' => 'Finna\RecordTab\AuthorityRecordsAuthor',
                    'authorityrecordstopic' => 'Finna\RecordTab\AuthorityRecordsTopic',
                    'componentparts' => 'Finna\RecordTab\ComponentParts',
                    'holdingsarchive' => 'Finna\RecordTab\HoldingsArchive',

                    // Overrides:
                    'VuFind\RecordTab\CollectionHierarchyTree' => 'Finna\RecordTab\CollectionHierarchyTree',
                    'VuFind\RecordTab\CollectionList' => 'Finna\RecordTab\CollectionList',
                    'VuFind\RecordTab\HierarchyTree' => 'Finna\RecordTab\HierarchyTree',
                    'VuFind\RecordTab\Map' => 'Finna\RecordTab\Map',
                    'VuFind\RecordTab\UserComments' => 'Finna\RecordTab\UserComments',
                ],
            ],
            'related' => [
                'factories' => [
                    'Finna\Related\RecordDriverRelated' => 'Finna\Related\RecordDriverRelatedFactory',
                    'Finna\Related\Nothing' => 'Laminas\ServiceManager\Factory\InvokableFactory',
                    'Finna\Related\SimilarDeferred' => 'Laminas\ServiceManager\Factory\InvokableFactory',
                    'Finna\Related\WorkExpressions' => 'Finna\Related\WorkExpressionsFactory',
                ],
                'aliases' =>  [
                    'nothing' => 'Finna\Related\Nothing',
                    'recorddriverrelated' => 'Finna\Related\RecordDriverRelated',
                    'similardeferred' => 'Finna\Related\SimilarDeferred',
                    'workexpressions' => 'Finna\Related\WorkExpressions',
                ],
            ],
            'view_customelement' => [
                'factories' => [
                    'Finna\View\CustomElement\FinnaList' => 'Finna\View\CustomElement\AbstractBaseFactory',
                    'Finna\View\CustomElement\FinnaPanel' => 'Finna\View\CustomElement\AbstractBaseFactory',
                    'Finna\View\CustomElement\FinnaTruncate' => 'Finna\View\CustomElement\AbstractBaseFactory',
                ],
                'aliases' => [
                    'finna-list' => 'Finna\View\CustomElement\FinnaList',
                    'finna-panel' => 'Finna\View\CustomElement\FinnaPanel',
                    'finna-truncate' => 'Finna\View\CustomElement\FinnaTruncate',
                ],
            ],
        ],
    ],

    // Authorization configuration:
    'lmc_rbac' => [
        'vufind_permission_provider_manager' => [
            'factories' => [
                'Finna\Role\PermissionProvider\AuthenticationStrategy' => 'Finna\Role\PermissionProvider\AuthenticationStrategyFactory',
                'Finna\Role\PermissionProvider\IpRange' => 'VuFind\Role\PermissionProvider\IpRangeFactory',
            ],
            'aliases' => [
                'authenticationStrategy' => 'Finna\Role\PermissionProvider\AuthenticationStrategy',

                'VuFind\Role\PermissionProvider\IpRange' => 'Finna\Role\PermissionProvider\IpRange',
            ],
        ],
    ],
];

$recordRoutes = [
    'metalibrecord' => 'MetaLibRecord',
    'solrauthrecord' => 'AuthorityRecord',
    // BrowseRecord is practically just the same as Record, but the route must be
    // distinct so that getMatchedRouteName returns the correct one:
    'solrbrowserecord' => 'BrowseRecord',
    'l1record' => 'L1Record',
];

// Define non tab record actions
$nonTabRecordActions = [
    'Feedback', 'RepositoryLibraryRequest', 'ArchiveRequest', 'ValidationReport',
];

// Define dynamic routes -- controller => [route name => action]
$dynamicRoutes = [
    'Comments' => ['inappropriate' => 'inappropriate/[:id]'],
    'LibraryCards' => [
        'newLibraryCardPassword' => 'newPassword/[:id]',
        'librarycards-displaybarcode' => 'displayBarcode/[:id]',
    ],
    'MyResearch' => ['sortList' => 'SortList/[:id]'],
    'ReservationList' => [
        'reservationlist-placeorderoptions' => 'PlaceOrderOptions',
        'reservationlist-displaylists' => 'DisplayLists',
        'reservationlist-displaylist' => 'DisplayList/:listId',
        // Keep :id optional in placeorder routes for compatibility with form logic
        'reservationlist-placeorder' => 'PlaceOrder/[:id]',
        'reservationlist-placesingleorder' => 'PlaceSingleOrder/[:id]',
        'reservationlist-deletelist' => 'DeleteList/:listId',
        'reservationlist-deletebulk' => 'DeleteBulk/:listId',
        'reservationlist-additemtolist' => 'AddItemToList',
        'reservationlist-createlist' => 'CreateList',
    ],
];

$staticRoutes = [
    'LibraryCards/Recover', 'LibraryCards/Register',
    'LibraryCards/RegistrationDone', 'LibraryCards/RegistrationForm',
    'LibraryCards/ResetPassword',
    'LocationService/Modal',
    'Cover/Pipe',
    'MetaLib/Home', 'MetaLib/Search', 'MetaLib/Advanced',
    'MyResearch/SaveCustomOrder', 'MyResearch/SaveHistoricLoans',
    'MyResearch/DownloadCheckoutHistory',
    'OrganisationInfo/Home',
    'PCI/Home', 'PCI/Search', 'PCI/Record',
    'Search/StreetSearch',
    'Barcode/Show', 'Search/MapFacet',
    'L1/Advanced', 'L1/FacetList', 'L1/Home', 'L1/Results',
    'Record/DownloadModel',
    'Record/DownloadFile',
    'Bazaar/Home',
    'Bazaar/Cancel',
    'ReservationList/CreateList',
];

$routeGenerator = new \VuFind\Route\RouteGenerator();
$routeGenerator->addNonTabRecordActions($config, $nonTabRecordActions);
$routeGenerator->addRecordRoutes($config, $recordRoutes);
$routeGenerator->addDynamicRoutes($config, $dynamicRoutes);
$routeGenerator->addStaticRoutes($config, $staticRoutes);

// These need to be defined after VuFind's record routes:
$config['router']['routes']['l1record-feedback'] = [
    'type'    => 'Laminas\Router\Http\Segment',
    'options' => [
        'route'    => '/L1Record/[:id]/Feedback',
        'constraints' => [
            'controller' => '[a-zA-Z][a-zA-Z0-9_-]*',
            'action'     => '[a-zA-Z][a-zA-Z0-9_-]*',
        ],
        'defaults' => [
            'controller' => 'L1Record',
            'action'     => 'Feedback',
        ],
    ],
];
$config['router']['routes']['solrrecord-feedback'] = [
    'type'    => 'Laminas\Router\Http\Segment',
    'options' => [
        'route'    => '/Record/[:id]/Feedback',
        'constraints' => [
            'controller' => '[a-zA-Z][a-zA-Z0-9_-]*',
            'action'     => '[a-zA-Z][a-zA-Z0-9_-]*',
        ],
        'defaults' => [
            'controller' => 'Record',
            'action'     => 'Feedback',
        ],
    ],
];
$config['router']['routes']['solrauthrecord-feedback'] = [
    'type'    => 'Laminas\Router\Http\Segment',
    'options' => [
        'route'    => '/AuthorityRecord/[:id]/Feedback',
        'constraints' => [
            'controller' => '[a-zA-Z][a-zA-Z0-9_-]*',
            'action'     => '[a-zA-Z][a-zA-Z0-9_-]*',
        ],
        'defaults' => [
            'controller' => 'AuthorityRecord',
            'action'     => 'Feedback',
        ],
    ],
];

return $config;
