<?php

/**
 * Configuration loader for single IdP
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

/**
 * Configuration loader for single IdP
 *
 * @category VuFind
 * @package  Authentication
 * @author   Vaclav Rosecky <vaclav.rosecky@mzk.cz>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */
class SingleIdPConfigurationLoader implements ConfigurationLoaderInterface
{
    /**
     * Configured IdPs with entityId and overridden attribute mapping
     *
     * @var \VuFind\Config\Config
     */
    protected $config;

    /**
     * Constructor
     *
     * @param \VuFind\Config\Config $config Configuration
     */
    public function __construct(\VuFind\Config\Config $config)
    {
        $this->config = $config;
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
        return $this->config->Shibboleth->toArray();
    }
}
