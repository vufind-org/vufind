<?php

/**
 * Implementation of ISO Schematron (http://www.schematron.com) validator with
 * Schematron 1.5 back compatibility.
 *
 * PHP version 8
 *
 * Class do not require XSLT extension nor additional XSLT documents. It works
 * with DOM extension only. It is a main purpose of this implementation.
 *
 * Schema validity is not checked completly, only self important things like
 * ID references and required attributes are checked. So, you should pass valid
 * schema.
 *
 * Presence of <sch:schema> is not required, so you can validate schematron in
 * Relax NG documents. But set ALLOW_MISSING_SCHEMA_ELEMENT option to enable it.
 *
 * Not implemented elements: let, diagnostic, diagnostics, dir, emph, flag, fpi,
 * icon, p, role, see, span, subject. Almost all of them are for documentation
 * purpose. Open issue on repository if you wish to be implemented.
 *
 * Example of usage:
 * <code>
 * use Milo\Schematron;
 *
 * $validator = new Schematron(Schematron::NS_ISO);
 * $validator->load('personal-schema.sch');
 *
 * $doc = new DOMDocument;
 * $doc->load($xmlDocument);
 *
 * $result = $validator->validate($doc, Schematron::RESULT_COMPLEX);
 * var_dump($result);
 * </code>
 *
 * You can choose one of four licences:
 *
 * @licence New BSD License
 * @licence GNU General Public License version 2
 * @licence GNU General Public License version 3
 * @licence MIT
 *
 * @category VuFind
 * @package  Record
 * @author   Miloslav Hůla <miloslav.hula@gmail.com>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://github.com/milo/schematron
 */

namespace Finna\Record\Schema\Milo;

use DOMDocument;
use DOMElement;
use DOMNode;
use DOMXPath;
use Finna\Record\Schema\Milo\SchematronHelpers as Helpers;
use InvalidArgumentException;
use RuntimeException;
use stdClass;

use function array_key_exists;
use function call_user_func;
use function count;
use function dirname;
use function in_array;

/**
 * Implementation of ISO Schematron (http://www.schematron.com) validator with
 * Schematron 1.5 back compatibility.
 *
 * @category VuFind
 * @package  Record
 * @author   Miloslav Hůla <miloslav.hula@gmail.com>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://github.com/milo/schematron
 */
class Schematron
{
    /**
     * Class version
     */
    public const
        VERSION = '1.0.0';

    /**
     * Namespace of supported schematron versions
     */
    public const
        NS_DETECT = null,
        NS_ISO = 'http://purl.oclc.org/dsdl/schematron',
        NS_1_5 = 'http://www.ascc.net/xml/schematron';

    /**
     * Type of {@link self::validate()} return value
     */
    public const
        RESULT_SIMPLE = 'simple',
        RESULT_COMPLEX = 'complex',
        RESULT_EXCEPTION = 'exception';

    /**
     * Standardized validation phase
     *
     * @var string
     */
    public const
        PHASE_ALL = '#ALL',
        PHASE_DEFAULT = '#DEFAULT';

    /**
     * Type of include URIs for {@link self::setAllowedInclude()}
     *
     * @var int
     */
    public const
        INCLUDE_URL = 0x01,
        INCLUDE_ABSOLUTE_PATH = 0x02,
        INCLUDE_RELATIVE_PATH = 0x04,
        INCLUDE_ALL = 0xFF;

    /**
     * Default options
     *
     * @var int
     */
    public const
        DEFAULT_OPTIONS = 0x0000,

        // Allow missing <sch:schema> (useful for Relax NG)
        ALLOW_MISSING_SCHEMA_ELEMENT = 0x0001,

        // Ignore <sch:include>, do not expand them
        IGNORE_INCLUDE = 0x0002,

        // Forbid <sch:include>, do not allow them
        FORBID_INCLUDE = 0x0004,

        // Skip <sch:rule> with same context as any rule before
        SKIP_DUPLICIT_RULE_CONTEXT = 0x0008,

        // Allow to <sch:schema> do not contain <sch::pattern>
        ALLOW_EMPTY_SCHEMA = 0x0010,

