<?php

/**
 * Prepares data for the Get This button.
 *
 * PHP version 8
 *
 * Copyright (C) Michigan State University Board of Trustees 2025.
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
 * along with this program; if not, see <https://www.gnu.org/licenses/>
 *
 * @category VuFind
 * @package  GetThis
 * @author   MSUL Public Catalog Team <LIB.DL.pubcat@msu.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */

namespace VuFind\GetThis;

use Exception;
use Psr\Log\LoggerAwareInterface;
use Throwable;
use VuFind\ILS\Logic\AvailabilityStatusInterface;
use VuFind\Log\LoggerAwareTrait;
use VuFind\RecordDriver\DefaultRecord as RecordDriver;
use VuFind\Regex\Regex;

use function array_key_exists;
use function call_user_func;
use function count;
use function is_array;

/**
 * Class to hold data for the Get This button.
 *
 * @category VuFind
 * @package  GetThis
 * @author   MSUL Public Catalog Team <LIB.DL.pubcat@msu.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/vufind/ Main page
 */
class GetThisLoader implements LoggerAwareInterface
{
    use LoggerAwareTrait;

    /**
     * Items.
     *
     * @var ?array
     */
    protected ?array $items;

    /**
     * Holding item id to use when none is passed.
     *
     * @var ?string
     */
    protected ?string $defaultItemId;

    /**
     * Sub-templates to display.
     *
     * @var ?array
     */
    protected ?array $subTemplates;

    /**
     * Sub-templates params from config.
     *
     * @var ?array
     */
    protected ?array $subTemplatesParams;

    /**
     * Record driver.
     *
     * @var RecordDriver
     */
    protected RecordDriver $recordDriver;

    /**
     * Initializes the loader.
     *
     * @param array $config Config to use
     * @param Regex $regex  Regex service
     */
    public function __construct(
        protected array $config,
        protected Regex $regex
    ) {
    }

    /**
     * Return the result of the function passed, negates the result if the first char is "!".
     *
     * @param string $function The function to test
     *
     * @return bool
     * @throws Exception
     */
    protected function isConditionFunctionFilled(string $function): bool
    {
        $negate = false;
        if (str_starts_with($function, '!')) {
            $function = substr($function, 1);
            $negate = true;
        }
        if (!method_exists($this, $function)) {
            throw new Exception('The condition function "' . $function . '" does not exist in ' . static::class);
        }
        $result = call_user_func([$this, $function]);
        return $negate ? !$result : $result;
    }

    /**
     * Whether the condition block contains an operator "and".
     *
     * @param array $conditions Array of conditions to determine the result
     *
     * @return bool
     * @throws Exception
     */
    protected function doConditionsContainAnd(array $conditions): bool
    {
        foreach ($conditions as $condition) {
            if (isset($condition['operator']) && $condition['operator'] === 'and') {
                return true;
            }
        }
        return false;
    }

    /**
     * Go through an array recursively to determine if the condition functions are matched.
     *
     * @param array $conditions Array of conditions to determine the result
     *
     * @return bool
     * @throws Exception
     */
    protected function loopThroughConditionBlock(array $conditions): bool
    {
        $result = false; // default to false if no conditions are found
        $and = $this->doConditionsContainAnd($conditions);
        foreach ($conditions as $condition) {
            if (isset($condition['operator'])) {
                continue;
            }
            $result = $this->areConditionsFilled($condition);
            // Any false result in AND mode means an overall false:
            if ($and === true && $result === false) {
                return false;
            }
            // Any true result in OR mode means an overall true:
            if ($and === false && $result === true) {
                return true;
            }
        }
        // If we got this far, the most recent result is the overall result:
        return $result;
    }

    /**
     * Go through an array recursively to determine if the condition functions are matched.
     *
     * @param array $condition Array of conditions to determine the result
     *
     * @return bool
     * @throws Exception
     */
    protected function areConditionsFilled(array $condition): bool
    {
        if (isset($condition['condition_function'])) {
            return $this->isConditionFunctionFilled($condition['condition_function']);
        } elseif (isset($condition['condition_group'])) {
            return $this->loopThroughConditionBlock($condition['condition_group']);
        } else {
            throw new Exception(
                'It seems like conditions are not properly formatted, unexpected value in array'
            );
        }
    }

