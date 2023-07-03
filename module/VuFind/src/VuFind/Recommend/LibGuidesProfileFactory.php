<?php

namespace VuFind\Recommend;

use Psr\Container\ContainerInterface;

class LibGuidesProfileFactory implements \Laminas\ServiceManager\Factory\FactoryInterface
{
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
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function __invoke(
        ContainerInterface $container,
        $requestedName,
        array $options = null
    ) {
        if (!empty($options)) {
            throw new \Exception('Unexpected options passed to factory.');
        }
        $config = $container->get(\VuFind\Config\PluginManager::class)
            ->get('LibGuidesAPI');
        if (!isset($config->General->client_id)) {
            throw new \Exception('client_id key missing from configuration.');
        }
        if (!isset($config->General->client_secret)) {
            throw new \Exception('client_secret key missing from configuration.');
        }
        return new $requestedName(
            $config,
            $container->get(\VuFindHttp\HttpService::class)->createClient()
        );
    }
}