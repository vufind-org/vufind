<?php

/**
 * VuFind XSLT importer
 *
 * PHP version 8
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
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301  USA
 *
 * @category VuFind
 * @package  XSLT
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/ Wiki
 */

namespace VuFind\XSLT;

use DOMDocument;
use Laminas\ServiceManager\ServiceLocatorInterface;
use VuFind\Service\GetServiceTrait;
use VuFindSearch\Backend\Solr\Document\RawXMLDocument;
use XSLTProcessor;

use function is_array;

/**
 * VuFind XSLT importer
 *
 * @category VuFind
 * @package  XSLT
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/ Wiki
 */
class Importer
{
    use GetServiceTrait;

    /**
     * Constructor
     *
     * @param ServiceLocatorInterface $sm Service manager
     */
    public function __construct(ServiceLocatorInterface $sm)
    {
        $this->serviceLocator = $sm;
    }

    /**
     * Save an XML file to the Solr index using the specified configuration.
     *
     * @param string $xmlFile    XML file to transform.
     * @param string $properties Properties file.
     * @param string $index      Solr index to use.
     * @param bool   $testMode   Are we in test-only mode?
     *
     * @throws \Exception
     * @return string            Transformed XML
     */
    public function save(
        $xmlFile,
        $properties,
        $index = 'Solr',
        $testMode = false
    ) {
        // Process the file:
        $xml = $this->generateXML($xmlFile, $properties);

        // Save the results (or just display them, if in test mode):
        if (!$testMode) {
            $solr = $this->getService(\VuFind\Solr\Writer::class);
            $solr->save($index, new RawXMLDocument($xml));
        }
        return $xml;
    }

    /**
     * Transform $xmlFile using the provided $properties configuration.
     *
     * @param string $xmlFile    XML file to transform.
     * @param string $properties Properties file.
     *
     * @throws \Exception
     * @return mixed             Transformed XML.
     */
    protected function generateXML($xmlFile, $properties)
    {
        // Load properties file:
        $resolver = $this->getService(\VuFind\Config\PathResolver::class);
        $properties = $resolver->getConfigPath($properties, 'import');
        if (!file_exists($properties)) {
            throw new \Exception("Cannot load properties file: {$properties}.");
        }
        $options = parse_ini_file($properties, true);

        // Make sure required parameter is set:
        if (!($filename = $options['General']['xslt'] ?? '')) {
            throw new \Exception(
                "Properties file ({$properties}) is missing General/xslt setting."
            );
        }
        $xslFile = $resolver->getConfigPath($filename, 'import/xsl');

        // Initialize the XSL processor:
        $xsl = $this->initProcessor($options);

        // Load up the style sheet
        $style = new DOMDocument();
        if (!$style->load($xslFile)) {
            throw new \Exception("Problem loading XSL file: {$xslFile}.");
        }
        $xsl->importStyleSheet($style);

        // Load up the XML document
        $xml = new DOMDocument();
        if (!$xml->load($xmlFile)) {
            throw new \Exception("Problem loading XML file: {$xmlFile}.");
        }

        // Process and return the XML through the style sheet
        $result = $xsl->transformToXML($xml);
        if (!$result) {
            throw new \Exception('Problem transforming XML.');
        }
        return $result;
    }

    /**
     * Initialize an XSLT processor using settings from the user-specified properties
     * file.
     *
     * @param array $options Parsed contents of properties file.
     *
     * @throws \Exception
     * @return object        XSLT processor.
     */
    protected function initProcessor($options)
    {
        // Prepare an XSLT processor and pass it some variables
        $xsl = new XSLTProcessor();

        // Register PHP functions, if specified:
        if (isset($options['General']['php_function'])) {
            $functions = is_array($options['General']['php_function'])
                ? $options['General']['php_function']
                : [$options['General']['php_function']];
            foreach ($functions as $function) {
                $xsl->registerPHPFunctions($function);
            }
        }

        // Register custom classes, if specified:
        if (isset($options['General']['custom_class'])) {
            $classes = is_array($options['General']['custom_class'])
                ? $options['General']['custom_class']
                : [$options['General']['custom_class']];
            $truncate = $options['General']['truncate_custom_class'] ?? true;
            foreach ($classes as $class) {
                // Add a default namespace if none was provided:
                if (!str_contains($class, '\\')) {
                    $class = 'VuFind\XSLT\Import\\' . $class;
                }
                // If necessary, dynamically generate the truncated version of the
                // requested class:
                if ($truncate) {
                    $parts = explode('\\', $class);
                    $class = preg_replace('/[^A-Za-z0-9_]/', '', array_pop($parts));
                    $ns = implode('\\', $parts);
                    if (!class_exists($class)) {
                        class_alias("$ns\\$class", $class);
                    }
                }
                $methods = get_class_methods($class);
                if (method_exists($class, 'setServiceLocator')) {
                    $class::setServiceLocator($this->serviceLocator);
                }
                foreach ($methods as $method) {
                    $xsl->registerPHPFunctions($class . '::' . $method);
                }
            }
        }

        // Load parameters, if provided:
        if (isset($options['Parameters'])) {
            $xsl->setParameter('', $options['Parameters']);
        }

        return $xsl;
    }
}
