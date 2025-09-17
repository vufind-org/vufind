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
 * @author   Thomas Wagener <wagener@hebis.uni-frankfurt.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */

namespace VuFind\Config;

use Laminas\Log\LoggerAwareInterface;
use VuFind\Config\Location\ConfigDirectory;
use VuFind\Config\Location\ConfigLocationInterface;
use VuFind\Exception\FileAccess as FileAccessException;
use VuFind\Log\LoggerAwareTrait;

use function count;
use function dirname;
use function in_array;
use function is_array;

/**
 * Class to upgrade previous VuFind configurations to the current version
 *
 * @category VuFind
 * @package  Config
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Thomas Wagener <wagener@hebis.uni-frankfurt.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */
class Upgrade implements LoggerAwareInterface
{
    use LoggerAwareTrait;

    /**
     * Default full sections.
     *
     * @var array
     */
    protected array $defaultFullSections = [
        'Results', 'ResultsTop', 'Advanced', 'Author', 'CheckboxFacets',
        'HomePage', 'Facets', 'FacetsTop', 'Basic_Searches', 'Advanced_Searches',
        'Sort', 'Sorting', 'DefaultSortingByType',
    ];

    /**
     * Parsed old configurations
     *
     * @var array
     */
    protected array $oldConfigs = [];

    /**
     * Processed new configurations
     *
     * @var array
     */
    protected array $newConfigs = [];

    /**
     * Warnings generated during upgrade process
     *
     * @var array
     */
    protected array $warnings = [];

    /**
     * Have we modified permissions.ini?
     *
     * @var bool
     */
    protected bool $permissionsModified = false;

    /**
     * If writing of configuration is enabled (disabled in tests).
     *
     * @var bool
     */
    protected bool $writeMode = true;

    /**
     * Track which configs have already been written.
     */
    protected array $writtenConfig = [];

    /**
     * Constructor
     *
     * @param PathResolver           $pathResolver  Path Resolver
     * @param ConfigManagerInterface $configManager Config Manager
     */
    public function __construct(
        protected PathResolver $pathResolver,
        protected ConfigManagerInterface $configManager,
    ) {
    }

    /**
     * Set write mode
     *
     * @param bool $writeMode Write mode (true for enabling and false for disabling writing)
     *
     * @return void
     */
    public function setWriteMode(bool $writeMode): void
    {
        $this->writeMode = $writeMode;
    }

    /**
     * Run through all of the necessary upgrading.
     *
     * @param string $newVersion Version to upgrade to
     *
     * @return void
     */
    public function run(string $newVersion): void
    {
        // Reset upgrading state
        $this->permissionsModified = false;
        $this->writtenConfig = [];

        // Move RecordDataFormatter.ini to RecordDataFormatter/DefaultRecord.ini
        $this->moveRenamedConfig('RecordDataFormatter.ini', 'RecordDataFormatter/DefaultRecord.ini');

        // Load all old configurations:
        $this->loadConfigs();

        // Upgrade them one by one and write the results to disk; order is
        // important since in some cases, settings may migrate out of config.ini
        // and into other files.
        $this->applyOldSettings('searches');
        $this->upgradeConfig($newVersion);
        $this->upgradeFacetsAndCollection();
        $this->upgradeSearches();
        $this->upgradeSms();
        $this->upgradeEDS();
        $this->upgradeEPF();
        $this->upgradeSummon();
        $this->upgradePrimo();

        // The previous upgrade routines may have added values to permissions.ini,
        // so we should save it last. It doesn't have its own upgrade routine.
        $this->saveModifiedConfig('permissions', $this->permissionsModified);

        // Make sure to update any remaining configurations that were not explicitly updated above.
        foreach ($this->newConfigs as $configName => $newConfig) {
            if (!in_array($configName, $this->writtenConfig)) {
                $this->applyOldSettings($configName);
                $this->saveModifiedConfig($configName);
            }
        }
    }

    /**
     * Get processed configurations (used by test routines).
     *
     * @return array
     */
    public function getNewConfigs(): array
    {
        return $this->newConfigs;
    }

