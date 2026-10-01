<?php

/**
 * Abstract base class for install or upgrade actions.
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
use VuFind\Config\ConfigManagerInterface;
use VuFind\Config\PathResolver;
use VuFind\Db\Service\PluginManager as DbServicePluginManager;
use VuFind\Db\Service\UserCardServiceInterface;
use VuFind\Db\Service\UserServiceInterface;
use VuFind\ServiceManager\Factory\Autowire;

use function count;

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
abstract class AbstractInstallOrUpgradeAction extends AbstractTemplateRenderingAction
{
    /**
     * Constructor.
     *
     * @param PathResolver             $pathResolver    Path resolver
     * @param ConfigManagerInterface   $configManager   Config manager
     * @param UserServiceInterface     $userService     User database service
     * @param UserCardServiceInterface $userCardService User card database service
     * @param array                    $config          VuFind configuration
     */
    public function __construct(
        protected PathResolver $pathResolver,
        protected ConfigManagerInterface $configManager,
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
     * This method is executed in the very beginning of the action invocation before any permission checks etc.
     * It is meant for technical checks such as route-based configuration being correctly applied.
     * It may return a suitable response or throw an exception if there are issues.
     *
     * @param ServerRequestInterface $request  Request
     * @param ResponseInterface      $response Response
     *
     * @return ?ResponseInterface
     */
    protected function validateActionConfig(
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
}
