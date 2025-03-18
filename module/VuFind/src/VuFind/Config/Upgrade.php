<?php

/**
 * VF Configuration Upgrade Tool
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2010.
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
 * @package  Config
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */

namespace VuFind\Config;

use Composer\Semver\Comparator;
use VuFind\Config\Writer as ConfigWriter;
use VuFind\Exception\FileAccess as FileAccessException;

use function count;
use function in_array;
use function is_array;

/**
 * Class to upgrade previous VuFind configurations to the current version
 *
 * @category VuFind
 * @package  Config
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */
class Upgrade
{
    /**
     * Version we're upgrading from
     *
     * @var string
     */
    protected $from;

    /**
     * Version we're upgrading to
     *
     * @var string
     */
    protected $to;

    /**
     * Directory containing configurations to upgrade
     *
     * @var string
     */
    protected $oldDir;

    /**
     * Directory containing unmodified new configurations
     *
     * @var string
     */
    protected $rawDir;

    /**
     * Directory where new configurations should be written (null for test mode)
     *
     * @var string
     */
    protected $newDir;

    /**
     * Parsed old configurations
     *
     * @var array
     */
    protected $oldConfigs = [];

    /**
     * Processed new configurations
     *
     * @var array
     */
    protected $newConfigs = [];

    /**
     * Comments parsed from configuration files
     *
     * @var array
     */
    protected $comments = [];

    /**
     * Warnings generated during upgrade process
     *
     * @var array
     */
    protected $warnings = [];

    /**
     * Are we upgrading files in place rather than creating them?
     *
     * @var bool
     */
    protected $inPlaceUpgrade;

    /**
     * Have we modified permissions.ini?
     *
     * @var bool
     */
    protected $permissionsModified = false;

    /**
     * Constructor
     *
     * @param string $from   Version we're upgrading from.
     * @param string $to     Version we're upgrading to.
     * @param string $oldDir Directory containing old configurations.
     * @param string $rawDir Directory containing raw new configurations.
     * @param string $newDir Directory to write updated new configurations into
     * (leave null to disable writes -- used in test mode).
     */
    public function __construct($from, $to, $oldDir, $rawDir, $newDir = null)
    {
        $this->from = $from;
        $this->to = $to;
        $this->oldDir = $oldDir;
        $this->rawDir = $rawDir;
        $this->newDir = $newDir;
        $this->inPlaceUpgrade = ($this->oldDir == $this->newDir);
    }

    /**
     * Run through all of the necessary upgrading.
     *
     * @return void
     */
    public function run()
    {
        // Load all old configurations:
        $this->loadConfigs();

        // Upgrade them one by one and write the results to disk; order is
        // important since in some cases, settings may migrate out of config.ini
        // and into other files.
        $this->upgradeConfig();
        $this->upgradeAuthority();
        $this->upgradeFacetsAndCollection();
        $this->upgradeFulltext();
        $this->upgradeReserves();
        $this->upgradeSearches();
        $this->upgradeSitemap();
        $this->upgradeSms();
        $this->upgradeSummon();
        $this->upgradePrimo();

        // The previous upgrade routines may have added values to permissions.ini,
        // so we should save it last. It doesn't have its own upgrade routine.
        $this->saveModifiedConfig('permissions.ini');

        // The following routines load special configurations that were not
        // explicitly loaded by loadConfigs... note that some pieces only apply to
        // the 1.x upgrade!
        if (Comparator::lessThan($this->from, '2.0')) {
            $this->upgradeSolrMarc();
            $this->upgradeSearchSpecs();
        }
        $this->upgradeILS();
    }

    /**
     * Get processed configurations (used by test routines).
     *
     * @return array
     */
    public function getNewConfigs()
    {
        return $this->newConfigs;
    }

    /**
     * Get warning strings generated during upgrade process.
     *
     * @return array
     */
    public function getWarnings()
    {
        return $this->warnings;
    }

    /**
     * Add a warning message.
     *
     * @param string $msg Warning message.
     *
     * @return void
     */
    protected function addWarning($msg)
    {
        $this->warnings[] = $msg;
    }

    /**
     * Support function -- merge the contents of two arrays parsed from ini files.
     *
     * @param array $config_ini The base config array.
     * @param array $custom_ini Overrides to apply on top of the base array.
     *
     * @return array             The merged results.
     */
    public static function iniMerge($config_ini, $custom_ini)
    {
        foreach ($custom_ini as $k => $v) {
            // Make a recursive call if we need to merge array values into an
            // existing key... otherwise just drop the value in place.
            if (is_array($v) && isset($config_ini[$k])) {
                $config_ini[$k] = self::iniMerge($config_ini[$k], $custom_ini[$k]);
            } else {
                $config_ini[$k] = $v;
            }
        }
        return $config_ini;
    }

    /**
     * Load the old config.ini settings.
     *
     * @return void
     */
    protected function loadOldBaseConfig()
    {
        // Load the base settings:
        $oldIni = $this->oldDir . '/config.ini';
        $mainArray = file_exists($oldIni) ? parse_ini_file($oldIni, true) : [];

        // Merge in local overrides as needed. VuFind 2 structures configurations
        // differently, so people who used this mechanism will need to refactor
        // their configurations to take advantage of the new "local directory"
        // feature. For now, we'll just merge everything to avoid losing settings.
        if (
            isset($mainArray['Extra_Config'])
            && isset($mainArray['Extra_Config']['local_overrides'])
        ) {
            $file = trim(
                $this->oldDir . '/' . $mainArray['Extra_Config']['local_overrides']
            );
            $localOverride = @parse_ini_file($file, true);
            if ($localOverride) {
                $mainArray = self::iniMerge($mainArray, $localOverride);
            }
        }

        // Save the configuration to the appropriate place:
        $this->oldConfigs['config.ini'] = $mainArray;
    }

