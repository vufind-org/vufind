<?php
namespace TueFindApi\Controller;

use Interop\Container\ContainerInterface;
use Interop\Container\Exception\ContainerException;
use Laminas\ServiceManager\Exception\ServiceNotCreatedException;
use Laminas\ServiceManager\Exception\ServiceNotFoundException;
use Laminas\ServiceManager\Factory\FactoryInterface;

//class ApiControllerFactory extends \VuFindApi\Controller\ApiControllerFactory
class ApiControllerFactory implements FactoryInterface
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
     * @throws ContainerException if any other error occurs
     */
    public function __invoke(ContainerInterface $container, $requestedName,
        array $options = null
    ) {
        if (!empty($options)) {
            throw new \Exception('Unexpected options sent to factory.');
        }
        $controller = new $requestedName($container,
                                         $container->get(\VuFindApi\Formatter\RecordFormatter::class),
                                         $container->get(\VuFindApi\Formatter\FacetFormatter::class));
        return $controller;
    }
}
?>
