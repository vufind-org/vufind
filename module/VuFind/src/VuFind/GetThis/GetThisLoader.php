<?php

/**
 * Prepares data for the Get This button
 *
 * PHP version 8
 *
 * @category VuFind
 * @package  Get_This
 * @author   MSUL Public Catalog Team <LIB.DL.pubcat@msu.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/vufind/ Main page
 */

namespace VuFind\GetThis;

use Exception;
use Laminas\Log\LoggerAwareInterface;
use Throwable;
use VuFind\ILS\Logic\AvailabilityStatusInterface;
use VuFind\Log\LoggerAwareTrait;
use VuFind\Regex\Regex;
use VuFind\View\Helper\Root\Translate;

use function array_key_exists;
use function call_user_func;
use function count;
use function is_array;

/**
 * Class to hold data for the Get This button
 *
 * @category VuFind
 * @package  Get_This
 * @author   MSUL Public Catalog Team <LIB.DL.pubcat@msu.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/vufind/ Main page
 */
class GetThisLoader implements LoggerAwareInterface
{
    use LoggerAwareTrait;

    /**
     * Config file name
     *
     * @var string
     */
    public const CONFIG_FILENAME = 'GetThis.yaml';

    /**
     * Items
     *
     * @var
     */
    protected $items;

    /**
     * Holding current item
     *
     * @var
     */
    protected $item;

    /**
     * Sub-templates to display
     *
     * @var
     */
    protected $subTemplates;

    /**
     * Sub-templates params from config
     *
     * @var
     */
    protected $subTemplatesParams;

    /**
     * Record driver
     *
     * @var
     */
    protected $record;

    /**
     * Initializes the loader
     *
     * @param array     $config     Config pulled from the config file defined above
     * @param Regex     $regex      Regex service
     * @param Translate $translator Translator plugin
     */
    public function __construct(
        protected array $config,
        protected Regex $regex,
        protected Translate $translator
    ) {
    }

    /**
     * Return the result of the function passed, negates the result if the first char is "!"
     *
     * @param string $function The function to test
     *
     * @return bool
     */
    protected function isConditionFunctionFilled(string $function): bool
    {
        if (str_starts_with($function, '!')) {
            $function = substr($function, 1);
            return method_exists($this, $function) && !call_user_func([$this, $function]);
        } else {
            return method_exists($this, $function) && call_user_func([$this, $function]);
        }
    }

    /**
     * Whether the condition block contains an operator "and"
     *
     * @param array $conditions Array of conditions to determine the result
     *
     * @return bool
     * @throws Exception
     */
    protected function isConditionsAnd(array $conditions): bool
    {
        foreach ($conditions as $condition) {
            if (isset($condition['operator']) && $condition['operator'] === 'and') {
                return true;
            }
        }
        return false;
    }

    /**
     * Go through an array recursively to determine if the condition functions are matched
     *
     * @param array $conditions Array of conditions to determine the result
     *
     * @return bool
     * @throws Exception
     */
    protected function loopThroughConditionBlock(array $conditions): bool
    {
        $previousResult = null;
        $result = null;
        $and = $this->isConditionsAnd($conditions);
        foreach ($conditions as $condition) {
            if (isset($condition['operator'])) {
                continue;
            } else {
                $result = $this->areConditionsFilled($condition);
            }
            if ($previousResult !== null && ($previousResult === true || $result === true) && $and === false) {
                return true;
            }
            $previousResult = $result;
        }
        return $result || $previousResult;
    }

    /**
     * Go through an array recursively to determine if the condition functions are matched
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
     * Add template and template parameters to property
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
     * Add parameters to the template
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
     * Add a parameters to the template
     *
     * @param string $templateName Template name
     * @param string $key          Key value of the parameter
     * @param mixed  $value        Parameter to add
     *
     * @return void
     */
    protected function setSubTemplateParam(string $templateName, string $key, $value): void
    {
        $this->subTemplatesParams[$templateName][$key] = $value;
    }