    /**
     * Find the path to the old configuration file.
     *
     * @param string $filename Filename of configuration file.
     *
     * @return string
     */
    protected function getOldConfigPath($filename)
    {
        // Check if the user has overridden the filename in the [Extra_Config]
        // section:
        $index = str_replace('.ini', '', $filename);
        if (isset($this->oldConfigs['config.ini']['Extra_Config'][$index])) {
            $path = $this->oldDir . '/'
                . $this->oldConfigs['config.ini']['Extra_Config'][$index];
            if (file_exists($path) && is_file($path)) {
                return $path;
            }
        }
        return $this->oldDir . '/' . $filename;
    }

    /**
     * Load all of the user's existing configurations.
     *
     * @return void
     */
    protected function loadConfigs()
    {
        // Configuration files to load. Note that config.ini must always be loaded
        // first so that getOldConfigPath can work properly!
        $configs = ['config.ini'];
        foreach (glob($this->rawDir . '/*.ini') as $ini) {
            $parts = explode('/', str_replace('\\', '/', $ini));
            $filename = array_pop($parts);
            if ($filename !== 'config.ini') {
                $configs[] = $filename;
            }
        }
        foreach ($configs as $config) {
            // Special case for config.ini, since we may need to overlay extra
            // settings:
            if ($config == 'config.ini') {
                $this->loadOldBaseConfig();
            } else {
                $path = $this->getOldConfigPath($config);
                $this->oldConfigs[$config] = file_exists($path)
                    ? parse_ini_file($path, true) : [];
            }
            $this->newConfigs[$config]
                = parse_ini_file($this->rawDir . '/' . $config, true);
            $this->comments[$config]
                = $this->extractComments($this->rawDir . '/' . $config);
        }
    }

    /**
     * Apply settings from an old configuration to a new configuration.
     *
     * @param string $filename     Name of the configuration being updated.
     * @param array  $fullSections Array of section names that need to be fully
     * overridden (as opposed to overridden on a setting-by-setting basis).
     *
     * @return void
     */
    protected function applyOldSettings($filename, $fullSections = [])
    {
        // First override all individual settings:
        foreach ($this->oldConfigs[$filename] as $section => $subsection) {
            foreach ($subsection as $key => $value) {
                $this->newConfigs[$filename][$section][$key] = $value;
            }
        }

        // Now override on a section-by-section basis where necessary:
        foreach ($fullSections as $section) {
            $this->newConfigs[$filename][$section]
                = $this->oldConfigs[$filename][$section] ?? [];
        }
    }

    /**
     * Save a modified configuration file.
     *
     * @param string $filename Name of config file to write (contents will be
     * pulled from current state of object properties).
     *
     * @throws FileAccessException
     * @return void
     */
    protected function saveModifiedConfig($filename)
    {
        if (null === $this->newDir) {   // skip write if no destination
            return;
        }

        // If we're doing an in-place upgrade, and the source file is empty,
        // there is no point in upgrading anything (the file doesn't exist).
        if (empty($this->oldConfigs[$filename]) && $this->inPlaceUpgrade) {
            // Special case: if we set up custom permissions, we need to
            // write the file even if it didn't previously exist.
            if (!$this->permissionsModified || $filename !== 'permissions.ini') {
                return;
            }
        }

        // If target file already exists, back it up:
        $outfile = $this->newDir . '/' . $filename;
        $bakfile = $outfile . '.bak.' . time();
        if (file_exists($outfile) && !copy($outfile, $bakfile)) {
            throw new FileAccessException(
                "Error: Could not copy {$outfile} to {$bakfile}."
            );
        }

        $writer = new ConfigWriter(
            $outfile,
            $this->newConfigs[$filename],
            $this->comments[$filename]
        );
        if (!$writer->save()) {
            throw new FileAccessException(
                "Error: Problem writing to {$outfile}."
            );
        }
    }

    /**
     * Save an unmodified configuration file -- copy the old version, unless it is
     * the same as the new version!
     *
     * @param string $filename Path to the old config file
     *
     * @throws FileAccessException
     * @return void
     */
    protected function saveUnmodifiedConfig($filename)
    {
        if (null === $this->newDir) {   // skip write if no destination
            return;
        }

        if ($this->inPlaceUpgrade) {    // skip write if doing in-place upgrade
            return;
        }

        // Figure out directories for all versions of this config file:
        $src = $this->getOldConfigPath($filename);
        $raw = $this->rawDir . '/' . $filename;
        $dest = $this->newDir . '/' . $filename;

        // Compare the source file against the raw file; if they happen to be the
        // same, we don't need to copy anything!
        if (
            file_exists($src) && file_exists($raw)
            && md5(file_get_contents($src)) === md5(file_get_contents($raw))
        ) {
            return;
        }

        // If we got this far, we need to copy the user's file into place:
        if (file_exists($src) && !copy($src, $dest)) {
            throw new FileAccessException(
                "Error: Could not copy {$src} to {$dest}."
            );
        }
    }