        // Allow to <sch:pattern> do not contain <sch:rule>
        ALLOW_EMPTY_PATTERN = 0x0020,

        // Allow to <sch:rule> do not contain <sch:assert> nor <sch:report>
        ALLOW_EMPTY_RULE = 0x0040;

    /**
     * XPath class used in this class
     *
     * @var string
     */
    public static $xPathClass = 'Milo\SchematronXPath';

    /**
     * Schema has been loaded
     *
     * @var bool
     */
    protected $loaded = false;

    /**
     * Options
     *
     * @var int
     */
    protected $options = self::DEFAULT_OPTIONS;

    /**
     * Schema namespace
     *
     * @var string
     */
    protected $ns;

    /**
     * Absolute path for <sch:include> relative paths
     *
     * @var ?string
     */
    protected $directory;

    /**
     * LibXML options which were used for schema loading
     *
     * @var int
     */
    protected $domOptions;

    /**
     * Version from @schemaVersion in <sch:schema>
     *
     * @var ?string
     */
    protected $version;

    /**
     * Title from <sch:title> in <sch:schema>
     *
     * @var ?string
     */
    protected $title;

    /**
     * Default validation phase
     *
     * @var string
     */
    protected $defaultPhase = self::PHASE_ALL;

    /**
     * Restrictions on <sch:include>; self::INCLUDE_* value/mask
     *
     * @var int|false|null
     */
    protected $allowedInclude = self::INCLUDE_RELATIVE_PATH;

    /**
     * How deep can be <sch:include>
     *
     * @var int
     */
    protected $maxIncludeDepth = 10;

    /**
     * XPath handler
     *
     * @var SchematronXPath
     */
    protected $xPath;

    /**
     * Namespace mapping [prefix => URI] loaded from <sch:ns>
     *
     * @var array
     */
    protected $namespaces = [];

    /**
     * Patterns
     *
     * @see self::findPatterns()
     *
     * @var stdClass[]
     */
    protected $patterns = [];

    /**
     * Phases [id => value]
     *
     * @see self::findPhases()
     *
     * @var array
     */
    protected $phases = [];

    /**
     * List of opened external DOMDOCUMENT and Xpath (to support document() in xpath )
     *
     * @var array
     */
    protected $externals = [];

    /**
     * Constructor
     *
     * @param string $namespace Schema namespace (self::NS_*)
     *
     * @throws InvalidArgumentException when unsupported namespace passed
     */
    public function __construct($namespace = self::NS_DETECT)
    {
        if (!in_array($namespace, [self::NS_DETECT, self::NS_ISO, self::NS_1_5], true)) {
            throw new InvalidArgumentException("Unsupported schema namespace '$namespace'.");
        }

        $this->ns = $namespace;
    }

    /**
     * Loads schematron schema from file.
     *
     * @param string $file    Path/URI to schema file
     * @param int    $options LibXML options
     *
     * @return static
     *
     * @throws SchematronException when schema loading fails
     */
    public function load($file, $options = null)
    {
        $this->domOptions = $options === null ? (LIBXML_NOENT | LIBXML_NOBLANKS) : $options;

        $doc = new DOMDocument();
        Helpers::handleXmlErrors();
        $doc->load($file, $this->domOptions);
        if ($e = Helpers::fetchXmlErrors()) {
            throw new SchematronException("Cannot load schema from file '$file'.", 0, $e);
        }

        if (is_file($file)) {
            $this->directory = dirname(realpath($file));
        }

        return $this->loadDom($doc);
    }

