<?php
/* SVN FILE: $Id$ */
/**
 * SassContext class file.
 * @author      Chris Yates <chris.l.yates@gmail.com>
 * @copyright   Copyright (c) 2010 PBM Web Development
 * @license      http://phamlp.googlecode.com/files/license.txt
 * @package      PHamlP
 * @subpackage  Sass.tree
 */

/**
 * SassContext class.
 * Defines the context that the parser is operating in and so allows variables
 * to be scoped.
 * A new context is created for Mixins and imported files.
 * @package      PHamlP
 * @subpackage  Sass.tree
 */
class SassContext
{
  /**
   * @var SassContext enclosing context
   */
  public $parent;

  /**
   * @var array mixins defined in this context
   */
  public $mixins = array();

  /**
   * @var array mixins defined in this context
   */
  public $functions = array();

  /**
   * @var array variables defined in this context
   */
  public $variables = array();

  /**
   * @var tree representing any contextual content.
   */
  public $content = array();

  /**
   * @var SassNode the node being processed
   */
  public $node;

  /**
   * SassContext constructor.
   * @param SassContext - the enclosing context
   * @return SassContext
   */
  public function __construct($parent = null)
  {
    $this->parent = $parent;
  }

  /**
   *
   */
  public function getContent()
  {
    if ($this->content) {
      return $this->content;
    }
    if (!empty($this->parent)) {
      return $this->parent->getContent();
    }
    throw new SassContextException('@content requested but no content passed', $this->node);
  }

  /**
   * Adds a mixin
   * @param string name of mixin
   * @return SassMixinDefinitionNode the mixin
   */
  public function addMixin($name, $mixin)
  {
    $this->mixins[$name] = $mixin;

    return $this;
  }

  /**
   * Returns a mixin
   * @param string name of mixin to return
   * @return SassMixinDefinitionNode the mixin
   * @throws SassContextException if mixin not defined in this context
   */
  public function getMixin($name)
  {
    if (isset($this->mixins[$name])) {
      return $this->mixins[$name];
    } elseif (!empty($this->parent)) {
      return $this->parent->getMixin($name);
    }
    throw new SassContextException('Undefined Mixin: ' . $name, $this->node);
  }

  /**
   * Adds a function
   * @param string name of function
   * @return SassFunctionDefinitionNode the function
   */
  public function addFunction($name, $function)
  {
    $this->functions[$name] = $function;
    if (!empty($this->parent)) {
      $this->parent->addFunction($name);
    }

    return $this;
  }

  /**
   * Returns a function
   * @param string name of function to return
   * @return SassFunctionDefinitionNode the mixin
   * @throws SassContextException if function not defined in this context
   */
  public function getFunction($name)
  {
    if ($fn = $this->hasFunction($name)) {
      return $fn;
    }
    throw new SassContextException('Undefined Function: ' . $name, $this->node);
  }

  /**
   * Returns a boolean wether this function exists
   * @param string name of function to check for
   * @return boolean
   */
  public function hasFunction($name)
  {
    if (isset($this->functions[$name])) {
      return $this->functions[$name];
    } elseif (!empty($this->parent)) {
      return $this->parent->hasFunction($name);
    }

    return FALSE;
  }

  /**
   * Returns a variable defined in this context
   * @param string name of variable to return
   * @return string the variable
   * @throws SassContextException if variable not defined in this context
   */
  public function getVariable($name)
  {
    $name = str_replace('-', '_', $name);
    if ($this->hasVariable($name)) {
      return $this->variables[$name];
    } elseif (!empty($this->parent)) {
      return $this->parent->getVariable($name);
    } else {
      // Return false instead of throwing an exception.
      // throw new SassContextException('Undefined Variable: ' . $name, $this->node);
      return new SassBoolean('false');
    }
  }

  /**
   * Returns a value indicating if the variable exists in this context
   * @param string name of variable to test
   * @return boolean true if the variable exists in this context, false if not
   */
  public function hasVariable($name)
  {
    $name = str_replace('-', '_', $name);

    return isset($this->variables[$name]);
  }

  /**
   * Sets a variable to the given value
   * @param string name of variable
   * @param sassLiteral value of variable
   */
  public function setVariable($name, $value)
  {
    $name = str_replace('-', '_', $name);
    $this->variables[$name] = $value;

    return $this;
  }

  public function setVariables($vars)
  {
    foreach ($vars as $key => $value) {
      if ($value !== NULL) {
        $this->setVariable($key, $value);
      }
    }
  }

  /**
   * Makes variables and mixins from this context available in the parent context.
   * Note that if there are variables or mixins with the same name in the two
   * contexts they will be set to that defined in this context.
   */
  public function merge()
  {
    $this->parent->variables = array_merge($this->parent->variables, $this->variables);
    $this->parent->mixins = array_merge($this->parent->mixins, $this->mixins);
  }
}
