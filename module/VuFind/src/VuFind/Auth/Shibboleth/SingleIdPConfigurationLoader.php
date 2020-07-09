<?php
namespace VuFind\Auth\Shibboleth;

class SingleIdPConfigurationLoader implements ConfigurationLoaderInterface
{
    /**
     * Configured IdPs with entityId and overridden attribute mapping
     *
     * @var \Laminas\Config\Config
     */
    protected $config;

    /**
     * Constructor
     *
     * @param \Laminas\Session\ManagerInterface $sessionManager Session manager
     */
    public function __construct(\Laminas\Config\Config $config)
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