    /**
     * Loads schematron schema from DOMDocument.
     *
     * @param DOMDocument $schema Schema
     *
     * @return static
     *
     * @throws SchematronException  when schema loading fails
     * @throws RuntimeException  when <sch:include> expanding fails
     */
    public function loadDom(DOMDocument $schema)
    {
        if ($this->ns === self::NS_DETECT) {
            $this->ns = $schema->getElementsByTagNameNS(self::NS_ISO, '*')->length
                ? self::NS_ISO
                : self::NS_1_5;
        }

        $this->expandIncludes($schema);

        $this->xPath = new self::$xPathClass($schema);
        $this->xPath->registerNamespace('sch', $this->ns);

        $this->loadSchemaBasics($schema);
        $this->namespaces = $this->findNamespaces($schema);
        $this->patterns = $this->findPatterns($schema);
        if (!count($this->patterns) && !($this->options & self::ALLOW_EMPTY_SCHEMA)) {
            throw new SchematronException('None <sch:pattern> found in schema.');
        }
        $this->phases = $this->findPhases($schema);

        $this->loaded = true;

        return $this;
    }

    /**
     * Validate document over against loaded schema.
     *
     * @param DOMDocument $doc    Document to validate
     * @param string      $result Type of return value
     * @param string      $phase  Validation phase
     *
     * @return array
     *
     * @throws RuntimeException  when schema has not been loaded yet
     * @throws InvalidArgumentException  when validation $phase is not defined
     * @throws SchematronException  when $result is RESULT_EXCEPTION and document is not valid
     */
    public function validate(DOMDocument $doc, $result = self::RESULT_SIMPLE, $phase = self::PHASE_DEFAULT)
    {
        if (!$this->loaded) {
            throw new RuntimeException('Schema has not been loaded yet. Load it before validation.');
        }

        $xpath = new self::$xPathClass($doc);
        foreach ($this->namespaces as $prefix => $uri) {
            $xpath->registerNamespace($prefix, $uri);
        }

        if ($phase === self::PHASE_DEFAULT) {
            $phase = $this->defaultPhase;
        }

        if ($phase === self::PHASE_ALL) {
            $activePatternKeys = array_keys($this->patterns);
        } elseif (!array_key_exists($phase, $this->phases)) {
            throw new InvalidArgumentException("Validation phase '$phase' is not defined.");
        } else {
            $activePatternKeys = array_keys($this->phases[$phase]);
        }

        $return = [];
        foreach ($activePatternKeys as $patternKey) {
            $pattern = $this->patterns[$patternKey];
            foreach ($pattern->rules as $ruleKey => $rule) {
                foreach ($xpath->queryContext($rule->context, $doc) as $currentNode) {
                    $lets = [];
                    if ($rule->lets) {
                        foreach ($rule->lets as $name => $value) {
                            $let = $xpath->evaluate("string($value)", $currentNode);
                            // Adding quotes is necessary to be able search strings
                            // TODO : maybe escape?
                            $lets[$name] = is_numeric($let) ? $let : "'$let'";
                        }
                    }
                    foreach ($rule->statements as $statement) {
                        $testStatement = $statement->test;
                        $nodeToEval = $currentNode;
                        $xpathToEval = $xpath;
                        if ($lets) {
                            $testStatement = call_user_func($this->getReplaceCb(), $testStatement, $lets);
                        }
                        // Added support to evaluate document()
                        // Maybe it should move to SchematronXPath, but we would have to deal with paths
                        // as neither DOMDocument or XPATH holds information about the file, so is it really
                        // worth the trouble?

                        // Commented ou for now. This simple check fails with rules that contain // in a string.

                        /*$parts = explode('//', $testStatement);
                        if (count($parts) == 2) {
                            if ($parts[0]) {
                                if (strpos($parts[0], 'document(') == 0) {
                                    $file = substr($parts[0], 10, -2);
                                    if (!isset($this->externals[$file])) {
                                        $external = new DOMDocument();
                                        $external->load($this->directory . DIRECTORY_SEPARATOR . $file);
                                        $this->externals[$file] = [
                                            'node' => $external,
                                            'xpath' => new DOMXPath($external),
                                        ];
                                    }
                                    $nodeToEval = $this->externals[$file]['node'];
                                    $xpathToEval = $this->externals[$file]['xpath'];
                                    $testStatement = '//' . $parts[1];
                                }
                            }
                        }*/
                        if ($statement->isAssert ^ $xpathToEval->evaluate("boolean($testStatement)", $nodeToEval)) {
                            $message = $this->statementToMessage($statement->node, $xpath, $currentNode, $lets);

                            switch ($result) {
                                case self::RESULT_EXCEPTION:
                                    throw new SchematronException($message);

                                case self::RESULT_COMPLEX:
                                    if (!isset($return[$patternKey])) {
                                        $return[$patternKey] = (object)[
                                            'title' => $pattern->title,
                                            'rules' => [],
                                        ];
                                    }

                                    if (!isset($return[$patternKey]->rules[$ruleKey])) {
                                        $return[$patternKey]->rules[$ruleKey] = (object)[
                                            'context' => $rule->context,
                                            'errors' => [],
                                        ];
                                    }

                                    $return[$patternKey]->rules[$ruleKey]->errors[] = (object)[
                                        'test' => $statement->test,
                                        'message' => $message,
                                        'path' => $currentNode->getNodePath(),
                                    ];
                                    break;

                                default:
                                    $return[] = $message;
                                    break;
                            }
                        } // test
                    } // statements for context
                } // context elements
            } // rules
        } // patterns

        return $return;
    }

