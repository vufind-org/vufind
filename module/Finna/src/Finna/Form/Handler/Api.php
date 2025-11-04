<?php

/**
 * Class Api
 *
 * PHP version 8
 *
 * Copyright (C) The National Library of Finland 2021-2022.
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
 * along with this program; if not, see
 * <https://www.gnu.org/licenses/>.
 *
 * @category VuFind
 * @package  Form
 * @author   Josef Moravec <moravec@mzk.cz>
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  https://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */

declare(strict_types=1);

namespace Finna\Form\Handler;

use Psr\Log\LoggerAwareInterface;
use VuFind\Db\Entity\UserEntityInterface;
use VuFind\Form\Handler\HandlerInterface;
use VuFind\I18n\Translator\TranslatorAwareInterface;
use VuFind\Log\LoggerAwareTrait;
use VuFindHttp\HttpServiceAwareInterface;

use function in_array;
use function is_array;
use function strval;

/**
 * Class Api
 *
 * @category VuFind
 * @package  Form
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  https://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
class Api implements
    HandlerInterface,
    LoggerAwareInterface,
    TranslatorAwareInterface,
    HttpServiceAwareInterface
{
    use LoggerAwareTrait;
    use \VuFind\I18n\Translator\TranslatorAwareTrait;
    use \VuFindHttp\HttpServiceAwareTrait;

    /**
     * Site base url
     *
     * @var string
     */
    protected $baseUrl;

    /**
     * Constructor
     *
     * @param string $baseUrl Site base url
     */
    public function __construct(string $baseUrl)
    {
        $this->baseUrl = $baseUrl;
    }

    /**
     * Get data from submitted form and process them.
     *
     * @param \VuFind\Form\Form                     $form   Submitted form
     * @param \Laminas\Mvc\Controller\Plugin\Params $params Request params
     * @param ?UserEntityInterface                  $user   Authenticated user
     *
     * @return bool
     */
    public function handle(
        \VuFind\Form\Form $form,
        \Laminas\Mvc\Controller\Plugin\Params $params,
        ?UserEntityInterface $user = null
    ): bool {
        if (!($form instanceof \Finna\Form\Form)) {
            throw new \VuFind\Exception\BadConfig('Unexpected form class');
        }

        $recordParamMap = [
            'record' => 'record',
            'record_id' => 'recordId',
            'record_info' => 'recordInfo',
        ];

        $postParams = (array)$params->fromPost();
        $fieldValues = $form->mapRequestParamsToFieldValues($postParams);
        $message = array_column($fieldValues, 'value', 'name');

        foreach ($fieldValues as $field) {
            if (in_array($field['name'], array_keys($recordParamMap))) {
                continue;
            }
            $details = [
                'type' => $field['type'],
                'label' => $field['label'] ?? '',
                'labelTranslated' => $this->translate($field['label'] ?? ''),
                'value' => $field['value'] ?? '',
            ];
            if (isset($field['valueLabel'])) {
                $details['valueLabel'] = $field['valueLabel'];
                $details['valueLabelTranslated'] = is_array($field['valueLabel'])
                    ? array_map([$this, 'translate'], $field['valueLabel'])
                    : $this->translate($field['valueLabel']);
            }
            $message['fields'][$field['name']] = $details;
        }
        foreach ($recordParamMap as $from => $to) {
            if (isset($message[$from])) {
                $message[$to] = $message[$from];
                unset($message[$from]);
            }
        }
        $message['emailSubject'] = $form->getEmailSubject($params->fromPost());
        $message['internalUserId'] = $user?->getId();
        $message['viewBaseUrl'] = $this->baseUrl;
        if ($driver = $form->getRecord()) {
            $message['recordMetadata'] = [
                'title' => $driver->tryMethod('getTitle'),
                'authors' => $driver->tryMethod('getAuthorsWithRoles'),
                'publicationDates' => $driver->tryMethod('getPublicationDates'),
                'formats' => array_values(
                    array_unique(
                        array_map(
                            function ($s) {
                                if ($s instanceof \VuFind\I18n\TranslatableString) {
                                    return $s->getDisplayString();
                                }
                                return strval($s);
                            },
                            $driver->tryMethod('getFormats', [], [])
                        )
                    )
                ),
                'formatsRaw' => array_values(
                    array_unique(
                        array_map(
                            'strval',
                            $driver->tryMethod('getFormats', [], [])
                        )
                    )
                ),
                'isbns' => $driver->tryMethod('getISBNs'),
                'issns' => $driver->tryMethod('getISSNs'),
            ];
            if ($openUrl = $driver->tryMethod('getOpenUrl')) {
                parse_str($openUrl, $openUrlFields);
                $message['recordMetadata']['openurl'] = $openUrlFields;
            }
            if ($rawData = $driver->getRawData()) {
                if ($holdings = $rawData['holdings_txtP_mv'] ?? []) {
                    $message['recordHoldingsSummary'] = (array)$holdings;
                }
            }
        }

        $apiSettings = $form->getApiSettings();

        if (empty($apiSettings['url'])) {
            throw new \VuFind\Exception\BadConfig(
                "'apiSettings/url' is required for api handler"
            );
        }
        if (
            !str_starts_with($apiSettings['url'], 'https://')
            && $apiSettings['url'] !== 'test'
            && 'development' !== APPLICATION_ENV
        ) {
            throw new \VuFind\Exception\BadConfig(
                "'apiSettings/url' must be 'test' or begin with https://"
            );
        }

        if ('test' === $apiSettings['url']) {
            $controller = $params->getController();
            if ('lightbox' === ($postParams['layout'] ?? '')) {
                $controller->flashMessenger()
                    ->addErrorMessage("API url set to 'test' - see dump below.");
                $controller->flashMessenger()->addWarningMessage('API Request:');
                $controller->flashMessenger()->addWarningMessage(
                    json_encode($message, JSON_PRETTY_PRINT)
                );
                return false;
            } else {
                header('Content-type: application/json');
                echo json_encode($message, JSON_PRETTY_PRINT);
                exit(0);
            }
        }
        $messageJson = json_encode($message);
        $client = $this->httpService->createClient(
            $apiSettings['url'],
            \Laminas\Http\Request::METHOD_POST
        );
        $client->setOptions(['useragent' => 'VuFind']);
        $client->setRawBody($messageJson);
        $headers = array_merge(
            [
                'Content-Type' => 'application/json',
                'Content-Length' => mb_strlen($messageJson, 'UTF-8'),
            ],
            (array)($apiSettings['headers'] ?? [])
        );
        try {
            if ($username = $apiSettings['username'] ?? '') {
                $method = ($apiSettings['authMethod'] ?? '') === 'digest'
                    ? \Laminas\Http\Client::AUTH_DIGEST
                    : \Laminas\Http\Client::AUTH_BASIC;
                $client->setAuth(
                    $username,
                    $apiSettings['password'] ?? '',
                    $method
                );
            }
            $client->setHeaders($headers);
            $result = $client->send();
            if ($result->getStatusCode() >= 300) {
                $this->logError(
                    "Sending of feedback form to '{$apiSettings['url']}' failed:"
                    . ' HTTP error ' . $result->getStatusCode() . ': '
                    . $result->getBody()
                );

                return false;
            }
            if (!empty($apiSettings['successCodes'])) {
                $codeOk = in_array(
                    (string)$result->getStatusCode(),
                    $apiSettings['successCodes']
                );
                if (!$codeOk) {
                    $this->logError(
                        "Sending of feedback form to '{$apiSettings['url']}' failed:"
                        . ' HTTP status code ' . $result->getStatusCode()
                        . ' not in configured sucess codes'
                    );

                    return false;
                }
            }
        } catch (\Exception $e) {
            $this->logError(
                "Sending of feedback form to '{$apiSettings['url']}' failed: "
                . $e->getMessage()
            );

            return false;
        }

        return true;
    }
}
