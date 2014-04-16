<?php
/* SVN FILE: $Id$ */
/**
 * SassRuleNode class file.
 * @author      Chris Yates <chris.l.yates@gmail.com>
 * @copyright   Copyright (c) 2010 PBM Web Development
 * @license      http://phamlp.googlecode.com/files/license.txt
 * @package      PHamlP
 * @subpackage  Sass.tree
 */

/**
 * SassRuleNode class.
 * Represents a CSS rule.
 * @package      PHamlP
 * @subpackage  Sass.tree
 */
class SassRuleNode extends SassNode
{
  const MATCH = '/^(.+?)(?:\s*\{)?$/';
  const SELECTOR = 1;
  const CONTINUED = ',';

  /**
   * @const string that is replaced with the parent node selector
   */
  const PARENT_REFERENCE = '&';

  /**
   * @var array selector(s)
   */
  private $selectors = array();

  /**
   * @var array parent selectors
   */
  private $parentSelectors = array();

  /**
   * @var array resolved selectors
   */
  private $resolvedSelectors = array();

  /**
   * @var boolean whether the node expects more selectors
   */
  private $isContinued;

  /**
   * SassRuleNode constructor.
   * @param object source token
   * @param string rule selector
   * @return SassRuleNode
   */
  public function __construct($token)
  {
    parent::__construct($token);
    preg_match(self::MATCH, $token->source, $matches);
    $this->addSelectors($matches[SassRuleNode::SELECTOR]);
  }

  /**
   * Adds selector(s) to the rule.
   * If the selectors are to continue for the rule the selector must end in a comma
   * @param string selector
   */
  public function addSelectors($selectors, $explode = true)
  {
    $this->isContinued = substr($selectors, -1) === self::CONTINUED;
    $this->selectors = array_merge($this->selectors, $explode ? $this->explode($selectors) : $selectors);
  }

  /**
   * Returns a value indicating if the selectors for this rule are to be continued.
   * @param boolean true if the selectors for this rule are to be continued,
   * false if not
   */
  public function getIsContinued()
  {
    return $this->isContinued;
  }

  /**
   * Parse this node and its children into static nodes.
   * @param SassContext the context in which this node is parsed
   * @return array the parsed node and its children
   */
  public function parse($context)
  {
    $node = clone $this;
    $node->selectors = $this->resolveSelectors($context);
    $node->children = $this->parseChildren($context);

    return array($node);
  }

  /**
   * Render this node and its children to CSS.
   * @return string the rendered node
   */
  public function render()
  {
    $this->extend();
    $rules = '';
    $properties = array();

    foreach ($this->children as $child) {
      $child->parent = $this;
      if ($child instanceof SassRuleNode) {
        $rules .= $child->render();
      } else {
        $properties[] = $child->render();
      }
    }

    return $this->renderer->renderRule($this, $properties, $rules);
  }

  /**
   * Extend this nodes selectors
   * $extendee is the subject of the @extend directive
   * $extender is the selector that contains the @extend directive
   * $selector a selector or selector sequence that is to be extended
   */
  public function extend()
  {
    foreach ($this->root->getExtenders() as $extendee => $extenders) {
      if ($this->isPsuedo($extendee)) {
        $extendee = explode(':', $extendee);
        $pattern = preg_quote($extendee[0]).'((\.[-\w]+)*):'.preg_quote($extendee[1]);
      } else {
        $pattern = preg_quote($extendee);
      }

      foreach (preg_grep('/'.$pattern.'/', $this->selectors) as $selector) {
        foreach ($extenders as $extender) {
          # first if establishes that we are using a placeholder and the extendee begins with a tag
          if ($extendee{0} == '%' && $selector{0} != '%' && preg_match('/(^| )[a-zA-Z][^%]*' . preg_quote($extendee) . '([^a-z0-9_-]|$)/', $selector)) {
            # the second if establishes that the extender is a tag rather than a class/id
            $zero = ord(strtolower(substr($extender, 0, 1))); // cheaper than regex
            if ($zero >= 97 && $zero <= 122) {
              continue;
            }
          }
          if (is_array($extendee)) {
            $this->selectors[] = preg_replace('/(.*?)'.$pattern.'([^a-zA-Z0-9_-]|$)/', '$1' . $extender . '$2', $selector);
          } elseif ($this->isSequence($extender) || $this->isSequence($selector)) {
            $this->selectors = array_merge($this->selectors, $this->mergeSequence($extender, $extendee, $selector));
          } else {
            $this->selectors[] = str_replace($extendee, $extender, $selector);
          }
        }
      }
      $this->selectors = array_unique($this->selectors);
    }
  }

  /**
   * Tests whether the selector is a psuedo selector
   * @param string selector to test
   * @return boolean true if the selector is a psuedo selector, false if not
   */
  private function isPsuedo($selector)
  {
    return strpos($selector, ':') !== false;
  }

  /**
   * Tests whether the selector is a sequence selector
   * @param string selector to test
   * @return boolean true if the selector is a sequence selector, false if not
   */
  private function isSequence($selector)
  {
    return strpos($selector, ' ') !== false;
  }

  public function isPlaceholder($selector)
  {
    return strpos($selector, '%') !== false;
  }

