<?php

/**
 * Abstract handler
 *
 * PHP Version 8
 *
 * Copyright (C) The National Library of Finland 2024.
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
 * @category VuFind
 * @package  ReservationList
 * @author   Juha Luoma <juha.luoma@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:controllers Wiki
 */

namespace Finna\ReservationList\Handler;

use Exception;
use Finna\Db\Entity\FinnaResourceListEntityInterface;
use Finna\ReservationList\Form\Form;
use Psr\Container\ContainerInterface;
use VuFind\Auth\ILSAuthenticator;
use VuFind\Db\Entity\UserEntityInterface;
use VuFind\Db\Service\UserCardServiceInterface;
use VuFind\Service\GetServiceTrait;

use function in_array;

/**
 * Abstract handler
 *
 * @category VuFind
 * @package  ReservationList
 * @author   Juha Luoma <juha.luoma@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org   Main Site
 */
abstract class AbstractBase implements HandlerInterface, \Laminas\Log\LoggerAwareInterface
{
    use \VuFind\Log\LoggerAwareTrait;
    use GetServiceTrait;

    /**
     * Unique identifier to identify forms used for reservation lists.
     *
     * @var string
     */
    public const FORM_ID = 'ReservationListRequest';

    /**
     * Order form configuration defined.
     *
     * @var array
     */
    protected array $orderFormConfig = [];

    /**
     * Singular item order form configuration
     *
     * @var array
     */
    protected array $singleOrderFormConfig = [];

    /**
     * Title translations as lang code => translation
     *
     * @var array
     */
    protected array $titleTranslations = [];

    /**
     * Description translations as lang code => translation
     *
     * @var array
     */
    protected array $descriptionTranslations = [];

    /**
     * Address information
     *
     * @var array
     */
    protected array $addressInfo = [];

    /**
     * Identifier
     *
     * @var string
     */
    protected string $identifier;

    /**
     * Library card sources
     *
     * @var array
     */
    protected array $libraryCardSources = [];

    /**
     * Datasources
     *
     * @var array
     */
    protected array $datasources = [];

    /**
     * Recipient
     *
     * @var array
     */
    protected array $recipient = [];

    /**
     * Connection type
     *
     * @var string
     */
    protected string $connectionType;

    /**
     * Connection settings
     *
     * @var array
     */
    protected array $connectionSettings = [];

    /**
     * Institution
     *
     * @var string
     */
    protected string $institution;

    /**
     * Is the list enabled
     *
     * @var bool
     */
    protected bool $enabled;

    /**
     * Specific type of the list
     *
     * @var string
     */
    protected string $listType;

    /**
     * List configuration as an array
     *
     * @var array
     */
    protected array $listConfiguration;

    /**
     * Is enabled
     *
     * @return bool
     */
    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    /**
     * Get translation for title
     *
     * @param string $language Language to get title for
     *
     * @return string
     */
    public function getTitle(string $language): string
    {
        return $this->titleTranslations[$language] ?? '';
    }

    /**
     * Get translation for description
     *
     * @param string $language Language to get description for
     *
     * @return string
     */
    public function getDescription(string $language): string
    {
        return $this->descriptionTranslations[$language] ?? '';
    }

    /**
     * Get address information
     *
     * @return array
     */
    public function getAddress(): array
    {
        return $this->addressInfo;
    }

    /**
     * Get recipient
     *
     * @return array
     */
    public function getRecipient(): array
    {
        return $this->recipient;
    }

    /**
     * Check if library card matches to allowed sources
     *
     * @param string $libraryCardSource Library card source
     *
     * @return bool
     */
    public function cardIsValid(string $libraryCardSource): bool
    {
        return in_array($libraryCardSource, $this->libraryCardSources);
    }

    /**
     * Check if datasource matches to allowed sources
     *
     * @param string $datasource Datasource
     *
     * @return bool
     */
    public function datasourceIsValid(string $datasource): bool
    {
        return in_array($datasource, $this->datasources);
    }

    /**
     * Get connection type
     *
     * @return string
     */
    public function getConnectionType(): string
    {
        return $this->connectionType;
    }

    /**
     * Get connection settings
     *
     * @return array
     */
    public function getConnectionSettings(): array
    {
        return $this->connectionSettings;
    }

    /**
     * Get institution
     *
     * @return string
     */
    public function getInstitution(): string
    {
        return $this->institution;
    }

    /**
     * Get identifier
     *
     * @return string
     */
    public function getIdentifier(): string
    {
        return $this->identifier;
    }

    /**
     * Get all list properties
     *
     * @return array
     */
    public function getAsArray(): array
    {
        return $this->listConfiguration;
    }

    /**
     * Get api url
     *
     * @return string
     */
    public function getApiUrl(): string
    {
        if ($url = $this->getConnectionSettings()['base_url'] ?? '') {
            return str_ends_with($url, '/') ? $url : "$url/";
        }
        return '';
    }

