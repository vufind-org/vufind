<?php
/* SVN FILE: $Id$ */
/**
 * SassReturnNode class file.
 * @author      Chris Yates <chris.l.yates@gmail.com>
 * @copyright   Copyright (c) 2010 PBM Web Development
 * @license      http://phamlp.googlecode.com/files/license.txt
 * @package      PHamlP
 * @subpackage  Sass.tree
 */

/**
 * SassReturnNode class.
 * Represents a Return.
 * @package      PHamlP
 * @subpackage  Sass.tree
 */
class SassReturnNode extends SassNode
{
  const NODE_IDENTIFIER = '+';
  const MATCH = '/^(@return\s+)(.*)$/i';
  const IDENTIFIER = 1;
  const STATEMENT = 2;

  /**
   * @var statement to execute and return
   */
  private $statement;

  /**
   * SassReturnNode constructor.
   * @param object source token
   * @return SassReturnNode
   */
  public function __construct($token)
  {
    parent::__construct($token);
    preg_match(self::MATCH, $token->source, $matches);

    if (empty($matches)) {
      return new SassBoolean('false');
    }

    $this->statement = $matches[self::STATEMENT];
  }

  /**
   * Parse this node.
   * Set passed arguments and any optional arguments not passed to their
   * defaults, then render the children of the return definition.
   * @param SassContext the context in which this node is parsed
   * @return array the parsed node
   */
  public function parse($pcontext)
  {
    $return = $this;
    $context = new SassContext($pcontext);
    $statement = $this->statement;

    $parent = $this->parent->parent->parser;
    $script = $this->parent->parent->script;
    $lexer = $script->lexer;

    $result = $script->evaluate($statement, $context);

    throw new SassReturn($result);
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
}

class SassReturn extends Exception
{
  public function __construct($value)
  {
    $this->value = $value;
  }
}