  /**
   * Merges selector sequences
   * @param string the extender selector
   * @param string selector to extend
   * @return array the merged sequences
   */
  private function mergeSequence($extender, $extendee, $selector)
  {
    // if it's a placeholder, be lazy. Needs tests.
    if ($extendee[0] == '%') {
      // need to stop things like a%foo accepting div { @extend %foo }
      return array(str_replace($extendee, $extender,  $selector));
    }

    $extender = explode(' ', $extender);
    $end = array_pop($extender);
    $selector = explode(' ', $selector);
    array_pop($selector);

    $common = array();
    if (count($extender) && count($selector)) {
      while (trim($extender[0]) === trim($selector[0])) {
        $common[] = array_shift($selector);
        array_shift($extender);
        if (!count($extender)) {
          break;
        }
      }
    }

    $beginning = (!empty($common) ? join(' ', $common) . ' ' : '');

    # Richard Lyon - 2011-10-25 - removes duplicates by uniquing and trimming.
    # regex removes whitespace from start and and end of string as well as removing
    # whitespace following whitespace. slightly quicker than a trim and simpler replace

    return array_unique(array(
      preg_replace('/(^\s+|(\s)\s+|\s+$)/', '$2', $beginning.join(' ', $selector).' '.join(' ', $extender). ' ' . $end),
      preg_replace('/(^\s+|(\s)\s+|\s+$)/', '$2', $beginning.join(' ', $extender).' '.join(' ', $selector). ' ' . $end)
    ));
  }

  /**
   * Returns the selectors
   * @return array selectors
   */
  public function getSelectors()
  {
    return $this->selectors;
  }

  /**
   * Resolves selectors.
   * Interpolates SassScript in selectors and resolves any parent references or
   * appends the parent selectors.
   * @param SassContext the context in which this node is parsed
   *
   * Change: 7/Dec/11 - change to make selector ordering conform to Ruby compiler.
   */
  public function resolveSelectors($context)
  {
    $resolvedSelectors = $normalSelectors = array();
    $this->parentSelectors = $this->getParentSelectors($context);

    foreach ($this->selectors as $key=>$selector) {
      $selector = $this->interpolate($selector, $context);
      $selectors = SassList::_build_list($selector);

      foreach ($selectors as $selector) {
        $selector = trim($selector, ' \'"'); // strip whitespace and quotes, just-in-case.
        if ($this->hasParentReference($selector)) {
          $resolvedSelectors = array_merge($resolvedSelectors, $this->resolveParentReferences($selector, $context));
        } else {
          $normalSelectors[] = $selector;
        }
      }
    } // foreach

    // merge with parent selectors
    if ($this->parentSelectors) {
      $return = array();
      foreach ($this->parentSelectors as $parent) {
        foreach ($normalSelectors as $selector) {
          $spacer = (substr($selector, 0, 1) == '[') ? '' : ' ';

          $return[] = $parent . $spacer . $selector;
        }
      }
      $normalSelectors = $return;
    }

    return array_merge($normalSelectors, $resolvedSelectors);
  }

  /**
   * Returns the parent selector(s) for this node.
   * This in an empty array if there is no parent selector.
   * @return array the parent selector for this node
   */
  protected function getParentSelectors($context)
  {
    $ancestor = $this->parent;
    while (!$ancestor instanceof SassRuleNode && $ancestor->hasParent()) {
      $ancestor = $ancestor->parent;
    }

    if ($ancestor instanceof SassRuleNode) {
      return $ancestor->resolveSelectors($context);
    }

    return array();
  }

  /**
   * Returns the position of the first parent reference in the selector.
   * If there is no parent reference in the selector this function returns
   * boolean FALSE.
   * Note that the return value may be non-Boolean that evaluates to FALSE,
   * i.e. 0. The return value should be tested using the === operator.
   * @param string selector to test
   * @return mixed integer: position of the the first parent reference,
   * boolean: false if there is no parent reference.
   */
  private function parentReferencePos($selector)
  {
    $inString = '';
    for ($i = 0, $l = strlen($selector); $i < $l; $i++) {
      $c = $selector[$i];
      if ($c === self::PARENT_REFERENCE && empty($inString)) {
        return $i;
      } elseif (empty($inString) && ($c === '"' || $c === "'")) {
        $inString = $c;
      } elseif ($c === $inString) {
        $inString = '';
      }
    }

    return false;
  }

  /**
   * Determines if there is a parent reference in the selector
   * @param string selector
   * @return boolean true if there is a parent reference in the selector
   */
  private function hasParentReference($selector)
  {
    return $this->parentReferencePos($selector) !== false;
  }

  /**
   * Resolves parent references in the selector
   * @param string selector
   * @return string selector with parent references resolved
   */
  private function resolveParentReferences($selector, $context)
  {
    $resolvedReferences = array();
    if (!count($this->parentSelectors)) {
      throw new SassRuleNodeException('Can not use parent selector (' . self::PARENT_REFERENCE . ') when no parent selectors', $this);
    }
    foreach ($this->getParentSelectors($context) as $parentSelector) {
      $resolvedReferences[] = str_replace(self::PARENT_REFERENCE, $parentSelector, $selector);
    }

    return $resolvedReferences;
  }

  /**
   * Explodes a string of selectors into an array.
   * We can't use PHP::explode as this will potentially explode attribute
   * matches in the selector, e.g. div[title="some,value"] and interpolations.
   * @param string selectors
   * @return array selectors
   */
  private function explode($string)
  {
    $selectors = array();
    $inString = false;
    $interpolate = false;
    $selector = '';

    for ($i = 0, $l = strlen($string); $i < $l; $i++) {
      $c = $string[$i];
      if ($c === self::CONTINUED && !$inString && !$interpolate) {
        $selectors[] = trim($selector);
        $selector = '';
      } else {
        $selector .= $c;
        if ($c === '"' || $c === "'") {
          do {
            $_c = $string[++$i];
            $selector .= $_c;
          } while ($_c !== $c && isset($string[$i+1]));
        } elseif ($c === '#' && $string[$i+1] === '{') {
          do {
            $c = $string[++$i];
            $selector .= $c;
          } while ($c !== '}');
        }
      }
    }

    if (!empty($selector)) {
      $selectors[] = trim($selector);
    }

    return $selectors;
  }
}
