<?php

/**
 * Record driver data formatting view helper
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2016.
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
 * @package  View_Helpers
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Juha Luoma <juha.luoma@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:architecture:record_data_formatter
 * Wiki
 */

namespace VuFind\View\Helper\Root;

use Laminas\View\Helper\AbstractHelper;
use VuFind\RecordDataFormatter\Specs\PluginManager as SpecsManager;
use VuFind\RecordDataFormatter\Specs\SpecInterface;
use VuFind\RecordDriver\AbstractBase as RecordDriver;
use VuFind\String\PropertyStringInterface;

use function call_user_func;
use function count;
use function is_array;
use function is_callable;

/**
 * Record driver data formatting view helper
 *
 * @category VuFind
 * @package  View_Helpers
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Juha Luoma <juha.luoma@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:architecture:record_data_formatter
 * Wiki
 */
class RecordDataFormatter extends AbstractHelper
{
    /**
     * Record driver object.
     *
     * @var ?RecordDriver
     */
    protected ?RecordDriver $driver = null;

    /**
     * Constructor
     *
     * @param SpecsManager $specsManager Specs Plugin Manager
     */
    public function __construct(protected SpecsManager $specsManager)
    {
    }

    /**
     * Store a record driver object and return this object so that the appropriate
     * data can be rendered.
     *
     * @param ?RecordDriver $driver Record driver object.
     *
     * @return RecordDataFormatter
     */
    public function __invoke(?RecordDriver $driver = null): RecordDataFormatter
    {
        $this->driver = $driver;
        return $this;
    }

    /**
     * Sort callback for field specification.
     *
     * @param array $a First value to compare
     * @param array $b Second value to compare
     *
     * @return int
     */
    protected function sortCallback(array $a, array $b): int
    {
        // Sort on 'pos' and 'multiPos' with 'label' as tie-breaker.
        foreach (['pos', 'multiPos', 'label'] as $sortKey) {
            if (isset($a[$sortKey]) && isset($b[$sortKey]) && $a[$sortKey] !== $b[$sortKey]) {
                return $a[$sortKey] <=> $b[$sortKey];
            }
        }
        return 0;
    }

    /**
     * Should we allow a value? (Always accepts non-empty values; for empty
     * values, allows zero when configured to do so).
     *
     * @param mixed $value            Data to check for zero value.
     * @param array $options          Rendering options.
     * @param bool  $ignoreCombineAlt If value should always be allowed when renderType is CombineAlt
     *
     * @return bool
     */
    protected function allowValue(mixed $value, array $options, bool $ignoreCombineAlt = false): bool
    {
        if ($value instanceof PropertyStringInterface) {
            $value = (string)$value;
        }
        if (!empty($value) || ($ignoreCombineAlt && ($options['renderType'] ?? 'Simple') == 'CombineAlt')) {
            return true;
        }
        $allowZero = $options['allowZero'] ?? true;
        return $allowZero && ($value === 0 || $value === '0');
    }

    /**
     * Return rendered text (or null if nothing to render).
     *
     * @param string $field   Field being rendered (i.e. default label)
     * @param mixed  $data    Data to render
     * @param array  $options Rendering options
     *
     * @return ?array
     */
    protected function render(string $field, mixed $data, array $options): ?array
    {
        if (!($options['enabled'] ?? true)) {
            return null;
        }

        // Check whether the data is worth rendering.
        if (!$this->allowValue($data, $options, true)) {
            return null;
        }

        // Determine the rendering method to use, and bail out if it's illegal:
        $method = empty($options['renderType'])
            ? 'renderSimple' : 'render' . $options['renderType'];
        if (!is_callable([$this, $method])) {
            return null;
        }

        // If the value evaluates false, we should double-check our zero handling:
        $value = $this->$method($data, $options);
        if (!$this->allowValue($value, $options)) {
            return null;
        }

        // Special case: if we received an array rather than a string, we should
        // return it as-is (it probably came from renderMulti()).
        if (is_array($value)) {
            return $value;
        }

        // Allow dynamic label override:
        $label = is_callable($options['labelFunction'] ?? null)
            ? call_user_func($options['labelFunction'], $data, $this->driver)
            : $field;
        $context = $options['context'] ?? [];
        $pos = $options['pos'] ?? 0;
        return [compact('label', 'value', 'context', 'pos')];
    }