    /**
     * Returns version loaded from @schemaVersion on <sch:schema>
     *
     * @return ?string
     */
    public function getSchemaVersion()
    {
        return $this->version;
    }

    /**
     * Returns title loaded from <sch:title> in <sch:schema>
     *
     * @return ?string
     */
    public function getSchemaTitle()
    {
        return $this->title;
    }

    /**
     * Set processing options, {@link self::DEFAULT_OPTIONS}
     *
     * @param int $options Mask of options
     *
     * @return static
     */
    public function setOptions($options = self::DEFAULT_OPTIONS)
    {
        $this->options = $options;
        return $this;
    }

    /**
     * Returns processing options, {@link self::DEFAULT_OPTIONS}
     *
     * @return int
     */
    public function getOptions()
    {
        return $this->options;
    }

    /**
     * Has been schema loaded?
     *
     * @return bool
     */
    public function isLoaded()
    {
        return $this->loaded;
    }

    /**
     * Set which URIa are allowed for <sch:include> (self::INCLUDE_*)
     *
     * @param int $mask Mask of types
     *
     * @return static
     */
    public function setAllowedInclude($mask)
    {
        $this->allowedInclude = $mask;
        return $this;
    }

    /**
     * Returns which URIa are allowed for <sch:include> (self::INCLUDE_*)
     *
     * @return int
     */
    public function getAllowedInclude()
    {
        return $this->allowedInclude;
    }

    /**
     * Sets how deep can be <sch:include> in <sch:include> in <sch:include> ...
     *
     * @param int $depth Depth
     *
     * @return static
     */
    public function setMaxIncludeDepth($depth)
    {
        $this->maxIncludeDepth = (int)$depth;
        return $this;
    }

    /**
     * Returns how deep can be <sch:include> in <sch:include> in <sch:include> ...
     *
     * @return int
     */
    public function getMaxIncludeDepth()
    {
        return $this->maxIncludeDepth;
    }

    /**
     * Sets include directory path for relative file paths in <sch:include>
     *
     * @param string $dir Directory path
     *
     * @return static
     *
     * @throws RuntimeException  when directory does not exist
     */
    public function setIncludeDir($dir)
    {
        if (!is_dir($dir)) {
            throw new RuntimeException("Directory '$dir' does not exist.");
        }
        $this->directory = realpath($dir);

        return $this;
    }

    /**
     * Returns path to directory which is used for relative file paths from <sch:include>
     *
     * @return ?string
     */
    public function getIncludeDir()
    {
        return $this->directory;
    }

