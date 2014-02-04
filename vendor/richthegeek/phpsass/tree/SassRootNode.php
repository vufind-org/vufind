<?php
/* SVN FILE: $Id$ */
/**
 * SassRootNode class file.
 * @author      Chris Yates <chris.l.yates@gmail.com>
 * @copyright   Copyright (c) 2010 PBM Web Development
 * @license      http://phamlp.googlecode.com/files/license.txt
 * @package      PHamlP
 * @subpackage  Sass.tree
 */

require_once(dirname(__FILE__).'/../script/SassScriptParser.php');
require_once(dirname(__FILE__).'/../renderers/SassRenderer.php');

/**
 * SassRootNode class.
 * Also the root node of a document.
 * @package      PHamlP
 * @subpackage  Sass.tree
 */
class SassRootNode extends SassNode
{
  /**
   * @var SassScriptParser SassScript parser
   */
  public $script;
  /**
   * @var SassRenderer the renderer for this node
   */
  public $renderer;
  /**
   * @var SassParser
   */
  public $parser;
  /**
   * @var array extenders for this tree in the form extendee=>extender
   */
  public $extenders = array();

  /**
   * Extend_parent - for resolving extends across imported files.
   */
  public $extend_parent = null;

  /**
   * Root SassNode constructor.
   * @param SassParser Sass parser
   * @return SassNode
   */
  public function __construct($parser)
  {
    parent::__construct((object) array(
      'source' => '',
      'level' => -1,
      'filename' => $parser->filename,
      'line' => 0,
    ));
    $this->parser = $parser;
    $this->script = new SassScriptParser();
    $this->renderer = SassRenderer::getRenderer($parser->style);
    $this->root = $this;
  }

  /**
   * Parses this node and its children into the render tree.
   * Dynamic nodes are evaluated, files imported, etc.
   * Only static nodes for rendering are in the resulting tree.
   * @param SassContext the context in which this node is parsed
   * @return SassNode root node of the render tree
   */
  public function parse($context)
  {
    $node = clone $this;
    $node->children = $this->parseChildren($context);

    return $node;
  }

  /**
   * Render this node.
   * @return string the rendered node
   */
  public function render($context = null)
  {
    $context = new SassContext($context);
    $node = $this->parse($context);
    $output = '';
    foreach ($node->children as $child) {
      $output .= $child->render();
    } // foreach

    return $output;
  }

  public function extend($extendee, $selectors)
  {
    if ($this->extend_parent && method_exists($this->extend_parent, 'extend')) {
      return $this->extend_parent->extend($extendee, $selectors);
    }
    $this->extenders[$extendee] = (isset($this->extenders[$extendee])
      ? array_merge($this->extenders[$extendee], $selectors) : $selectors);
  }

  public function getExtenders()
  {
    if ($this->extend_parent && method_exists($this->extend_parent, 'getExtenders')) {
      return $this->extend_parent->getExtenders();
    }

    return $this->extenders;
  }

  /**
   * Returns a value indicating if the line represents this type of node.
   * Child classes must override this method.
   * @throws SassNodeException if not overriden
   */
  public static function isa($line)
  {
    throw new SassNodeException('Child classes must override this method');
  }
}