    /**
     * Get api secret
     *
     * @return string
     */
    public function getApiSecret(): string
    {
        return $this->getConnectionSettings()['secret'] ?? '';
    }

    /**
     * Get email sender name
     *
     * @return string
     */
    public function getSenderName(): string
    {
        return $this->getConnectionSettings()['Sender']['name'] ?? '';
    }

    /**
     * Get email sender
     *
     * @return string
     */
    public function getSenderEmail(): string
    {
        return $this->getConnectionSettings()['Sender']['email'] ?? '';
    }

    /**
     * Get email sender
     *
     * @return string
     */
    public function getEmailSubject(): string
    {
        return $this->getConnectionSettings()['Subject'] ?? '';
    }

    /**
     * Use patron id to send information
     *
     * @return bool
     */
    public function getUsePatronId(): bool
    {
        return $this->getConnectionSettings()['useKohaId'] ?? true;
    }

    /**
     * Constructor
     *
     * @param ContainerInterface $serviceLocator Service locator used with GetServiceTrait
     */
    public function __construct(ContainerInterface $serviceLocator)
    {
        $this->serviceLocator = $serviceLocator;
    }

    /**
     * Get values required for placing the order.
     *
     * @param FinnaResourceListEntityInterface $list          List being ordered
     * @param UserEntityInterface              $user          User who owns the list
     * @param array                            $requestValues Values obtained i.e from post request as array
     *
     * @return array
     */
    public function getValuesForListOrder(
        FinnaResourceListEntityInterface $list,
        UserEntityInterface $user,
        array $requestValues
    ): array {
        $result = $this->getValuesForSingleOrder($list, $user, $requestValues);
        $reservationListService = $this->getService(\Finna\ReservationList\ReservationListService::class);
        $result['record_ids_text'] = '';
        $result['record_source_and_ids'] = [];
        foreach ($reservationListService->getResourcesForList($list, $user) as $resource) {
            $result['record_ids_text'] .= $resource->getTitle() . ' (' . $resource->getRecordId() . ')' . PHP_EOL;
            $result['record_source_and_ids'][] = $resource->getSource() . '|' . $resource->getRecordId();
        }
        return $result;
    }

    /**
     * Get values for placing single order form
     *
     * @param FinnaResourceListEntityInterface $list          List being ordered
     * @param UserEntityInterface              $user          User who owns the list
     * @param array                            $requestValues Values obtained i.e from post request as array
     *
     * @return array
     */
    public function getValuesForSingleOrder(
        FinnaResourceListEntityInterface $list,
        UserEntityInterface $user,
        array $requestValues
    ): array {
        $cardInfo = $this->getPreferredCardInfo($user);
        $result = [
            'listId' => $list->getId(),
            'institution' => $list->getInstitution(),
            'listIdentifier' => $list->getListConfigIdentifier(),
            'full_name' => $requestValues['full_name'] ?? $cardInfo['full_name'],
            'email' => $requestValues['email'] ?? $cardInfo['email'],
            'phone' => $requestValues['phone'] ?? null,
            'pickup_date' => $requestValues['pickup_date'] ?? null,
            'message' => $requestValues['message'] ?? null,
            'card_info' => $cardInfo['card_name'],
        ];

        if (empty($requestValues['recordId'])) {
            return $result;
        }

        $recordLoader = $this->getService(\VuFind\Record\Loader::class);
        $recordID = $requestValues['recordId'];
        $source = $requestValues['source'] ?? DEFAULT_SEARCH_BACKEND;
        $record = $recordLoader->load($recordID, $source);
        $result['list_title'] = $requestValues['list_title'];
        $result['recordId'] = $record->getUniqueID();
        $result['source'] = $record->getSourceIdentifier();
        $result['record_ids_text'] = $record->getUniqueID() . '||' . $record->getTitle();
        $result['record_source_and_ids'] = [$record->getSourceIdentifier() . '|' . $record->getUniqueID()];
        return $result;
    }

    /**
     * Get preferred card info as associative array. Shibboleth login saves the whole name into last name so
     * try to get users name from patron primarily. Prefer card name from database and use local name (without prefix)
     * as fallback. Get local patron id (without prefix).
     *
     * @param UserEntityInterface $user User to get information for
     *
     * @return array [firstname, lastname, patron_id, card_name]
     */
    protected function getPreferredCardInfo(UserEntityInterface $user): array
    {
        $patron = $this->getService(ILSAuthenticator::class)->storedCatalogLogin();
        $cardService = $this->getService(\VuFind\Db\Service\PluginManager::class)->get(UserCardServiceInterface::class);
        $cardName = $patron['__local_cat_username'] ?? $patron['cat_username'];
        if ($cards = $cardService->getLibraryCards($user, null, $patron['cat_username'])) {
            $dbCardName = reset($cards)->getCardName();
            if ($dbCardName !== $patron['cat_username']) {
                $cardName = $dbCardName;
            }
        }

        $firstName = $patron['firstname'];
        $lastName = $patron['lastname'];
        $fullName = trim("$firstName $lastName");

        return [
            'first_name' => $firstName,
            'last_name' => $lastName,
            'full_name' => $fullName,
            'patron_id' => $patron['__local_id'] ?? $patron['id'],
            'email' => $patron['email'],
            'card_name' => $cardName,
        ];
    }

