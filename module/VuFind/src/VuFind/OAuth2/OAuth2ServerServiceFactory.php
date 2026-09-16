<?php

/**
 * OAuth2 server service factory.
 *
 * PHP version 8
 *
 * Copyright (C) The National Library of Finland 2022-2026.
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
 * @package  OAuth2
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */

namespace VuFind\OAuth2;

use Closure;
use Laminas\ServiceManager\Exception\ServiceNotCreatedException;
use Laminas\ServiceManager\Exception\ServiceNotFoundException;
use Laminas\ServiceManager\Factory\FactoryInterface;
use League\OAuth2\Server\AuthorizationServer;
use League\OAuth2\Server\CryptKey;
use League\OAuth2\Server\Grant\AuthCodeGrant;
use League\OAuth2\Server\Grant\RefreshTokenGrant;
use League\OAuth2\Server\ResourceServer;
use League\OAuth2\Server\ResponseTypes\ResponseTypeInterface;
use OpenIDConnectServer\ClaimExtractor;
use OpenIDConnectServer\Entities\ClaimSetEntity;
use OpenIDConnectServer\IdTokenResponse;
use Psr\Container\ContainerExceptionInterface as ContainerException;
use Psr\Container\ContainerInterface;
use VuFind\Auth\ILSAuthenticator;
use VuFind\Config\PathResolver;
use VuFind\Db\Service\AccessTokenServiceInterface;
use VuFind\Http\RouteHelper;
use VuFind\Http\ServerUrlHelper;
use VuFind\ILS\Connection;
use VuFind\OAuth2\Repository\AccessTokenRepository;
use VuFind\OAuth2\Repository\AuthCodeRepository;
use VuFind\OAuth2\Repository\ClientRepository;
use VuFind\OAuth2\Repository\IdentityRepository;
use VuFind\OAuth2\Repository\RefreshTokenRepository;
use VuFind\OAuth2\Repository\ScopeRepository;

use function in_array;

/**
 * OAuth2 server service factory.
 *
 * @category VuFind
 * @package  OAuth2
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */
class OAuth2ServerServiceFactory implements FactoryInterface
{
    /**
     * Service manager.
     *
     * @var ?ContainerInterface
     */
    protected ?ContainerInterface $container = null;

    /**
     * OAuth2 configuration.
     *
     * @var ?array
     */
    protected ?array $oauth2Config = null;

    /**
     * Config file path resolver.
     *
     * @var ?PathResolver
     */
    protected ?PathResolver $pathResolver = null;

    /**
     * Claim extractor.
     *
     * @var ?ClaimExtractor
     */
    protected ?ClaimExtractor $claimExtractor = null;

    /**
     * Create an object.
     *
     * @param ContainerInterface $container     Service manager
     * @param string             $requestedName Service being created
     * @param null|array         $options       Extra options (optional)
     *
     * @return object
     *
     * @throws ServiceNotFoundException if unable to resolve the service.
     * @throws ServiceNotCreatedException if an exception is raised when
     * creating a service.
     * @throws ContainerException&\Throwable if any other error occurs
     */
    public function __invoke(
        ContainerInterface $container,
        $requestedName,
        ?array $options = null
    ) {
        if (!empty($options)) {
            throw new \Exception('Unexpected options passed to factory.');
        }
        $this->container = $container;
        $this->pathResolver = $container->get(PathResolver::class);

        // Load configuration:
        $this->oauth2Config = $container
            ->get(\VuFind\Config\ConfigManagerInterface::class)
            ->getConfigArray('OAuth2Server');

        // Check that user identifier field is valid
        $this->checkIfUserIdentifierFieldIsValid();

        $session = new \Laminas\Session\Container(
            OAuth2ServerService::SESSION_NAME,
            $container->get(\Laminas\Session\SessionManager::class)
        );
        $dbPluginManager = $container->get(\VuFind\Db\Service\PluginManager::class);
        $serverUrlHelper = $container->get(ServerUrlHelper::class);
        $routeHelper = $container->get(RouteHelper::class);
        $baseUrl = $serverUrlHelper->getUrlForPath($routeHelper->getUrlFromRoute('home'));

        return new $requestedName(
            Closure::fromCallable($this->getAuthorizationServerFactory()),
            Closure::fromCallable($this->getResourceServerFactory()),
            $container->get(\VuFind\Validator\CsrfInterface::class),
            $session,
            $container->get(IdentityRepository::class),
            $dbPluginManager->get(AccessTokenServiceInterface::class),
            $this->getClaimExtractor(),
            $this->pathResolver,
            $container->get(ILSAuthenticator::class),
            $container->get(Connection::class),
            $this->oauth2Config,
            $baseUrl
        );
    }

    /**
     * Return a factory function for creating the authorization server.
     *
     * @return callable
     */
    protected function getAuthorizationServerFactory(): callable
    {
        return function (?string $clientId): AuthorizationServer {
            // This could be called with incomplete configuration, so get settings
            // first:
            $privateKeyPath = $this->getKeyFromConfigPath('privateKeyPath');
            $encryptionKey = $this->getOAuth2ServerSetting('encryptionKey');
            $server = new AuthorizationServer(
                $this->container->get(ClientRepository::class),
                $this->container->get(AccessTokenRepository::class),
                $this->container->get(ScopeRepository::class),
                $privateKeyPath,
                $encryptionKey,
                $this->getResponseType()
            );
            $clientConfig = $clientId
                ? ($this->oauth2Config['Clients'][$clientId] ?? null) : null;
            $this->addGrantTypes($server, $clientConfig);
            return $server;
        };
    }

