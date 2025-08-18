<?php

/**
 * VuFind Action Helper - Record Preview Support Methods
 *
 * PHP version 8
 *
 * Copyright (C) The National Library of Finland 2025.
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
 * @package  Controller_Plugins
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */

namespace Finna\Controller\Plugin;

use DOMDocument;
use DOMXPath;
use Finna\Record\Schema\Schematron;
use Finna\Util\CachingHttpStreamWrapper;
use Laminas\Cache\Storage\StorageInterface;
use Laminas\Mvc\Controller\Plugin\AbstractPlugin;
use Psr\Container\ContainerInterface;
use VuFind\Config\PathResolver;
use VuFindHttp\HttpServiceInterface;

/**
 * VuFind Action Helper - Record Preview Support Methods
 *
 * @category VuFind
 * @package  Controller_Plugins
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */
class Preview extends AbstractPlugin
{
    /**
     * Record validation - no issues
     *
     * @var int
     */
    public const VALIDATION_NO_ISSUES = 0;

    /**
     * Record validation - only recommendations found
     *
     * @var int
     */
    public const VALIDATION_RECOMMENDATIONS = 1;

    /**
     * Record validation - warnings found
     *
     * @var int
     */
    public const VALIDATION_WARNINGS = 2;

    /**
     * Record validation - errors found
     *
     * @var int
     */
    public const VALIDATION_ERRORS = 3;

    /**
     * Constructor
     *
     * @param ContainerInterface   $serviceLocator Service locator
     * @param array                $config         Main configuration
     * @param HttpServiceInterface $httpService    HTTP Service for validation
     * @param StorageInterface     $cacheStorage   Cache storage for validation
     */
    public function __construct(
        protected ContainerInterface $serviceLocator,
        protected array $config,
        protected HttpServiceInterface $httpService,
        protected StorageInterface $cacheStorage
    ) {
    }

    /**
     * Load and validate a preview record
     *
     * @return array Associative array with driver, errors and validation_result (see consts at the top)
     */
    public function loadAndValidatePreviewRecord(): array
    {
        $params = $this->getController()->plugin('params');
        if ($params->fromHeader('Content-Type')?->getFieldValue() === 'application/json') {
            if (!($request = json_decode(file_get_contents('php://input'), true))) {
                throw new \InvalidArgumentException('Invalid JSON request');
            }
        } else {
            $request = [
                'data' => $params->fromPost('data') ?: $params->fromQuery('data'),
                'format' => $params->fromPost('format') ?: $params->fromQuery('format'),
                'source' => $params->fromPost('source') ?: $params->fromQuery('source'),
            ];
        }
        $missingParams = array_diff(['data', 'format', 'source'], array_keys(array_filter($request)));

        $manager = $this->serviceLocator->get(\Laminas\Session\SessionManager::class);
        $sessionContainer = new \Laminas\Session\Container('RecordPreview', $manager);
        if (!$missingParams) {
            $previewData = $this->loadPreviewRecordData($request['data'], $request['format'], $request['source']);
            $sessionContainer->previewData = $previewData;
            // Validate the metadata if configured and no errors during load:
            $sessionContainer->validation_report = !$previewData['errors']
                ? $this->validateRecord($request['data'], $request['format'], $request['source'])
                : null;
        } elseif (empty($request['data']) && !empty($sessionContainer->previewData)) {
            // Use cached record for tab support:
            $previewData = $sessionContainer->previewData;
        } else {
            throw new \InvalidArgumentException('Missing parameter(s): ' . implode(', ', $missingParams));
        }

        $recordFactory = $this->serviceLocator->get(\VuFind\RecordDriver\PluginManager::class);
        $driver = $recordFactory->getSolrRecord($previewData['metadata']);
        $locale = $this->serviceLocator->get(\VuFind\I18n\Locale\LocaleSettings::class)->getUserLocale();
        $driver->tryMethod('setPreferredLanguage', [$locale]);
        $driver->setExtraDetail('preview_record', true);
        return [
            'driver' => $driver,
            'errors' => $previewData['errors'] ?? [],
            'validation_result' => $sessionContainer->validation_report['result'] ?? self::VALIDATION_NO_ISSUES,
            'validation_report' => $sessionContainer->validation_report ?? null,
        ];
    }

