<?php

/**
 * Action plugin manager.
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2025.
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
 * along with this program; if not, see
 * <https://www.gnu.org/licenses/>.
 *
 * @category VuFind
 * @package  Action
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:hierarchy_components Wiki
 */

namespace VuFind\Action;

use Laminas\ServiceManager\Exception\ContainerModificationsNotAllowedException;
use Laminas\ServiceManager\Exception\InvalidServiceException;
use Laminas\ServiceManager\Exception\ServiceNotFoundException;
use VuFind\ServiceManager\Factory\AbstractAutowiringFactory;
use VuFind\ServiceManager\Factory\AutowiringFactory;

use function count;

/**
 * Action plugin manager.
 *
 * @category VuFind
 * @package  Action
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:hierarchy_components Wiki
 */
class PluginManager extends \VuFind\ServiceManager\AbstractPluginManager
{
    /**
     * Default plugin aliases.
     *
     * Action classes are auto-discovered in autoDiscoveryNamespaces (see below) when the names follow the convention
     * Category\Classname. Other forms will need to be mapped from 'category/action' (all lowercase) here.
     *
     * @var array
     */
    protected $aliases = [
        'ajax/json' => Ajax\JsonAction::class,
        'ajax/onlinepaymentnotify' => Ajax\OnlinePaymentNotifyAction::class,
        'ajax/systemstatus' => Ajax\SystemStatusAction::class,

        'author/facetlist' => Author\FacetListAction::class,

        'cart/doexport' => Cart\DoExportAction::class,
        'cart/myresearchbulk' => Cart\MyResearchBulkAction::class,
        'cart/printcart' => Cart\PrintCartAction::class,
        'cart/searchresultsbulk' => Cart\SearchResultsBulkAction::class,

        'checkouts/purgehistory' => Checkouts\PurgeHistoryAction::class,

        // At least hierarchy tree links use the collection AjaxTab route:
        'collection/ajaxtab' => Record\AjaxTabAction::class,

        'collections/bytitle' => Collections\ByTitleAction::class,

        'comments/deletecomments' => Comments\DeleteCommentsAction::class,
        'comments/userlist' => Comments\UserListAction::class,

        'developersettings/deleteapikey' => DeveloperSettings\DeleteApiKeyAction::class,
        'developersettings/displaysettings' => DeveloperSettings\DisplaySettingsAction::class,
        'developersettings/generateapikey' => DeveloperSettings\GenerateApiKeyAction::class,

        'edsrecord/addcomment' => Record\AddComentAction::class,
        'edsrecord/addtag' => Record\AddTagAction::class,
        'edsrecord/ajaxtab' => Record\AjaxTabAction::class,
        'edsrecord/cite' => Record\CiteAction::class,
        'edsrecord/deletecomment' => Record\DeleteComentAction::class,
        'edsrecord/deletetag' => Record\DeleteTagAction::class,
        'edsrecord/email' => Record\EmailAction::class,
        'edsrecord/epub' => EdsRecord\EPubAction::class,
        'edsrecord/export' => Record\ExportAction::class,
        'edsrecord/hold' => Record\HoldAction::class,
        'edsrecord/home' => Record\HomeAction::class,
        'edsrecord/linkedtext' => EdsRecord\LinkedTextAction::class,
        'edsrecord/permalink' => Record\PermalinkAction::class,
        'edsrecord/pdf' => EdsRecord\PdfAction::class,
        'edsrecord/rating' => Record\RatingAction::class,
        'edsrecord/rdf' => Record\RdfAction::class,
        'edsrecord/save' => Record\SaveAction::class,
        'edsrecord/sms' => Record\SmsAction::class,

        'eitrecord/addcomment' => Record\AddComentAction::class,
        'eitrecord/addtag' => Record\AddTagAction::class,
        'eitrecord/ajaxtab' => Record\AjaxTabAction::class,
        'eitrecord/cite' => Record\CiteAction::class,
        'eitrecord/deletecomment' => Record\DeleteComentAction::class,
        'eitrecord/deletetag' => Record\DeleteTagAction::class,
        'eitrecord/email' => Record\EmailAction::class,
        'eitrecord/export' => Record\ExportAction::class,
        'eitrecord/hold' => Record\HoldAction::class,
        'eitrecord/home' => Record\HomeAction::class,
        'eitrecord/permalink' => Record\PermalinkAction::class,
        'eitrecord/rating' => Record\RatingAction::class,
        'eitrecord/rdf' => Record\RdfAction::class,
        'eitrecord/save' => Record\SaveAction::class,
        'eitrecord/sms' => Record\SmsAction::class,

        'externalauth/ezproxylogin' => ExternalAuth\EzproxyLoginAction::class,

        'missingrecord/home' => MissingRecord\HomeAction::class,

        'myresearch/cataloglogin' => MyResearch\CatalogLoginAction::class,

        'pazpar2record/home' => Pazpar2Record\HomeAction::class,

        'primorecord/addcomment' => Record\AddComentAction::class,
        'primorecord/addtag' => Record\AddTagAction::class,
        'primorecord/ajaxtab' => Record\AjaxTabAction::class,
        'primorecord/cite' => Record\CiteAction::class,
        'primorecord/deletecomment' => Record\DeleteComentAction::class,
        'primorecord/deletetag' => Record\DeleteTagAction::class,
        'primorecord/email' => Record\EmailAction::class,
        'primorecord/export' => Record\ExportAction::class,
        'primorecord/hold' => Record\HoldAction::class,
        'primorecord/home' => Record\HomeAction::class,
        'primorecord/permalink' => Record\PermalinkAction::class,
        'primorecord/rating' => Record\RatingAction::class,
        'primorecord/rdf' => Record\RdfAction::class,
        'primorecord/save' => Record\SaveAction::class,
        'primorecord/sms' => Record\SmsAction::class,

        'proquestfsgrecord/addcomment' => Record\AddComentAction::class,
        'proquestfsgrecord/addtag' => Record\AddTagAction::class,
        'proquestfsgrecord/ajaxtab' => Record\AjaxTabAction::class,
        'proquestfsgrecord/cite' => Record\CiteAction::class,
        'proquestfsgrecord/deletecomment' => Record\DeleteComentAction::class,
        'proquestfsgrecord/deletetag' => Record\DeleteTagAction::class,
        'proquestfsgrecord/email' => Record\EmailAction::class,
        'proquestfsgrecord/export' => Record\ExportAction::class,
        'proquestfsgrecord/hold' => Record\HoldAction::class,
        'proquestfsgrecord/home' => Record\HomeAction::class,
        'proquestfsgrecord/permalink' => Record\PermalinkAction::class,
        'proquestfsgrecord/rating' => Record\RatingAction::class,
        'proquestfsgrecord/rdf' => Record\RdfAction::class,
        'proquestfsgrecord/save' => Record\SaveAction::class,
        'proquestfsgrecord/sms' => Record\SmsAction::class,

        'ratings/deleteratings' => Ratings\DeleteRatingsAction::class,
        'ratings/userlist' => Ratings\UserListAction::class,

        'record/addcomment' => Record\AddComentAction::class,
        'record/addtag' => Record\AddTagAction::class,
        'record/ajaxtab' => Record\AjaxTabAction::class,
        'record/deletecomment' => Record\DeleteComentAction::class,
        'record/deletetag' => Record\DeleteTagAction::class,
        'record/getthis' => Record\GetThisAction::class,
        'record/illrequest' => Record\IllRequestAction::class,
        'record/storageretrievalrequest' => Record\StorageRetrievalRequestAction::class,

        // At least hierarchy tree links use the collection AjaxTab route:
        'search2collection/ajaxtab' => Record\AjaxTabAction::class,

        'search2record/addcomment' => Record\AddComentAction::class,
        'search2record/addtag' => Record\AddTagAction::class,
        'search2record/ajaxtab' => Record\AjaxTabAction::class,
        'search2record/cite' => Record\CiteAction::class,
        'search2record/deletecomment' => Record\DeleteComentAction::class,
        'search2record/deletetag' => Record\DeleteTagAction::class,
        'search2record/email' => Record\EmailAction::class,
        'search2record/export' => Record\ExportAction::class,
        'search2record/hold' => Record\HoldAction::class,
        'search2record/home' => Record\HomeAction::class,
        'search2record/permalink' => Record\PermalinkAction::class,
        'search2record/rating' => Record\RatingAction::class,
        'search2record/rdf' => Record\RdfAction::class,
        'search2record/save' => Record\SaveAction::class,
        'search2record/sms' => Record\SmsAction::class,

        'summonrecord/addcomment' => Record\AddComentAction::class,
        'summonrecord/addtag' => Record\AddTagAction::class,
        'summonrecord/ajaxtab' => Record\AjaxTabAction::class,
        'summonrecord/cite' => Record\CiteAction::class,
        'summonrecord/deletecomment' => Record\DeleteComentAction::class,
        'summonrecord/deletetag' => Record\DeleteTagAction::class,
        'summonrecord/email' => Record\EmailAction::class,
        'summonrecord/export' => Record\ExportAction::class,
        'summonrecord/hold' => Record\HoldAction::class,
        'summonrecord/home' => Record\HomeAction::class,
        'summonrecord/permalink' => Record\PermalinkAction::class,
        'summonrecord/rating' => Record\RatingAction::class,
        'summonrecord/rdf' => Record\RdfAction::class,
        'summonrecord/save' => Record\SaveAction::class,
        'summonrecord/sms' => Record\SmsAction::class,

        // Legacy WorldcatRecord actions:
        'worldcatrecord/addcomment' => Record\AddComentAction::class,
        'worldcatrecord/addtag' => Record\AddTagAction::class,
        'worldcatrecord/ajaxtab' => Record\AjaxTabAction::class,
        'worldcatrecord/cite' => Record\CiteAction::class,
        'worldcatrecord/deletecomment' => Record\DeleteComentAction::class,
        'worldcatrecord/deletetag' => Record\DeleteTagAction::class,
        'worldcatrecord/email' => Record\EmailAction::class,
        'worldcatrecord/export' => Record\ExportAction::class,
        'worldcatrecord/hold' => Record\HoldAction::class,
        'worldcatrecord/home' => Record\HomeAction::class,
        'worldcatrecord/permalink' => Record\PermalinkAction::class,
        'worldcatrecord/rating' => Record\RatingAction::class,
        'worldcatrecord/rdf' => Record\RdfAction::class,
        'worldcatrecord/save' => Record\SaveAction::class,
        'worldcatrecord/sms' => Record\SmsAction::class,

        'worldcat2record/addcomment' => Record\AddComentAction::class,
        'worldcat2record/addtag' => Record\AddTagAction::class,
        'worldcat2record/ajaxtab' => Record\AjaxTabAction::class,
        'worldcat2record/cite' => Record\CiteAction::class,
        'worldcat2record/deletecomment' => Record\DeleteComentAction::class,
        'worldcat2record/deletetag' => Record\DeleteTagAction::class,
        'worldcat2record/email' => Record\EmailAction::class,
        'worldcat2record/export' => Record\ExportAction::class,
        'worldcat2record/hold' => Record\HoldAction::class,
        'worldcat2record/home' => Record\HomeAction::class,
        'worldcat2record/permalink' => Record\PermalinkAction::class,
        'worldcat2record/rating' => Record\RatingAction::class,
        'worldcat2record/rdf' => Record\RdfAction::class,
        'worldcat2record/save' => Record\SaveAction::class,
        'worldcat2record/sms' => Record\SmsAction::class,

        'tag/deletetags' => Tag\DeleteTagsAction::class,
        'tag/userlist' => Tag\UserListAction::class,
    ];

