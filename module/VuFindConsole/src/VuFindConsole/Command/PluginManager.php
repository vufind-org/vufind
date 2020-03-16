<?php
/**
 * Console command plugin manager
 *
 * PHP version 7
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
        'generate/plugin' => Generate\PluginCommand::class,
        'generate/recordroute' => Generate\RecordRouteCommand::class,
        'generate/staticroute' => Generate\StaticRouteCommand::class,
        'harvest/merge-marc' => Harvest\MergeMarcCommand::class,
        'util/lint_marc' => Util\LintMarcCommand::class,
        /*
        'generate/extendclass' =>
            'generate extendclass [--extendfactory] [<class>] [<target>]',
        'generate/extendservice' =>
            'generate extendservice [<source>] [<target>]',
        'generate/nontabrecordaction' =>
            'generate nontabrecordaction [<newAction>] [<module>]',
        'generate/theme' => 'generate theme [<themename>]',
        'generate/thememixin' => 'generate thememixin [<name>]',
        'harvest/harvest_oai' => 'harvest harvest_oai [...params]',
        'import/import-xsl' =>
            'import import-xsl [--test-only] [--index=] [<xml>] [<properties>]',
        'import/webcrawl' => 'import webcrawl [--test-only] [--index=]',
        'language/addusingtemplate' =>
            'language addusingtemplate [<target>] [<template>]',
        'language/copystring' => 'language copystring [<source>] [<target>]',
        'language/delete' => 'language delete [<target>]',
        'language/normalize' => 'language normalize [<target>]',
        'scheduledsearch/notify' => 'scheduledsearch notify',
        'util/cleanup_record_cache' =>
            'util (cleanuprecordcache|cleanup_record_cache) [--help|-h]',
        'util/commit' => 'util commit [<core>]',
        'util/createHierarchyTrees' =>
            'util createHierarchyTrees [--skip-xml|-sx]
            [--skip-json|-sj] [<backend>] [--help|-h]',
        'util/cssBuilder' => 'util cssBuilder [...themes]',
        'util/deletes' =>
            'util deletes [--verbose] [<filename>] [<format>] [<index>]',
        'util/expire_auth_hashes' =>
            'util expire_auth_hashes [--help|-h] [--batch=] [--sleep=] [<daysOld>]',
        'util/expire_external_sessions' =>
            'util expire_external_sessions [--help|-h] [--batch=] [--sleep=]
             [<daysOld>]',
        'util/expire_searches' =>
            'util expire_searches [--help|-h] [--batch=] [--sleep=] [<daysOld>]',
        'util/expire_sessions' =>
            'util expire_sessions [--help|-h] [--batch=] [--sleep=] [<daysOld>]',
        'util/index_reserves' =>
            'util index_reserves [--help|-h] [-d=s] [-t=s] [-f=s]',
        'util/optimize' => 'util optimize [<core>]',
        'util/sitemap' =>
            'util sitemap [--help|-h] [--verbose] [--baseurl=s]
            [--basesitemapurl=s]',
        'util/suppressed' =>
            'util suppressed [--help|-h] [--authorities] [--outfile=s]',
        'util/switch_db_hash' => 'util switch_db_hash [<newhash>] [<newkey>]',
        */
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
        Generate\PluginCommand::class => Generate\PluginCommandFactory::class,
        Generate\RecordRouteCommand::class =>
            Generate\AbstractRouteCommandFactory::class,
        Generate\StaticRouteCommand::class =>
            Generate\AbstractRouteCommandFactory::class,
        Harvest\MergeMarcCommand::class => InvokableFactory::class,
        Util\LintMarcCommand::class => InvokableFactory::class,
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
    public function __construct($configOrContainerInstance = null,
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
