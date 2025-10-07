<?php

/**
 * Zotero Export Service
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
 * along with this program; if not, see
 * <https://www.gnu.org/licenses/>.
 *
 * @category VuFind
 * @package  Zotero
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */

declare(strict_types=1);

namespace VuFind\Export\Zotero;

use Laminas\Cache\Storage\StorageInterface;
use Laminas\Log\LoggerAwareInterface;
use Laminas\Session\Container as SessionContainer;
use League\OAuth1\Client\Credentials\CredentialsException;
use VuFind\Cache\CacheTrait;
use VuFind\Db\Entity\AccessTokenEntityInterface;
use VuFind\Db\Entity\UserEntityInterface;
use VuFind\Db\Service\AccessTokenServiceInterface;
use VuFind\Exception\ZoteroException;
use VuFind\Http\GuzzleService;
use VuFind\I18n\Translator\TranslatorAwareInterface;
use VuFind\I18n\Translator\TranslatorAwareTrait;
use VuFind\Log\LoggerAwareTrait;

use function assert;
use function count;
use function is_array;

/**
 * Zotero Export Service
 *
 * Class for authenticating with Zotero using OAuth.
 *
 * @category VuFind
 * @package  Zotero
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
class ZoteroService implements LoggerAwareInterface, TranslatorAwareInterface
{
    use CacheTrait;
    use LoggerAwareTrait;
    use TranslatorAwareTrait;

    /**
     * Access token type
     *
     * @var string
     */
    public const ACCESS_TOKEN_TYPE = 'zotero_oauth_token';

    /**
     * Constructor.
     *
     * @param SessionContainer            $sessionContainer   Session container
     * @param ZoteroOAuth                 $zoteroOAuth        Zotero OAuth handler
     * @param AccessTokenServiceInterface $accessTokenService Access token database service
     * @param GuzzleService               $httpService        Guzzle HTTP service (required for proper handling of
     * gzipped responses)
     * @param StorageInterface            $cache              Cache
     */
    public function __construct(
        protected SessionContainer $sessionContainer,
        protected ZoteroOAuth $zoteroOAuth,
        protected AccessTokenServiceInterface $accessTokenService,
        protected GuzzleService $httpService,
        StorageInterface $cache
    ) {
        $this->setCacheStorage($cache);
    }

    /**
     * Handle an export request
     *
     * @param UserEntityInterface $user        User
     * @param string              $callbackUrl Export callback URL for fetching the actual records
     *
     * @return int|string Number of records exported or an authorization URL for redirection
     *
     * @throws ZoteroException
     */
    public function export(UserEntityInterface $user, string $callbackUrl): int|string
    {
        // Store record callback URL in session:
        $this->sessionContainer->exportCallbackUrl = $callbackUrl;
        $accessToken
            = $this->accessTokenService->getByIdAndType((string)$user->getId(), self::ACCESS_TOKEN_TYPE, false);
        if (!$accessToken) {
            // Authenticate with Zotero:
            return $this->getZoteroAuthUrlWithTempCredentials();
        }
        try {
            $count = $this->exportToZotero($accessToken);
            // Rewrite the access token so that an actively used one doesn't expire so easily:
            $this->accessTokenService->deleteAccessToken($accessToken);
            $newAccessToken
                = $this->accessTokenService->getByIdAndType((string)$user->getId(), self::ACCESS_TOKEN_TYPE);
            $newAccessToken
                ->setUser($user)
                ->setData($accessToken->getData());
            $this->accessTokenService->persistEntity($newAccessToken);
            return $count;
        } catch (ZoteroException $e) {
            // Retry authentication once just in case our credentials have expired:
            $this->accessTokenService->deleteAccessToken($accessToken);
            return $this->getZoteroAuthUrlWithTempCredentials();
        }
    }

    /**
     * Handle OAuth authorization callback
     *
     * @param UserEntityInterface $user   User
     * @param array               $params Query parameters
     *
     * @return int|string Number of records exported or an authorization URL for redirection
     *
     * @throws ZoteroException
     */
    public function handleAuthCallback(UserEntityInterface $user, array $params): int|string
    {
        if (!$this->sessionContainer->tempCredentials) {
            // Authenticate with Zotero (this will redirect the user to Zotero and then back to this action):
            return $this->getZoteroAuthUrlWithTempCredentials();
        }

        // Retrieve credentials from Zotero:
        try {
            $credentials = $this->zoteroOAuth->getTokenCredentials(
                $this->sessionContainer->tempCredentials,
                $params['oauth_token'] ?? '',
                $params['oauth_verifier'] ?? ''
            );
        } catch (CredentialsException $e) {
            $this->logError('Could not retrieve token credentials from Zotero: ' . (string)$e);
            throw new ZoteroException('An error has occurred');
        }
        assert($credentials instanceof ZoteroTokenCredentials);
        unset($this->sessionContainer->tempCredentials);
        // Create access token:
        $accessToken = $this->accessTokenService->getByIdAndType((string)$user->getId(), self::ACCESS_TOKEN_TYPE);
        $accessToken
            ->setUser($user)
            ->setData(
                json_encode(
                    [
                        'zoteroUserId' => $credentials->getUserId(),
                        'zoteroApiKey' => $credentials->getSecret(),
                    ]
                )
            );
        $this->accessTokenService->persistEntity($accessToken);
        // Perform the actual export:
        return $this->exportToZotero($accessToken);
    }

    /**
     * Get temporary credentials from Zotero and return an authorization URL.
     *
     * @return string
     *
     * @throws ZoteroException
     */
    protected function getZoteroAuthUrlWithTempCredentials(): string
    {
        // Get request token from Zotero:
        try {
            $this->sessionContainer->tempCredentials = $this->zoteroOAuth->getTemporaryCredentials();
        } catch (CredentialsException $e) {
            $this->logError('Could not retrieve temporary credentials from Zotero: ' . (string)$e);
            throw new ZoteroException('Could not retrieve temporary credentials from Zotero');
        }

        // Redirect to Zotero for authentication:
        return $this->zoteroOAuth->getAuthorizationUrl($this->sessionContainer->tempCredentials);
    }

    /**
     * Export record(s) to Zotero using its v3 API.
     *
     * @param AccessTokenEntityInterface $accessToken Access token with Zotero credentials
     *
     * @return int Exported record count
     *
     * @throws ZoteroException
     */
    protected function exportToZotero(AccessTokenEntityInterface $accessToken): int
    {
        if (!($callbackUrl = $this->sessionContainer->exportCallbackUrl)) {
            $this->logError('Export callback URL not available in session');
            throw new ZoteroException('Export callback URL not available in session');
        }

        try {
            $vufindResponse = $this->httpService->createClient($callbackUrl)->request('GET', $callbackUrl);
        } catch (\Exception $e) {
            $this->logError("GET request for '$callbackUrl' failed: " . (string)$e);
            throw new ZoteroException('Export request failed', previous: $e);
        }

        if ($vufindResponse->getStatusCode() !== 200) {
            $this->logError(
                "GET request for '$callbackUrl' failed: "
                . $vufindResponse->getStatusCode() . ': ' . $vufindResponse->getReasonPhrase()
            );
            throw new ZoteroException('Export request failed');
        }

        if (!($records = json_decode($vufindResponse->getBody()->getContents()))) {
            $this->logError('Could not decode VuFind export response as JSON: ' . $vufindResponse->getBody());
            throw new ZoteroException('Could not decode export response');
        }

        $credentials = json_decode($accessToken->getData(), true);
        $zoteroUserId = $credentials['zoteroUserId'];
        $zoteroApiKey = $credentials['zoteroApiKey'];

        $apiUrl = "https://api.zotero.org/users/$zoteroUserId/items";
        // Make sure we send an array of records for a single one as well (casting to array doesn't work for objects!):
        $records = is_array($records) ? $records : [$records];
        $recordCount = count($records);
        $records = $this->cleanupRecords($records);
        // Zotero API accepts up to 50 records per request, so send chunks as required:
        $chunkCount = 0;
        $zoteroResponse = null;
        while ($records) {
            $chunk = array_splice($records, 0, 50);
            ++$chunkCount;
            try {
                $zoteroResponse = $this->httpService->createClient($apiUrl)->request(
                    'POST',
                    $apiUrl,
                    [
                        'json' => $chunk,
                        'headers' => [
                            'Zotero-API-Key' => $zoteroApiKey,
                        ],
                    ],
                );
            } catch (\Exception $e) {
                $this->logError("POST request for '$apiUrl' (chunk $chunkCount) failed: " . (string)$e);
                throw new ZoteroException('Zotero request failed', previous: $e);
            }
            if ($zoteroResponse->getStatusCode() !== 200) {
                $this->logError(
                    "POST request for '$apiUrl' failed: "
                    . $zoteroResponse->getStatusCode() . ': ' . $zoteroResponse->getReasonPhrase()
                    . ' - Response: ' . $zoteroResponse->getBody()->getContents()
                );
                if ($zoteroResponse->getStatusCode() === 403) {
                    // Forget insufficient credentials:
                    $this->accessTokenService->deleteAccessToken($accessToken);
                    throw new ZoteroException('Zotero: Access denied');
                } else {
                    throw new ZoteroException('export_fail');
                }
                break;
            }
        }

        if (!$zoteroResponse) {
            return 0;
        }

        // Check results:
        $results = json_decode($zoteroResponse->getBody()->getContents(), true);
        if ($results['failed'] ?? null) {
            $this->logError("Zotero export from '$callbackUrl' failed: " . var_export($results, true));
            throw new ZoteroException('export_fail');
        }

        // Reset callback URL so that we never export the same records again:
        unset($this->sessionContainer->exportCallbackUrl);
        return $recordCount;
    }

    /**
     * Clean up records by removing fields and remapping author types to match Zotero's schema.
     *
     * @param array $records Records
     *
     * @return array
     */
    protected function cleanupRecords(array $records): array
    {
        if (null === ($schemaJson = $this->getCachedData('zotero-schema'))) {
            $url = 'https://api.zotero.org/schema';
            $response = $this->httpService->createClient($url)->request('GET', $url);
            if ($response->getStatusCode() !== 200) {
                $this->logError(
                    "GET request for 'https://api.zotero.org/schema' failed: "
                    . $response->getStatusCode() . ': ' . $response->getReasonPhrase()
                    . ' - Response: ' . $response->getBody()->getContents()
                );
                throw new ZoteroException('An error has occurred');
            }
            $schemaJson = $response->getBody()->getContents();
            $this->putCachedData('zotero-schema', $schemaJson, 60 * 60 * 24);
        }
        if (!($fullSchema = json_decode($schemaJson, true))) {
            $this->logError('Failed to parse Zotero schema: ' . json_last_error_msg());
            $this->removeCachedData('zotero-schema');
            throw new ZoteroException('An error has occurred');
        }
        foreach ($records as &$record) {
            $itemType = $record->itemType ?? 'book';
            $itemSchema = $this->getItemTypeSchema($fullSchema, $itemType);
            // Check fields:
            foreach (array_keys(get_object_vars($record)) as $field) {
                if (!isset($itemSchema['fields'][$field])) {
                    // Check for field mapping:
                    if ($target = $itemSchema['mappedFields'][$field] ?? null) {
                        $record->$target = $record->$field;
                    }
                    // Remove original:
                    unset($record->$field);
                }
            }
            // Check authors:
            if (isset($record->creators)) {
                foreach ($record->creators as &$creator) {
                    if (!isset($itemSchema['creatorTypes'][$creator['creatorType']])) {
                        $creator->creatorType = $itemSchema['primaryCreatorType'];
                    }
                }
                // Unset reference:
                unset($creator);
            }
        }
        // Unset reference:
        unset($record);

        return $records;
    }

    /**
     * Get a subset of the schema for an item type modified for easier access.
     *
     * @param array  $fullSchema Full schema
     * @param string $itemType   Item type
     *
     * @return ?array
     */
    protected function getItemTypeSchema(array $fullSchema, string $itemType): ?array
    {
        foreach ($fullSchema['itemTypes'] as $itemTypeSchema) {
            if ($itemType === $itemTypeSchema['itemType']) {
                $result = [
                    'fields' => [
                        'itemType' => true,
                    ],
                ];
                foreach ($itemTypeSchema['fields'] as $fieldSpec) {
                    $result['fields'][$fieldSpec['field']] = true;
                    if ($baseField = $fieldSpec['baseField'] ?? null) {
                        $result['mappedFields'][$baseField] = $fieldSpec['field'];
                    }
                }
                foreach ($itemTypeSchema['creatorTypes'] as $creatorSpec) {
                    $result['creatorTypes'][$creatorSpec['creatorType']] = true;
                    if ($creatorSpec['primary'] ?? false) {
                        $result['primaryCreatorType'] = $creatorSpec['creatorType'];
                    }
                }
                return $result;
            }
        }

        return null;
    }
}