    /**
     * Get warning strings generated during upgrade process.
     *
     * @return array
     */
    public function getWarnings(): array
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
    protected function addWarning(string $msg): void
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
     *
     * @deprecated
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
     * Move configuration that was renamed to new location.
     *
     * @param string $from Relative path of source
     * @param string $to   Relative path of destination
     *
     * @return void
     */
    protected function moveRenamedConfig(string $from, string $to): void
    {
        $localConfigDir = $this->pathResolver->getLocalConfigDirPath();
        $fullFrom = $localConfigDir . '/' . $from;
        if ($this->writeMode && file_exists($fullFrom)) {
            $fullTo = $localConfigDir . '/' . $to;
            $toDir = dirname($fullTo);
            if (!is_dir($toDir)) {
                mkdir($toDir, recursive: true);
            }
            if (!file_exists($fullTo)) {
                rename($fullFrom, $fullTo);
            } else {
                $this->logWarning(
                    'Legacy configuration file ' . $fullFrom
                    . ' still exists besides updated file ' . $fullTo
                    . ' and should be removed!'
                );
            }
        }
    }

    /**
     * Load all of the user's existing configurations.
     *
     * @return void
     */
    protected function loadConfigs(): void
    {
        $baseConfigLocations = $this->pathResolver->getConfigLocationsInPath(
            $this->pathResolver->getBaseConfigDirPath()
        );
        $localConfigDir = $this->pathResolver->getLocalConfigDirPath();
        foreach ($baseConfigLocations as $configLocation) {
            $configName = $configLocation->getConfigName();
            if ($configLocation instanceof ConfigDirectory) {
                $subDirLocations = $this->pathResolver->getConfigLocationsInPath(
                    $configLocation->getPath()
                );
                foreach ($subDirLocations as $subDirLocation) {
                    $subConfigName = $configName . '/' . $subDirLocation->getConfigName();
                    $oldConfigLocation = $this->pathResolver->getMatchingConfigLocation(
                        $localConfigDir . '/' . $configName,
                        $subDirLocation->getConfigName()
                    );
                    $this->registerConfigToUpgrade($subConfigName, $subDirLocation, $oldConfigLocation);
                }
            } else {
                $oldConfigLocation = $this->pathResolver->getMatchingConfigLocation($localConfigDir, $configName);
                $this->registerConfigToUpgrade($configName, $configLocation, $oldConfigLocation);
            }
        }
    }

    /**
     * Load configuration used during upgrade.
     *
     * @param string                   $name        Identifier for the configuration
     * @param ConfigLocationInterface  $newLocation Location of new configuration
     * @param ?ConfigLocationInterface $oldLocation Optional location of old configuration
     *
     * @return void
     */
    protected function registerConfigToUpgrade(
        string $name,
        ConfigLocationInterface $newLocation,
        ?ConfigLocationInterface $oldLocation
    ): void {
        $this->oldConfigs[$name] = ($oldLocation !== null)
            ? $this->configManager->loadConfigFromLocation(
                $oldLocation,
                handleParentConfig: false
            ) : [];
        $this->newConfigs[$name] = $this->configManager->loadConfigFromLocation(
            $newLocation,
            handleParentConfig: false
        );
    }

    /**
     * Apply settings from an old configuration to a new configuration.
     *
     * @param string $configName   Name of the configuration being updated.
     * @param ?array $fullSections Array of section names that need to be fully
     * overridden (as opposed to overridden on a setting-by-setting basis).
     *
     * @return void
     */
    protected function applyOldSettings(string $configName, ?array $fullSections = null): void
    {
        foreach ($this->oldConfigs[$configName] as $section => $subsection) {
            if (in_array($section, $fullSections ?? $this->defaultFullSections)) {
                $this->newConfigs[$configName][$section] = $this->oldConfigs[$configName][$section];
            } else {
                foreach ($subsection as $key => $value) {
                    $this->newConfigs[$configName][$section][$key] = $value;
                }
            }
        }
    }

    /**
     * Save a modified configuration.
     *
     * @param string $configName    Name of config to write (contents will be
     * pulled from current state of object properties).
     * @param bool   $forceCreation Force the creation of the config even if it does not exist yet.
     *
     * @throws FileAccessException
     * @return void
     */
    protected function saveModifiedConfig(string $configName, bool $forceCreation = false): void
    {
        $this->writtenConfig[] = $configName;

        // don't write to files when write mode is disabled.
        if (!$this->writeMode) {
            return;
        }

        // If the source config is empty, there is usually no point in upgrading anything (the config doesn't exist).
        if (
            empty($this->oldConfigs[$configName])
            && !$forceCreation
        ) {
            return;
        }

        $configNameParts = explode('/', $configName, 2);
        $subDir = (count($configNameParts) > 1) ? '/' . $configNameParts[0] : '';
        $baseConfigLocation = $this->pathResolver->getMatchingConfigLocation(
            $this->pathResolver->getBaseConfigDirPath() . $subDir,
            $configNameParts[1] ?? $configName
        );

        $destinationLocation = clone $baseConfigLocation;
        $destinationLocation->setBasePath($this->pathResolver->getLocalConfigDirPath() . $subDir);
        $this->configManager->writeConfig($destinationLocation, $this->newConfigs[$configName], $baseConfigLocation);
    }