    /**
     * Add template and template parameters to property.
     *
     * @param string $templateName   Template name
     * @param array  $templateConfig Template config
     *
     * @return void
     */
    protected function addSubTemplates(string $templateName, array $templateConfig): void
    {
        $this->subTemplates[] = $templateName;
        if (isset($templateConfig['view_variables'])) {
            $this->setSubTemplateParams($templateName, $templateConfig['view_variables']);
        }
    }

    /**
     * Add parameters to the template.
     *
     * @param string $templateName Template name
     * @param mixed  $value        Parameter to add
     *
     * @return void
     */
    protected function setSubTemplateParams(string $templateName, $value): void
    {
        $this->subTemplatesParams[$templateName] = $value;
    }

    /**
     * Add a parameter to the template.
     *
     * @param string $templateName Template name
     * @param string $key          Key value of the parameter
     * @param mixed  $value        Parameter to add
     *
     * @return void
     */
    protected function setSubTemplateParam(string $templateName, string $key, mixed $value): void
    {
        $this->subTemplatesParams[$templateName][$key] = $value;
    }

    /**
     * Get the templates to display according to the config file.
     *
     * @return array
     * @throws Exception
     */
    public function getSubTemplates(): array
    {
        if (isset($this->subTemplates)) {
            return $this->subTemplates;
        }
        try {
            foreach ($this->config['templates'] ?? [] as $templateName => $template) {
                // If the enabled attribute is not present, we display the template
                if ($template['enabled'] ?? true) {
                    // If condition_function is not present we display the templates
                    // If it's present we display the template only if the function exists and return true
                    if (
                        !isset($template['condition_function']) && !isset($template['condition_group'])
                        || $this->areConditionsFilled($template)
                    ) {
                        $this->addSubTemplates($templateName, $template);
                    }
                }
            }
        } catch (Throwable $t) {
            throw new Exception('Error with the get this configuration : ' . $t->getMessage(), previous: $t);
        }
        $this->sortSubTemplateParams();
        return $this->subTemplates ?? [];
    }

    /**
     * Sort the sub templates to match the order in the config.
     *
     * @return void
     */
    public function sortSubTemplateParams(): void
    {
        if (!isset($this->config['templates_order'], $this->subTemplates)) {
            return;
        }
        $orderMap = array_flip($this->config['templates_order']);
        usort($this->subTemplates, function ($a, $b) use ($orderMap) {
            return isset($orderMap[$a], $orderMap[$b]) ? $orderMap[$a] <=> $orderMap[$b] : 0;
        });
    }

    /**
     * Return the template parameters in the config for the given template or all of them if none passed.
     *
     * @param ?string $templateName Template name you want the params for
     *
     * @return array
     */
    public function getSubTemplateParams(?string $templateName = null): array
    {
        if (isset($templateName)) {
            return $this->subTemplatesParams[$templateName] ?? [];
        }
        return $this->subTemplatesParams ?? [];
    }

    /**
     * Get the status for a holding item.
     *
     * @param ?string $itemId The holding item UUID. If null (default) will return status for first item.
     *
     * @return string The status string
     */
    public function getStatus(?string $itemId = null): string
    {
        $item = $this->getItem($itemId);
        if (empty($item['availability']) || !$item['availability'] instanceof AvailabilityStatusInterface) {
            return 'Unknown';
        }
        return $item['availability']->getStatusDescription();
    }

    /**
     * Matches given haystack against the regex in config;
     * Return true if any of the given string matches any of the regex.
     *
     * @param string       $regexName Regex name matching the config file
     * @param string|array $haystack  Subject to match the regex against
     * @param bool         $default   Return if no matches
     *
     * @return mixed
     */
    protected function matches(string $regexName, string|array $haystack, bool $default = false): bool
    {
        if (is_array($haystack)) {
            foreach ($haystack as $item) {
                if ($this->matches($regexName, $item, $default)) {
                    return true;
                }
            }
            return $default;
        }
        // No need to do a try catch for this function. The exception happens only when passing null which we don't
        return $this->regex->matches($regexName, $haystack, $default);
    }