    /**
     * Category aliases from default case to the actual case used.
     *
     * Required for action categories not following the format Uppercase letter + lowercase letters.
     *
     * @var array
     */
    protected $categoryAliases = [
        'Authorityrecord' => 'AuthorityRecord',
        'Browzine' => 'BrowZine',
        'Myresearch' => 'MyResearch',
        'Shortlink' => 'ShortLink',
    ];

    /**
     * Default plugin factories.
     *
     * Autowiring factory is automatically added for any auto-discovered class unless already specified here.
     *
     * @var array
     */
    protected $factories = [
    ];

    /**
     * Namespaces used for auto-discovery (excluding leading backslash).
     *
     * The namespaces are checked in order from first to last.
     *
     * @var array
     */
    protected $autoDiscoveryNamespaces = [
        __NAMESPACE__ => true,
    ];

    /**
     * Constructor.
     *
     * Make sure plugins are properly initialized.
     *
     * @param mixed $configOrContainerInstance Configuration or container instance
     * @param array $v3config                  If $configOrContainerInstance is a
     * container, this value will be passed to the parent constructor.
     */
    public function __construct(
        $configOrContainerInstance = null,
        array $v3config = []
    ) {
        $this->addAbstractFactory(AbstractAutowiringFactory::class);
        $this->addInitializer(ActionInitializer::class);
        parent::__construct($configOrContainerInstance, $v3config);
    }

