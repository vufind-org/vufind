<?php
/* SVN FILE: $Id: SassPropertyNode.php 118 2010-09-21 09:45:11Z chris.l.yates@gmail.com $ */
/**
 * SassPropertyNode class file.
 * @author      Chris Yates <chris.l.yates@gmail.com>
 * @copyright   Copyright (c) 2010 PBM Web Development
 * @license      http://phamlp.googlecode.com/files/license.txt
 * @package      PHamlP
 * @subpackage  Sass.tree
 */

/**
 * SassPropertyNode class.
 * Represents a CSS property.
 * @package      PHamlP
 * @subpackage  Sass.tree
 */
class SassPropertyNode extends SassNode
{
  const MATCH_PROPERTY_SCSS = '/^([^\s=:"(\\\\:)]*)\s*(?:(= )|:)([^\:].*?)?(\s*!important[^;]*)?;*$/';
  const MATCH_PROPERTY_NEW = '/^([^\s=:"]+)\s*(?:(= )|:)([^\:].*?)?(\s*!important[^;]*)?;*$/';
  const MATCH_PROPERTY_OLD = '/^:([^\s=:]+)(?:\s*(=)\s*|\s+|$)(.*)(\s*!important.*)?/';
  const MATCH_PSUEDO_SELECTOR = '/^:*\w[-\w]+\(?/i';
  const MATCH_INTERPOLATION = '/^#\{(.*?)\}/i';
  const MATCH_PROPRIETARY_SELECTOR = '/^:?-(moz|webkit|o|ms)-/';
  const NAME   = 1;
  const SCRIPT = 2;
  const VALUE   = 3;
  const IS_SCRIPT = '= ';

  public static $psuedoSelectors = array(
    'root',
    'nth-child(',
    'nth-last-child(',
    'nth-of-type(',
    'nth-last-of-type(',
    'first-child',
    'last-child',
    'first-of-type',
    'last-of-type',
    'only-child',
    'only-of-type',
    'empty',
    'link',
    'visited',
    'active',
    'hover',
    'focus',
    'target',
    'lang(',
    'enabled',
    'disabled',
    'checked',
    ':first-line',
    ':first-letter',
    ':before',
    ':after',
    // CSS 2.1
    'first-line',
    'first-letter',
    'before',
    'after',
    // CSS 3
    'not(',
  );

  /**
   * @var string property name
   */
  public $name;
  /**
   * @var string property value or expression to evaluate
   */
  public $value;

  /**
   * @var boolean, wether the property is important
   */
  public $important;

  /**
   * SassPropertyNode constructor.
   * @param object source token
   * @param string property syntax
   * @return SassPropertyNode
   */
  public function __construct($token, $syntax = 'new')
  {
    parent::__construct($token);
    $matches = self::match($token, $syntax);
    $this->name = @$matches[self::NAME];
    if (!isset($matches[self::VALUE])) {
      $this->value = '';
    } else {
      $this->value = $matches[self::VALUE];
      if ($matches[self::SCRIPT] === self::IS_SCRIPT) {
        $this->addWarning('Setting CSS properties with "=" is deprecated; use "{name}: {value};"',
            array('{name}'=>$this->name, '{value}'=>$this->value)
        );
      }
    }
    $this->important = trim(array_pop($matches)) == '!important';
  }

  /**
   * Parse this node.
   * If the node is a property namespace return all parsed child nodes. If not
   * return the parsed version of this node.
   * @param SassContext the context in which this node is parsed
   * @return array the parsed node
   */
  public function parse($context)
  {
    $return = array();
     if ($this->value !== "") {
      $node = clone $this;
      $node->name = ($this->inNamespace() ? "{$this->namespace}-" : '') . $this->interpolate($this->name, $context);

      $result = $this->evaluate($this->interpolate($this->value, $context), $context, SassScriptParser::CSS_PROPERTY);

      $node->value = $result && is_object($result) ? $result->toString() : $this->value;
      $return[] = $node;
    }
    if ($this->children) {
      $return = array_merge($return, $this->parseChildren($context));
    }

    return $return;
  }

