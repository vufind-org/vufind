<?php
/**
 * Record driver data formatting view helper
 *
 * PHP version 7
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
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301  USA
 *
 * @category VuFind
 * @package  View_Helpers
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:architecture:record_data_formatter
 * Wiki
 */
namespace VuFind\View\Helper\Root;

use Laminas\View\Helper\AbstractHelper;
use VuFind\RecordDriver\AbstractBase as RecordDriver;

/**
 * Record driver data formatting view helper
 *
 * @category VuFind
 * @package  View_Helpers
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:architecture:record_data_formatter
 * Wiki
 */
class RecordDataFormatter extends AbstractHelper
{
    /**
     * Default settings.
     *
     * @var array
     */
    protected $defaults = [];

    /**
     * Record driver object.
     *
     * @var RecordDriver
     */
    protected $driver;

    /**
     * Store a record driver object and return this object so that the appropriate
     * data can be rendered.
     *
     * @param RecordDriver $driver Record driver object.
     *
     * @return RecordDataFormatter
     */
    public function __invoke(RecordDriver $driver): RecordDataFormatter
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
    protected function sortCallback($a, $b)
    {
        // Sort on 'pos' with 'label' as tie-breaker.
        return ($a['pos'] == $b['pos'])
            ? $a['label'] <=> $b['label']
            : $a['pos'] <=> $b['pos'];
    }

    /**
     * Should we allow a value? (Always accepts non-empty values; for empty
     * values, allows zero when configured to do so).
     *
     * @param mixed $value   Data to check for zero value.
     * @param array $options Rendering options.
     *
     * @return bool
     */
    protected function allowValue($value, $options)
    {
        if (!empty($value)) {
            return true;
        }
        $allowZero = $options['allowZero'] ?? true;
        return $allowZero && ($value === 0 || $value === '0');
    }

    /**
     * Return rendered text (or null if nothing to render).
     *
     * @param string       $field   Field being rendered (i.e. default label)
     * @param mixed        $data    Data to render
     * @param array        $options Rendering options
     *
     * @return array
     */
    protected function render($field, $data, $options)
    {
        // Check whether the data is worth rendering.
        if (!$this->allowValue($data, $options)) {
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
     *
     * @param array        $spec   Formatting specification
     *
     * @return array
     */
    public function getData(array $spec)
    {
        // Apply the spec:
        $result = [];
        foreach ($spec as $field => $current) {
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
    public function getDefaults($key)
    {
        // No value stored? Return empty array:
        if (!isset($this->defaults[$key])) {
            return [];
        }
        // Callback stored? Resolve to array on demand:
        if (is_callable($this->defaults[$key])) {
            $this->defaults[$key] = $this->defaults[$key]();
            if (!is_array($this->defaults[$key])) {
                throw new \Exception('Callback for ' . $key . ' must return array');
            }
        }
        // Send back array:
        return $this->defaults[$key];
    }

    /**
     * Set default configuration.
     *
     * @param string         $key    Key for configuration to set.
     * @param array|callable $values Defaults to store (either an array, or a
     * callable returning an array).
     *
     * @return void
     */
    public function setDefaults($key, $values)
    {
        if (!is_array($values) && !is_callable($values)) {
            throw new \Exception('$values must be array or callable');
        }
        $this->defaults[$key] = $values;
    }

    /**
     * Extract data (usually from the record driver).
     *
     * @param array        $options Incoming options
     *
     * @return mixed
     */
    protected function extractData(array $options)
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
                . $this->driver->getSourceIdentifier() . '|' . $method;
            if (isset($cache[$cacheKey])) {
                return $cache[$cacheKey];
            }
        }

        // Default action: try to extract data from the record driver:
        $data = $this->driver->tryMethod($method);

        if ($useCache) {
            $cache[$cacheKey] = $data;
        }

        return $data;
    }

    /**
     * Render multiple lines for a single set of data.
     *
     * @param mixed        $data    Data to render
     * @param array        $options Rendering options.
     *
     * @return array
     */
    protected function renderMulti(
        $data,
        array $options
    ) {
        // Make sure we have a callback for sorting the $data into groups...
        $callback = $options['multiFunction'] ?? null;
        if (!is_callable($callback)) {
            throw new \Exception('Invalid multiFunction callback.');
        }

        // Adjust the options array so we can use it to call the standard
        // render function on the grouped data....
        $defaultOptions = ['renderType' => $options['multiRenderType'] ?? 'Simple']
            + $options;

        // Collect the results:
        $results = [];
        $input = $callback($data, $options, $this->driver);
        foreach (is_array($input) ? $input : [] as $current) {
            $label = $current['label'] ?? '';
            $values = $current['values'] ?? null;
            $currentOptions = ($current['options'] ?? []) + $defaultOptions;
            $next = $this->render($label, $values, $currentOptions);
            if ($next !== null) {
                $results = array_merge($results, $next);
            }
        }
        return $results;
    }

    /**
     * Render using the record view helper.
     *
     * @param mixed        $data    Data to render
     * @param array        $options Rendering options.
     *
     * @return string
     */
    protected function renderRecordHelper(
        $data,
        array $options
    ) {
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
     * @param mixed        $data    Data to render
     * @param array        $options Rendering options.
     *
     * @return string
     */
    protected function renderRecordDriverTemplate(
        $data,
        array $options
    ) {
        if (!isset($options['template'])) {
            throw new \Exception('Template option missing.');
        }
        $helper = $this->getView()->plugin('record');
        $context = $options['context'] ?? [];
        $context['driver'] = $this->driver;
        $context['data'] = $data;
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
    protected function getLink($value, $options)
    {
        if ($options['recordLink'] ?? false) {
            $helper = $this->getView()->plugin('record');
            return $helper->getLink($options['recordLink'], $value);
        }
        return false;
    }

    /**
     * Simple rendering method.
     *
     * @param mixed        $data    Data to render
     * @param array        $options Rendering options.
     *
     * @return string
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    protected function renderSimple($data, array $options)
    {
        $view = $this->getView();
        $escaper = ($options['translate'] ?? false)
            ? $view->plugin('transEsc') : $view->plugin('escapeHtml');
        $transDomain = $options['translationTextDomain'] ?? '';
        $separator = $options['separator'] ?? '<br />';
        $retVal = '';
        $array = (array)$data;
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
