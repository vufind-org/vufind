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
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301  USA
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
        'install/install' => Install\InstallCommand::class,
        'language/addusingtemplate' => Language\AddUsingTemplateCommand::class,
        'language/copystring' => Language\CopyStringCommand::class,
        'language/delete' => Language\DeleteCommand::class,
        'language/importlokalise' => Language\ImportLokaliseCommand::class,
        'language/normalize' => Language\NormalizeCommand::class,
        'scheduledsearch/notify' => ScheduledSearch\NotifyCommand::class,
        'util/browscap' => Util\BrowscapCommand::class,
        'util/cleanuprecordcache' => Util\CleanUpRecordCacheCommand::class,
        'util/cleanup_record_cache' => Util\CleanUpRecordCacheCommand::class,
        'util/commit' => Util\CommitCommand::class,
        'util/createHierarchyTrees' => Util\CreateHierarchyTreesCommand::class,
        'util/dedupe' => Util\DedupeCommand::class,
        'util/deletes' => Util\DeletesCommand::class,
        'util/expire_access_tokens' => Util\ExpireAccessTokensCommand::class,
        'util/expire_auth_hashes' => Util\ExpireAuthHashesCommand::class,
        'util/expire_external_sessions' => Util\ExpireExternalSessionsCommand::class,
        'util/expire_login_tokens' => Util\ExpireLoginTokensCommand::class,
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
    ];

    /**
     * Default plugin factories.
     *
     * @var array
     */
    protected $factories = [
        Compile\ThemeCommand::class => Compile\ThemeCommandFactory::class,
        Generate\DynamicRouteCommand::class =>
            Generate\AbstractRouteCommandFactory::class,
        Generate\ExtendClassCommand::class =>
            Generate\AbstractContainerAwareCommandFactory::class,
        Generate\ExtendServiceCommand::class =>
            Generate\AbstractCommandFactory::class,
        Generate\NonTabRecordActionCommand::class =>
            Generate\NonTabRecordActionCommandFactory::class,
        Generate\PluginCommand::class =>
            Generate\AbstractContainerAwareCommandFactory::class,
        Generate\RecordRouteCommand::class =>
            Generate\AbstractRouteCommandFactory::class,
        Generate\StaticRouteCommand::class =>
            Generate\AbstractRouteCommandFactory::class,
        Generate\ThemeCommand::class =>
            Generate\ThemeCommandFactory::class,
        Generate\ThemeMixinCommand::class =>
            Generate\ThemeMixinCommandFactory::class,
        Harvest\MergeMarcCommand::class => InvokableFactory::class,
        Harvest\HarvestOaiCommand::class => Harvest\HarvestOaiCommandFactory::class,
        Import\ImportCsvCommand::class => Import\ImportCsvCommandFactory::class,
        Import\ImportXslCommand::class => Import\ImportXslCommandFactory::class,
        Import\WebCrawlCommand::class => Import\WebCrawlCommandFactory::class,
        Install\InstallCommand::class => InvokableFactory::class,
        Language\AddUsingTemplateCommand::class =>
            Language\AbstractCommandFactory::class,
        Language\CopyStringCommand::class => Language\AbstractCommandFactory::class,
        Language\DeleteCommand::class => Language\AbstractCommandFactory::class,
        Language\ImportLokaliseCommand::class => Language\AbstractCommandFactory::class,
        Language\NormalizeCommand::class => Language\AbstractCommandFactory::class,
        ScheduledSearch\NotifyCommand::class =>
            ScheduledSearch\NotifyCommandFactory::class,
        Util\BrowscapCommand::class => Util\BrowscapCommandFactory::class,
        Util\CleanUpRecordCacheCommand::class =>
            Util\CleanUpRecordCacheCommandFactory::class,
        Util\CommitCommand::class => Util\AbstractSolrCommandFactory::class,
        Util\CreateHierarchyTreesCommand::class =>
        Util\CreateHierarchyTreesCommandFactory::class,
        Util\DedupeCommand::class => InvokableFactory::class,
        Util\DeletesCommand::class => Util\AbstractSolrCommandFactory::class,
        Util\ExpireAccessTokensCommand::class =>
            Util\ExpireAccessTokensCommandFactory::class,
        Util\ExpireAuthHashesCommand::class =>
            Util\ExpireAuthHashesCommandFactory::class,
        Util\ExpireExternalSessionsCommand::class =>
            Util\ExpireExternalSessionsCommandFactory::class,
        Util\ExpireLoginTokensCommand::class =>
            Util\ExpireLoginTokensCommandFactory::class,
        Util\ExpireSearchesCommand::class =>
            Util\ExpireSearchesCommandFactory::class,
        Util\ExpireSessionsCommand::class =>
            Util\ExpireSessionsCommandFactory::class,
        Util\IndexReservesCommand::class =>
            Util\AbstractSolrAndIlsCommandFactory::class,
        Util\LintMarcCommand::class => InvokableFactory::class,
        Util\OptimizeCommand::class => Util\AbstractSolrCommandFactory::class,
        Util\PurgeCachedRecordCommand::class => Util\PurgeCachedRecordCommandFactory::class,
        Util\ScssBuilderCommand::class => Util\ScssBuilderCommandFactory::class,
        Util\SitemapCommand::class => Util\SitemapCommandFactory::class,
        Util\SuppressedCommand::class =>
            Util\AbstractSolrAndIlsCommandFactory::class,
        Util\SwitchDbHashCommand::class => Util\SwitchDbHashCommandFactory::class,
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
        //$this->addAbstractFactory(PluginFactory::class);
        parent::__construct($configOrContainerInstance, $v3config);
    }

    /**
     * Get a list of all available commands in the plugin manager.
     *
     * @return array
     */
    public function getCommandList()
    {
        return array_keys($this->factories);
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
