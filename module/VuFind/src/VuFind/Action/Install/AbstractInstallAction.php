<?php

/**
 * Abstract base class for install actions.
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2010, 2022.
 * Copyright (C) The National Library of Finland 2026.
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License version 2,
 * as published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.    See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, see
 * <https://www.gnu.org/licenses/>.
 *
 * @category VuFind
 * @package  Action
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */

namespace VuFind\Action\Install;

use VuFind\Config\ConfigManagerInterface;
use VuFind\Config\PathResolver;
use VuFind\Db\Service\PluginManager as DbServicePluginManager;
use VuFind\Db\Service\TagServiceInterface;
use VuFind\Db\Service\UserCardServiceInterface;
use VuFind\Db\Service\UserServiceInterface;
use VuFind\Http\ServerUrlHelper;
use VuFind\ILS\Connection;
use VuFind\ServiceManager\Factory\Autowire;
use VuFindHttp\HttpService;
use VuFindSearch\Command\RetrieveCommand;
use VuFindSearch\Service as SearchService;

use function defined;
use function function_exists;
use function is_callable;
use function sprintf;

/**
 * Abstract base class for install actions.
 *
 * @category VuFind
 * @package  Action
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */
abstract class AbstractInstallAction extends AbstractInstallOrUpgradeAction
{
    /**
     * Constructor.
     *
     * @param PathResolver             $pathResolver    Path resolver
     * @param ConfigManagerInterface   $configManager   Config manager
     * @param UserServiceInterface     $userService     User database service
     * @param UserCardServiceInterface $userCardService User card database service
     * @param array                    $config          VuFind configuration
     * @param Connection               $ilsConnection   ILS connection
     * @param SearchService            $searchService   Search service
     * @param ServerUrlHelper          $serverUrlHelper Server URL helper
     * @param HttpService              $httpService     HTTP service
     * @param TagServiceInterface      $tagService      Tags database service
     */
    public function __construct(
        PathResolver $pathResolver,
        ConfigManagerInterface $configManager,
        #[Autowire(container: DbServicePluginManager::class)]
        UserServiceInterface $userService,
        #[Autowire(container: DbServicePluginManager::class)]
        UserCardServiceInterface $userCardService,
        #[Autowire(config: 'config')]
        protected array $config,
        protected Connection $ilsConnection,
        protected SearchService $searchService,
        protected ServerUrlHelper $serverUrlHelper,
        protected HttpService $httpService,
        #[Autowire(container: DbServicePluginManager::class)]
        protected TagServiceInterface $tagService,
    ) {
        parent::__construct($pathResolver, $configManager, $userService, $userCardService, $config);
    }

    /**
     * Copy the basic configuration file into position and report success or failure.
     *
     * @return bool
     */
    protected function installBasicConfig(): bool
    {
        $config = $this->getForcedLocalConfigPath('config');
        if (!file_exists($config)) {
            // Suppress errors so we don't cause a fatal error if copy is disallowed.
            return @copy($this->getBaseConfigFilePath('config'), $config);
        }
        return true; // report success if file already exists
    }

    /**
     * Fix security configuration.
     *
     * @param array $config Existing VuFind configuration
     *
     * @return array Fixed configuration
     */
    protected function getFixedSecurityConfiguration(array $config): array
    {
        $fixedConfig = [];

        if (
            !($config['Authentication']['hash_passwords'] ?? false)
            || !($config['Authentication']['encrypt_ils_password'] ?? false)
        ) {
            $fixedConfig['Authentication']['hash_passwords'] = true;
            $fixedConfig['Authentication']['encrypt_ils_password'] = true;
        }
        // Only rewrite encryption key if we don't already have one:
        if (empty($config['Authentication']['ils_encryption_key'])) {
            [$algorithm, $key] = $this->getSecureAlgorithmAndKey();
            $fixedConfig['Authentication']['ils_encryption_algo'] = $algorithm;
            $fixedConfig['Authentication']['ils_encryption_key'] = $key;
        }

        return $fixedConfig;
    }

    /**
     * Change configuration.
     *
     * @param string $configName Config name
     * @param array  $config     Config to change
     *
     * @return void
     */
    protected function changeConfig(string $configName, array $config): void
    {
        $currentConfig = $this->configManager->getConfigArray($configName);
        foreach ($config as $section => $sectionConfig) {
            foreach ($sectionConfig as $setting => $value) {
                if ($value === null) {
                    unset($currentConfig[$section][$setting]);
                } else {
                    $currentConfig[$section][$setting] = $value;
                }
            }
        }
        $configLocation = $this->pathResolver->getForcedLocalConfigLocation($configName);
        $baseConfigLocation = file_exists($configLocation->getPath())
            ? $configLocation
            : $this->pathResolver->getBaseConfigLocation($configName);
        $this->configManager->writeConfig($configLocation, $currentConfig, $baseConfigLocation);
    }