    /**
     * Check for invalid theme setting.
     *
     * @param string $setting Name of setting in [Site] section to check.
     * @param string $default Default value to use if invalid option was found.
     *
     * @return void
     */
    protected function checkTheme($setting, $default = null)
    {
        // If a setting is not set, there is nothing to check:
        $theme = $this->newConfigs['config.ini']['Site'][$setting] ?? null;
        if (empty($theme)) {
            return;
        }

        $parts = explode(',', $theme);
        $theme = trim($parts[0]);

        if (
            !file_exists(APPLICATION_PATH . '/themes/' . $theme)
            || !is_dir(APPLICATION_PATH . '/themes/' . $theme)
        ) {
            if ($default === null) {
                $this->addWarning(
                    "WARNING: This version of VuFind does not support the {$theme} "
                    . "theme. As such, we have disabled your {$setting} setting."
                );
                unset($this->newConfigs['config.ini']['Site'][$setting]);
            } else {
                $this->addWarning(
                    'WARNING: This version of VuFind does not support '
                    . "the {$theme} theme. Your config.ini [Site] {$setting} setting"
                    . " has been reset to the default: {$default}. You may need to "
                    . 'reimplement your custom theme.'
                );
                $this->newConfigs['config.ini']['Site'][$setting] = $default;
            }
        }
    }

    /**
     * Is this a default BulkExport options setting?
     *
     * @param string $eo Bulk export options
     *
     * @return bool
     */
    protected function isDefaultBulkExportOptions($eo)
    {
        if (Comparator::greaterThanOrEqualTo($this->from, '2.4')) {
            $default = 'MARC:MARCXML:EndNote:EndNoteWeb:RefWorks:BibTeX:RIS';
        } elseif (Comparator::greaterThanOrEqualTo($this->from, '2.0')) {
            $default = 'MARC:MARCXML:EndNote:EndNoteWeb:RefWorks:BibTeX';
        } elseif (Comparator::greaterThanOrEqualTo($this->from, '1.4')) {
            $default = 'MARC:MARCXML:EndNote:RefWorks:BibTeX';
        } elseif (Comparator::greaterThanOrEqualTo($this->from, '1.3')) {
            $default = 'MARC:EndNote:RefWorks:BibTeX';
        } elseif (Comparator::greaterThanOrEqualTo($this->from, '1.2')) {
            $default = 'MARC:EndNote:BibTeX';
        } else {
            $default = 'MARC:EndNote';
        }
        return $eo == $default;
    }

    /**
     * Add warnings if Amazon problems were found.
     *
     * @param array $config Configuration to check
     *
     * @return void
     */
    protected function checkAmazonConfig($config)
    {
        // Warn the user if they have Amazon enabled but do not have the appropriate
        // credentials set up.
        $hasAmazonReview = stristr($config['Content']['reviews'] ?? '', 'amazon');
        $hasAmazonCover = stristr($config['Content']['coverimages'] ?? '', 'amazon');
        if ($hasAmazonReview || $hasAmazonCover) {
            $this->addWarning(
                'WARNING: You have Amazon content enabled, but VuFind no longer '
                . 'supports it. You should remove Amazon references from config.ini.'
            );
        }
    }

