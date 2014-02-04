<?php
/* SVN FILE: $Id$ */
/**
 * SassDirectiveNode class file.
 * @author      Chris Yates <chris.l.yates@gmail.com>
 * @copyright   Copyright (c) 2010 PBM Web Development
 * @license      http://phamlp.googlecode.com/files/license.txt
 * @package      PHamlP
 * @subpackage  Sass.tree
 */

/**
 * SassDirectiveNode class.
 * Represents a CSS directive.
 * @package      PHamlP
 * @subpackage  Sass.tree
 */
class SassDirectiveNode extends SassNode
{
  const NODE_IDENTIFIER = '@';
  const MATCH = '/^(@[\w-]+)/';

  const INTERPOLATION_MATCH = '/\$([\w-]+)/';

  /**
   * SassDirectiveNode.
   * @param object source token
   * @return SassDirectiveNode
   */
  public function __construct($token)
  {
    parent::__construct($token);
  }

  protected function getDirective()
  {
    return $this->token->source;
    preg_match('/^(@[\w-]+)(?:\s*(\w+))*/', $this->token->source, $matches);
    array_shift($matches);
    $parts = implode(' ', $matches);

    return strtolower($parts);
  }

  /**
   * Parse this node.
   * @param SassContext the context in which this node is parsed
   * @return array the parsed node
   */
  public function parse($context)
  {
    $this->token->source = self::interpolate_nonstrict($this->token->source, $context);

    $this->children = $this->parseChildren($context);

    return array($this);
  }

  /**
   * Render this node.
   * @return string the rendered node
   */
  public function render()
  {
    $properties = array();
    foreach ($this->children as $child) {
      $properties[] = $child->render();
    } // foreach

    return $this->renderer->renderDirective($this, $properties);
  }

  /**
   * Returns a value indicating if the token represents this type of node.
   * @param object token
   * @return boolean true if the token represents this type of node, false if not
   */
  public static function isa($token)
  {
    return $token->source[0] === self::NODE_IDENTIFIER;
  }

  /**
   * Returns the directive
   * @param object token
   * @return string the directive
   */
  public static function extractDirective($token)
  {
    preg_match(self::MATCH, $token->source, $matches);

    return strtolower($matches[1]);
  }

  public static function interpolate_nonstrict($string, $context)
  {
    for ($i = 0, $n = preg_match_all(self::INTERPOLATION_MATCH, $string, $matches); $i < $n; $i++) {
      $var = SassScriptParser::$instance->evaluate($matches[0][$i], $context);

      if ($var instanceOf SassString) {
        $var = $var->value;
      } else {
        $var = $var->toString();
      }

      if (preg_match('/^unquote\((["\'])(.*)\1\)$/', $var, $match)) {
          $val = $match[2];
      } elseif ($var == '""') {
          $val = "";
      } elseif (preg_match('/^(["\'])(.*)\1$/', $var, $match)) {
          $val = $match[2];
      } else {
          $val = $var;
      }
      $matches[1][$i] = $val;
    }
    $matches[0][] = '#{';
    $matches[0][] = '}';
    $matches[1][] = '';
    $matches[1][] = '';

    return str_replace($matches[0], $matches[1], $string);
  }
}