    /**
     * Support method for check/fix dependencies code -- do we have a new enough
     * version of PHP?
     *
     * @return bool
     */
    protected function phpVersionIsNewEnough(): bool
    {
        // PHP_VERSION_ID was introduced in 5.2.7; if it's missing, we have a problem.
        if (!defined('PHP_VERSION_ID')) {
            return false;
        }

        // We need at least PHP version as defined in composer.json file:
        return PHP_VERSION_ID >= $this->getMinimalPhpVersionId();
    }

    /**
     * Get minimal PHP version required for VuFind to run.
     *
     * @return string
     */
    protected function getMinimalPhpVersion(): string
    {
        $composer = $this->getComposerJson();
        if (empty($composer)) {
            throw new \Exception('Cannot find composer.json');
        }
        $rawVersion = $composer['require']['php']
            ?? $composer['config']['platform']['php']
            ?? '';
        $version = preg_replace('/[^0-9. ]/', '', $rawVersion);
        if (empty($version) || !preg_match('/^[0-9]/', $version)) {
            throw new \Exception('Cannot parse PHP version from composer.json');
        }
        $versionParts = preg_split('/[. ]/', $version);
        $versionParts = array_pad($versionParts, 3, '0');
        return sprintf('%d.%d.%d', ...$versionParts);
    }

    /**
     * Get minimal PHP version ID required for VuFind to run.
     *
     * @return int
     */
    protected function getMinimalPhpVersionId(): int
    {
        $version = explode('.', $this->getMinimalPhpVersion());
        return $version[0] * 10000 + $version[1] * 100 + $version[2];
    }

    /**
     * Get composer.json data as array.
     *
     * @return array
     */
    protected function getComposerJson(): array
    {
        try {
            $composerJsonFileName = APPLICATION_PATH . '/composer.json';
            if (file_exists($composerJsonFileName)) {
                return json_decode(file_get_contents($composerJsonFileName), true);
            }
        } catch (\Throwable $exception) {
            return [];
        }
        return [];
    }

    /**
     * Try to establish a secure connection using HTTPS.
     *
     * @return bool
     */
    protected function testSslConnection(): bool
    {
        // Try to retrieve an SSL URL; if we're misconfigured, it will fail.
        try {
            $this->httpService->get('https://vufind.org');
            return true;
        } catch (\VuFindHttp\Exception\RuntimeException $e) {
            // Any exception means we have a problem!
            return false;
        }
    }

    /**
     * Support method to test the search service.
     *
     * @return void
     * @throws \Exception
     */
    protected function testSearchService(): void
    {
        // Try to retrieve an arbitrary ID -- this will fail if Solr is down:
        $command = new RetrieveCommand('Solr', '1');
        $this->searchService->invoke($command)->getResult();
    }

    /**
     * Get a list of missing extensions required for proper operation.
     *
     * @return array
     */
    protected function getMissingExtensions(): array
    {
        $missingExtensions = [];
        // Is the mbstring library missing?
        if (!function_exists('mb_substr')) {
            $missingExtensions[] = 'mbstring';
        }

        // Is the GD library missing?
        if (!is_callable('imagecreatefromstring')) {
            $missingExtensions[] = 'GD';
        }

        // Is the openssl library missing?
        if (!function_exists('openssl_encrypt')) {
            $missingExtensions[] = 'openssl';
        }

        // Is the XSL library missing?
        if (!class_exists('XSLTProcessor')) {
            $missingExtensions[] = 'XSL';
        }

        // Is the sodium extension missing?
        if (!defined('SODIUM_LIBRARY_VERSION')) {
            $missingExtensions[] = 'sodium';
        }

        return $missingExtensions;
    }

    /**
     * Get effective user name for the current process.
     *
     * @return ?string
     */
    protected function getProcessUserName(): ?string
    {
        if (
            function_exists('posix_getpwuid')
            && function_exists('posix_geteuid')
            && ($processUser = posix_getpwuid(posix_geteuid()))
        ) {
            return $processUser['name'];
        }
        return null;
    }
}