    /**
     * Upgrade config.ini.
     *
     * @throws FileAccessException
     * @return void
     */
    protected function upgradeConfig()
    {
        // override new version's defaults with matching settings from old version:
        $this->applyOldSettings('config.ini');

        // Set up reference for convenience (and shorter lines):
        $newConfig = & $this->newConfigs['config.ini'];

        // If the [BulkExport] options setting is present and non-default, warn
        // the user about its deprecation.
        if (isset($newConfig['BulkExport']['options'])) {
            $default = $this->isDefaultBulkExportOptions(
                $newConfig['BulkExport']['options']
            );
            if (!$default) {
                $this->addWarning(
                    'The [BulkExport] options setting is deprecated; please '
                    . 'customize the [Export] section instead.'
                );
            }
            unset($newConfig['BulkExport']['options']);
        }

        // If [Statistics] is present, warn the user about its removal.
        if (isset($newConfig['Statistics'])) {
            $this->addWarning(
                'The Statistics module has been removed from VuFind. ' .
                'For usage tracking, please configure Google Analytics or Matomo.'
            );
            unset($newConfig['Statistics']);
        }

        // Warn the user about Amazon configuration issues:
        $this->checkAmazonConfig($newConfig);

        // Warn the user if they have enabled a deprecated Google API:
        if (isset($newConfig['GoogleSearch'])) {
            unset($newConfig['GoogleSearch']);
            $this->addWarning(
                'The [GoogleSearch] section of config.ini is no '
                . 'longer supported due to changes in Google APIs.'
            );
        }
        if (
            isset($newConfig['Content']['recordMap'])
            && 'google' == strtolower($newConfig['Content']['recordMap'])
        ) {
            unset($newConfig['Content']['recordMap']);
            unset($newConfig['Content']['googleMapApiKey']);
            $this->addWarning(
                'Google Maps is no longer a supported Content/recordMap option;'
                . ' please review your config.ini.'
            );
        }
        if (isset($newConfig['GoogleAnalytics']['apiKey'])) {
            if (
                !isset($newConfig['GoogleAnalytics']['universal'])
                || !$newConfig['GoogleAnalytics']['universal']
            ) {
                $this->addWarning(
                    'The [GoogleAnalytics] universal setting is off. See config.ini '
                    . 'for important information on how to upgrade your Analytics.'
                );
            }
        }

        // Upgrade CAPTCHA Options
        $legacySettingsMap = [
            'publicKey' => 'recaptcha_siteKey',
            'siteKey' => 'recaptcha_siteKey',
            'privateKey' => 'recaptcha_secretKey',
            'secretKey' => 'recaptcha_secretKey',
            'theme' => 'recaptcha_theme',
        ];
        $foundRecaptcha = false;
        foreach ($legacySettingsMap as $old => $new) {
            if (isset($newConfig['Captcha'][$old])) {
                $newConfig['Captcha'][$new]
                    = $newConfig['Captcha'][$old];
                unset($newConfig['Captcha'][$old]);
            }
            if (isset($newConfig['Captcha'][$new])) {
                $foundRecaptcha = true;
            }
        }
        if ($foundRecaptcha && !isset($newConfig['Captcha']['types'])) {
            $newConfig['Captcha']['types'] = ['recaptcha'];
        }

        // Warn the user about deprecated WorldCat settings:
        if (isset($newConfig['WorldCat'])) {
            unset($newConfig['WorldCat']);
            $this->addWarning(
                'The [WorldCat] section of config.ini has been removed following'
                . ' the shutdown of the v1 WorldCat search API; use WorldCat2.ini instead.'
            );
        }
        if (
            isset($newConfig['Record']['related'])
            && in_array('Editions', $newConfig['Record']['related'])
        ) {
            $newConfig['Record']['related'] = array_diff(
                $newConfig['Record']['related'],
                ['Editions']
            );
            $this->addWarning(
                'The Editions related record module is no longer '
                . 'supported due to OCLC\'s xID API shutdown.'
                . ' It has been removed from your settings.'
            );
        }

        // Upgrade Google Options:
        if (
            isset($newConfig['Content']['GoogleOptions'])
            && !is_array($newConfig['Content']['GoogleOptions'])
        ) {
            $newConfig['Content']['GoogleOptions']
                = ['link' => $newConfig['Content']['GoogleOptions']];
        }

        // Disable unused, obsolete setting:
        unset($newConfig['Index']['local']);

        // Warn the user if they are using an unsupported theme:
        $this->checkTheme('theme', 'bootprint3');
        $this->checkTheme('mobile_theme', null);

        // Translate legacy auth settings:
        if (strtolower($newConfig['Authentication']['method']) == 'db') {
            $newConfig['Authentication']['method'] = 'Database';
        }
        if (strtolower($newConfig['Authentication']['method']) == 'sip') {
            $newConfig['Authentication']['method'] = 'SIP2';
        }

        // Translate legacy session settings:
        $newConfig['Session']['type'] = ucwords(
            str_replace('session', '', strtolower($newConfig['Session']['type']))
        );
        if ($newConfig['Session']['type'] == 'Mysql') {
            $newConfig['Session']['type'] = 'Database';
        }

        // If we have granular database settings, disable the legacy version:
        $databaseKeys = array_keys($newConfig['Database'] ?? []);
        if (
            in_array('database_driver', $databaseKeys)
            && in_array('database_username', $databaseKeys)
            && (in_array('database_password', $databaseKeys) || in_array('database_password_file', $databaseKeys))
            && in_array('database_host', $databaseKeys)
            && in_array('database_name', $databaseKeys)
        ) {
            unset($newConfig['Database']['database']);
        }

        // Eliminate obsolete config override settings:
        unset($newConfig['Extra_Config']);

        // Update generator if it contains a version number:
        if (
            isset($newConfig['Site']['generator'])
            && preg_match('/^VuFind (\d+\.?)+$/', $newConfig['Site']['generator'])
        ) {
            $newConfig['Site']['generator'] = 'VuFind ' . $this->to;
        }

        // Update Syndetics config:
        if (isset($newConfig['Syndetics']['url'])) {
            $newConfig['Syndetics']['use_ssl']
                = (!str_contains($newConfig['Syndetics']['url'], 'https://'))
                ? '' : 1;
            unset($newConfig['Syndetics']['url']);
        }

        // Convert spellchecker 'simple' option
        if (
            // If 'simple' is set
            isset($newConfig['Spelling']['simple']) &&
            // and 'dictionaries' is set to default
            ($newConfig['Spelling']['dictionaries'] == ['default', 'basicSpell'])
        ) {
            $newConfig['Spelling']['dictionaries'] = $newConfig['Spelling']['simple']
                ? ['basicSpell'] : ['default', 'basicSpell'];
        }
        unset($newConfig['Spelling']['simple']);

        // Update mail config
        if (isset($newConfig['Mail']['require_login'])) {
            $require_login = $newConfig['Mail']['require_login'];
            unset($newConfig['Mail']['require_login']);
            $newConfig['Mail']['email_action'] = $require_login ? 'require_login' : 'enabled';
        }

        // Translate obsolete permission settings:
        $this->upgradeAdminPermissions();

        // Deal with shard settings (which may have to be moved to another file):
        $this->upgradeShardSettings();

        // save the file
        $this->saveModifiedConfig('config.ini');
    }

    /**
     * Translate obsolete permission settings.
     *
     * @return void
     */
    protected function upgradeAdminPermissions()
    {
        $config = & $this->newConfigs['config.ini'];
        $permissions = & $this->newConfigs['permissions.ini'];

        if (isset($config['AdminAuth'])) {
            $permissions['access.AdminModule'] = [];
            if (isset($config['AdminAuth']['ipRegEx'])) {
                $permissions['access.AdminModule']['ipRegEx']
                    = $config['AdminAuth']['ipRegEx'];
            }
            if (isset($config['AdminAuth']['userWhitelist'])) {
                $permissions['access.AdminModule']['username']
                    = $config['AdminAuth']['userWhitelist'];
            }
            // If no settings exist in config.ini, we grant access to everyone
            // by allowing both logged-in and logged-out roles.
            if (empty($permissions['access.AdminModule'])) {
                $permissions['access.AdminModule']['role'] = ['guest', 'loggedin'];
            }
            $permissions['access.AdminModule']['permission'] = 'access.AdminModule';
            $this->permissionsModified = true;

            // Remove any old settings remaining in config.ini:
            unset($config['AdminAuth']);
        }
    }