    /**
     * Get the location for a holding item.
     *
     * @param ?string $itemId The holding item UUID. If null (default) will return status for first item
     *
     * @return string The location string
     */
    public function getLocation(?string $itemId = null): string
    {
        return $this->getItem($itemId)['location'] ?? '';
    }

    /**
     * Get the location code for a holding item.
     *
     * @param ?string $itemId The holding item UUID. If null (default) will return status for first item
     *
     * @return string The location code
     */
    public function getLocationCode(?string $itemId = null): string
    {
        return $this->getItem($itemId)['location_code'] ?? '';
    }

    /**
     * Get the link data for requesting the item.
     *
     * @param ?string $itemId The holding item UUID. If null (default) will return status for first item
     *
     * @return array|string The data required to build a request URL for the item
     */
    public function getLink(?string $itemId = null): array|string
    {
        $isProvidedItemIdNull = null === $itemId;
        $itemId = $this->getItemId($itemId);

        $holdings = $this->recordDriver->getRealTimeHoldings();
        if (!isset($holdings['holdings'])) {
            return '';
        }
        $link = '';
        foreach ($holdings['holdings'] as $location) {
            if (!isset($location['items'])) {
                continue;
            }
            foreach ((array)$location['items'] as $item) {
                if (empty($item['link'])) {
                    continue;
                }
                if (isset($item['item_id']) && $item['item_id'] == $itemId) {
                    $link = $item['link'];
                    break 2;
                } elseif ($itemId === null || $isProvidedItemIdNull) {
                    $link = $item['link'];
                    break;
                }
            }
        }
        return $link;
    }

    /**
     * Get the call number for the record.
     *
     * @param ?string $itemId Item to filter the result for
     *
     * @return ?array{
     *     text: string,
     *     translate: bool,
     * } The call number and whether it's a string to translate
     */
    public function getCallNumber(?string $itemId = null): ?array
    {
        if ($this->isOnlineResource($itemId)) {
            return [
                'text' => 'Online',
                'translate' => true,
            ];
        }

        $item = $this->getItem($itemId);
        $callNumber = '';
        if (!empty($item['callnumber'])) {
            if (!empty($item['callnumber_prefix'])) {
                $callNumber .= $item['callnumber_prefix'] . ' ';
            }
            $callNumber .= $item['callnumber'];
        }

        if (!empty($item['enumchron'])) {
            $callNumber .= ' ' . $item['enumchron'];
        }

        return empty($callNumber) ? null : [
            'text' => trim($callNumber),
            'translate' => false,
        ];
    }

    /**
     * Get the copy number for the record.
     *
     * @param ?string $itemId Item to filter the result for
     *
     * @return ?string The copy number
     */
    public function getCopyNumber(?string $itemId = null): ?string
    {
        if ($this->showCopyNumber() === false) {
            return null;
        }
        $item = $this->getItem($itemId);
        return $item['number'] ?? null;
    }

    /**
     * Get the description for the record.
     *
     * @return string The description string
     */
    public function getSummary(): string
    {
        return implode(', ', $this->recordDriver->getSummary());
    }

    /**
     * Determine if the given item is an online resource.
     *
     * @param ?string $itemId Item ID to filter for
     *
     * @return bool  If the item is an online resource
     */
    public function isOnlineResource(?string $itemId = null): bool
    {
        $location = $this->getLocation($itemId);
        return $this->matches('LOCATION_ONLINE', $location);
    }