    /**
     * Build form with configuration obtained from ReservationList.yaml <Action>Forms section.
     *
     * @param array $prefill Prefill form with these values.
     *
     * @return Form
     */
    public function getPlaceOrderForm(array $prefill = []): Form
    {
        $form = $this->getService(Form::class);
        $form->buildFromConfig($this->orderFormConfig, self::FORM_ID, $prefill);
        $form->setData($prefill);
        $form->setName(self::FORM_ID);
        return $form;
    }

    /**
     * Build form with configuration obtained from ReservationList.yaml <Action>Forms section.
     *
     * @param array $prefill Prefill form with these values.
     *
     * @return Form
     */
    public function getSingleOrderForm(array $prefill = []): Form
    {
        $form = $this->getService(Form::class);
        $form->buildFromConfig($this->singleOrderFormConfig, self::FORM_ID, $prefill);
        $form->setData($prefill);
        $form->setName(self::FORM_ID);
        return $form;
    }

    /**
     * Places an order
     *
     * @param array               $formValues Values gathered from submitted form
     * @param UserEntityInterface $user       User entity
     *
     * @return array [
     *  external_id: Id in external service or null,
     *  success: true or false,
     *  pickup_date: date for preferred pickup,
     *  connection Type of the connection
     * ]
     */
    abstract public function placeOrder(array $formValues, UserEntityInterface $user): array;

    /**
     * Check list status. Used for external services.
     *
     * @param FinnaResourceListEntityInterface $list List to check for status
     *
     * @return string
     */
    abstract public function getListStatus(FinnaResourceListEntityInterface $list): string;

    /**
     * Initialize connection handler
     *
     * @param string $institution List owner institution code
     * @param array  $config      List specific configuration as an array
     *
     * @return static
     */
    public function init(string $institution, array $config = []): static
    {
        $this->titleTranslations = $config['Translations']['Title'] ?? [];
        $this->descriptionTranslations = $config['Translations']['Description'] ?? [];
        $this->addressInfo = $config['Information'] ?? [];
        $this->identifier = $config['Identifier'] ?? '';
        $this->libraryCardSources = $config['LibraryCardSources'] ?? [];
        $this->datasources = $config['Datasources'] ?? [];
        $this->recipient = $config['Recipient'] ?? [];
        $this->connectionType = $config['Connection']['type'] ?? '';
        $this->connectionSettings = $config['Connection'] ?? [];
        $this->enabled = $config['Enabled'] ?? false;
        $this->listType = $config['Type'] ?? 'default';
        $this->institution = $institution;
        $this->listConfiguration = $config;

        $definedForms = $this->getService(\Finna\Config\YamlReader::class)
            ->getFinna('ReservationList.yaml', 'config/finna', true)['Forms'] ?? [];
        // Check that single order and multi order forms exist
        $orderFormConfig = $definedForms['PlaceOrder']['default'] ?? [];
        if (!$orderFormConfig) {
            throw new Exception('ReservationList: No forms defined.');
        }
        // Allow selecting preferred forms in future.
        $selectedOrderForm = $config['Forms']['PlaceOrder'] ?? 'default';
        $this->orderFormConfig = $definedForms['PlaceOrder'][$selectedOrderForm]
            ?? $orderFormConfig;

        $singleOrderFormConfig = $definedForms['PlaceOrder']['single'] ?? [];
        $selectedSingleOrderForm = $config['Forms']['PlaceSingleOrder'] ?? 'single';
        $this->singleOrderFormConfig = $definedForms['PlaceOrder'][$selectedSingleOrderForm]
            ?? $singleOrderFormConfig;

        // Extend form as required
        if ($extend = $this->singleOrderFormConfig['extends'] ?? false) {
            $extendFrom = $definedForms['PlaceOrder'][$extend];
            $mergedFields = [...$extendFrom['fields'], ...$this->singleOrderFormConfig['fields'] ?? []];
            $this->singleOrderFormConfig = array_merge(
                $extendFrom,
                $this->singleOrderFormConfig
            );
            $this->singleOrderFormConfig['fields'] = $mergedFields;
        }
        return $this;
    }
}