    /* ~~~ Schematron schema loading part ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~ */
    /**
     * Expands all <sch:include> in DOM.
     *
     * @param DOMDocument $schema Schema
     * @param int         $depth  Include depth level
     *
     * @return void
     *
     * @throws SchematronException
     * @throws RuntimeException  when applied any include restriction
     */
    protected function expandIncludes(DOMDocument $schema, $depth = 0)
    {
        if ($this->options & self::IGNORE_INCLUDE) {
            return;
        }

        if ($depth > $this->maxIncludeDepth) {
            throw new RuntimeException("Reached maximum ($this->maxIncludeDepth) include depth.");
        }

        $list = $schema->getElementsByTagNameNS($this->ns, 'include');
        if ($list->length > 0 && ($this->options & self::FORBID_INCLUDE)) {
            throw new RuntimeException(
                "Include functionality is disabled. Found $list->length <{$list->item(0)->nodeName}> elements,"
                . " first on line {$list->item(0)->getLineNo()}."
            );
        }

        while ($list->length) { // do not foreach(), list is affected by replaceChild
            $element = $list->item(0);

            $href = $rawHref = Helpers::getAttribute($element, 'href');
            if (substr_compare($href, 'file://', 0, 7, true) === 0) {
                $href = substr($href, 7);
            }

            $type = static::detectIncludeType($href, $typeStr);

            if (!($this->allowedInclude & $type)) {
                throw new RuntimeException(
                    "Including URI of type '$typeStr' referenced by <$element->nodeName>"
                    . " on line {$element->getLineNo()} is not allowed."
                );
            }

            if ($type === self::INCLUDE_RELATIVE_PATH) {
                if ($this->directory === null) {
                    throw new RuntimeException(
                        "Cannot evaluate relative URI '$rawHref' referenced by <$element->nodeName>"
                        . " on line {$element->getLineNo()}, schema has not been loaded from file. "
                        . 'Set schema directory by setIncludeDir() method.'
                    );
                }
                $href = $this->directory . DIRECTORY_SEPARATOR . $href;
            }

            $doc = new DOMDocument();
            Helpers::handleXmlErrors();
            $doc->load($href, $this->domOptions);
            if ($e = Helpers::fetchXmlErrors()) {
                throw new RuntimeException(
                    "Cannot load '$rawHref' referenced by <$element->nodeName> on line {$element->getLineNo()}.",
                    0,
                    $e
                );
            }

            $this->expandIncludes($doc, $depth + 1);

            $element->parentNode->replaceChild(
                $schema->importNode($doc->documentElement, true),
                $element
            );
        }
    }

    /**
     * Fills object members by basics schema properties.
     *
     * @param DOMDocument $schema Schema
     *
     * @return void
     *
     * @throws SchematronException
     */
    protected function loadSchemaBasics(DOMDocument $schema)
    {
        $list = $this->xPath->query('//sch:schema', $schema);
        if ($list->length > 1) {
            throw new SchematronException("Only one <schema> element in document is allowed, but $list->length found.");
        } elseif ($list->length < 1) {
            if (!($this->options & self::ALLOW_MISSING_SCHEMA_ELEMENT)) {
                throw new SchematronException('<schema> element not found.');
            }
        } else {
            $element = $list->item(0);

            $this->version = Helpers::getAttribute($element, 'schemaVersion', null);
            $this->defaultPhase = Helpers::getAttribute($element, 'defaultPhase', self::PHASE_ALL);
            if (strtolower($binding = Helpers::getAttribute($element, 'queryBinding', 'xslt')) !== 'xslt') {
                throw new SchematronException("Query binding '$binding' is not supported.");
            }

            $titleElements = $this->xPath->query('sch:title', $element);
            if ($titleElements->length > 0) {
                $this->title = $titleElements->item(0)->textContent;
            }
        }
    }

    /**
     * Search for all <sch:ns>.
     *
     * @param DOMDocument $schema Schema
     *
     * @return array[string prefix => string URI]
     *
     * @throws SchematronException
     */
    protected function findNamespaces(DOMDocument $schema)
    {
        $namespaces = $elements = [];
        foreach ($this->xPath->query('//sch:ns', $schema) as $element) {
            $prefix = Helpers::getAttribute($element, 'prefix');
            $uri = Helpers::getAttribute($element, 'uri');

            if (array_key_exists($prefix, $elements)) {
                throw new SchematronException(
                    "Namespace prefix '$prefix' on line {$element->getLineNo()} is alredy declared"
                    . " on line {$elements[$prefix]->getLineNo()}."
                );
            }

            $elements[$prefix] = $element;
            $namespaces[$prefix] = $uri;
        }
        return $namespaces;
    }