    /**
     * Determine if the given item is a serial or not.
     *
     * @return bool  If the item is a serial or not
     */
    public function isSerial(): bool
    {
        foreach ($this->recordDriver->getFormats() as $format) {
            if ($this->matches('FORMAT_SERIAL', $format)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Determine if the given item is checked or not.
     *
     * @param ?string $itemId Item ID to filter for
     *
     * @return bool  If the item is out or not
     */
    public function isOut(?string $itemId = null): bool
    {
        if (!$item = $this->getItem($itemId)) {
            return false;
        }
        $haystack = [];
        if (
            !empty($item['availability']) && $item['availability'] instanceof AvailabilityStatusInterface
            && $availability = $item['availability']->getStatusDescription()
        ) {
            $haystack[] = $availability;
        }
        if ($loanType = $item['temporary_loan_type'] ?? null) {
            $haystack[] = $loanType;
        }
        return $this->matches('STATUS_CHECKED_OUT', $haystack);
    }

    /**
     * Determine if the given item is media of audio/video form.
     *
     * @param ?string $itemId Item ID to filter for
     *
     * @return bool  Whether the item is audio or video media item or not
     */
    public function isAudioVideoMedia(?string $itemId = null): bool
    {
        if ($callNum = $this->getItem($itemId)['callnumber'] ?? false) {
            return $this->matches('CALLNUMBER_AV_MEDIA', $callNum);
        }
        return false;
    }

    /**
     * Determine if the given item is for library use only or not.
     *
     * @param ?string $itemId Item ID to filter for
     *
     * @return bool  If the item is for library use only or not
     */
    public function isLibUseOnly(?string $itemId = null): bool
    {
        if (!$item = $this->getItem($itemId)) {
            return false;
        }
        $haystack = [];
        if (
            !empty($item['availability']) && $item['availability'] instanceof AvailabilityStatusInterface
            && $availability = $item['availability']->getStatusDescription()
        ) {
            $haystack[] = $availability;
        }
        if ($loanType = $item['temporary_loan_type'] ?? null) {
            $haystack[] = $loanType;
        }
        return $this->matches('STATUS_LIB_USE_ONLY', $haystack);
    }

    /**
     * Determine if the given item is unavailable (false if uncertain).
     *
     * @param ?string $itemId Item ID to filter for
     *
     * @return bool  If the item is unavailable
     */
    public function isUnavailable(?string $itemId = null): bool
    {
        if (!$item = $this->getItem($itemId)) {
            return false;
        }
        if (empty($item['availability']) || !$item['availability'] instanceof AvailabilityStatusInterface) {
            return false;
        }
        $availability = $item['availability']->getStatusDescription();
        return !empty($availability) && $this->matches('STATUS_UNAVAILABLE', $availability);
    }

    /**
     * Whether to display the copy number (next to the call number), default false.
     *
     * @return bool
     */
    protected function showCopyNumber(): bool
    {
        return ($this->config['showCopyNumber'] ?? false) && $this->showHoldings();
    }

    /**
     * Determine if to show holdings.
     *
     * @return bool
     */
    public function showHoldings(): bool
    {
        return isset($this->items) && count($this->items) > 1;
    }

    /**
     * Determine if the faculty delivery template should display.
     *
     * @param ?string $itemId Item ID to filter for
     *
     * @return bool  If the template should display
     */
    public function showStaffDelivery(?string $itemId = null): bool
    {
        $item = $this->getItem($itemId);
        if (
            empty($item)
            || empty($item['availability'])
            || $this->isOut($itemId)
            || $this->isUnavailable($itemId)
            || !$item['availability'] instanceof AvailabilityStatusInterface
        ) {
            return false;
        }

        $availability = $item['availability']->getStatusDescription();
        return !empty($availability) && $this->matches('STATUS_AVAILABLE', $availability);
    }

    /**
     * Determine if the remote parton template should display.
     *
     * @param ?string $itemId Item ID to filter for
     *
     * @return bool  If the template should display
     */
    public function showRemoteDelivery(?string $itemId = null): bool
    {
        $item = $this->getItem($itemId);
        if (
            empty($item)
            || empty($item['availability'])
            || $this->isOut($itemId)
            || $this->isUnavailable($itemId)
            || !$item['availability'] instanceof AvailabilityStatusInterface
        ) {
            return false;
        }
        $availability = $item['availability']->getStatusDescription();
        return !empty($availability) && $this->matches('STATUS_AVAILABLE', $availability);
    }

    /**
     * Determine if the other library links template should display.
     *
     * @param ?string $itemId Item ID to filter for
     *
     * @return bool  If the template should display
     */
    public function showInterLibrary(?string $itemId = null): bool
    {
        $itemId = $this->getItemId($itemId);
        $haystack = [];
        if ($location = $this->getLocation($itemId)) {
            $haystack[] = $location;
        }
        if ($locationCode = $this->getLocationCode($itemId)) {
            $haystack[] = $locationCode;
        }

        if (!empty($haystack) && $this->matches('LOCATION_EXCLUSIVE', $haystack)) {
            return false;
        }

        return $this->isOut($itemId) || $this->isLibUseOnly($itemId) || $this->isUnavailable($itemId);
    }

    /**
     * Determine if the microform template should display.
     *
     * @param ?string $itemId Item ID to filter for
     *
     * @return bool If the template should display
     */
    public function showMicroForm(?string $itemId = null): bool
    {
        $location = $this->getLocation($itemId);
        return $this->matches('LOCATION_MICROFORMS', $location);
    }

    /**
     * Setter for record.
     *
     * @param RecordDriver $driver Record driver object
     *
     * @return void
     */
    public function setRecordDriver(RecordDriver $driver): void
    {
        $this->recordDriver = $driver;
        $this->subTemplates = null;
    }

    /**
     * Getter for items.
     *
     * @return array
     */
    public function getItems(): array
    {
        return $this->items ?? [];
    }

    /**
     * Setter for items.
     *
     * @param array $items Array of holding items
     *
     * @return void
     */
    public function setItems(array $items): void
    {
        $this->items = $items;
        $this->defaultItemId = null;
        $this->subTemplates = null;
    }

    /**
     * Logic used to determine which item id to use.
     *
     * @param ?string $itemId The holding item UUID.
     *
     * @return ?string The provided item ID, or an appropriate default if none provided; null if no IDs found anywhere
     */
    protected function getItemId(?string $itemId = null): ?string
    {
        if (isset($itemId)) {
            return $itemId; // Use the one passed as a parameter first
        } elseif (isset($this->defaultItemId)) {
            return $this->defaultItemId; // Get the one set by the loader
        } elseif (isset($this->items) && is_array($this->items) && isset(current($this->items)['item_id'])) {
            return current($this->items)['item_id']; // Grab the first holding record
        } else {
            return null; // This shouldn't happen, but we have no item id!
        }
    }

    /**
     * Get the item record for the given item id. If no id is provided, the first item
     * record will be returned.
     *
     * @param ?string $itemId The item UUID. If null (default) will return for what is set
     * in the class if available, else the first item
     *
     * @return ?array The matching item data (null if no item data available)
     */
    public function getItem(?string $itemId = null): ?array
    {
        $item = null;
        $itemId = $this->getItemId($itemId);
        if ($itemId === null && isset($this->items) && current($this->items)) {
            $item = current($this->items);
        } else {
            foreach ($this->getItems() as $hold_item) {
                if (isset($hold_item['item_id']) && $hold_item['item_id'] == $itemId) {
                    $item = $hold_item;
                    break;
                }
            }
        }
        return $item;
    }

    /**
     * Setter for defaultItemId, the default will be set only if the item_id exists.
     *
     * @param ?string $defaultItemId Item id of the holding for the record
     *
     * @return void
     */
    public function setDefaultItemId(?string $defaultItemId): void
    {
        // We make sure the passed id matched an item we have
        $this->defaultItemId = ($this->getItem($defaultItemId)['item_id'] ?? null) == $defaultItemId
            ? $defaultItemId : null;
    }

    /**
     * Given holdings, return whether the holdings are compatible with the get this feature.
     *
     * @param array $items Holdings
     *
     * @return bool
     */
    public function areItemsSupported(array $items): bool
    {
        foreach ($items as $item) {
            if (
                array_key_exists('location', $item)
                || array_key_exists('location_code', $item)
                || (array_key_exists('availability', $item)
                    && $item['availability'] instanceof AvailabilityStatusInterface)
            ) {
                return true;
            }
        }
        return false;
    }

    /**
     * Whether to comment in the HTML code the displayed template name for
     * troubleshooting/debugging, default false.
     *
     * @return bool
     */
    public function commentTemplateName(): bool
    {
        return (bool)($this->config['commentTemplateName'] ?? false);
    }

    /**
     * Whether to make the holding list a dropdown.
     *
     * @return bool
     */
    public function makeHoldingsDropdown(): bool
    {
        return (bool)($this->config['holdingsDropdown'] ?? false);
    }
}
