<?php

/**
 * Factory for record driver data formatting view helper
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2016.
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
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301  USA
 *
 * @category VuFind
 * @package  View_Helpers
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:architecture:record_data_formatter
 * Wiki
 */

namespace VuFind\View\Helper\Root;

use Laminas\ServiceManager\Exception\ServiceNotCreatedException;
use Laminas\ServiceManager\Exception\ServiceNotFoundException;
use Laminas\ServiceManager\Factory\FactoryInterface;
use Psr\Container\ContainerExceptionInterface as ContainerException;
use Psr\Container\ContainerInterface;
use VuFind\RecordDataFormatter\Specs\DefaultRecord as DefaultRecordSpec;

use function get_class;

/**
 * Factory for record driver data formatting view helper
 *
 * @category VuFind
 * @package  View_Helpers
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:architecture:record_data_formatter
 * Wiki
 */
class RecordDataFormatterFactory implements FactoryInterface
{
    /**
     * Schema.org view helper
     *
     * @var SchemaOrg
     */
    protected $schemaOrgHelper = null;

    /**
     * The order in which groups of authors are displayed.
     *
     * The dictionary keys here correspond to the dictionary keys in the $labels
     * array in getAuthorFunction()
     *
     * @var array<string, int>
     *
     * @deprecated Use \VuFind\RecordDataFormatter\Specs\DefaultRecord instead of defining the specs in this factory
     */
    protected $authorOrder = ['primary' => 1, 'corporate' => 2, 'secondary' => 3];

    /**
     * Default record spec.
     *
     * @var DefaultRecordSpec
     */
    protected DefaultRecordSpec $defaultRecordSpec;

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
        ?array $options = null
    ) {
        if (!empty($options)) {
            throw new \Exception('Unexpected options sent to factory.');
        }
        $specPluginManager = $container->get(\VuFind\RecordDataFormatter\Specs\PluginManager::class);
        // for legacy backward compatibility check if getDefault*Specs methods got overridden.
        $this->schemaOrgHelper = $container->get('ViewHelperManager')->get('schemaOrg');
        $this->defaultRecordSpec = $specPluginManager->get(DefaultRecordSpec::class);
        $methodMapping = [
            'collection-info' => 'getDefaultCollectionInfoSpecs',
            'collection-record' => 'getDefaultCollectionRecordSpecs',
            'core' => 'getDefaultCoreSpecs',
            'description' => 'getDefaultDescriptionSpecs',
        ];
        $showDeprecationWarning = false;
        foreach ($methodMapping as $context => $method) {
            $reflector = new \ReflectionMethod($this, $method);
            if ($reflector->getDeclaringClass()->getName() !== RecordDataFormatterFactory::class) {
                $showDeprecationWarning = true;
                $this->defaultRecordSpec->setDefaults($context, [$this, $method]);
            }
        }
        if ($showDeprecationWarning) {
            $logger = $container->get(\VuFind\Log\Logger::class);
            $warningMessage = 'Using deprecated customization of RecordDataFormatter specs! '
                . 'Please use the \VuFind\RecordDataFormatter\Specs\DefaultRecord instead. '
                . 'See https://vufind.org/wiki/development:architecture:record_data_formatter for more information.';
            $logger->warn(get_class($this) . ': ' . $warningMessage);
        }
        return new $requestedName($specPluginManager);
    }

    /**
     * Get the callback function for processing authors.
     *
     * @return callable
     *
     * @deprecated Use \VuFind\RecordDataFormatter\Specs\DefaultRecord instead of defining the specs in this factory
     */
    protected function getAuthorFunction(): callable
    {
        return $this->defaultRecordSpec->getAuthorFunction();
    }

    /**
     * Get the settings for formatting language lines.
     *
     * @return array
     *
     * @deprecated Use \VuFind\RecordDataFormatter\Specs\DefaultRecord instead of defining the specs in this factory
     */
    protected function getLanguageLineSettings(): array
    {
        return $this->defaultRecordSpec->getLanguageLineSettings();
    }

    /**
     * Get default specifications for displaying data in collection-info metadata.
     *
     * @return array
     *
     * @deprecated Use \VuFind\RecordDataFormatter\Specs\DefaultRecord instead of defining the specs in this factory
     */
    public function getDefaultCollectionInfoSpecs(): array
    {
        return $this->defaultRecordSpec->getDefaults('collection-info');
    }

    /**
     * Get default specifications for displaying data in collection-record metadata.
     *
     * @return array
     *
     * @deprecated Use \VuFind\RecordDataFormatter\Specs\DefaultRecord instead of defining the specs in this factory
     */
    public function getDefaultCollectionRecordSpecs(): array
    {
        return $this->defaultRecordSpec->getDefaults('collection-record');
    }

    /**
     * Get default specifications for displaying data in core metadata.
     *
     * @return array
     *
     * @deprecated Use \VuFind\RecordDataFormatter\Specs\DefaultRecord instead of defining the specs in this factory
     */
    public function getDefaultCoreSpecs(): array
    {
        return $this->defaultRecordSpec->getDefaults('core');
    }

    /**
     * Get default specifications for displaying data in the description tab.
     *
     * @return array
     *
     * @deprecated Use \VuFind\RecordDataFormatter\Specs\DefaultRecord instead of defining the specs in this factory
     */
    public function getDefaultDescriptionSpecs(): array
    {
        return $this->defaultRecordSpec->getDefaults('description');
    }
}