    /**
     * Change an array key.
     *
     * @param array  $array Array to rewrite
     * @param string $old   Old key name
     * @param string $new   New key name
     *
     * @return array
     */
    protected function changeArrayKey($array, $old, $new)
    {
        $newArr = [];
        foreach ($array as $k => $v) {
            if ($k === $old) {
                $k = $new;
            }
            $newArr[$k] = $v;
        }
        return $newArr;
    }

    /**
     * Support method for upgradeFacetsAndCollection() - change the name of
     * a facet field.
     *
     * @param string $old Old field name
     * @param string $new New field name
     *
     * @return void
     */
    protected function renameFacet($old, $new)
    {
        $didWork = false;
        if (isset($this->newConfigs['facets.ini']['Results'][$old])) {
            $this->newConfigs['facets.ini']['Results'] = $this->changeArrayKey(
                $this->newConfigs['facets.ini']['Results'],
                $old,
                $new
            );
            $didWork = true;
        }
        if (isset($this->newConfigs['Collection.ini']['Facets'][$old])) {
            $this->newConfigs['Collection.ini']['Facets'] = $this->changeArrayKey(
                $this->newConfigs['Collection.ini']['Facets'],
                $old,
                $new
            );
            $didWork = true;
        }
        if ($didWork) {
            $this->newConfigs['facets.ini']['LegacyFields'][$old] = $new;
        }
    }

    /**
     * Upgrade facets.ini and Collection.ini (since these are tied together).
     *
     * @throws FileAccessException
     * @return void
     */
    protected function upgradeFacetsAndCollection()
    {
        // we want to retain the old installation's various facet groups
        // exactly as-is
        $facetGroups = [
            'Results', 'ResultsTop', 'Advanced', 'Author', 'CheckboxFacets',
            'HomePage',
        ];
        $this->applyOldSettings('facets.ini', $facetGroups);
        $this->applyOldSettings('Collection.ini', ['Facets', 'Sort']);

        // fill in home page facets with advanced facets if missing:
        if (!isset($this->oldConfigs['facets.ini']['HomePage'])) {
            $this->newConfigs['facets.ini']['HomePage']
                = $this->newConfigs['facets.ini']['Advanced'];
        }

        // rename changed facets
        $this->renameFacet('authorStr', 'author_facet');

        // save the file
        $this->saveModifiedConfig('facets.ini');
        $this->saveModifiedConfig('Collection.ini');
    }

    /**
     * Update an old VuFind 1.x-style autocomplete handler name to the new style.
     *
     * @param string $name Name of module.
     *
     * @return string
     */
    protected function upgradeAutocompleteName($name)
    {
        if ($name == 'NoAutocomplete') {
            return 'None';
        }
        return str_replace('Autocomplete', '', $name);
    }

    /**
     * Upgrade searches.ini.
     *
     * @throws FileAccessException
     * @return void
     */
    protected function upgradeSearches()
    {
        // we want to retain the old installation's Basic/Advanced search settings
        // and sort settings exactly as-is
        $groups = [
            'Basic_Searches', 'Advanced_Searches', 'Sorting', 'DefaultSortingByType',
        ];
        $this->applyOldSettings('searches.ini', $groups);

        // Fix autocomplete settings in case they use the old style:
        $newConfig = & $this->newConfigs['searches.ini'];
        if (isset($newConfig['Autocomplete']['default_handler'])) {
            $newConfig['Autocomplete']['default_handler']
                = $this->upgradeAutocompleteName(
                    $newConfig['Autocomplete']['default_handler']
                );
        }
        if (isset($newConfig['Autocomplete_Types'])) {
            foreach ($newConfig['Autocomplete_Types'] as $k => $v) {
                $parts = explode(':', $v);
                $parts[0] = $this->upgradeAutocompleteName($parts[0]);
                $newConfig['Autocomplete_Types'][$k] = implode(':', $parts);
            }
        }

        // fix call number sort settings:
        if (isset($newConfig['Sorting']['callnumber'])) {
            $newConfig['Sorting']['callnumber-sort']
                = $newConfig['Sorting']['callnumber'];
            unset($newConfig['Sorting']['callnumber']);
        }
        if (isset($newConfig['DefaultSortingByType'])) {
            foreach ($newConfig['DefaultSortingByType'] as & $v) {
                if ($v === 'callnumber') {
                    $v = 'callnumber-sort';
                }
            }
        }
        $this->upgradeSpellingSettings('searches.ini', ['CallNumber', 'WorkKeys']);

        // save the file
        $this->saveModifiedConfig('searches.ini');
    }

    /**
     * Upgrade spelling settings to account for refactoring of spelling as a
     * recommendation module starting in release 2.4.
     *
     * @param string $ini  .ini file to modify
     * @param array  $skip Keys to skip within [TopRecommendations]
     *
     * @return void
     */
    protected function upgradeSpellingSettings($ini, $skip = [])
    {
        // Turn on the spelling recommendations if we're upgrading from a version
        // prior to 2.4.
        if (Comparator::lessThan($this->from, '2.4')) {
            // Fix defaults in general section:
            $cfg = & $this->newConfigs[$ini]['General'];
            $keys = ['default_top_recommend', 'default_noresults_recommend'];
            foreach ($keys as $key) {
                if (!isset($cfg[$key])) {
                    $cfg[$key] = [];
                }
                if (!in_array('SpellingSuggestions', $cfg[$key])) {
                    $cfg[$key][] = 'SpellingSuggestions';
                }
            }

            // Fix settings in [TopRecommendations]
            $cfg = & $this->newConfigs[$ini]['TopRecommendations'];
            // Add SpellingSuggestions to all non-skipped handlers:
            foreach ($cfg as $key => & $value) {
                if (
                    !in_array($key, $skip)
                    && !in_array('SpellingSuggestions', $value)
                ) {
                    $value[] = 'SpellingSuggestions';
                }
            }
            // Define handlers with no spelling support as the default minus the
            // Spelling option:
            foreach ($skip as $key) {
                if (!isset($cfg[$key])) {
                    $cfg[$key] = array_diff(
                        $this->newConfigs[$ini]['General']['default_top_recommend'],
                        ['SpellingSuggestions']
                    );
                }
            }
        }
    }

