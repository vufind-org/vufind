<?php
/**
 * Class for managing search (options/params/results) objects.
 *
 * PHP version 5
 *
 * Copyright (C) Villanova University 2010.
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
 * Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
 *
 * @category VuFind2
 * @package  Search
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org   Main Site
 */
namespace VuFind\Search;
use Zend\ServiceManager\ServiceLocatorAwareInterface,
    Zend\ServiceManager\ServiceLocatorInterface;

/**
 * Class for managing search (options/params/results) objects.
 *
 * @category VuFind2
 * @package  Search
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org   Main Site
 */
class Manager implements ServiceLocatorAwareInterface
{
    /**
     * Scoped copy of the config array
     */
    protected $config;
    /**
     * Search class id
     */
    protected $classId = 'Solr';

    /**
     * Service locator
     *
     * @var ServiceLocatorInterface
     */
    protected $serviceLocator;

    /**
     * Constructor
     *
     * @param array $config Configuration from VuFind module
     */
    public function __construct($config)
    {
        $this->config = $config;
    }

    /**
     * Set the search class ID to load.  Implements a fluent interface.
     *
     * @param string $id Search class ID
     *
     * @return Manager
     */
    public function setSearchClassId($id)
    {
        $this->classId = $id;
        return $this;
    }

    /**
     * Get the namespace for the current search class ID.
     *
     * @return string
     */
    public function getNamespace()
    {
        // Process aliases first:
        $key = isset($this->config['aliases'][$this->classId])
            ? $this->config['aliases'][$this->classId] : $this->classId;


        // If we have an explicit namespace configuration, use that next:
        if (isset($this->config['namespaces_by_id'][$key])) {
            return $this->config['namespaces_by_id'][$key];
        }

        // Use default namespace if we got this far:
        $ns = isset($this->config['default_namespace'])
            ? $this->config['default_namespace'] : 'VuFind\Search';
        return $ns . '\\' . $key;
    }

    /**
     * Get the options class name for the current search class ID.
     *
     * @return string
     */
    public function getOptionsClass()
    {
        return $this->getNamespace() . '\Options';
    }

    /**
     * Get the params class name for the current search class ID.
     *
     * @return string
     */
    public function getParamsClass()
    {
        return $this->getNamespace() . '\Params';
    }

    /**
     * Get the results class name for the current search class ID.
     *
     * @return string
     */
    public function getResultsClass()
    {
        return $this->getNamespace() . '\Results';
    }

    /**
     * Inject dependencies into an object.
     *
     * @param object $obj Object to inject.
     *
     * @return void
     */
    protected function injectDependencies($obj)
    {
        if ($obj instanceof ServiceLocatorAwareInterface) {
            $obj->setServiceLocator($this->getServiceLocator());
        }
    }

    /**
     * Get an options instance for the current search class ID.
     *
     * @return \VuFind\Search\Base\Options
     */
    public function getOptionsInstance()
    {
        return $this->getServiceLocator()->get('VuFind\SearchOptionsPluginManager')
            ->get($this->classId);
    }

    /**
     * Get a parameters object for the current search class ID.
     *
     * @param \VuFind\Search\Base\Options $options Search options to load (null for
     * defaults).
     *
     * @return VuFind\Search\Base\Params
     */
    public function getParams($options = null)
    {
        $class = $this->getParamsClass();
        $params = new $class($options);
        if (!($params instanceof \VuFind\Search\Base\Params)) {
            throw new \Exception('Invalid params object.');
        }
        $this->injectDependencies($params);
        $params->init();
        return $params;
    }

    /**
     * Get a results object for the current search class ID.
     *
     * @param \VuFind\Search\Base\Params $params Search parameters to load.
     *
     * @return VuFind\Search\Base\Results
     */
    public function getResults($params = null)
    {
        $class = $this->getResultsClass();
        if (null === $params) {
            $params = $this->getParams();
        }
        $results = new $class($params);
        if (!($results instanceof \VuFind\Search\Base\Results)) {
            throw new \Exception('Invalid results object.');
        }
        $this->injectDependencies($results);
        return $results;
    }

    /**
     * Extract the name of the search class family from a class name.
     *
     * @param string $className Class name to examine.
     *
     * @return string
     */
    public function extractSearchClassId($className)
    {
        // Parse identifier out of class name of format VuFind\Search\[id]\Params:
        $class = explode('\\', $className);
        return $class[2];
    }

    /**
     * Set the service locator.
     *
     * @param ServiceLocatorInterface $serviceLocator Locator to register
     *
     * @return Manager
     */
    public function setServiceLocator(ServiceLocatorInterface $serviceLocator)
    {
        $this->serviceLocator = $serviceLocator;
        return $this;
    }

    /**
     * Get the service locator.
     *
     * @return \Zend\ServiceManager\ServiceLocatorInterface
     */
    public function getServiceLocator()
    {
        return $this->serviceLocator;
    }
}