    /**
     * Create formatted key/value data based on a record driver and field spec.
     * The first argument can be a descendant of RecordDriver.
     * If omitted, then invoke this class with the desired driver.
     * The second or first argument is an array containing formatting specifications.
     *
     * @param array ...$args Record driver object and/or formatting specifications.
     *
     * @return array
     */
    public function getData(...$args): array
    {
        if ($args[0] instanceof RecordDriver) {
            $this->driver = $args[0];
            array_shift($args);
        }
        if (empty($args[0])) {
            return [];
        }
        if (null === $this->driver) {
            throw new \Exception('No driver set in RecordDataFormatter');
        }
        if (!is_array($args[0])) {
            throw new \Exception('Argument 0 must be an array');
        }
        // Apply the spec:
        $result = [];
        foreach ($args[0] as $field => $current) {
            // Extract the relevant data from the driver and try to render it.
            $data = $this->extractData($current);
            $value = $this->render($field, $data, $current);
            if ($value !== null) {
                $result = array_merge($result, $value);
            }
        }
        // Sort the result:
        usort($result, [$this, 'sortCallback']);
        return $result;
    }

    /**
     * Get default configuration.
     *
     * @param string $key Key for configuration to look up.
     *
     * @return array
     */
    public function getDefaults(string $key): array
    {
        $specs = $this->getSpecPluginForDriver();
        if ($specs === null) {
            throw new \Exception('Using the RecordDataFormatter view helper with a driver that is not supported.');
        }
        return $specs->getDefaults($key);
    }

    /**
     * Set default configuration.
     *
     * @param string         $key    Key for configuration to set.
     * @param array|callable $values Defaults to store (either an array, or a
     * callable returning an array).
     *
     * @return void
     *
     * @deprecated Set defaults on spec class directly
     */
    public function setDefaults(string $key, array|callable $values): void
    {
        $specs = $this->getSpecPluginForDriver();
        if ($specs !== null && method_exists($specs, 'setDefaults')) {
            $specs->setDefaults($key, $values);
        }
    }

    /**
     * Get matching spec plugin for the driver.
     *
     * @return ?SpecInterface
     */
    protected function getSpecPluginForDriver(): ?SpecInterface
    {
        $specClass = \VuFind\RecordDataFormatter\Specs\DefaultRecord::class;
        if ($this->driver !== null) {
            $specClass = $this->driver->getRecordDataFormatterSpecClass();
        }
        if ($specClass === null) {
            return null;
        }
        return $this->specsManager->get($specClass);
    }

    /**
     * Extract data (usually from the record driver).
     *
     * @param array $options Incoming options
     *
     * @return mixed
     */
    protected function extractData(array $options): mixed
    {
        // Static cache for persisting data.
        static $cache = [];

        // If $method is a bool, return it as-is; this allows us to force the
        // rendering (or non-rendering) of particular data independent of the
        // record driver.
        $method = $options['dataMethod'] ?? false;
        if ($method === true || $method === false) {
            return $method;
        }

        if ($useCache = ($options['useCache'] ?? false)) {
            $cacheKey = $this->driver->getUniqueID() . '|'
                . $this->driver->getSourceIdentifier() . '|' . $method
                . (isset($options['dataMethodParams']) ? '|' . serialize($options['dataMethodParams']) : '');
            if (isset($cache[$cacheKey])) {
                return $cache[$cacheKey];
            }
        }

        // Default action: try to extract data from the record driver:
        $data = $this->driver->tryMethod($method, $options['dataMethodParams'] ?? []);

        if ($useCache) {
            $cache[$cacheKey] = $data;
        }

        return $data;
    }

    /**
     * Render multiple lines for a single set of data.
     *
     * @param mixed $data    Data to render
     * @param array $options Rendering options.
     *
     * @return array
     */
    protected function renderMulti(
        mixed $data,
        array $options
    ): array {
        // Make sure we have a callback for sorting the $data into groups...
        $callback = $options['multiFunction'] ?? null;
        if (!is_callable($callback)) {
            throw new \Exception('Invalid multiFunction callback.');
        }

        // Adjust the options array so we can use it to call the standard
        // render function on the grouped data....
        $defaultOptions = array_merge(
            $options,
            [
                'renderType' => $options['multiRenderType'] ?? 'Simple',
                'enabled' => $options['multiEnabled'] ?? true,
            ]
        );

        // Collect the results:
        $results = [];
        $input = $callback($data, $options, $this->driver) ?? [];
        $multiPositions = array_filter(array_map(function ($line) {
            return $line['options']['multiPos'] ?? null;
        }, $input));
        $multiPositions[] = 0;
        $multiPos = max($multiPositions) + 10;
        foreach ($input as $current) {
            $label = $current['label'] ?? '';
            $values = $current['values'] ?? null;
            $currentOptions = array_merge($defaultOptions, $current['options'] ?? []);
            foreach ($current as $key => $value) {
                $currentOptions = array_merge(
                    $currentOptions,
                    $options['lineOptions'][$key][$value] ?? [],
                );
            }
            if (isset($currentOptions['multiEnabled'])) {
                $currentOptions['enabled'] = $currentOptions['multiEnabled'];
            }
            if (!($currentOptions['enabled'] ?? true)) {
                continue;
            }
            if (isset($currentOptions['multiAltDataMethod'])) {
                $currentOptions['dataMethod'] = $currentOptions['multiAltDataMethod'];
                $values = $this->extractData($currentOptions);
            }
            $currentResult = $this->render($label, $values, $currentOptions);
            foreach ($currentResult ?? [] as $resultLine) {
                $resultLine['multiPos'] = $currentOptions['multiPos'] ?? $multiPos++;
                $results[] = $resultLine;
            }
        }
        return $results;
    }