    /**
     * Upgrade fulltext.ini.
     *
     * @throws FileAccessException
     * @return void
     */
    protected function upgradeFulltext()
    {
        $this->saveUnmodifiedConfig('fulltext.ini');
    }

    /**
     * Upgrade sitemap.ini.
     *
     * @throws FileAccessException
     * @return void
     */
    protected function upgradeSitemap()
    {
        $this->saveUnmodifiedConfig('sitemap.ini');
    }

    /**
     * Upgrade sms.ini.
     *
     * @throws FileAccessException
     * @return void
     */
    protected function upgradeSms()
    {
        $this->applyOldSettings('sms.ini', ['Carriers']);
        $this->saveModifiedConfig('sms.ini');
    }

    /**
     * Upgrade authority.ini.
     *
     * @throws FileAccessException
     * @return void
     */
    protected function upgradeAuthority()
    {
        // we want to retain the old installation's search and facet settings
        // exactly as-is
        $groups = [
            'Facets', 'Basic_Searches', 'Advanced_Searches', 'Sorting',
        ];
        $this->applyOldSettings('authority.ini', $groups);

        // save the file
        $this->saveModifiedConfig('authority.ini');
    }

    /**
     * Upgrade reserves.ini.
     *
     * @throws FileAccessException
     * @return void
     */
    protected function upgradeReserves()
    {
        // If Reserves module is disabled, don't bother updating config:
        if (
            !isset($this->newConfigs['config.ini']['Reserves']['search_enabled'])
            || !$this->newConfigs['config.ini']['Reserves']['search_enabled']
        ) {
            return;
        }

        // we want to retain the old installation's search and facet settings
        // exactly as-is
        $groups = [
            'Facets', 'Basic_Searches', 'Advanced_Searches', 'Sorting',
        ];
        $this->applyOldSettings('reserves.ini', $groups);

        // save the file
        $this->saveModifiedConfig('reserves.ini');
    }

    /**
     * Upgrade Summon.ini.
     *
     * @throws FileAccessException
     * @return void
     */
    protected function upgradeSummon()
    {
        // If Summon is disabled in our current configuration, we don't need to
        // load any Summon-specific settings:
        if (!isset($this->newConfigs['config.ini']['Summon']['apiKey'])) {
            return;
        }

        // we want to retain the old installation's search and facet settings
        // exactly as-is
        $groups = [
            'Facets', 'FacetsTop', 'Basic_Searches', 'Advanced_Searches', 'Sorting',
        ];
        $this->applyOldSettings('Summon.ini', $groups);

        // Turn on advanced checkbox facets if we're upgrading from a version
        // prior to 2.3.
        if (Comparator::lessThan($this->from, '2.3')) {
            $cfg = & $this->newConfigs['Summon.ini']['Advanced_Facet_Settings'];
            $specialFacets = $cfg['special_facets'] ?? null;
            if (empty($specialFacets)) {
                $cfg['special_facets'] = 'checkboxes:Summon';
            } elseif (!str_contains('checkboxes', (string)$specialFacets)) {
                $cfg['special_facets'] .= ',checkboxes:Summon';
            }
        }

        // update permission settings
        $this->upgradeSummonPermissions();

        $this->upgradeSpellingSettings('Summon.ini');

        // save the file
        $this->saveModifiedConfig('Summon.ini');
    }

    /**
     * Translate obsolete permission settings.
     *
     * @return void
     */
    protected function upgradeSummonPermissions()
    {
        $config = & $this->newConfigs['Summon.ini'];
        $permissions = & $this->newConfigs['permissions.ini'];
        if (isset($config['Auth'])) {
            $permissions['access.SummonExtendedResults'] = [];
            if (
                isset($config['Auth']['check_login'])
                && $config['Auth']['check_login']
            ) {
                $permissions['access.SummonExtendedResults']['role'] = ['loggedin'];
            }
            if (isset($config['Auth']['ip_range'])) {
                $permissions['access.SummonExtendedResults']['ipRegEx']
                    = $config['Auth']['ip_range'];
            }
            if (!empty($permissions['access.SummonExtendedResults'])) {
                $permissions['access.SummonExtendedResults']['boolean'] = 'OR';
                $permissions['access.SummonExtendedResults']['permission']
                    = 'access.SummonExtendedResults';
                $this->permissionsModified = true;
            } else {
                unset($permissions['access.SummonExtendedResults']);
            }

            // Remove any old settings remaining in Summon.ini:
            unset($config['Auth']);
        }
    }

    /**
     * Upgrade Primo.ini.
     *
     * @throws FileAccessException
     * @return void
     */
    protected function upgradePrimo()
    {
        // we want to retain the old installation's search and facet settings
        // exactly as-is
        $groups = [
            'Facets', 'FacetsTop', 'Basic_Searches', 'Advanced_Searches', 'Sorting',
        ];
        $this->applyOldSettings('Primo.ini', $groups);

        // update permission settings
        $this->upgradePrimoPermissions();

        // update server settings
        $this->upgradePrimoServerSettings();

        // save the file
        $this->saveModifiedConfig('Primo.ini');
    }

