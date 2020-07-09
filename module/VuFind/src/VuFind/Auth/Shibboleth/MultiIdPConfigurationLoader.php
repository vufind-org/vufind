<?php
namespace VuFind\Auth\Shibboleth;

class MultiIdPConfigurationLoader implements ConfigurationLoaderInterface,
    \Laminas\Log\LoggerAwareInterface
{
    use \VuFind\Log\LoggerAwareTrait;

    /**
     * Configured IdPs with entityId and overridden attribute mapping
     *
     * @var \Laminas\Config\Config
     */
    protected $config;

    /**
     * Configured IdPs with entityId and overridden attribute mapping
     *
     * @var \Laminas\Config\Config
     */
    protected $shibConfig;

    /**
     * Constructor
     *
     * @param \Laminas\Session\ManagerInterface $sessionManager Session manager
     */
    public function __construct(\Laminas\Config\Config $config,
        \Laminas\Config\Config $shibConfig)
    {
        $this->config = $config;
        $this->shibConfig = $shibConfig;
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
        $config = $this->config->Shibboleth->toArray();
        $idpConfig = null;
        $prefix = null;
        foreach ($this->shibConfig as $name => $configuration) {
            if ($entityId == trim($configuration['entityId'])) {
                $idpConfig = $configuration->toArray();
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
        foreach ($idpConfig as $key => $value) {
            $config[$key] = $value;
        }
        $config['prefix'] = $prefix;
        return $config;
    }
}