    /**
     * Render using the record view helper.
     *
     * @param mixed $data    Data to render
     * @param array $options Rendering options.
     *
     * @return string
     */
    protected function renderRecordHelper(
        mixed $data,
        array $options
    ): string {
        $method = $options['helperMethod'] ?? null;
        $plugin = $this->getView()->plugin('record');
        if (empty($method) || !is_callable([$plugin, $method])) {
            throw new \Exception('Cannot call "' . $method . '" on helper.');
        }
        return $plugin($this->driver)->$method($data);
    }

    /**
     * Render a record driver template.
     *
     * @param mixed $data    Data to render
     * @param array $options Rendering options.
     *
     * @return string
     */
    protected function renderRecordDriverTemplate(
        mixed $data,
        array $options
    ): string {
        if (!isset($options['template'])) {
            throw new \Exception('Template option missing.');
        }
        $helper = $this->getView()->plugin('record');
        $context = $options['context'] ?? [];
        $context['driver'] = $this->driver;
        $context['data'] = $data;
        $context['options'] = $options;
        return trim(
            $helper($this->driver)->renderTemplate($options['template'], $context)
        );
    }

    /**
     * Get a link associated with a value, or else return false if link does
     * not apply.
     *
     * @param string $value   Value associated with link.
     * @param array  $options Rendering options.
     *
     * @return string|bool
     */
    protected function getLink(string $value, array $options): string|bool
    {
        if ($options['recordLink'] ?? false) {
            $helper = $this->getView()->plugin('record');
            return $helper->getLink($options['recordLink'], $value);
        }
        return false;
    }

    /**
     * Render standard and alternative fields together.
     *
     * @param mixed $data    Data to render
     * @param array $options Rendering options.
     *
     * @return ?string
     */
    protected function renderCombineAlt(
        mixed $data,
        array $options
    ): ?string {
        // Determine the rendering method to use, and bail out if it's illegal:
        $method = empty($options['combineAltRenderType'])
            ? 'renderSimple' : 'render' . $options['combineAltRenderType'];
        if (!is_callable([$this, $method])) {
            return null;
        }

        // get standard value
        $stdValue = $this->$method($data, $options);

        // get alternative value
        $altDataMethod = $options['altDataMethod'] ?? $options['dataMethod'] . 'AltScript';

        $altOptions = $options;
        $altOptions['dataMethod'] = $altDataMethod;
        $altData = $this->extractData($altOptions);

        $altValue = $altData != null ? $this->$method($altData, $altOptions) : null;

        // check if both values are not allowed
        if (!$this->allowValue($stdValue, $options) && !$this->allowValue($altValue, $options)) {
            return null;
        }

        // render both values
        $helper = $this->getView()->plugin('record');
        $template = $options['combineAltTemplate'] ?? 'combine-alt';
        $context = [
            'stdValue' => $stdValue,
            'altValue' => $altValue,
            'prioritizeAlt' => $options['prioritizeAlt'] ?? false,
        ];
        return trim(
            $helper($this->driver)->renderTemplate($template, $context)
        );
    }

    /**
     * Simple rendering method.
     *
     * @param mixed $data    Data to render
     * @param array $options Rendering options.
     *
     * @return string
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    protected function renderSimple(mixed $data, array $options): string
    {
        $view = $this->getView();
        $escaper = ($options['translate'] ?? false)
            ? $view->plugin('transEsc') : $view->plugin('escapeHtml');
        $transDomain = $options['translationTextDomain'] ?? '';
        $separator = $options['separator'] ?? '<br>';
        $retVal = '';
        // Avoid casting since the field can be a PropertyString too (and casting would return an array of object
        // properties):
        $array = null === $data ? [] : (is_array($data) ? $data : [$data]);
        $remaining = count($array);
        foreach ($array as $line) {
            $remaining--;
            $text = $options['itemPrefix'] ?? '';
            $text .= $escaper($transDomain . $line);
            $text .= $options['itemSuffix'] ?? '';
            $retVal .= ($link = $this->getLink($line, $options))
                ? '<a href="' . $link . '">' . $text . '</a>' : $text;
            if ($remaining > 0) {
                $retVal .= $separator;
            }
        }
        return ($options['prefix'] ?? '')
            . $retVal
            . ($options['suffix'] ?? '');
    }
}
