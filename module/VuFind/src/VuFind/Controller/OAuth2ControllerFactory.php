<?php
/**
 * OAuth2 controller factory.
 *
 * PHP version 7
 *
 * Copyright (C) The National Library of Finland 2022.
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
 * @package  Controller
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */
namespace VuFind\Controller;

use Interop\Container\ContainerInterface;
use Interop\Container\Exception\ContainerException;
use Laminas\ServiceManager\Exception\ServiceNotCreatedException;
use Laminas\ServiceManager\Exception\ServiceNotFoundException;
use League\OAuth2\Server\AuthorizationServer;
use League\OAuth2\Server\Grant\AuthCodeGrant;
use League\OAuth2\Server\Grant\RefreshTokenGrant;
use League\OAuth2\Server\ResourceServer;
use League\OAuth2\Server\ResponseTypes\BearerTokenResponse;
use League\OAuth2\Server\ResponseTypes\ResponseTypeInterface;
use OpenIDConnectServer\ClaimExtractor;
use OpenIDConnectServer\Entities\ClaimSetEntity;
use OpenIDConnectServer\IdTokenResponse;
use VuFind\Config\Locator;
use VuFind\Db\Table\AccessToken;
use VuFind\OAuth2\Repository\AccessTokenRepository;
use VuFind\OAuth2\Repository\AuthCodeRepository;
use VuFind\OAuth2\Repository\ClientRepository;
use VuFind\OAuth2\Repository\IdentityRepository;
use VuFind\OAuth2\Repository\RefreshTokenRepository;
use VuFind\OAuth2\Repository\ScopeRepository;

/**
 * OAuth2 controller factory.
 *
 * @category VuFind
 * @package  Controller
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */
class OAuth2ControllerFactory extends AbstractBaseFactory
{
    /**
     * Service manager
     *
     * @var ContainerInterface
     */
    protected $container;

    /**
     * OAuth2 configuration
     *
     * @var array
     */
    protected $oauth2Config;

    /**
     * Access token table
     *
     * @var AccessToken
     */
    protected $accessTokenTable;

    /**
     * Protected scopes in OpenID Connect
     *
     * @var array
     */
    protected $openIdConnectProtectedScopes = [
        'profile',
        'email',
        'address',
        'phone'
    ];