  /**
   * Render this node.
   * @return string the rendered node
   */
  public function render()
  {
    return $this->renderer->renderProperty($this);
  }

  /**
   * Returns a value indicating if this node is in a namespace
   * @return boolean true if this node is in a property namespace, false if not
   */
  public function inNamespace()
  {
    $parent = $this->parent;
    do {
      if ($parent instanceof SassPropertyNode) {
        return true;
      }
      $parent = $parent->parent;
    } while (is_object($parent));

    return false;
  }

  /**
   * Returns the namespace for this node
   * @return string the namespace for this node
   */
  public function getNamespace()
  {
    $namespace = array();
    $parent = $this->parent;
    do {
      if ($parent instanceof SassPropertyNode) {
        $namespace[] = $parent->name;
      }
      $parent = $parent->parent;
    } while (is_object($parent));

    return join('-', array_reverse($namespace));
  }

  /**
   * Returns the name of this property.
   * If the property is in a namespace the namespace is prepended
   * @return string the name of this property
   */
  public function getName()
  {
    return $this->name;
  }

  /**
   * Returns the parsed value of this property.
   * @return string the parsed value of this property
   */
  public function getValue()
  {
    return $this->value;
  }

  /**
   * Returns a value indicating if the token represents this type of node.
   * @param object token
   * @param string the property syntax being used
   * @return boolean true if the token represents this type of node, false if not
   */
  public static function isa($token)
  {
    if (!is_array($token)) {
      $syntax = 'old';
    } else {
      $syntax = $token['syntax'];
      $token = $token['token'];
    }

    $matches = self::match($token, $syntax);

    if (!empty($matches)) {
      if (isset($matches[self::VALUE]) && self::isPseudoSelector($matches[self::VALUE])) {
        return false;
      }
      if ($token->level === 0) {
        # RL - if it's on the first level it's probably a false positive, not an error.
        # even if it is a genuine error, no need to kill the compiler about it.

        return false;
        // throw new SassPropertyNodeException('Properties can not be assigned at root level', $token);
      } else {
        return true;
      }
    } else {
      return false;
    }
  }

  /**
   * Returns the matches for this type of node.
   * @param array the line to match
   * @param string the property syntax being used
   * @return array matches
   */
  public static function match($token, $syntax)
  {
    switch ($syntax) {
      case 'scss':
        preg_match(self::MATCH_PROPERTY_SCSS, $token->source, $matches);
        break;
      case 'new':
        preg_match(self::MATCH_PROPERTY_NEW, $token->source, $matches);
        break;
      case 'old':
        preg_match(self::MATCH_PROPERTY_OLD, $token->source, $matches);
        break;
      default:
        if (preg_match(self::MATCH_PROPERTY_NEW, $token->source, $matches) == 0) {
          preg_match(self::MATCH_PROPERTY_OLD, $token->source, $matches);
        }
        break;
    }

    return $matches;
  }

  /**
   * Returns a value indicating if the string starts with a pseudo selector.
   * This is used to reject pseudo selectors as property values as, for example,
   * "a:hover" and "text-decoration:underline" look the same to the property
   * match regex.
   * It will also match interpolation to allow for constructs such as
   * content:#{$pos}
   * @see isa()
   * @param string the string to test
   * @return bool true if the string starts with a pseudo selector, false if not
   */
  public static function isPseudoSelector($string)
  {
    preg_match(self::MATCH_PSUEDO_SELECTOR, $string, $matches);

    return (isset($matches[0]) && in_array($matches[0], self::$psuedoSelectors)) ||
      preg_match(self::MATCH_INTERPOLATION, $string) ||
      preg_match(self::MATCH_PROPRIETARY_SELECTOR, $string);
  }
}