    /**
     * Search for all <sch:pattern>. Abstract patterns are instantized.
     *
     * @param DOMDocument $schema Schema
     *
     * @return stdClass[]
     *
     * @throws SchematronException
     */
    protected function findPatterns(DOMDocument $schema)
    {
        $abstracts = $this->findPatternAbstracts($schema);

        $patterns = [];
        foreach ($this->xPath->query('//sch:pattern[not(@abstract) or @abstract!="true"]', $schema) as $element) {
            if (($isA = Helpers::getAttribute($element, 'is-a', null)) !== null) {
                if (!array_key_exists($isA, $abstracts)) {
                    throw new SchematronException(
                        "<$element->nodeName> on line {$element->getLineNo()} references to undefined abstract pattern"
                        . " by ID '$isA'."
                    );
                }
                $pattern = $this->instantiatePattern($abstracts[$isA], $this->findParams($element));
            } else {
                $pattern = (object)[
                    'title' => $this->xPath->evaluate('boolean(sch:title)', $element)
                        ? $this->xPath->evaluate('string(sch:title)', $element)
                        : Helpers::getAttribute($element, 'name', null), // Schematron v1.5
                    'rules' => $rules = $this->findRules($element),
                ];

                if (!count($rules) && !($this->options & self::ALLOW_EMPTY_PATTERN)) {
                    throw new SchematronException(
                        "Missing rules for <$element->nodeName> on line {$element->getLineNo()}."
                    );
                }
            }
            $pattern->id = Helpers::getAttribute($element, 'id', null);

            if ($pattern->id === null) {
                $patterns[] = $pattern;
            } else {
                $patterns["#$pattern->id"] = $pattern;
            }
        }

        return $patterns;
    }

    /**
     * Search for all <sch:pattern abstract="TRUE">
     *
     * @param DOMDocument $schema Schema
     *
     * @return array[id => stdClass]
     *
     * @throws SchematronException
     */
    protected function findPatternAbstracts(DOMDocument $schema)
    {
        $patterns = [];
        foreach ($this->xPath->query('//sch:pattern[@abstract="true"]', $schema) as $element) {
            if ($element->hasAttribute('is-a')) {
                throw new SchematronException(
                    "An abstract <$element->nodeName> on line {$element->getLineNo()} shall not have a 'is-a'"
                    . '  attribute.'
                );
            }

            $id = Helpers::getAttribute($element, 'id');
            $patterns[$id] = (object)[
                'title' => $this->xPath->evaluate('boolean(sch:title)', $element)
                    ? $this->xPath->evaluate('string(sch:title)', $element)
                    : Helpers::getAttribute($element, 'name', null), // Schematron v1.5
                'rules' => $rules = $this->findRules($element),
            ];

            if (!count($rules) && !($this->options & self::ALLOW_EMPTY_PATTERN)) {
                throw new SchematronException(
                    "Missing rules for <$element->nodeName> on line {$element->getLineNo()}."
                );
            }
        }
        return $patterns;
    }

    /**
     * Returns callable for replacing parameters in XPath expressions.
     *
     * @return callable(string $expression, array $parameters)
     */
    protected function getReplaceCb()
    {
        static $replaceCb;

        if ($replaceCb === null) {
            $replaceCb = function ($expression, $parameters) {
                foreach ($parameters as $name => $value) {
                    $expression = str_replace("\$$name", $value, $expression);
                }

                return $expression;
            };
        }
        return $replaceCb;
    }

    /**
     * Creates pattern instance from abstract pattern.
     *
     * @param stdClass             $abstract   Abstract pattern
     * @param array[name => value] $parameters Parameters
     *
     * @return stdClass
     */
    protected function instantiatePattern(stdClass $abstract, array $parameters)
    {
        $instance = clone $abstract;
        foreach ($instance->rules as & $rule) {
            $rule = clone $rule;
            $rule->context = call_user_func($this->getReplaceCb(), $rule->context, $parameters);
            foreach ($rule->statements as & $stmt) {
                $stmt = clone $stmt;
                $stmt->test = call_user_func($this->getReplaceCb(), $stmt->test, $parameters);
            }
        }
        return $instance;
    }

