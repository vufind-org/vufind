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

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use VuFind\Action\AbstractTemplateRenderingAction;
use VuFind\Cache\Manager as CacheManager;
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
use VuFindSearch\Service as SearchService;

use function count;
use function defined;
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
abstract class AbstractInstallAction extends AbstractTemplateRenderingAction
{
    /**
     * Constructor.
     *
     * @param CacheManager             $cacheManager    Cache manager
     * @param Connection               $ilsConnection   ILS connection
     * @param SearchService            $searchService   Search service
     * @param PathResolver             $pathResolver    Path resolver
     * @param ConfigManagerInterface   $configManager   Config manager
     * @param ServerUrlHelper          $serverUrlHelper Server URL helper
     * @param HttpService              $httpService     HTTP service
     * @param TagServiceInterface      $tagService      Tags database service
     * @param UserServiceInterface     $userService     User database service
     * @param UserCardServiceInterface $userCardService User card database service
     * @param array                    $config          VuFind configuration
     */
    public function __construct(
        protected CacheManager $cacheManager,
        protected Connection $ilsConnection,
        protected SearchService $searchService,
        protected PathResolver $pathResolver,
        protected ConfigManagerInterface $configManager,
        protected ServerUrlHelper $serverUrlHelper,
        protected HttpService $httpService,
        #[Autowire(container: DbServicePluginManager::class)]
        protected TagServiceInterface $tagService,
        #[Autowire(container: DbServicePluginManager::class)]
        protected UserServiceInterface $userService,
        #[Autowire(container: DbServicePluginManager::class)]
        protected UserCardServiceInterface $userCardService,
        #[Autowire(config: 'config')]
        protected array $config,
    ) {
        parent::__construct();
    }

    /**
     * Check that everything is in order for the action to be executed.
     *
     * May return a response or throw an exception if there are issues.
     *
     * @param ServerRequestInterface $request  Request
     * @param ResponseInterface      $response Response
     *
     * @return ?ResponseInterface
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    protected function checkPrerequisites(
        ServerRequestInterface $request,
        ResponseInterface $response
    ): ?ResponseInterface {
        // If auto-configuration is disabled, prevent any other action from being accessed:
        if (!($this->config['System']['autoConfigure'] ?? false)) {
            return $this->renderTemplate($request, $response, template: 'install/disabled');
        }
        return null;
    }

    /**
     * Get path to base configuration file.
     *
     * @param string $configName Configuration name
     *
     * @return string
     */
    protected function getBaseConfigFilePath(string $configName): string
    {
        return $this->pathResolver
            ->getBaseConfigLocation($configName)
            ->getPath();
    }

    /**
     * Get path to local configuration file (even if it does not yet exist).
     *
     * @param string $configName Configuration name
     *
     * @return string
     */
    protected function getForcedLocalConfigPath(string $configName): string
    {
        return $this->pathResolver
            ->getForcedLocalConfigLocation($configName)
            ->getPath();
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
     * Get an array containing an ILS encryption algorithm and a randomly generated
     * key.
     *
     * @return array
     */
    protected function getSecureAlgorithmAndKey(): array
    {
        // Make example hash for AES
        $alpha = 'abcdefghijklmnopqrstuvwxyz';
        $chars = str_repeat($alpha . strtoupper($alpha) . '0123456789,.@#%^&*', 4);
        return ['aes', substr(str_shuffle($chars), 0, 32)];
    }

    /**
     * Does the instance have secure database configuration and contents?
     *
     * @return bool
     */
    protected function hasSecureDatabase(): bool
    {
        // Are configuration settings missing?
        $status = ($this->config['Authentication']['hash_passwords'] ?? false)
            && ($this->config['Authentication']['encrypt_ils_password'] ?? false);

        // If we're correctly configured, check that the data in the database is ok:
        if ($status) {
            try {
                $userRows = $this->userService->getInsecureRows();
                $cardRows = $this->userCardService->getInsecureRows();
                $status = count($userRows) + count($cardRows) === 0;
            } catch (\Exception $e) {
                // Any exception means we have a problem!
                $status = false;
            }
        }

        return $status;
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
}
