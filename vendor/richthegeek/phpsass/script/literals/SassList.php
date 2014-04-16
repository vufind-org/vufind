<?php
/* SVN FILE: $Id$ */
/**
 * SassBoolean class file.
 * @author      Chris Yates <chris.l.yates@gmail.com>
 * @copyright   Copyright (c) 2010 PBM Web Development
 * @license      http://phamlp.googlecode.com/files/license.txt
 * @package      PHamlP
 * @subpackage  Sass.script.literals
 */

require_once 'SassLiteral.php';

/**
 * SassBoolean class.
 * @package      PHamlP
 * @subpackage  Sass.script.literals
 */
class SassList extends SassLiteral
{
  public $separator = ' ';

  /**
   * SassBoolean constructor
   * @param string value of the boolean type
   * @return SassBoolean
   */
  public function __construct($value, $separator = 'auto')
  {
    if (is_array($value)) {
      $this->value = $value;
      $this->separator = ($separator == 'auto' ? ', ' : $separator);
    } elseif ($value == '()') {
      $this->value = array();
      $this->separator = ($separator == 'auto' ? ', ' : $separator);
    } elseif (list($list, $separator) = $this->_parse_list($value, $separator, true, SassScriptParser::$context)) {
      $this->value = $list;
      $this->separator = ($separator == ',' ? ', ' : ' ');
    } else {
      throw new SassListException('Invalid SassList', SassScriptParser::$context->node);
    }
  }

  public function nth($i)
  {
    $i = $i - 1; # SASS uses 1-offset arrays
    if (isset($this->value[$i])) {
      return $this->value[$i];
    }

    return new SassBoolean(false);
  }

  public function length()
  {
    return count($this->value);
  }

  public function append($other, $separator = null)
  {
    if ($separator) {
      $this->separator = $separator;
    }
    if ($other instanceof SassList) {
      $this->value = array_merge($this->value, $other->value);
    } elseif ($other instanceof SassLiteral) {
      $this->value[] = $other;
    } else {
      throw new SassListException('Appendation can only occur with literals', SassScriptParser::$context->node);
    }
  }

  // New function index returns the list index of a value within a list. For example: index(1px solid red, solid) returns 2. When the value is not found false is returned.
  public function index($value)
  {
    for ($i = 0; $i < count($this->value); $i++) {
      if (trim((string) $value) == trim((string) $this->value[$i])) {
        return new SassNumber($i);
      }
    }

    return new SassBoolean(false);
  }

  /**
   * Returns the value of this boolean.
   * @return boolean the value of this boolean
   */
  public function getValue()
  {
    $result = array();
    foreach ($this->value as $k => $v) {
      if ($v instanceOf SassString) {
        $list = $this->_parse_list($v);
        if (count($list[0]) > 1) {
          if ($list[1] == $this->separator) {
            $result = array_merge($result, $list[0]);
          } else {
            $result[] = $v;
          }
        } else {
          $result[] = $v;
        }
      } else {
        $result[] = $v;
      }
    }
    $this->value = $result;

    return $this->value;
  }

  /**
   * Returns a string representation of the value.
   * @return string string representation of the value.
   */
  public function toString()
  {
    $aliases = array(
      'comma' => ',',
      'space' => '',
    );
    $this->separator = trim($this->separator);
    if (isset($aliases[$this->separator])) {
      $this->separator = $aliases[$this->separator];
    }

    return implode($this->separator . ' ', $this->getValue());
  }

  /**
   * Returns a value indicating if a token of this type can be matched at
   * the start of the subject string.
   * @param string the subject string
   * @return mixed match at the start of the string or false if no match
   */
  public static function isa($subject)
  {
    list($list, $separator) = self::_parse_list($subject, 'auto', false);

    return count($list) > 1 ? $subject : FALSE;
  }

  public static function _parse_list($list, $separator = 'auto', $lex = true, $context = null)
  {
    if ($lex) {
      $context = new SassContext($context);
      $list = SassScriptParser::$instance->evaluate($list, $context);
      $list = $list->toString();
    }
    if ($separator == 'auto') {
      $separator = ',';
      $list = $list = self::_build_list($list, ',');
      if (count($list) < 2) {
        $separator = ' ';
        $list = self::_build_list($list, ' ');
      }
    } else {
      $list = self::_build_list($list, $separator);
    }

    if ($lex) {
      $context = new SassContext($context);
      foreach ($list as $k => $v) {
        $list[$k] = SassScriptParser::$instance->evaluate($v, $context);
      }
    }

    return array($list, $separator);
  }

  public static function _build_list($list, $separator = ',')
  {
    if (is_object($list)) {
      $list = $list->value;
    }

    if (is_array($list)) {
      $newlist = array();
      foreach ($list as $listlet) {
        list($newlist, $separator) = array_merge($newlist, self::_parse_list($listlet, $separator, false));
      }
      $list = implode(', ', $newlist);
    }

    $out = array();
    $size = 0;
    $braces = 0;
    $quotes = false;
    $stack = '';
    for ($i = 0; $i < strlen($list); $i++) {
      $char = substr($list, $i, 1);
      switch ($char) {
        case '"':
        case "'":
          if (!$quotes) {
            $quotes = $char;
          } elseif ($quotes && $quotes == $char) {
            $quotes = false;
          }
          $stack .= $char;
          break;
        case '(':
          $braces++;
          $stack .= $char;
          break;
        case ')':
          $braces--;
          $stack .= $char;
          break;
        case $separator:
          if ($braces === 0 && !$quotes) {
            $out[] = $stack;
            $stack = '';
            $size++;
            break;
          }
        default:
          $stack .= $char;
      }
    }
    if (strlen($stack)) {
      if (($braces || $quotes) && count($out)) {
        $out[count($out) - 1] .= $stack;
      } else {
        $out[] = $stack;
      }
    }

    foreach ($out as $k => $v) {
      $v = trim($v, ', ');
      $out[$k] = $v;
    }

    return $out;
  }
}
