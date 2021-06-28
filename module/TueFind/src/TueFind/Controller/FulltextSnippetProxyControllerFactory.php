<?php
namespace TueFind\Controller;

use \Elasticsearch\ClientBuilder;
use Interop\Container\ContainerInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;

class FulltextSnippetProxyControllerFactory implements FactoryInterface {

   public function __invoke(ContainerInterface $container, $requestedName, array $options = null) {
       return new FulltextSnippetProxyController(new ClientBuilder,
                                                 $container,
                                                 $container->get('VuFind\Logger'));
   }

}
