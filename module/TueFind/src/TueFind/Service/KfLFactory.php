<?php

namespace TueFind\Service;

use Interop\Container\ContainerInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;


class KfLFactory implements FactoryInterface
{

    public function __invoke(ContainerInterface $container, $requestedName,
        array $options = null
    ) {
        if (!empty($options)) {
            throw new \Exception('Unexpected options passed to factory.');
        }

        $authManager = $container->get(\VuFind\Auth\Manager::class);
        $user = $authManager->isLoggedIn();
        if (!$user)
            throw new \Exception('Could not init KfL Service, user is not logged in!');

        // We pass an anonymized version of the user id together with host+tuefind instance.
        // This value will be saved by the proxy and reported back to us
        // in case of abuse.
        $userUniqueId = implode('#', [gethostname(),
                                      $container->get('ViewHelperManager')->get('tuefind')->getTueFindInstance(),
                                      $user->tuefind_uuid]);

        $config = $container->get(\VuFind\Config\PluginManager::class)
            ->get('tuefind')->KfL;

        return new $requestedName(
            $config->base_url, $config->api_id, $config->cipher, $config->encryption_key, $userUniqueId
        );
    }
}