    /**
     * Load normalized record metadata from RecordManager for preview
     *
     * @param string $data   Record Metadata
     * @param string $format Metadata format
     * @param string $source Data source
     *
     * @return array
     *
     * @throws \Exception
     */
    protected function loadPreviewRecordData($data, $format, $source): array
    {
        if (!($previewUrl = $this->config['NormalizationPreview']['url'] ?? null)) {
            throw new \Exception('Normalization preview URL not configured');
        }

        $httpService = $this->serviceLocator->get(\VuFindHttp\HttpService::class);
        $client = $httpService->createClient($previewUrl, \Laminas\Http\Request::METHOD_POST);
        $client->setOptions(['useragent' => 'FinnaRecordPreview VuFind']);
        $client->setParameterPost(
            ['data' => $data, 'format' => $format, 'source' => $source]
        );
        $errors = [];
        $response = $client->send();
        if (!$response->isSuccess()) {
            if ($response->getStatusCode() === 400) {
                $errors[] = 'Failed to load preview';
                $result = json_decode($response->getBody(), true);
                $errors = [
                    'Failed to load preview',
                    ...array_filter(explode("\n", $result['error_message'])),
                ];
                $metadata = [
                    'id' => '1',
                    'record_format' => $format,
                    'title' => 'Failed to load preview',
                    'title_short' => 'Failed to load preview',
                    'title_full' => 'Failed to load preview',
                    // This works for MARC and other XML loaders too
                    'fullrecord'
                        => '<collection><record><leader/></record></collection>',
                ];
            } else {
                throw new \Exception(
                    'Failed to load preview: ' . $response->getStatusCode() . ' '
                    . $response->getReasonPhrase()
                );
            }
        } else {
            $body = $response->getBody();
            $metadata = json_decode($body, true);
        }

        return compact('errors', 'metadata');
    }

    /**
     * Validate a record if configured
     *
     * @param string $metadata Metadata
     * @param string $format   Metadata format
     * @param string $source   Record source
     *
     * @return array Validation report with keys result, errors, warnings and recommendations
     */
    protected function validateRecord(string $metadata, string $format, string $source): array
    {
        $pathResolver = $this->serviceLocator->get(PathResolver::class);

        $errors = [];
        $warnings = [];
        $recommendations = [];
        $xsd = $this->config['NormalizationPreview']['validation_xsd'][$format] ?? null;
        $schematronRule = $this->config['NormalizationPreview']['validation_schematron'][$format] ?? null;

        // Use our own stream wrappers to cache external entity loads:
        CachingHttpStreamWrapper::enable($this->httpService, $this->cacheStorage);
        try {
            if ($xsd || $schematronRule) {
                if (!($document = $this->createDOMDocumentWithDefaultNamespace($metadata, $format))) {
                    $errors[] = 'Could not parse document XML';
                } else {
                    if ($xsd) {
                        $saveInternalErrors = libxml_use_internal_errors(true);
                        try {
                            if (!$document->schemaValidate($pathResolver->getConfigPath($xsd))) {
                                foreach (libxml_get_errors() as $error) {
                                    $errorDesc = '[' . $error->line . '] ' . trim($error->message);
                                    if (LIBXML_ERR_WARNING !== $error->level) {
                                        $errors[] = $errorDesc;
                                    }
                                }
                            }
                        } finally {
                            libxml_use_internal_errors($saveInternalErrors);
                        }
                    }
                    if ($schematronRule) {
                        $schematron = new Schematron();
                        $schematron->load($pathResolver->getConfigPath($schematronRule));
                        foreach ($schematron->validate($document) as $message) {
                            if (str_starts_with($message, '[WARN] ')) {
                                $warnings[] = substr($message, 7);
                            } elseif (str_starts_with($message, '[INFO] ')) {
                                $recommendations[] = substr($message, 7);
                            } else {
                                $warnings[] = $message;
                            }
                        }
                    }
                }
            }
        } finally {
            CachingHttpStreamWrapper::disable();
        }

        if ($errors) {
            $result = self::VALIDATION_ERRORS;
        } elseif ($warnings) {
            $result = self::VALIDATION_WARNINGS;
        } elseif ($recommendations) {
            $result = self::VALIDATION_RECOMMENDATIONS;
        } else {
            $result = self::VALIDATION_NO_ISSUES;
        }
        return compact('result', 'errors', 'warnings', 'recommendations');
    }