    /**
     * Check for invalid theme setting.
     *
     * @param string  $setting Name of setting in [Site] section to check.
     * @param ?string $default Default value to use if invalid option was found.
     *
     * @return void
     */
    protected function checkTheme(string $setting, ?string $default = null): void
    {
        // If a setting is not set, there is nothing to check:
        $theme = $this->newConfigs['config']['Site'][$setting] ?? null;
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
                unset($this->newConfigs['config']['Site'][$setting]);
            } else {
                $this->addWarning(
                    'WARNING: This version of VuFind does not support '
                    . "the {$theme} theme. Your config.ini [Site] {$setting} setting"
                    . " has been reset to the default: {$default}. You may need to "
                    . 'reimplement your custom theme.'
                );
                $this->newConfigs['config']['Site'][$setting] = $default;
            }
        }
    }

    /**
     * Add warnings if Amazon problems were found.
     *
     * @param array $config Configuration to check
     *
     * @return void
     */
    protected function checkAmazonConfig(array $config): void
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
     * @param string $newVersion Version to upgrade to
     *
     * @throws FileAccessException
     * @return void
     */
    protected function upgradeConfig(string $newVersion): void
    {
        // override new version's defaults with matching settings from old version:
        $this->applyOldSettings('config', []);

        // Set up reference for convenience (and shorter lines):
        $newConfig = & $this->newConfigs['config'];

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

        // Update generator if it contains a version number:
        if (
            isset($newConfig['Site']['generator'])
            && preg_match('/^VuFind (\d+\.?)+$/', $newConfig['Site']['generator'])
        ) {
            $newConfig['Site']['generator'] = 'VuFind ' . $newVersion;
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

        // Update searchspecs cache config
        if (isset($this->newConfigs['searches']['Cache'])) {
            if (!($this->newConfigs['searches']['Cache']['type'] ?? false)) {
                $newConfig['CacheConfigName_searchspecs']['disabled'] = true;
            }
            unset($this->newConfigs['searches']['Cache']);
        }

        // Translate obsolete permission settings:
        $this->upgradeAdminPermissions();

        // Deal with shard settings (which may have to be moved to another file):
        $this->upgradeShardSettings();

        // save the configuration
        $this->saveModifiedConfig('config');
    }

    /**
     * Translate obsolete permission settings.
     *
     * @return void
     */
    protected function upgradeAdminPermissions(): void
    {
        $config = & $this->newConfigs['config'];
        $permissions = & $this->newConfigs['permissions'];

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
    protected function changeArrayKey(array $array, string $old, string $new): array
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
    protected function renameFacet(string $old, string $new): void
    {
        $didWork = false;
        if (isset($this->newConfigs['facets']['Results'][$old])) {
            $this->newConfigs['facets']['Results'] = $this->changeArrayKey(
                $this->newConfigs['facets']['Results'],
                $old,
                $new
            );
            $didWork = true;
        }
        if (isset($this->newConfigs['Collection']['Facets'][$old])) {
            $this->newConfigs['Collection']['Facets'] = $this->changeArrayKey(
                $this->newConfigs['Collection']['Facets'],
                $old,
                $new
            );
            $didWork = true;
        }
        if ($didWork) {
            $this->newConfigs['facets']['LegacyFields'][$old] = $new;
        }
    }

    /**
     * Upgrade facets.ini and Collection.ini (since these are tied together).
     *
     * @throws FileAccessException
     * @return void
     */
    protected function upgradeFacetsAndCollection(): void
    {
        // we want to retain the old installation's various facet groups
        // exactly as-is
        $this->applyOldSettings('facets');
        $this->applyOldSettings('Collection');

        // fill in home page facets with advanced facets if missing:
        if (!isset($this->oldConfigs['facets']['HomePage'])) {
            $this->newConfigs['facets']['HomePage']
                = $this->newConfigs['facets']['Advanced'];
        }

        // rename changed facets
        $this->renameFacet('authorStr', 'author_facet');

        // save the configuration
        $this->saveModifiedConfig('facets');
        $this->saveModifiedConfig('Collection');
    }

    /**
     * Upgrade searches.ini.
     *
     * @throws FileAccessException
     * @return void
     */
    protected function upgradeSearches(): void
    {
        // fix call number sort settings:
        $newConfig = & $this->newConfigs['searches'];
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

        // save the configuration
        $this->saveModifiedConfig('searches');
    }

    /**
     * Upgrade sms.ini.
     *
     * @throws FileAccessException
     * @return void
     */
    protected function upgradeSms(): void
    {
        $this->applyOldSettings('sms', ['Carriers']);
        $this->saveModifiedConfig('sms');
    }

    /**
     * Upgrade EDS.ini.
     *
     * @throws FileAccessException
     * @return void
     */
    protected function upgradeEDS(): void
    {
        $this->upgradeEbsco('EDS');
    }

    /**
     * Upgrade EPF.ini.
     *
     * @throws FileAccessException
     * @return void
     */
    protected function upgradeEPF(): void
    {
        $this->upgradeEbsco('EPF');
    }

    /**
     * Upgrade EDS or EPF
     *
     * @param string $configName Config name
     *
     * @throws FileAccessException
     * @return void
     */
    protected function upgradeEbsco(string $configName): void
    {
        // we want to retain the old installation's search and facet settings
        // exactly as-is
        $this->applyOldSettings($configName);
        $this->applyOldSettings('RecordDataFormatter/' . $configName);

        // Fix default view settings in case they use the old style:
        $newBaseConfig = & $this->newConfigs[$configName];
        $newRecordDataFormatterConfig = & $this->newConfigs['RecordDataFormatter/' . $configName];
        $recordDataFormatterConfigModified = false;

        if (!str_contains($newBaseConfig['General']['default_view'], '_')) {
            $newBaseConfig['General']['default_view'] = 'list_' . $newBaseConfig['General']['default_view'];
        }

        // Move several settings to RecordDataFormatter/EDS
        foreach ($newBaseConfig['ItemCoreFilter']['excludeLabel'] ?? [] as $label) {
            $this->setEbscoItemFilter($newRecordDataFormatterConfig, 'CoreItems', 'Label', $label);
            $recordDataFormatterConfigModified = true;
        }
        foreach ($newBaseConfig['ItemCoreFilter']['excludeGroup'] ?? [] as $group) {
            $this->setEbscoItemFilter($newRecordDataFormatterConfig, 'CoreItems', 'Group', $group);
            $recordDataFormatterConfigModified = true;
        }
        unset($newBaseConfig['ItemCoreFilter']);

        foreach ($newBaseConfig['ItemResultListFilter']['excludeLabel'] ?? [] as $label) {
            $this->setEbscoItemFilter($newRecordDataFormatterConfig, 'ResultListItems', 'Label', $label);
            $recordDataFormatterConfigModified = true;
        }
        foreach ($newBaseConfig['ItemResultListFilter']['excludeGroup'] ?? [] as $group) {
            $this->setEbscoItemFilter($newRecordDataFormatterConfig, 'ResultListItems', 'Group', $group);
            $recordDataFormatterConfigModified = true;
        }
        unset($newBaseConfig['ItemResultListFilter']);

        if (
            isset($newBaseConfig['AuthorDisplay']['DetailPageFormat'])
            && $newBaseConfig['AuthorDisplay']['DetailPageFormat'] === 'Short'
        ) {
            $this->setEbscoItemFilter($newRecordDataFormatterConfig, 'CoreItems', 'Group', 'AuInfo');
            $newRecordDataFormatterConfig['CoreItems']['extraLineOptions'][] = 'CoreAuthors';
            $newRecordDataFormatterConfig['CoreAuthors']['multiAltDataMethod'] =
                'getPrimaryAuthorsWithHighlighting';
            $newRecordDataFormatterConfig['CoreAuthors']['limit'] =
                $newBaseConfig['AuthorDisplay']['ShortAuthorLimit'] ?? 3;
            $recordDataFormatterConfigModified = true;
        }

        if (
            isset($newBaseConfig['AuthorDisplay']['ResultListFormat'])
        ) {
            if ($newBaseConfig['AuthorDisplay']['ResultListFormat'] === 'Short') {
                $newRecordDataFormatterConfig['ResultListAuthors']['limit']
                    = $newBaseConfig['AuthorDisplay']['ShortAuthorLimit'] ?? 3;
            } else {
                unset($newRecordDataFormatterConfig['ResultListAuthors']['limit']);
                unset($newRecordDataFormatterConfig['ResultListAuthors']['multiAltDataMethod']);
            }
            $recordDataFormatterConfigModified = true;
        }
        unset($newBaseConfig['AuthorDisplay']);

        // save the configuration
        $this->saveModifiedConfig($configName);
        $this->saveModifiedConfig('RecordDataFormatter/' . $configName, $recordDataFormatterConfigModified);
    }

    /**
     * Set EBSCO item filter.
     *
     * @param array  $newRecordDataFormatterConfig New RecordDataFormatter config
     * @param string $section                      Section to change
     * @param string $lineIdentifierKey            Identifier key to filter
     * @param string $lineIdentifierValue          Identifier value to filter
     *
     * @return void
     */
    protected function setEbscoItemFilter(
        array &$newRecordDataFormatterConfig,
        string $section,
        string $lineIdentifierKey,
        string $lineIdentifierValue
    ): void {
        $filterSection = "{$section}_Filter_{$lineIdentifierKey}_$lineIdentifierValue";
        $newRecordDataFormatterConfig[$section]['extraLineOptions'][] = $filterSection;
        $newRecordDataFormatterConfig[$filterSection] = [
            'lineIdentifierKey' => $lineIdentifierKey,
            'lineIdentifierValue' => $lineIdentifierValue,
            'multiEnabled' => false,
        ];
    }

    /**
     * Upgrade Summon.ini.
     *
     * @throws FileAccessException
     * @return void
     */
    protected function upgradeSummon(): void
    {
        // If Summon is disabled in our current configuration, we don't need to
        // load any Summon-specific settings:
        if (!isset($this->newConfigs['config']['Summon']['apiKey'])) {
            return;
        }

        // we want to retain the old installation's search and facet settings
        // exactly as-is
        $this->applyOldSettings('Summon');

        // update permission settings
        $this->upgradeSummonPermissions();

        // save the configuration
        $this->saveModifiedConfig('Summon');
    }

    /**
     * Translate obsolete permission settings.
     *
     * @return void
     */
    protected function upgradeSummonPermissions(): void
    {
        $config = & $this->newConfigs['Summon'];
        $permissions = & $this->newConfigs['permissions'];
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
    protected function upgradePrimo(): void
    {
        // we want to retain the old installation's search and facet settings
        // exactly as-is
        $this->applyOldSettings('Primo');

        // update permission settings
        $this->upgradePrimoPermissions();

        // update server settings
        $this->upgradePrimoServerSettings();

        // save the configuration
        $this->saveModifiedConfig('Primo');
    }

    /**
     * Translate obsolete permission settings.
     *
     * @return void
     */
    protected function upgradePrimoPermissions(): void
    {
        $config = & $this->newConfigs['Primo'];
        $permissions = & $this->newConfigs['permissions'];
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
    protected function upgradePrimoServerSettings(): void
    {
        $config = & $this->newConfigs['Primo'];
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
    protected function upgradeShardSettings(): void
    {
        // move settings from config to searches:
        if (isset($this->newConfigs['config']['IndexShards'])) {
            $this->oldConfigs['searches']['IndexShards']
                = $this->newConfigs['config']['IndexShards'];
            unset($this->newConfigs['config']['IndexShards']);
        }
        if (isset($this->newConfigs['config']['ShardPreferences'])) {
            $this->oldConfigs['searches']['ShardPreferences']
                = $this->newConfigs['config']['ShardPreferences'];
            unset($this->newConfigs['config']['ShardPreferences']);
        }

        // move settings from facets.ini to searches.ini (merging StripFacets
        // setting with StripFields setting):
        if (isset($this->oldConfigs['facets']['StripFacets'])) {
            if (!isset($this->oldConfigs['searches']['StripFields'])) {
                $this->oldConfigs['searches']['StripFields'] = [];
            }
            foreach ($this->oldConfigs['facets']['StripFacets'] as $k => $v) {
                // If we already have values for the current key, merge and dedupe:
                if (isset($this->oldConfigs['searches']['StripFields'][$k])) {
                    $v .= ',' . $this->oldConfigs['searches']['StripFields'][$k];
                    $parts = explode(',', $v);
                    foreach ($parts as $i => $part) {
                        $parts[$i] = trim($part);
                    }
                    $v = implode(',', array_unique($parts));
                }
                $this->oldConfigs['searches']['StripFields'][$k] = $v;
            }
            unset($this->oldConfigs['facets']['StripFacets']);
        }
    }
}