    /**
     * Configure the plugin manager.
     *
     * @param array $config Plugin manager configuration
     *
     * @return static
     *
     * @throws ContainerModificationsNotAllowedException If the allow
     *     override flag has been toggled off, and a service instance
     *     exists for a given service.
     */
    public function configure(array $config)
    {
        parent::configure($config);
        $this->categoryAliases = ($config['category_aliases'] ?? []) + $this->categoryAliases;
        if ($namespaces = $config['autodiscovery_namespaces'] ?? null) {
            foreach ($namespaces as $ns => $active) {
                if ($active) {
                    $this->autoDiscoveryNamespaces[$ns] = true;
                } else {
                    unset($this->autoDiscoveryNamespaces[$ns]);
                }
            }
        }
        return $this;
    }

    /**
     * Get an action.
     *
     * @param class-string<InstanceType>|string $name    Service name of plugin to retrieve.
     * @param null|array<mixed>                 $options Options to use when creating the instance.
     *
     * @return mixed
     *
     * @throws ServiceNotFoundException If the manager does not have a service definition for the instance,
     * and the service is not auto-invokable.
     * @throws InvalidServiceException  If the plugin created is invalid for the plugin context.
     */
    public function get($name, ?array $options = null)
    {
        return parent::get($this->resolveAlias($name), $options);
    }

