<?php

/**
 * Class for accessing OCLC WorldCat search API v2.
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2024.
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
 * @package  WorldCat
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */

namespace VuFindSearch\Backend\WorldCat2;

use Laminas\Http\Client\Exception\RuntimeException as ExceptionRuntimeException;
use Laminas\Http\Exception\InvalidArgumentException;
use Laminas\Http\Exception\RuntimeException;
use Laminas\Http\Response;
use Laminas\Log\LoggerAwareInterface;
use League\OAuth2\Client\OptionProvider\HttpBasicAuthOptionProvider;
use League\OAuth2\Client\Provider\Exception\IdentityProviderException;
use League\OAuth2\Client\Provider\GenericProvider;
use VuFind\Log\LoggerAwareTrait;
use VuFindSearch\ParamBag;

/**
 * Class for accessing OCLC WorldCat search API v2.
 *
 * @category VuFind
 * @package  WorldCat
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
class Connector implements LoggerAwareInterface
{
    use LoggerAwareTrait;

    /**
     * OAuth2 provider
     *
     * @var GenericProvider
     */
    protected $authProvider;

    /**
     * Constructor
     *
     * @param \Laminas\Http\Client $client  An HTTP client object
     * @param array                $options Additional config settings
     */
    public function __construct(
        protected \Laminas\Http\Client $client,
        protected array $options = []
    ) {
        $authOptions = [
            'clientId' => $options['wskey'],
            'clientSecret' => $options['secret'],
            'urlAuthorize' => 'https://oauth.oclc.org/auth',
            'urlAccessToken' => 'https://oauth.oclc.org/token',
            'urlResourceOwnerDetails' => '',
        ];
        $optionProvider = new HttpBasicAuthOptionProvider();
        $this->authProvider = new GenericProvider($authOptions, compact('optionProvider'));
    }

    /**
     * Return a fake API response for development purposes.
     * TODO: delete when no longer needed.
     *
     * @return array
     */
    protected function getFakeResponse()
    {
        $sampleResponse = <<<SAMPLE
              {
                "numberOfRecords": 2,
                "briefRecords": [
                  {
                    "oclcNumber": "44959645",
                    "title": "Pride and prejudice.",
                    "creator": "Jane Austen",
                    "date": "199u",
                    "language": "eng",
                    "generalFormat": "Book",
                    "specificFormat": "Digital",
                    "publisher": "Project Gutenberg",
                    "publicationPlace": "Champaign, Ill.",
                    "isbns": [
                      "0585013365",
                      "9780585013367",
                      "9781925480337",
                      "192548033X"
                    ],
                    "mergedOclcNumbers": [
                      "818363152",
                      "854852439",
                      "859164912",
                      "956345342"
                    ],
                    "catalogingInfo": {
                      "catalogingAgency": "N\$T",
                      "transcribingAgency": "N\$T",
                      "catalogingLanguage": "eng",
                      "levelOfCataloging": "L"
                    }
                  },
                  {
                    "oclcNumber": "1103229133",
                    "title": "Pride and Prejudice [eBook - RBdigital].",
                    "creator": "Jane Austen",
                    "date": "1998",
                    "language": "eng",
                    "generalFormat": "Book",
                    "specificFormat": "Digital",
                    "publisher": "Project Gutenberg Literary Archive Foundation",
                    "publicationPlace": "Salt Lake City",
                    "isbns": [
                      "9781470398842",
                      "1470398842"
                    ],
                    "mergedOclcNumbers": null,
                    "catalogingInfo": {
                      "catalogingAgency": "HQD",
                      "transcribingAgency": "HQD",
                      "catalogingLanguage": "eng",
                      "levelOfCataloging": " "
                    }
                  }
                ]
              }
            SAMPLE;
        return json_decode($sampleResponse, true);
    }

    /**
     * Get an OAuth2 token.
     *
     * @return string
     * @throws IdentityProviderException
     */
    public function getToken(): string
    {
        return $this->authProvider->getAccessToken('client_credentials', ['scope' => 'wcapi'])->getToken();
    }

    /**
     * Make an API call.
     *
     * @param string $path  Path to query
     * @param array  $query Query parameters
     *
     * @return Response
     * @throws IdentityProviderException
     * @throws InvalidArgumentException
     * @throws RuntimeException
     * @throws ExceptionRuntimeException
     */
    public function makeApiCall(string $path, array $query = [])
    {
        $headers = [
            'Authorization: Bearer ' . $this->getToken(),
        ];
        $this->client->setHeaders($headers);
        $this->client->setUri("https://metadata.api.oclc.org$path?" . http_build_query($query));
        return $this->client->send();
    }

    /**
     * Retrieve a specific record.
     *
     * @param string   $id     Record ID to retrieve
     * @param ParamBag $params Parameters
     *
     * @throws \Exception
     * @return string    MARC XML
     */
    public function getRecord($id, ParamBag $params = null)
    {
        // TODO: implement something real here.
        $this->debug("Fetching record $id");
        //$result = $this->makeApiCall('/worldcat/search/brief-bibs/' . urlencode($id));
        $result = $this->getFakeResponse();
        $found = false;
        foreach ($result['briefRecords'] as $record) {
            if ($record['oclcNumber'] === $id) {
                $found = true;
                break;
            }
        }
        return [
            'docs' => $found ? [$record] : [],
            'offset' => 0,
            'total' => $found ? 1 : 0,
        ];
    }

    /**
     * Execute a search.
     *
     * @param ParamBag $params Parameters
     * @param int      $offset Search offset
     * @param int      $limit  Search limit
     *
     * @return string
     */
    public function search(ParamBag $params, $offset, $limit)
    {
        // TODO: implement something real here.
        //$result = $this->makeApiCall("/worldcat/search/brief-bibs", ['q' => 'test']);
        $response = $this->getFakeResponse();
        $docs = $response['briefRecords'];
        $total = $response['numberOfRecords'];
        return compact('docs', 'offset', 'total');
    }
}