    /**
     * Create a DOMDocument and inject the default namespace for the given format if necessary
     *
     * Also pretty-prints the document so that it can be output nicely in a validation report.
     *
     * @param string $xml    Record
     * @param string $format Format
     *
     * @return void
     */
    protected function createDOMDocumentWithDefaultNamespace(string $xml, string $format): DOMDocument
    {
        $document = new DOMDocument();
        $document->preserveWhiteSpace = true;
        $document->loadXML($xml);

        // Pretty-print:
        $document->formatOutput = true;

        // Handle namespaces:
        [$formatNs, $formatNsUri, $elementNsMap] = match ($format) {
            'dc' => ['dc', 'http://purl.org/dc/elements/1.1/', []],
            'qdc' => ['', '', []],
            'ead' => ['', '', []],
            'ead3' => ['ead3', 'http://ead3.archivists.org/schema/', []],
            'aipa' => ['', '', []],
            'forward' => ['', '', []],
            'lido' => [
                'lido',
                'http://www.lido-schema.org',
                [
                    'Point' => ['gml', 'http://www.opengis.net/gml'],
                    'LineString' => ['gml', 'http://www.opengis.net/gml'],
                    'Polygon' => ['gml', 'http://www.opengis.net/gml'],
                    'pos' => ['gml', 'http://www.opengis.net/gml'],
                ],
            ],
            'marc' => ['marc', 'http://www.loc.gov/MARC21/slim', []],
        };
        if ($formatNs) {
            $addNamespaces = true;
            $xpath = new DOMXPath($document);
            $namespaces = $xpath->query('//namespace::*');
            foreach ($namespaces as $namespace) {
                if ($formatNsUri === $namespace->nodeValue) {
                    // Found the default, no need to add namespaces!
                    $addNamespaces = false;
                }
            }
            if ($addNamespaces) {
                // Add namespace declarations and move any schemaLocation:
                $root = $document->documentElement;
                $root->setAttributeNS('http://www.w3.org/2000/xmlns/', "xmlns:$formatNs", $formatNsUri);
                foreach ($elementNsMap as $elementNs) {
                    $root->setAttributeNS('http://www.w3.org/2000/xmlns/', "xmlns:$elementNs[0]", $elementNs[1]);
                }
                if ($schemaLocation = $root->getAttribute('schemaLocation')) {
                    $root->removeAttribute('schemaLocation');
                    $root->setAttributeNS(
                        'http://www.w3.org/2000/xmlns/',
                        'xmlns:xsi',
                        'http://www.w3.org/2001/XMLSchema-instance'
                    );
                    $root->setAttributeNS(
                        'http://www.w3.org/2001/XMLSchema-instance',
                        'schemaLocation',
                        $schemaLocation
                    );
                }

                // Move elements from default namespace to the format's namespace (or xml namespace):
                // Opening tags and self-closing tags:
                $xml = $document->saveXML();
                $xml = preg_replace_callback(
                    '/<(\w+?)\b *([^>]*?)(\/?>)/',
                    function ($matches) use ($formatNs, $elementNsMap) {
                        [, $tagName, $attrs, $closing] = $matches;
                        $tagNs = $elementNsMap[$tagName][0] ?? $formatNs;
                        $tag = "$tagNs:$tagName";
                        if ('' !== $attrs) {
                            // Attributes:
                            $attrs = preg_replace_callback(
                                '/(^|\s)(\w+)=\s*"([^"]*)"/',
                                function ($subMatches) use ($tagNs) {
                                    $ns = 'lang' === $subMatches[2] ? 'xml' : $tagNs;
                                    return "$subMatches[1]$ns:$subMatches[2]=\"$subMatches[3]\"";
                                },
                                $attrs
                            );
                            return "<$tag $attrs$closing";
                        } else {
                            return "<$tag$closing";
                        }
                    },
                    $xml
                );
                // Closing tags:
                $xml = preg_replace_callback(
                    '/<\/(\w+?)\s*>/',
                    function ($matches) use ($formatNs, $elementNsMap) {
                        $tagName = $matches[1];
                        $tagNs = $elementNsMap[$tagName][0] ?? $formatNs;
                        $tag = "$tagNs:$tagName";
                        return "</$tag>";
                    },
                    $xml
                );
            }
        } else {
            $xml = $document->saveXML();
        }

        // Reload for any changes to take effect:
        $document->loadXML($xml);

        return $document;
    }
}