    /**
     * Check if an action is available.
     *
     * @param string|class-string $name Name
     *
     * @return bool
     */
    public function has($name)
    {
        return parent::has($this->resolveAlias($name));
    }

    /**
     * Given a category and action name, return the most appropriate action handler class name, or null if not
     * available.
     *
     * @param ?string $category Category name
     * @param ?string $action   Action name
     *
     * @return ?string
     */
    public function getActionHandlerName(?string $category, ?string $action): ?string
    {
        if (!$category && !$action) {
            return null;
        }
        $normalizedCategory = $category ? strtolower($category) : '';
        $normalizedAction = $action ? strtolower($action) : '';

        // Check for "category/action" first, then for category or action alone:
        if ($normalizedCategory && $normalizedAction && $this->has("$normalizedCategory/$normalizedAction")) {
            return "$normalizedCategory/$normalizedAction";
        } elseif ($this->has("$normalizedCategory$normalizedAction")) {
            return "$normalizedCategory$normalizedAction";
        }

        return null;
    }

    /**
     * Return the name of the base class or interface that plug-ins must conform
     * to.
     *
     * @return string
     */
    protected function getExpectedInterface()
    {
        return ActionInterface::class;
    }

    /**
     * Resolve alias using auto-discovery as required.
     *
     * @param string $alias Alias
     *
     * @return string
     */
    protected function resolveAlias(string $alias): string
    {
        if (null !== ($result = $this->aliases[$alias] ?? null)) {
            return $result;
        }

        $nameParts = array_map(
            fn ($s) => ucfirst(strtolower($s)),
            explode('/', $alias)
        );
        if (count($nameParts) > 1) {
            $nameParts[0] = $this->categoryAliases[$nameParts[0]] ?? $nameParts[0];
        }
        $actionClass = implode('\\', $nameParts) . 'Action';

        foreach (array_keys($this->autoDiscoveryNamespaces) as $ns) {
            $className = $ns . '\\' . $actionClass;
            if (class_exists($className)) {
                $this->aliases[$alias] = $className;
                // Explicitly set the factory so that discovered classes don't need the Autowire attribute:
                $this->factories[$className] ??= AutowiringFactory::class;
                return $className;
            }
        }
        return $alias;
    }
}