    /**
     * Translate obsolete permission settings.
     *
     * @return void
     */
    protected function upgradePrimoPermissions()
    {
        $config = & $this->newConfigs['Primo.ini'];
        $permissions = & $this->newConfigs['permissions.ini'];
        if (
            isset($config['Institutions']['code'])
            && isset($config['Institutions']['regex'])
        ) {
            $codes = $config['Institutions']['code'];
            $regex = $config['Institutions']['regex'];
            if (count($regex) != count($codes)) {
                $this->addWarning(
                    'Mismatched code/regex counts in Primo.ini [Institutions].'
                );
            }

            // Map parallel arrays into code => array of regexes and detect
            // wildcard regex to treat as default code.
            $map = [];
            $default = null;
            foreach ($codes as $i => $code) {
                if ($regex[$i] == '/.*/') {
                    $default = $code;
                } else {
                    $map[$code] = !isset($map[$code])
                        ? [$regex[$i]]
                        : array_merge($map[$code], [$regex[$i]]);
                }
            }
            foreach ($map as $code => $regexes) {
                $perm = "access.PrimoInstitution.$code";
                $config['Institutions']["onCampusRule['$code']"] = $perm;
                $permissions[$perm] = [
                    'ipRegEx' => count($regexes) == 1 ? $regexes[0] : $regexes,
                    'permission' => $perm,
                ];
                $this->permissionsModified = true;
            }
            if (null !== $default) {
                $config['Institutions']['defaultCode'] = $default;
            }

            // Remove any old settings remaining in Primo.ini:
            unset($config['Institutions']['code']);
            unset($config['Institutions']['regex']);
        }
    }

    /**
     * Translate obsolete server settings.
     *
     * @return void
     */
    protected function upgradePrimoServerSettings()
    {
        $config = & $this->newConfigs['Primo.ini'];
        // Convert apiId to url
        if (isset($config['General']['apiId'])) {
            $url = 'http://' . $config['General']['apiId']
                . '.hosted.exlibrisgroup.com';
            if (isset($config['General']['port'])) {
                $url .= ':' . $config['General']['port'];
            } else {
                $url .= ':1701';
            }

            $config['General']['url'] = $url;

            // Remove any old settings remaining in Primo.ini:
            unset($config['General']['apiId']);
            unset($config['General']['port']);
        }
    }

    /**
     * Does the specified properties file contain any meaningful
     * (non-empty/non-comment) lines?
     *
     * @param string $src File to check
     *
     * @return bool
     */
    protected function fileContainsMeaningfulLines($src)
    {
        // Does the file contain any meaningful lines?
        foreach (file($src) as $line) {
            $line = trim($line);
            if ('' !== $line && !str_starts_with($line, '#')) {
                return true;
            }
        }
        return false;
    }

    /**
     * Upgrade SolrMarc configurations.
     *
     * @throws FileAccessException
     * @return void
     */
    protected function upgradeSolrMarc()
    {
        if (null === $this->newDir) {   // skip this step if no write destination
            return;
        }

        // Is there a marc_local.properties file?
        $src = realpath($this->oldDir . '/../../import/marc_local.properties');
        if (empty($src) || !file_exists($src)) {
            return;
        }

        // Copy the file if it contains customizations:
        if ($this->fileContainsMeaningfulLines($src)) {
            $dest = realpath($this->newDir . '/../../import')
                . '/marc_local.properties';
            if (!copy($src, $dest) || !file_exists($dest)) {
                throw new FileAccessException(
                    "Cannot copy {$src} to {$dest}."
                );
            }
        }
    }

    /**
     * Upgrade .yaml configurations.
     *
     * @throws FileAccessException
     * @return void
     */
    protected function upgradeSearchSpecs()
    {
        if (null === $this->newDir) {   // skip this step if no write destination
            return;
        }

        // VuFind 1.x uses *_local.yaml files as overrides; VuFind 2.x uses files
        // with the same filename in the local directory. Copy any old override
        // files into the new expected location:
        $files = ['searchspecs', 'authsearchspecs', 'reservessearchspecs'];
        foreach ($files as $file) {
            $old = $this->oldDir . '/' . $file . '_local.yaml';
            $new = $this->newDir . '/' . $file . '.yaml';
            if (file_exists($old)) {
                if (!copy($old, $new)) {
                    throw new FileAccessException(
                        "Cannot copy {$old} to {$new}."
                    );
                }
            }
        }
    }

    /**
     * Upgrade ILS driver configuration.
     *
     * @throws FileAccessException
     * @return void
     */
    protected function upgradeILS()
    {
        $driver = $this->newConfigs['config.ini']['Catalog']['driver'] ?? '';
        if (empty($driver)) {
            $this->addWarning('WARNING: Could not find ILS driver setting.');
        } elseif ('Sample' == $driver) {
            // No configuration file for Sample driver
        } elseif ('AdminScripts' == $driver) {
            // Prevent abuse if upgrade process is hijacked
        } elseif (!file_exists($this->oldDir . '/' . $driver . '.ini')) {
            $this->addWarning(
                "WARNING: Could not find {$driver}.ini file; "
                . 'check your ILS driver configuration.'
            );
        } else {
            $this->saveUnmodifiedConfig($driver . '.ini');
        }

        // If we're set to load NoILS.ini on failure, copy that over as well:
        if (
            isset($this->newConfigs['config.ini']['Catalog']['loadNoILSOnFailure'])
            && $this->newConfigs['config.ini']['Catalog']['loadNoILSOnFailure']
        ) {
            // If NoILS is also the main driver, we don't need to copy it twice:
            if ($driver != 'NoILS') {
                $this->saveUnmodifiedConfig('NoILS.ini');
            }
        }
    }

