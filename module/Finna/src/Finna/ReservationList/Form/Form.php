<?php

/**
 * Configurable form.
 *
 * PHP version 8
 *
 * Copyright (C) The National Library of Finland 2018-2022.
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
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:controllers Wiki
 */

namespace Finna\ReservationList\Form;

/**
 * Configurable form.
 *
 * @category VuFind
 * @package  ReservationList
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:controllers Wiki
 */
class Form extends \Finna\Form\Form
{
    /**
     * Reservation request form id.
     *
     * @var string
     */
    public const RESERVATION_LIST_REQUEST = 'ReservationListRequest';

    /**
     * Recipients for reservation lists
     *
     * @var array
     */
    protected array $recipients = [];

    /**
     * Set recipients
     *
     * @param array $recipients Array containing recipients [name, email]
     *
     * @return void
     */
    public function setRecipients(array $recipients): void
    {
        $this->recipients = $recipients;
    }

    /**
     * Return form recipient. Name is in singular to override inherited methods
     *
     * @param array $postParams Posted form data
     *
     * @return array with name, email or null if not configured
     */
    public function getRecipient($postParams = null)
    {
        return $this->recipients;
    }

    /**
     * Get form elements
     *
     * @param array $config Form configuration
     *
     * @return array
     */
    protected function getFormElements($config)
    {
        $elements = parent::getFormElements($config);
        // Add hidden fields for reservation list order form
        $elements['institution'] = ['type' => 'hidden', 'name' => 'institution', 'value' => null];
        $elements['listIdentifier'] = ['type' => 'hidden', 'name' => 'listIdentifier', 'value' => null];
        $elements['listId'] = ['type' => 'hidden', 'name' => 'listId', 'value' => null];
        $elements['recordId'] = ['type' => 'hidden', 'name' => 'recordId', 'value' => null];
        return $elements;
    }

    /**
     * Build form with configuration obtained from ReservationList.yaml <Action>Forms section.
     *
     * @param array  $formConfig Configuration for a list found under ReservationList.yaml Forms section.
     * @param string $formID     [Optional] Form ID for internal use.
     * @param array  $prefill    [Optional] Prefill form with these values.
     *
     * @return void
     * @throws \Exception
     */
    public function buildFromConfig(
        array $formConfig,
        string $formID = 'default',
        array $prefill = []
    ) {
        $this->formElementConfig = $this->parseConfig($formID, $formConfig, [], $prefill);
        $this->buildForm();
    }
}
