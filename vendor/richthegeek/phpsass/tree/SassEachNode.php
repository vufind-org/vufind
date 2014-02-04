<?php
/* SVN FILE: $Id$ */
/**
 * SassEachNode class file.
 * The syntax is:
 * <pre>@each <var> in <list><pre>.
 *
 * <list> is comma+space separated
 * <var> is available to the rest of the script following evaluation
 * and has the value that terminated the loop.
 *
 * @author  Pavol (Lopo) Hluchy <lopo@losys.eu>
 * @copyright  Copyright (c) 2011 Lopo
 * @license  http://www.gnu.org/licenses/gpl.html GNU General Public License Version 3
 * @package  PHamlP
 * @subpackage  Sass.tree
 */

/**
 * SassEachNode class.
 * Represents a Sass @each loop.
 * @package  PHamlP
 * @subpackage  Sass.tree
 */
class SassEachNode extends SassNode
{
  const MATCH = '/@each\s+[!\$](.+?)in\s+(.+)$/i';

  const VARIABLE = 1;
  const IN = 2;

  /**
   * @var string variable name for the loop
   */
  private $variable;
  /**
   * @var string expression that provides the loop values
   */
  private $in;

  /**
   * SassEachNode constructor.
   * @param object source token
   * @return SassEachNode
   */
  public function __construct($token)
  {
    parent::__construct($token);
    if (!preg_match(self::MATCH, $token->source, $matches)) {
      throw new SassEachNodeException('Invalid @each directive', $this);
    } else {
      $this->variable = trim($matches[self::VARIABLE]);
      $this->in = $matches[self::IN];
    }
  }

  /**
   * Parse this node.
   * @param SassContext the context in which this node is parsed
   * @return array parsed child nodes
   */
  public function parse($context)
  {
    $children = array();

    if ($this->variable && $this->in) {
      $context = new SassContext($context);

      list($in, $sep) = SassList::_parse_list($this->in, 'auto', true, $context);
      foreach ($in as $var) {
        $context->setVariable($this->variable, $var);
        $children = array_merge($children, $this->parseChildren($context));
      }
    }
    $context->merge();

    return $children;
  }
}