    /**
     * Upgrade shard settings (they have moved to a different config file, so
     * this is handled as a separate method so that all affected settings are
     * addressed in one place.
     *
     * This gets called from updateConfig(), which gets called before other
     * configuration upgrade routines. This means that we need to modify the
     * config.ini settings in the newConfigs property (since it is currently
     * being worked on and will be written to disk shortly), but we need to
     * modify the searches.ini/facets.ini settings in the oldConfigs property
     * (because they have not been processed yet).
     *
     * @return void
     */
    protected function upgradeShardSettings()
    {
        // move settings from config.ini to searches.ini:
        if (isset($this->newConfigs['config.ini']['IndexShards'])) {
            $this->oldConfigs['searches.ini']['IndexShards']
                = $this->newConfigs['config.ini']['IndexShards'];
            unset($this->newConfigs['config.ini']['IndexShards']);
        }
        if (isset($this->newConfigs['config.ini']['ShardPreferences'])) {
            $this->oldConfigs['searches.ini']['ShardPreferences']
                = $this->newConfigs['config.ini']['ShardPreferences'];
            unset($this->newConfigs['config.ini']['ShardPreferences']);
        }

        // move settings from facets.ini to searches.ini (merging StripFacets
        // setting with StripFields setting):
        if (isset($this->oldConfigs['facets.ini']['StripFacets'])) {
            if (!isset($this->oldConfigs['searches.ini']['StripFields'])) {
                $this->oldConfigs['searches.ini']['StripFields'] = [];
            }
            foreach ($this->oldConfigs['facets.ini']['StripFacets'] as $k => $v) {
                // If we already have values for the current key, merge and dedupe:
                if (isset($this->oldConfigs['searches.ini']['StripFields'][$k])) {
                    $v .= ',' . $this->oldConfigs['searches.ini']['StripFields'][$k];
                    $parts = explode(',', $v);
                    foreach ($parts as $i => $part) {
                        $parts[$i] = trim($part);
                    }
                    $v = implode(',', array_unique($parts));
                }
                $this->oldConfigs['searches.ini']['StripFields'][$k] = $v;
            }
            unset($this->oldConfigs['facets.ini']['StripFacets']);
        }
    }

    /**
     * Read the specified file and return an associative array of this format
     * containing all comments extracted from the file:
     *
     * [
     *   'sections' => array
     *     'section_name_1' => array
     *       'before' => string ("Comments found at the beginning of this section")
     *       'inline' => string ("Comments found at the end of the section's line")
     *       'settings' => array
     *         'setting_name_1' => array
     *           'before' => string ("Comments found before this setting")
     *           'inline' => string ("Comments found at the end of setting's line")
     *           ...
     *         'setting_name_n' => array (same keys as setting_name_1)
     *        ...
     *      'section_name_n' => array (same keys as section_name_1)
     *   'after' => string ("Comments found at the very end of the file")
     * ]
     *
     * @param string $filename Name of ini file to read.
     *
     * @return array           Associative array as described above.
     */
    protected function extractComments($filename)
    {
        $lines = file($filename);

        // Initialize our return value:
        $retVal = ['sections' => [], 'after' => ''];

        // Initialize variables for tracking status during parsing:
        $section = $comments = '';

        foreach ($lines as $line) {
            // To avoid redundant processing, create a trimmed version of the current
            // line:
            $trimmed = trim($line);

            // Is the current line a comment?  If so, add to the currentComments
            // string. Note that we treat blank lines as comments.
            if ('' === $trimmed || str_starts_with($trimmed, ';')) {
                $comments .= $line;
            } elseif (
                str_starts_with($trimmed, '[')
                && ($closeBracket = strpos($trimmed, ']')) > 1
            ) {
                // Is the current line the start of a section?  If so, create the
                // appropriate section of the return value:
                $section = substr($trimmed, 1, $closeBracket - 1);
                if ('' !== $section) {
                    // Grab comments at the end of the line, if any:
                    if (($semicolon = strpos($trimmed, ';')) !== false) {
                        $inline = trim(substr($trimmed, $semicolon));
                    } else {
                        $inline = '';
                    }
                    $retVal['sections'][$section] = [
                        'before' => $comments,
                        'inline' => $inline,
                        'settings' => []];
                    $comments = '';
                }
            } elseif (($equals = strpos($trimmed, '=')) !== false) {
                // Is the current line a setting?  If so, add to the return value:
                $set = trim(substr($trimmed, 0, $equals));
                $set = trim(str_replace('[]', '', $set));
                if ('' !== $section && '' !== $set) {
                    // Grab comments at the end of the line, if any:
                    if (($semicolon = strpos($trimmed, ';')) !== false) {
                        $inline = trim(substr($trimmed, $semicolon));
                    } else {
                        $inline = '';
                    }
                    // Currently, this data structure doesn't support arrays very
                    // well, since it can't distinguish which line of the array
                    // corresponds with which comments. For now, we just append all
                    // the preceding and inline comments together for arrays.  Since
                    // we rarely use arrays in the config.ini file, this isn't a big
                    // concern, but we should improve it if we ever need to.
                    if (!isset($retVal['sections'][$section]['settings'][$set])) {
                        $retVal['sections'][$section]['settings'][$set]
                            = ['before' => $comments, 'inline' => $inline];
                    } else {
                        $retVal['sections'][$section]['settings'][$set]['before']
                            .= $comments;
                        $retVal['sections'][$section]['settings'][$set]['inline']
                            .= "\n" . $inline;
                    }
                    $comments = '';
                }
            }
        }

        // Store any leftover comments following the last setting:
        $retVal['after'] = $comments;

        return $retVal;
    }
}
