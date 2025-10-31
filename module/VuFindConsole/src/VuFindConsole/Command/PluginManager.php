<?php

/**
 * Console command plugin manager
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2020.
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
 * @package  Console
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:ils_drivers Wiki
 */

namespace VuFindConsole\Command;

use Laminas\ServiceManager\Factory\InvokableFactory;

/**
 * Console command plugin manager
 *
 * @category VuFind
 * @package  Console
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:ils_drivers Wiki
 */
class PluginManager extends \VuFind\ServiceManager\AbstractPluginManager
{
    /**
     * Default plugin aliases.
     *
     * @var array
     */
    protected $aliases = [
        'compile/theme' => Compile\ThemeCommand::class,
        'generate/dynamicroute' => Generate\DynamicRouteCommand::class,
        'generate/extendclass' => Generate\ExtendClassCommand::class,
        'generate/extendservice' => Generate\ExtendServiceCommand::class,
        'generate/nontabrecordaction' => Generate\NonTabRecordActionCommand::class,
        'generate/plugin' => Generate\PluginCommand::class,
        'generate/recordroute' => Generate\RecordRouteCommand::class,
        'generate/staticroute' => Generate\StaticRouteCommand::class,
        'generate/theme' => Generate\ThemeCommand::class,
        'generate/thememixin' => Generate\ThemeMixinCommand::class,
        'harvest/harvest_oai' => Harvest\HarvestOaiCommand::class,
        'harvest/merge-marc' => Harvest\MergeMarcCommand::class,
        'import/import-csv' => Import\ImportCsvCommand::class,
        'import/import-xsl' => Import\ImportXslCommand::class,
        'import/webcrawl' => Import\WebCrawlCommand::class,
        'install/database' => Install\DatabaseCommand::class,
        'install/install' => Install\InstallCommand::class,
        'language/addusingtemplate' => Language\AddUsingTemplateCommand::class,
        'language/copystring' => Language\CopyStringCommand::class,
        'language/delete' => Language\DeleteCommand::class,
        'language/importlokalise' => Language\ImportLokaliseCommand::class,
        'language/normalize' => Language\NormalizeCommand::class,
        'menu/menu' => Menu\MenuCommand::class,
        'onlinepayment/monitor' => OnlinePayment\MonitorCommand::class,
        'scheduledsearch/notify' => ScheduledSearch\NotifyCommand::class,
        'upgrade/config' => Upgrade\ConfigCommand::class,
        'upgrade/database' => Upgrade\DatabaseCommand::class,
        'util/browscap' => Util\BrowscapCommand::class,
        'util/cleanuprecordcache' => Util\CleanUpRecordCacheCommand::class,
        'util/cleanup_record_cache' => Util\CleanUpRecordCacheCommand::class,
        'util/commit' => Util\CommitCommand::class,
        'util/createHierarchyTrees' => Util\CreateHierarchyTreesCommand::class,
        'util/dedupe' => Util\DedupeCommand::class,
        'util/deletes' => Util\DeletesCommand::class,
        'util/download' => Util\DownloadCommand::class,
        'util/expire_access_tokens' => Util\ExpireAccessTokensCommand::class,
        'util/expire_audit_events' => Util\ExpireAuditEventsCommand::class,
        'util/expire_auth_hashes' => Util\ExpireAuthHashesCommand::class,
        'util/expire_external_sessions' => Util\ExpireExternalSessionsCommand::class,
        'util/expire_login_tokens' => Util\ExpireLoginTokensCommand::class,
        'util/expire_resumption_tokens' => Util\ExpireOaiResumptionCommand::class,
        'util/expire_searches' => Util\ExpireSearchesCommand::class,
        'util/expire_sessions' => Util\ExpireSessionsCommand::class,
        'util/index_reserves' => Util\IndexReservesCommand::class,
        'util/lint_marc' => Util\LintMarcCommand::class,
        'util/optimize' => Util\OptimizeCommand::class,
        'util/purge_cached_record' => Util\PurgeCachedRecordCommand::class,
        'util/scssBuilder' => Util\ScssBuilderCommand::class,
        'util/sitemap' => Util\SitemapCommand::class,
        'util/suppressed' => Util\SuppressedCommand::class,
        'util/switch_db_hash' => Util\SwitchDbHashCommand::class,
        'util/update_resource_metadata' => Util\UpdateResourceMetadataCommand::class,
    ];

    /**
     * Constructor
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
        $this->addAbstractFactory(PluginFactory::class);
        parent::__construct($configOrContainerInstance, $v3config);
    }

    /**
     * Get a list of all available commands in the plugin manager.
     *
     * @return array
     */
    public function getCommandList()
    {
        return array_values($this->aliases);
    }

    /**
     * Return the name of the base class or interface that plug-ins must conform
     * to.
     *
     * @return string
     */
    protected function getExpectedInterface()
    {
        return \Symfony\Component\Console\Command\Command::class;
    }
}