    /**
     * Get the templates to display according to the config file
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
     * Sort the sub templates to match the order in the config
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
     * Return the template parameters in the config for the given template or all of them if none passed
     *
     * @param string|null $templateName Template name you want the params for
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
     * Get the status for a holding item
     *
     * @param string|null $itemId The holding item UUID. If null (default) will return status for first item
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
     * Return true if any of the given string matches any of the regex
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
        // The exception can not happen as we are passing a boolean
        return $this->regex->matches($regexName, $haystack, $default);
    }

    /**
     * Get the location for a holding item
     *
     * @param string|null $itemId The holding item UUID. If null (default) will return status for first item
     *
     * @return string The location string
     */
    public function getLocation(?string $itemId = null): string
    {
        return $this->getItem($itemId)['location'] ?? '';
    }

    /**
     * Get the location code for a holding item
     *
     * @param string|null $itemId The holding item UUID. If null (default) will return status for first item
     *
     * @return string The location code
     */
    public function getLocationCode(?string $itemId = null): string
    {
        return $this->getItem($itemId)['location_code'] ?? '';
    }

    /**
     * Get the link data for requesting the item
     *
     * @param string|null $itemId The holding item UUID. If null (default) will return status for first item
     *
     * @return array|string The data required to build a request URL for the item
     */
    public function getLink(?string $itemId = null): array|string
    {
        $isProvidedItemIdNull = null === $itemId;
        // If $itemId is null, call getItem just in case $items returns the items in a different order
        // than the real time holdings information
        $itemId = $this->getItem($itemId)['item_id'] ?? null;

        $holdings = $this->record->getRealTimeHoldings();
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
     * Get the call number for the record
     *
     * @param string|null $itemId Item to filter the result for
     *
     * @return string|null The description string
     */
    public function getCallNumber(?string $itemId = null): ?string
    {
        $item = $this->getItem($itemId);

        if ($this->isOnlineResource($itemId)) {
            return $this->translator->translate('Online');
        }

        $callNum = '';
        if (!empty($item['callnumber'])) {
            if (!empty($item['callnumber_prefix'])) {
                $callNum .= $item['callnumber_prefix'] . ' ';
            }
            $callNum .= $item['callnumber'];
        }

        if (!empty($item['enumchron'])) {
            $callNum .= ' ' . $item['enumchron'];
        }

        return empty($callNum) ? null : $callNum;
    }

    /**
     * Get the copy number for the record
     *
     * @param ?string $itemId Item to filter the result for
     *
     * @return ?string The copy number
     */
    public function getCopyNumber(?string $itemId = null): ?string
    {
        $item = $this->getItem($itemId);
        var_dump($item);
        if (isset($item['number'])) {
            return $item['number'];
        }
        return null;
    }

    /**
     * Get the description for the record
     *
     * @return string The description string
     */
    public function getSummary(): string
    {
        return implode(', ', $this->record->getSummary());
    }

    /**
     * Determine if the given item is an online resource
     *
     * @param string|null $itemId Item ID to filter for
     *
     * @return bool  If the item is an online resource
     */
    public function isOnlineResource(?string $itemId = null): bool
    {
        $location = $this->getLocation($itemId);
        return $this->matches('LOCATION_ONLINE', $location);
    }

    /**
     * Determine if the given item is a serial or not
     *
     * @return bool  If the item is a serial or not
     */
    public function isSerial(): bool
    {
        foreach ($this->record->getFormats() as $format) {
            if ($this->matches('FORMAT_SERIAL', $format)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Determine if the given item is checked or not
     *
     * @param string|null $itemId Item ID to filter for
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
     * Determine if the given item is media of audio/video form
     *
     * @param string|null $itemId Item ID to filter for
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
     * Determine if the given item is for library use only or not
     *
     * @param string|null $itemId Item ID to filter for
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
     * Determine if the given item is unavailable (false if uncertain)
     *
     * @param string|null $itemId Item ID to filter for
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
     * Either to display or not copy number
     *
     * @return bool
     */
    public function showCopyNumber(): bool
    {
        return ($this->config['showCopyNumber'] ?? false) && $this->showHoldings();
    }

    /**
     * Determine if to show holdings
     *
     * @return bool
     */
    public function showHoldings(): bool
    {
        return isset($this->items) && count($this->items) > 1;
    }

    /**
     * Determine if the faculty delivery template should display
     *
     * @param string|null $itemId Item ID to filter for
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
     * Determine if the remote parton template should display
     *
     * @param string|null $itemId Item ID to filter for
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
     * Determine if the other library links template should display
     *
     * @param string|null $itemId Item ID to filter for
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

        if ($this->isOut($itemId) || $this->isLibUseOnly($itemId) || $this->isUnavailable($itemId)) {
            return true;
        }

        return false;
    }

    /**
     * Determine if the microform template should display
     *
     * @param string|null $itemId Item ID to filter for
     *
     * @return bool  If the template should display
     */
    public function showMicroForm(?string $itemId = null): bool
    {
        $location = $this->getLocation($itemId);
        return $this->matches('LOCATION_MICROFORMS', $location);
    }

    /**
     * Setter for record
     *
     * @param object $record Record driver object
     *
     * @return void
     */
    public function setRecord(object $record): void
    {
        $this->record = $record;
        $this->subTemplates = null;
    }

    /**
     * Getter for items
     *
     * @return object|array
     */
    public function getItems(): object|array
    {
        return $this->items ?? [];
    }

    /**
     * Setter for items
     *
     * @param object|array $items Array of holding items
     *
     * @return void
     */
    public function setItems(object|array $items): void
    {
        $this->items = $items;
        $this->subTemplates = null;
    }

    /**
     * Logic used to determine which item id to use
     *
     * @param string|null $itemId The holding item UUID.
     *
     * @return string|null $itemId for the selected item
     */
    private function getItemId(?string $itemId = null): ?string
    {
        if (isset($itemId)) {
            return $itemId; // Use the one passed as a parameter first
        } elseif (isset($this->item['item_id'])) {
            return $this->item['item_id']; // Get the one set by the loader
        } elseif (isset($this->items[0]['item_id'])) {
            return $this->items[0]['item_id']; // Grab the first holding record
        } else {
            return null; // This shouldn't happen, but we have no item id!
        }
    }

    /**
     * Get the holding record for the given item id. If none is provided, the first holding
     * record will be returned.
     *
     * @param string|null $itemId The holding item UUID. If null (default) will return for what
     *                            is set in the class if available, else the first item
     *
     * @return array The data for with the holding information of the given item
     */
    public function getItem(?string $itemId = null)
    {
        if (
            !isset($this->item)
            || (isset($itemId, $this->item['item_id']) && $this->item['item_id'] != $itemId)
        ) {
            $this->cacheItem($itemId);
        }
        return $this->item;
    }

    /**
     * Will cache the item passed as parameter if it exists
     *
     * @param string|null $itemId The holding item UUID. If null (default) will return for what
     *                            is set in the class if available, else the first item
     *
     * @return void
     */
    protected function cacheItem(?string $itemId = null): void
    {
        $this->item = null;
        if ($itemId = $this->getItemId($itemId)) {
            foreach ($this->getItems() as $hold_item) {
                if (isset($hold_item['item_id']) && $hold_item['item_id'] == $itemId) {
                    $this->item = $hold_item;
                    break;
                }
            }
        }
    }

    /**
     * Setter for itemId
     *
     * @param ?string $itemId Item id of the holding for the record
     *
     * @return void
     */
    public function setItemById(?string $itemId): void
    {
        $this->cacheItem($itemId);
    }

    /**
     * Given holdings, return whether the holdings are compatible with the get this feature
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
}
