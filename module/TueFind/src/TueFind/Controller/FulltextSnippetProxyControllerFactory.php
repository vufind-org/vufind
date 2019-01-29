<?php
namespace TueFind\Controller;

use \Elasticsearch\ClientBuilder;
use Interop\Container\ContainerInterface;
use Zend\ServiceManager\Factory\FactoryInterface;

class FulltextSnippetProxyControllerFactory {

   public function __invoke(ContainerInterface $container, $requestedName, array $options = null) {
       return new FulltextSnippetProxyController(new \Elasticsearch\ClientBuilder,
                                                 $container->get('VuFind\Logger'));
   }

}
?>

