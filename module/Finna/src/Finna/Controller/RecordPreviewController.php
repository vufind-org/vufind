<?php

/**
 * Record preview controller
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
 * @package  Controller
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */

namespace Finna\Controller;

use Finna\Controller\Plugin\Preview;
use InvalidArgumentException;

/**
 * Record preview controller
 *
 * @category VuFind
 * @package  Controller
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */
class RecordPreviewController extends \VuFind\Controller\AbstractBase
{
    /**
     * Action for record preview form.
     *
     * @return mixed
     */
    public function previewFormAction()
    {
        $config = $this->getConfig();
        if (empty($config->NormalizationPreview->url)) {
            throw new \Exception('Normalization preview URL not configured');
        }

        if ($this->formWasSubmitted()) {
            // Load record and redirect to validation results on errors or record page otherwise:
            $result = $this->plugin('preview')->loadAndValidatePreviewRecord();
            if (Preview::VALIDATION_ERRORS === $result['validation_result']) {
                return $this->redirect()->toRoute('record-validationreport', ['id' => '0']);
            }
            return $this->redirect()->toRoute('record-home', ['id' => 0]);
        }

        $httpService = $this->serviceLocator->get(\VuFindHttp\HttpService::class);
        $client = $httpService->createClient(
            $config->NormalizationPreview->url,
            \Laminas\Http\Request::METHOD_POST
        );
        $client->setOptions(['useragent' => 'FinnaRecordPreview VuFind']);
        $client->setParameterPost(
            ['func' => 'get_sources']
        );
        $response = $client->send();
        if (!$response->isSuccess()) {
            throw new \Exception(
                'Failed to load source list: ' . $response->getStatusCode() . ' '
                . $response->getReasonPhrase()
            );
        }
        $body = $response->getBody();
        $sources = json_decode($body, true);
        array_walk(
            $sources,
            function (&$a) {
                $a['institutionName'] = $this->translate(
                    '0/' . $a['institution'] . '/',
                    [],
                    $a['institution']
                );
            }
        );
        $searchConfig = $this->getConfig('searches');
        if (!empty($searchConfig->Records->sources)) {
            foreach (explode(',', $searchConfig->Records->sources) as $priority => $id) {
                foreach ($sources as &$source) {
                    if ($id === $source['id']) {
                        $source['priority'] = $priority;
                        break;
                    }
                }
                unset($source);
            }
        }
        usort(
            $sources,
            function ($a, $b) {
                $res = strcmp($a['institutionName'], $b['institutionName']);
                if ($res === 0) {
                    $res = strcasecmp($a['id'], $b['id']);
                }
                return $res;
            }
        );

        // Create ViewModel directly to avoid trying to actually load a record:
        return new \Laminas\View\Model\ViewModel(compact('sources'));
    }

    /**
     * Validate a record and return result as JSON
     *
     * @return mixed
     */
    public function validateAction()
    {
        $result = [];
        // Load and validate record:
        try {
            $previewResult = $this->plugin('preview')->loadAndValidatePreviewRecord();
            if ($previewResult['errors']) {
                $result['errors'] = $previewResult['errors'];
            } elseif (!$previewResult['validation_report']) {
                $result['errors'] = ['No validation report available'];
            } else {
                // Copy messages to the result:
                foreach (['errors', 'warnings', 'recommendations'] as $key) {
                    if (null !== ($messages = $previewResult['validation_report'][$key] ?? null)) {
                        $result['validation_report'][$key] = $messages;
                    }
                }
            }
        } catch (InvalidArgumentException $e) {
            $result['errors'] = [$e->getMessage()];
        }

        $response = $this->getResponse();
        $response->setContent(json_encode($result, JSON_PRETTY_PRINT));
        $headers = $response->getHeaders();
        $headers->addHeaderLine('Content-Type', 'application/json');
        return $response;
    }
}
