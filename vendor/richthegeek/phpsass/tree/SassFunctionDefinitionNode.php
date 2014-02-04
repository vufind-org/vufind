<?php
/* SVN FILE: $Id$ */
/**
 * SassFunctionDefinitionNode class file.
 * @author      Chris Yates <chris.l.yates@gmail.com>
 * @copyright   Copyright (c) 2010 PBM Web Development
 * @license      http://phamlp.googlecode.com/files/license.txt
 * @package      PHamlP
 * @subpackage  Sass.tree
 */

/**
 * SassFunctionDefinitionNode class.
 * Represents a Function definition.
 * @package      PHamlP
 * @subpackage  Sass.tree
 */
class SassFunctionDefinitionNode extends SassNode
{
  const NODE_IDENTIFIER = FALSE;
  const MATCH = '/^@function\s+([_-\w]+)\s*(?:\((.*?)\))?\s*$/im';
  const IDENTIFIER = 1;
  const NAME = 1;
  const ARGUMENTS = 2;

  /**
   * @var string name of the function
   */
  private $name;
  /**
   * @var array arguments for the function as name=>value pairs were value is the
   * default value or null for required arguments
   */
  private $args = array();

  public $parent;

  /**
   * SassFunctionDefinitionNode constructor.
   * @param object source token
   * @return SassFunctionDefinitionNode
   */
  public function __construct($token)
  {
    // if ($token->level !== 0) {
    //   throw new SassFunctionDefinitionNodeException('Functions can only be defined at root level', $token);
    // }
    parent::__construct($token);
    preg_match(self::MATCH, $token->source, $matches);
    if (empty($matches)) {
      throw new SassFunctionDefinitionNodeException('Invalid Function', $token);
    }
    $this->name = $matches[self::NAME];
    $this->name = preg_replace('/[^a-z0-9_]/', '_', strtolower($this->name));
    if (isset($matches[self::ARGUMENTS])) {
      if (strlen(trim($matches[self::ARGUMENTS]))) {
        foreach (explode(',', $matches[self::ARGUMENTS]) as $arg) {
          $arg = explode(($matches[self::IDENTIFIER] === self::NODE_IDENTIFIER ? '=' : ':'), trim($arg));
          $this->args[substr(trim($arg[0]), 1)] = (count($arg) == 2 ? trim($arg[1]) : null);
        } // foreach
      }
    }
  }

  /**
   * Parse this node.
   * Add this function to  the current context.
   * @param SassContext the context in which this node is parsed
   * @return array the parsed node - an empty array
   */
  public function parse($context)
  {
    $context->addFunction($this->name, $this);

    return array();
  }

  /**
   * Returns the arguments with default values for this function
   * @return array the arguments with default values for this function
   */
  public function getArgs()
  {
    return $this->args;
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
   * Evalutes the function in the given context, with the provided arguments
   * @param SassContext - the parent context
   * @param array - the list of provided variables
   * @throws SassReturn - if the @return is fired then this is thrown to break early
   * @return SassBoolean(false) - if no @return was fired, return false
   */
  public function execute($pcontext, $provided)
  {
    list($arguments, $context) = SassScriptFunction::fill_parameters($this->args, $provided, $pcontext, $this);
    $context->setVariables($arguments);

    $parser = $this->parent->parser;

    $children = array();
    try {
      foreach ($this->children as $child) {
        $child->parent = $this;
        $children = array_merge($children, $child->parse($context));
      }
    } catch (SassReturn $e) {
      return $e->value;
    }

    return new SassBoolean('false');
  }
}
