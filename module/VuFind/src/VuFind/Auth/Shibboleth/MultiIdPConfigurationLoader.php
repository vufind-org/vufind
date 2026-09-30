<?php

/**
 * Configuration loader for Multiple IdPs.
 *
 * PHP version 8
 *
 * @category VuFind
 * @package  Authentication
 * @author   Vaclav Rosecky <vaclav.rosecky@mzk.cz>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */

namespace VuFind\Auth\Shibboleth;

use VuFind\Exception\Auth as AuthException;

/**
 * Configuration loader for Multiple IdPs.
 *
 * @category VuFind
 * @package  Authentication
 * @author   Vaclav Rosecky <vaclav.rosecky@mzk.cz>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */
class MultiIdPConfigurationLoader implements
    ConfigurationLoaderInterface,
    \Psr\Log\LoggerAwareInterface
{
    use \VuFind\Log\LoggerAwareTrait;

    /**
     * Constructor.
     *
     * @param array $config     Configuration
     * @param array $shibConfig Shibboleth configuration for IdPs
     */
    public function __construct(
        protected array $config,
        protected array $shibConfig
    ) {
    }

    /**
     * Return shibboleth configuration.
     *
     * @param string $entityId entity Id
     *
     * @throws \VuFind\Exception\Auth
     * @return array shibboleth configuration
     */
    public function getConfiguration($entityId)
    {
        $config = $this->config['Shibboleth'] ?? [];
        $idpConfig = null;
        $prefix = null;
        foreach ($this->shibConfig as $name => $configuration) {
            if ($entityId == trim($configuration['entityId'])) {
                $idpConfig = $configuration;
                $prefix = $name;
                break;
            }
        }
        if ($idpConfig == null) {
            $this->debug(
                "Missing configuration for Idp with entityId: {$entityId})"
            );
            throw new AuthException('Missing configuration for IdP.');
        }
        $config = array_merge($config, $idpConfig);
        $config['prefix'] = $prefix;
        return $config;
    }
}