    /**
     * Create an object
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
        array $options = null
    ) {
        if (!empty($options)) {
            throw new \Exception('Unexpected options sent to factory.');
        }
        $yamlReader = $container->get(\VuFind\Config\YamlReader::class);
        $oauth2Config = $yamlReader->get('OAuth2Server.yaml');

        $this->container = $container;
        $this->oauth2Config = $oauth2Config;
        $tablePluginManager = $this->container
            ->get(\VuFind\Db\Table\PluginManager::class);
        $this->accessTokenTable = $tablePluginManager->get('AccessToken');

        $session = new \Laminas\Session\Container(
            OAuth2Controller::SESSION_NAME,
            $container->get(\Laminas\Session\SessionManager::class)
        );
        return $this->applyPermissions(
            $container,
            new $requestedName(
                $container,
                $oauth2Config,
                $this->getAuthorizationServerFactory(),
                $this->getResourceServerFactory(),
                $container->get(\LmcRbacMvc\Service\AuthorizationService::class),
                $container->get(\VuFind\Validator\CsrfInterface::class),
                $session,
                $this->getIdentityRepository(),
                $this->accessTokenTable
            )
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
            if (!($keyPath = $this->oauth2Config['Server']['privateKeyPath'] ?? '')
            ) {
                throw new \Exception(
                    'Server/privateKeyPath missing from OAuth2Server.yaml'
                );
            }
            if (strncmp($keyPath, '/', 1) !== 0) {
                // Convert relative path:
                $keyPath = Locator::getConfigPath($keyPath);
            }
            $encryptionKey
                = trim($this->oauth2Config['Server']['encryptionKey'] ?? '');
            if (!$encryptionKey) {
                throw new \Exception(
                    'Server/encryptionKey missing from OAuth2Server.yaml'
                );
            }

            $server = new AuthorizationServer(
                new ClientRepository($this->oauth2Config),
                new AccessTokenRepository($this->accessTokenTable),
                new ScopeRepository($this->oauth2Config),
                $keyPath,
                $encryptionKey,
                $this->getResponseType()
            );
            $clientConfig = $clientId
                ? ($this->oauth2Config[$clientId] ?? null) : null;
            $this->addGrantTypes($server, $clientConfig, $this->accessTokenTable);
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
            if (!($keyPath = $this->oauth2Config['Server']['publicKeyPath'] ?? '')) {
                throw new \Exception(
                    'Server/publicKeyPath missing from OAuth2Server.yaml'
                );
            }
            if (strncmp($keyPath, '/', 1) !== 0) {
                // Convert relative path:
                $keyPath = Locator::getConfigPath($keyPath);
            }

            return new ResourceServer(
                new AccessTokenRepository($this->accessTokenTable),
                $keyPath
            );
        };
    }

    /**
     * Add grant types to the server
     *
     * @param AuthorizationServer $server       Authorization server
     * @param ?array              $clientConfig Client configuration
     * @param AccessToken         $accessToken  Access token table
     *
     * @return void
     */
    protected function addGrantTypes(
        AuthorizationServer $server,
        ?array $clientConfig,
        AccessToken $accessToken
    ): void {
        $config = $this->oauth2Config['Grants'] ?? [];
        $accessTokenLifeTime = $config['accessTokenLifeTime'] ?? 'PT1H';
        $authCodeLifeTime = $config['authCodeLifeTime'] ?? 'PT1M';
        $refreshLifeTime = $config['refreshTokenLifeTime'] ?? 'PT1M';

        $refreshTokenRepository = new RefreshTokenRepository($accessToken);
        $grant = new AuthCodeGrant(
            new AuthCodeRepository($accessToken),
            $refreshTokenRepository,
            new \DateInterval($authCodeLifeTime)
        );

        // Configure for client, if any:
        if ($clientConfig && empty($clientConfig['pkce'])) {
            $grant->disableRequireCodeChallengeForPublicClients();
        }

        // Enable the password grant on the server
        $server->enableGrantType($grant, new \DateInterval($accessTokenLifeTime));

        // Enable the refresh token grant on the server
        $rtGrant = new RefreshTokenGrant($refreshTokenRepository);
        $rtGrant->setRefreshTokenTTL(new \DateInterval($refreshLifeTime));
        $server->enableGrantType($rtGrant, new \DateInterval($accessTokenLifeTime));
    }

    /**
     * Return an OAuth2 response type.
     *
     * @return ResponseTypeInterface
     */
    protected function getResponseType(): ResponseTypeInterface
    {
        if (empty($this->oauth2Config['ClaimSets'])) {
            return new BearerTokenResponse();
        }
        $claimExtractor = new ClaimExtractor();
        foreach ($this->oauth2Config['ClaimSets'] as $scope => $claimSetConf) {
            if (in_array($scope, $this->openIdConnectProtectedScopes)) {
                continue;
            }
            $claimExtractor->addClaimSet(new ClaimSetEntity($scope, $claimSetConf));
        }
        return new IdTokenResponse($this->getIdentityRepository(), $claimExtractor);
    }

    /**
     * Return an identity repository.
     *
     * @return IdentityRepository
     */
    protected function getIdentityRepository(): IdentityRepository
    {
        $tablePluginManager = $this->container
            ->get(\VuFind\Db\Table\PluginManager::class);
        return new IdentityRepository(
            $tablePluginManager->get('User'),
            $this->accessTokenTable,
            $this->container->get(\VuFind\ILS\Connection::class),
            $this->oauth2Config
        );
    }
}