    /**
     * Return a ResourceServer.
     *
     * @return callable
     */
    protected function getResourceServerFactory(): callable
    {
        return function (): ResourceServer {
            return new ResourceServer(
                $this->container->get(AccessTokenRepository::class),
                $this->getKeyFromConfigPath('publicKeyPath')
            );
        };
    }

    /**
     * Add grant types to the server.
     *
     * @param AuthorizationServer $server       Authorization server
     * @param ?array              $clientConfig Client configuration
     *
     * @return void
     */
    protected function addGrantTypes(
        AuthorizationServer $server,
        ?array $clientConfig
    ): void {
        $accessTokenLifeTime = new \DateInterval(
            $this->oauth2Config['Grants']['accessTokenLifeTime'] ?? 'PT1H'
        );

        // Enable the auth code grant on the server
        $server->enableGrantType(
            $this->createAuthCodeGrant($clientConfig),
            $accessTokenLifeTime
        );

        // Enable the refresh token grant on the server
        $server->enableGrantType(
            $this->createRefreshTokenGrant(),
            $accessTokenLifeTime
        );
    }

    /**
     * Create an auth code grant.
     *
     * @param ?array $clientConfig Client configuration
     *
     * @return AuthCodeGrant
     */
    protected function createAuthCodeGrant(?array $clientConfig): AuthCodeGrant
    {
        $config = $this->oauth2Config['Grants'] ?? [];
        $authCodeLifeTime = $config['authCodeLifeTime'] ?? 'PT1M';

        $grant = new AuthCodeGrant(
            $this->container->get(AuthCodeRepository::class),
            $this->container->get(RefreshTokenRepository::class),
            new \DateInterval($authCodeLifeTime)
        );

        // Configure for client, if any:
        if ($clientConfig && empty($clientConfig['pkce'])) {
            $grant->disableRequireCodeChallengeForPublicClients();
        }

        return $grant;
    }

    /**
     * Create a refresh token grant.
     *
     * @return RefreshTokenGrant
     */
    protected function createRefreshTokenGrant(): RefreshTokenGrant
    {
        $config = $this->oauth2Config['Grants'] ?? [];
        $refreshLifeTime = $config['refreshTokenLifeTime'] ?? 'PT1M';

        $rtGrant = new RefreshTokenGrant(
            $this->container->get(RefreshTokenRepository::class)
        );
        $rtGrant->setRefreshTokenTTL(new \DateInterval($refreshLifeTime));
        return $rtGrant;
    }

    /**
     * Return an OAuth2 response type.
     *
     * @return ResponseTypeInterface
     */
    protected function getResponseType(): ResponseTypeInterface
    {
        return new IdTokenResponse(
            $this->container->get(IdentityRepository::class),
            $this->getClaimExtractor()
        );
    }

    /**
     * Get the claim extractor.
     *
     * @return ClaimExtractor
     */
    protected function getClaimExtractor(): ClaimExtractor
    {
        if (null === $this->claimExtractor) {
            $this->claimExtractor = new ClaimExtractor();
            foreach ($this->oauth2Config['Scopes'] as $scopeId => $scopeConfig) {
                if (empty($scopeConfig['claims'])) {
                    continue;
                }
                $this->claimExtractor->addClaimSet(
                    new ClaimSetEntity($scopeId, $scopeConfig['claims'])
                );
            }
        }
        return $this->claimExtractor;
    }

    /**
     * Return a server setting from the OAuth2 configuration.
     *
     * @param string $setting Setting name
     *
     * @return string
     *
     * @throws \Exception if the setting doesn't exist or is empty.
     */
    protected function getOAuth2ServerSetting(string $setting): string
    {
        if (!($result = $this->oauth2Config['Server'][$setting] ?? '')) {
            throw new \Exception(
                "Server/$setting missing from OAuth2Server config"
            );
        }
        return $result;
    }

    /**
     * Check that the user identifier field is valid.
     *
     * @return void
     *
     * @throws \Exception if the field is invalid
     */
    protected function checkIfUserIdentifierFieldIsValid()
    {
        $userIdentifierField = $this->oauth2Config['Server']['userIdentifierField'] ?? 'id';
        if (
            !in_array(
                $userIdentifierField,
                ['id', 'username', 'cat_id']
            )
        ) {
            throw new \Exception(
                "User identifier field '$userIdentifierField' is invalid."
            );
        }
    }

    /**
     * Return a key path from the OAuth2 configuration.
     *
     * Converts the path to absolute as necessary.
     *
     * @param string $key Key path to return
     *
     * @return CryptKey
     *
     * @throws \Exception if the setting doesn't exist or is empty.
     */
    protected function getKeyFromConfigPath(string $key): CryptKey
    {
        $keyPath = $this->getOAuth2ServerSetting($key);
        if (strncmp($keyPath, '/', 1) !== 0) {
            // Convert relative path:
            $keyPath = $this->pathResolver->getConfigPath($keyPath);
        }
        return new CryptKey(
            $keyPath,
            null,
            $this->oauth2Config['Server']['keyPermissionChecks'] ?? true
        );
    }
}
