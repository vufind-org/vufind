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
use VuFind\Db\Entity\UserEntityInterface;
use VuFind\Service\GetServiceTrait;

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
     * Place order action form key
     *
     * @var string
     */
    public const PLACE_ORDER_FORM = 'PlaceOrder';

    /**
     * Unique identifier to identify forms used for reservation lists.
     *
     * @var string
     */
    public const FORM_ID = 'ReservationListRequest';

    /**
     * Keys to look for in an array to be returned when searching for requested params.
     *
     * @var array
     */
    public const PLACE_ORDER_REQUEST_KEYS = [
        'firstName' => '',
        'lastName' => '',
        'phone' => '',
        'email' => '',
        'pickup_date' => '',
        'message' => '',
    ];

    /**
     * Order form configuration defined.
     *
     * @var array
     */
    protected array $orderFormConfig = [];

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
    public function getValuesForPlaceOrderForm(
        FinnaResourceListEntityInterface $list,
        UserEntityInterface $user,
        array $requestValues
    ): array {
        $result = [
            'rl_list_id' => $list->getId(),
            'rl_institution' => $list->getInstitution(),
            'rl_list_identifier' => $list->getListConfigIdentifier(),
            'record_ids_text' => '',
            'record_source_and_ids' => [],
        ];
        $reservationListService = $this->getService(\Finna\ReservationList\ReservationListService::class);
        foreach ($reservationListService->getResourcesForList($list, $user) as $resource) {
            $result['record_ids_text'] .= $resource->getRecordId() . '||' . $resource->getTitle() . PHP_EOL;
            $result['record_source_and_ids'][] = $resource->getSource() . '|' . $resource->getRecordId();
        }
        foreach (array_intersect_key($requestValues, self::PLACE_ORDER_REQUEST_KEYS) as $foundKey => $value) {
            $result[$foundKey] = $requestValues[$foundKey];
        }
        return $result;
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
        $form = $this->getService(\Finna\ReservationList\Form\Form::class);
        $form->buildFromConfig($this->orderFormConfig, self::FORM_ID, $prefill);
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
     * @param array $config List specific configuration from ReservationList.yaml
     *
     * @return static
     */
    public function init(array $config): static
    {
        $orderFormKey = $config['Forms']['PlaceOrder'] ?? 'default';
        $definedForms = $this->getService(\Finna\Config\YamlReader::class)
            ->getFinna('ReservationList.yaml', 'config/finna', true)['Forms'] ?? [];
        if (!$definedForms) {
            throw new Exception('ReservationList: No forms defined.');
        }
        $this->orderFormConfig = $definedForms[self::PLACE_ORDER_FORM][$orderFormKey];
        return $this;
    }
}