    /**
     * Search for all <sch:param>.
     *
     * @param DOMElement $pattern Pattern
     *
     * @return array[string name => string value]
     *
     * @throws SchematronException
     */
    protected function findParams(DOMElement $pattern)
    {
        $params = $elements = [];
        foreach ($this->xPath->query('sch:param', $pattern) as $element) {
            $name = Helpers::getAttribute($element, 'name');
            $value = Helpers::getAttribute($element, 'value');

            if (array_key_exists($name, $elements)) {
                throw new SchematronException(
                    "Parameter '$name' is already defined on line {$elements[$name]->getLineNo()}."
                );
            }

            $elements[$name] = $element;
            $params[$name] = $value;
        }
        return $params;
    }

    /**
     * Search for all <sch:rule>.
     *
     * @param DOMElement $pattern Pattern
     *
     * @return stdClass[]
     *
     * @throws SchematronException
     */
    protected function findRules(DOMElement $pattern)
    {
        $abstracts = $this->findRuleAbstracts($pattern);

        $rules = $contexts = [];
        foreach ($this->xPath->query('sch:rule[not(@abstract) or @abstract!="true"]', $pattern) as $element) {
            $context = Helpers::getAttribute($element, 'context');

            if (array_key_exists($context, $contexts) && ($this->options & self::SKIP_DUPLICIT_RULE_CONTEXT)) {
                continue;
            }
            $contexts[$context] = true;

            $rules[] = (object)[
                'context' => $context,
                'lets' => $this->findLets($element),
                'statements' => $statements = $this->findStatements($element, $abstracts),
            ];

            if (!count($statements) && !($this->options & self::ALLOW_EMPTY_RULE)) {
                throw new SchematronException(
                    "Asserts nor reports not found for <$element->nodeName> on line {$element->getLineNo()}."
                );
            }
        }
        return $rules;
    }

    /**
     * Search for all <sch:rule abstract="TRUE">.
     *
     * @param DOMElement $pattern Pattern
     *
     * @return stdClass[]
     *
     * @throws SchematronException
     */
    protected function findRuleAbstracts(DOMElement $pattern)
    {
        $rules = [];
        foreach ($this->xPath->query('sch:rule[@abstract="true"]', $pattern) as $element) {
            $id = Helpers::getAttribute($element, 'id');
            if ($element->hasAttribute('context')) {
                throw new SchematronException(
                    "An abstract rule on line {$element->getLineNo()} shall not have a 'context' attribute."
                );
            }

            $rules[$id] = (object)[
                'statements' => $this->findStatements($element),
            ];
        }
        return $rules;
    }

    /**
     * Search for all <sch:assert> and <sch:report>.
     *
     * @param DOMElement $rule          Rule
     * @param array      $abstractRules Abstract rules
     *
     * @return stdClass[]
     *
     * @throws SchematronException
     */
    protected function findStatements(DOMElement $rule, array $abstractRules = [])
    {
        $statements = [];
        foreach ($this->xPath->query('sch:assert | sch:report | sch:extends', $rule) as $node) {
            if ($node->localName === 'extends') {
                $idRule = Helpers::getAttribute($node, 'rule');
                if (!isset($abstractRules[$idRule])) {
                    throw new SchematronException(
                        "<$node->nodeName> on line {$node->getLineNo()} references to undefined abstract rule by"
                        . " ID '$idRule'."
                    );
                }

                $statements = array_merge($statements, $abstractRules[$idRule]->statements);
            } else {
                $statements[] = (object)[
                    'test' => Helpers::getAttribute($node, 'test'),
                    'isAssert' => $node->localName === 'assert',
                    'node' => $node,
                ];
            }
        }
        return $statements;
    }

    /**
     * Search for all <sch:phase> and check existency of defaultPhase if set in <sch:schema>.
     *
     * @param DOMDocument $schema Schema
     *
     * @return array[id => array[idPattern]]
     *
     * @throws SchematronException
     */
    protected function findPhases(DOMDocument $schema)
    {
        $phases = $elements = [];
        foreach ($this->xPath->query('//sch:phase', $schema) as $element) {
            $id = Helpers::getAttribute($element, 'id');
            if (isset($elements[$id])) {
                throw new SchematronException(
                    "<$element->nodeName> with id '$id' is already defined on line {$elements[$id]->getLineNo()}."
                );
            }
            $elements[$id] = $element;
            $phases[$id] = $this->findActives($element);
        }

        if ($this->defaultPhase !== self::PHASE_ALL && !array_key_exists($this->defaultPhase, $phases)) {
            throw new SchematronException("Default validation phase '$this->defaultPhase' is not defined.");
        }

        return $phases;
    }

    /**
     * Search for all <sch:active>.
     *
     * @param DOMElement $phase Phase
     *
     * @return string[]  list of <sch:pattern> IDs
     *
     * @throws SchematronException
     */
    protected function findActives(DOMElement $phase)
    {
        $actives = [];
        foreach ($this->xPath->query('sch:active', $phase) as $element) {
            $idPattern = Helpers::getAttribute($element, 'pattern');
            if (!isset($this->patterns["#$idPattern"])) {
                throw new SchematronException(
                    "<$element->nodeName> on line {$element->getLineNo()} references to undefined pattern"
                    . " by ID '$idPattern'."
                );
            }
            $actives["#$idPattern"] = $idPattern;
        }
        return $actives;
    }

    /**
     * Search for all <sch:let>.
     *
     * @param DOMElement $rule Rule
     *
     * @return array
     */
    protected function findLets(DOMElement $rule)
    {
        $variables = [];
        foreach ($this->xPath->query('sch:let', $rule) as $node) {
            $name = Helpers::getAttribute($node, 'name');
            $value = Helpers::getAttribute($node, 'value');
            $variables[$name] = $value;
        }
        return $variables;
    }

    /**
     * Expands <sch:name> and <sch:value-of> in assertion/report message.
     *
     * @param DOMElement      $stmt    Statement
     * @param SchematronXPath $xPath   XPath
     * @param DOMNode         $current Current node
     * @param array           $lets    Lets
     *
     * @return string
     */
    protected function statementToMessage(DOMElement $stmt, SchematronXPath $xPath, DOMNode $current, $lets = [])
    {
        $message = '';
        foreach ($stmt->childNodes as $node) {
            if ($node->nodeType === XML_ELEMENT_NODE && $node->namespaceURI === $this->ns) {
                if ($node->localName === 'name') {
                    $message .= $xPath->evaluate('name(' . Helpers::getAttribute($node, 'path', '') . ')', $current);
                } elseif ($node->localName === 'value-of') {
                    $selected =  Helpers::getAttribute($node, 'select');
                    if ($lets) {
                        $selected = call_user_func($this->getReplaceCb(), $selected, $lets);
                    }
                    $message .= $xPath->evaluate('string(' . $selected . ')', $current);
                } else {
                    // @todo warning?
                    $message .= $node->textContent;
                }
            } else {
                $message .= $node->textContent;
            }
        }

        $message = preg_replace('#\s+#', ' ', trim($message));

        return $message;
    }

    /**
     * Detects include URI type.
     *
     * @param string  $uri     URI
     * @param ?string $typeStr Type string
     *
     * @return int
     */
    protected static function detectIncludeType($uri, &$typeStr = null)
    {
        $absolutePathRe = substr_compare(PHP_OS, 'WIN', 0, 3, true) === 0
            ? '#^[A-Z]:#i'
            : '#^/#';

        if (preg_match('#^[a-z-]+://#i', $uri)) {
            $type = self::INCLUDE_URL;
            $typeStr = 'URL';
        } elseif (preg_match($absolutePathRe, $uri)) {
            $type = self::INCLUDE_ABSOLUTE_PATH;
            $typeStr = 'Absolute file path';
        } else {
            $type = self::INCLUDE_RELATIVE_PATH;
            $typeStr = 'Relative file path';
        }

        return $type;
    }
}
