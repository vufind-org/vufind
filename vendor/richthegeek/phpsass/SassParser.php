<?php

/* SVN FILE: $Id$ */
/**
 * SassParser class file.
 * See the {@link http://sass-lang.com/docs Sass documentation}
 * for details of Sass.
 *
 * Credits:
 * This is a port of Sass to PHP. All the genius comes from the people that
 * invented and develop Sass; in particular:
 * + {@link http://hamptoncatlin.com/ Hampton Catlin},
 * + {@link http://nex-3.com/ Nathan Weizenbaum},
 * + {@link http://chriseppstein.github.com/ Chris Eppstein}
 *
 * The bugs are mine. Please report any found at {@link http://code.google.com/p/phamlp/issues/list}
 *
 * @author      Chris Yates <chris.l.yates@gmail.com>
 * @copyright   Copyright (c) 2010 PBM Web Development
 * @license      http://phamlp.googlecode.com/files/license.txt
 * @package      PHamlP
 * @subpackage  Sass
 */

require_once 'SassFile.php';
require_once 'SassException.php';
require_once 'tree/SassNode.php';

/**
 * SassParser class.
 * Parses {@link http://sass-lang.com/ .sass and .sccs} files.
 * @package      PHamlP
 * @subpackage  Sass
 */
class SassParser
{
  /**#@+
   * Default option values
   */
  const BEGIN_COMMENT      = '/';
  const BEGIN_CSS_COMMENT  = '/*';
  const END_CSS_COMMENT    = '*/';
  const BEGIN_SASS_COMMENT = '//';
  const BEGIN_INTERPOLATION = '#';
  const BEGIN_INTERPOLATION_BLOCK = '#{';
  const BEGIN_BLOCK        = '{';
  const END_BLOCK          = '}';
  const END_STATEMENT      = ';';
  const DOUBLE_QUOTE       = '"';
  const SINGLE_QUOTE       = "'";

  /**
   * Static holder for last instance of a SassParser
   */
  public static $instance;

  /**
   * @var string the character used for indenting
   * @see indentChars
   * @see indentSpaces
   */
  public $indentChar;
  /**
   * @var array allowable characters for indenting
   */
  public $indentChars = array(' ', "\t");
  /**
   * @var integer number of spaces for indentation.
   * Used to calculate {@link Level} if {@link indentChar} is space.
   */
  public $indentSpaces = 2;

  /**
   * @var string source
   */
  public $source;

  /**#@+
   * Option
   */

  public $basepath;

  /**
   * debug_info:
   * @var boolean When true the line number and file where a selector is defined
   * is emitted into the compiled CSS in a format that can be understood by the
   * {@link https://addons.mozilla.org/en-US/firefox/addon/103988/
   * FireSass Firebug extension}.
   * Disabled when using the compressed output style.
   *
   * Defaults to false.
   * @see style
   */
  public $debug_info;

  /**
   * filename:
   * @var string The filename of the file being rendered.
   * This is used solely for reporting errors.
   */
  public $filename;

  /**
   * function:
   * @var An array of (function_name => callback) items.
   */
  public static $functions;

  /**
   * line:
   * @var integer The number of the first line of the Sass template. Used for
   * reporting line numbers for errors. This is useful to set if the Sass
   * template is embedded.
   *
   * Defaults to 1.
   */
  public $line;

  /**
   * line_numbers:
   * @var boolean When true the line number and filename where a selector is
   * defined is emitted into the compiled CSS as a comment. Useful for debugging
   * especially when using imports and mixins.
   * Disabled when using the compressed output style or the debug_info option.
   *
   * Defaults to false.
   * @see debug_info
   * @see style
   */
   public $line_numbers;

  /**
   * load_paths:
   * @var array An array of filesystem paths which should be searched for
   * Sass templates imported with the @import directive.
   *
   * Defaults to './sass-templates'.
   */
  public $load_paths;
  public $load_path_functions;

  /**
   * property_syntax:
   * @var string Forces the document to use one syntax for
   * properties. If the correct syntax isn't used, an error is thrown.
   * Value can be:
   * + new - forces the use of a colon or equals sign after the property name.
   * For example   color: #0f3 or width: $main_width.
   * + old -  forces the use of a colon before the property name.
   * For example: :color #0f3 or :width = $main_width.
   *
   * By default, either syntax is valid.
   *
   * Ignored for SCSS files which alaways use the new style.
   */
  public $property_syntax;

  /**
   * quiet:
   * @var boolean When set to true, causes warnings to be disabled.
   * Defaults to false.
   */
  public $quiet;

  /**
   * callbacks:
   * @var array listing callbacks for @warn and @debug directives.
   * Callbacks are executed by call_user_func and thus must conform
   * to that standard.
   */
  public $callbacks;

  /**
   * style:
   * @var string the style of the CSS output.
   * Value can be:
   * + nested - Nested is the default Sass style, because it reflects the
   * structure of the document in much the same way Sass does. Each selector
   * and rule has its own line with indentation is based on how deeply the rule
   * is nested. Nested style is very useful when looking at large CSS files as
   * it allows you to very easily grasp the structure of the file without
   * actually reading anything.
   * + expanded - Expanded is the typical human-made CSS style, with each selector
   * and property taking up one line. Selectors are not indented; properties are
   * indented within the rules.
   * + compact - Each CSS rule takes up only one line, with every property defined
   * on that line. Nested rules are placed with each other while groups of rules
   * are separated by a blank line.
   * + compressed - Compressed has no whitespace except that necessary to separate
   * selectors and properties. It's not meant to be human-readable.
   *
   * Defaults to 'nested'.
   */
  public $style;

  /**
   * syntax:
   * @var string The syntax of the input file.
   * 'sass' for the indented syntax and 'scss' for the CSS-extension syntax.
   *
   * This is set automatically when parsing a file, else defaults to 'sass'.
   */
  public $syntax;

  private $_tokenLevel = 0;

  /**
   * debug:
   * If enabled it causes exceptions to be thrown on errors. This can be
   * useful for tracking down a bug in your sourcefile but will cause a
   * site to break if used in production unless the parser in wrapped in
   * a try/catch structure.
   *
   * Defaults to FALSE
   */
  public $debug = FALSE;

  /**
   * Constructor.
   * Sets parser options
   * @param array $options
   * @return SassParser
   */
  public function __construct($options = array())
  {
    if (!is_array($options)) {
      if (isset($options['debug']) && $options['debug']) {
        throw new SassException('Options must be an array');
      }
      $options = count((array) $options) ? (array) $options : array();
    }
    unset($options['language']);

    $basepath = $_SERVER['PHP_SELF'];
    $basepath = substr($basepath, 0, strrpos($basepath, '/') + 1);

    $defaultOptions = array(
      'basepath' => $basepath,
      'debug_info' => FALSE,
      'filename' => array('dirname' => '', 'basename' => ''),
      'functions' => array(),
      'load_paths' => array(),
      'load_path_functions' => array(),
      'line' => 1,
      'line_numbers' => FALSE,
      'style' => SassRenderer::STYLE_NESTED,
      'syntax' => SassFile::SASS,
      'debug' => FALSE,
      'quiet' => FALSE,
      'callbacks' => array(
        'warn' => FALSE,
        'debug' => FALSE,
      ),
    );

    if (isset(self::$instance)) {
      $defaultOptions['load_paths'] = self::$instance->load_paths;
    }

    $options = array_merge($defaultOptions, $options);

    // We don't want to allow setting of internal only property syntax value
    if (isset($options["property_syntax"]) && $options["property_syntax"] == "scss") {
        unset($options["property_syntax"]);
    }

    self::$instance = $this;
    self::$functions = $options['functions'];
    unset($options['functions']);

    foreach ($options as $name=>$value) {
      $this->$name = $value;
    }

    if (!$this->property_syntax && $this->syntax == SassFile::SCSS) {
        $this->property_syntax = "scss";
    }

    $GLOBALS['SassParser_debug'] = $this->debug;
  }

  /**
   * Getter.
   * @param string name of property to get
   * @return mixed return value of getter function
   */
  public function __get($name)
  {
    $getter = 'get' . ucfirst($name);
    if (method_exists($this, $getter)) {
      return $this->$getter();
    }
    if (property_exists($this, $name)) {
      return $this->$name;
    }
    if ($this->debug) {
      throw new SassException('No getter function for ' . $name);
    }
  }

  public function getBasepath()
  {
    return $this->basepath;
  }

  public function getDebug_info()
  {
    return $this->debug_info;
  }

  public function getFilename()
  {
    return $this->filename;
  }

  public function getLine()
  {
    return $this->line;
  }

  public function getSource()
  {
    return $this->source;
  }

  public function getLine_numbers()
  {
    return $this->line_numbers;
  }

  public function getFunctions()
  {
    return self::$functions;
  }

  public function getLoad_paths()
  {
    return $this->load_paths;
  }

  public function getLoad_path_functions()
  {
    return $this->load_path_functions;
  }

  public function getProperty_syntax()
  {
    return $this->property_syntax;
  }

  public function getQuiet()
  {
    return $this->quiet;
  }

  public function getStyle()
  {
    return $this->style;
  }

  public function getSyntax()
  {
    return $this->syntax;
  }

  public function getDebug()
  {
    return $this->debug;
  }

  public function getCallbacks()
  {
    return $this->callbacks + array(
      'warn' => NULL,
      'debug' => NULL,
    );
  }

  public function getOptions()
  {
    return array(
      'callbacks' => $this->callbacks,
      // 'debug' => $this->debug,
      'filename' => $this->filename,
      'functions' => $this->functions,
      'line' => $this->line,
      'line_numbers' => $this->line_numbers,
      'load_path_functions' => $this->load_path_functions,
      'load_paths' => $this->load_paths,
      'property_syntax' => ($this->property_syntax == "scss" ? null : $this->property_syntax),
      'quiet' => $this->quiet,
      'style' => $this->style,
      'syntax' => $this->syntax,
    );
  }

  /**
   * Parse a sass file or Sass source code and returns the CSS.
   * @param string name of source file or Sass source
   * @return string CSS
   */
  public function toCss($source, $isFile = true)
  {
    return $this->parse($source, $isFile)->render();
  }

  /**
   * Parse a sass file or Sass source code and
   * returns the document tree that can then be rendered.
   * The file will be searched for in the directories specified by the
   * load_paths option.
   * @param string name of source file or Sass source
   * @return SassRootNode Root node of document tree
   */
  public function parse($source, $isFile = true)
  {
    # Richard Lyon - 2011-10-25 - ignore unfound files
    # Richard Lyon - 2011-10-25 - add multiple files to load functions
    if (!$source) {
      return $this->toTree($source);
    }

    if (is_array($source)) {
      $return = null;
      foreach ($source as $key => $value) {
          if (is_numeric($key)) {
              $code = $value;
              $type = true;
          } else {
              $code = $key;
              $type = $value;
          }
          if ($return===null) {
            $return = $this->parse($code, $type);
          } else {
            $newNode = $this->parse($code, $type);
              foreach ($newNode->children as $children) {
                array_push($return->children, $children);
              }
          }
      }

      return $return;
    }

    if ($isFile && $files = SassFile::get_file($source, $this)) {
      $files_source = '';
      foreach ($files as $file) {
        $this->filename = $file;
        $this->syntax = substr(strrchr($file, '.'), 1);
        if ($this->syntax == SassFile::CSS) {
            $this->property_syntax = "css";
        } elseif (!$this->property_syntax && $this->syntax == SassFile::SCSS) {
            $this->property_syntax = "scss";
        }

        if ($this->syntax !== SassFile::SASS && $this->syntax !== SassFile::SCSS && $this->syntax !== SassFile::CSS) {
          if ($this->debug) {
            throw new SassException('Invalid {what}', array('{what}' => 'syntax option'));
          }

          return FALSE;
        }
        $files_source .= SassFile::get_file_contents($this->filename, $this);
      }

      return $this->toTree($files_source);
    } else {
      return $this->toTree($source);
    }
  }

  /**
   * Parse Sass source into a document tree.
   * If the tree is already created return that.
   * @param string Sass source
   * @return SassRootNode the root of this document tree
   */
  public function toTree($source)
  {
    if ($this->syntax === SassFile::SASS) {
      $source = str_replace(array("\r\n", "\n\r", "\r"), "\n", $source);
      $this->source = explode("\n", $source);
      $this->setIndentChar();
    } else {
      $this->source = $source;
    }
    unset($source);
    $root = new SassRootNode($this);
    $this->buildTree($root);

    if ($this->_tokenLevel != 0 && $this->debug) {
        if ($this->_tokenLevel < 0) {
            $message = 'Too many closing brackets';
        } else {
            $message = 'One or more missing closing brackets';
        }
        throw new SassException($message, $this);
    }

    return $root;
  }

  /**
   * Builds a parse tree under the parent node.
   * Called recursivly until the source is parsed.
   * @param SassNode the node
   */
  public function buildTree($parent)
  {
    $node = $this->getNode($parent);
    while (is_object($node) && $node->isChildOf($parent)) {
      $parent->addChild($node);
      $node = $this->buildTree($node);
    }

    return $node;
  }

  /**
   * Creates and returns the next SassNode.
   * The tpye of SassNode depends on the content of the SassToken.
   * @return SassNode a SassNode of the appropriate type. Null when no more
   * source to parse.
   */
  public function getNode($node)
  {
    $token = $this->getToken();
    if (empty($token)) return null;
    switch (true) {
      case SassDirectiveNode::isa($token):
        return $this->parseDirective($token, $node);
      case SassCommentNode::isa($token):
        return new SassCommentNode($token);
      case SassVariableNode::isa($token):
        return new SassVariableNode($token);
      case SassPropertyNode::isa(array('token' => $token, 'syntax' => $this->property_syntax)):
        return new SassPropertyNode($token, $this->property_syntax);
      case SassFunctionDefinitionNode::isa($token):
        return new SassFunctionDefinitionNode($token);
      case SassMixinDefinitionNode::isa($token):
        if ($this->syntax === SassFile::SCSS) {
          if ($this->debug) {
            throw new SassException('Mixin definition shortcut not allowed in SCSS', $this);
          }

          return;
        } else {
          return new SassMixinDefinitionNode($token);
        }
      case SassMixinNode::isa($token):
        if ($this->syntax === SassFile::SCSS) {
          if ($this->debug) {
            throw new SassException('Mixin include shortcut not allowed in SCSS', $this);
          }

          return;
        } else {
          return new SassMixinNode($token);
        }
      default:
        return new SassRuleNode($token);
        break;
    } // switch
  }

  /**
   * Returns a token object that contains the next source statement and
   * meta data about it.
   * @return object
   */
  public function getToken()
  {
    return ($this->syntax === SassFile::SASS ? $this->sass2Token() : $this->scss2Token());
  }

  /**
   * Returns an object that contains the next source statement and meta data
   * about it from SASS source.
   * Sass statements are passed over. Statements spanning multiple lines, e.g.
   * CSS comments and selectors, are assembled into a single statement.
   * @return object Statement token. Null if end of source.
   */
  public function sass2Token()
  {
    $statement = ''; // source line being tokenised
    $token = null;

    while (is_null($token) && !empty($this->source)) {
      while (empty($statement) && is_array($this->source) && !empty($this->source)) {
        $source = array_shift($this->source);
        $statement = trim($source);
        $this->line++;
      }

      if (empty($statement)) {
        break;
      }

      $level = $this->getLevel($source);

      // Comment statements can span multiple lines
      if ($statement[0] === self::BEGIN_COMMENT) {
        // Consume Sass comments
        if (substr($statement, 0, strlen(self::BEGIN_SASS_COMMENT)) === self::BEGIN_SASS_COMMENT) {
          unset($statement);
          while ($this->getLevel($this->source[0]) > $level) {
            array_shift($this->source);
            $this->line++;
          }
          continue;
        }
        // Build CSS comments
        elseif (substr($statement, 0, strlen(self::BEGIN_CSS_COMMENT))
            === self::BEGIN_CSS_COMMENT) {
          while ($this->getLevel($this->source[0]) > $level) {
            $statement .= "\n" . ltrim(array_shift($this->source));
            $this->line++;
          }
        } else {
          $this->source = $statement;

          if ($this->debug) {
            throw new SassException('Illegal comment type', $this);
          }
        }
      }
      // Selector statements can span multiple lines
      elseif (substr($statement, -1) === SassRuleNode::CONTINUED) {
        // Build the selector statement
        while ($this->getLevel($this->source[0]) === $level) {
          $statement .= ltrim(array_shift($this->source));
          $this->line++;
        }
      }

      $token = (object) array(
        'source' => $statement,
        'level' => $level,
        'filename' => $this->filename,
        'line' => $this->line - 1,
      );
    }

    return $token;
  }

  /**
   * Returns the level of the line.
   * Used for .sass source
   * @param string the source
   * @return integer the level of the source
   * @throws Exception if the source indentation is invalid
   */
  public function getLevel($source)
  {
    $indent = strlen($source) - strlen(ltrim($source));
    $level = $indent/$this->indentSpaces;
    if (is_float($level)) {
      $level = (int) ceil($level);
    }
    if (!is_int($level) || preg_match("/[^{$this->indentChar}]/", substr($source, 0, $indent))) {
      $this->source = $source;

      if ($this->debug) {
        throw new SassException('Invalid indentation', $this);
      } else {
        return 0;
      }
    }

    return $level;
  }

  /**
   * Returns an object that contains the next source statement and meta data
   * about it from SCSS source.
   * @return object Statement token. Null if end of source.
   */
  public function scss2Token()
  {
    static $srcpos = 0; // current position in the source stream
    static $srclen; // the length of the source stream

    $statement = '';
    $token = null;
    if (empty($srclen)) {
      $srclen = strlen($this->source);
    }
    while (is_null($token) && $srcpos < strlen($this->source)) {
      $c = $this->source[$srcpos++];
      switch ($c) {
        case self::BEGIN_COMMENT:
          if (substr($this->source, $srcpos-1, strlen(self::BEGIN_SASS_COMMENT)) === self::BEGIN_SASS_COMMENT) {
            while ($this->source[$srcpos++] !== "\n") {
              if ($srcpos >= $srclen)
                throw new SassException('Unterminated commend', (object) array(
                  'source' => $statement,
                  'filename' => $this->filename,
                  'line' => $this->line,
                ));
            }
            $statement .= "\n";
          } elseif (substr($this->source, $srcpos-1, strlen(self::BEGIN_CSS_COMMENT)) === self::BEGIN_CSS_COMMENT) {
            if (ltrim($statement)) {
              if ($this->debug) {
                throw new SassException('Invalid comment', (object) array(
                  'source' => $statement,
                  'filename' => $this->filename,
                  'line' => $this->line,
                ));
              }
            }
            $statement .= $c.$this->source[$srcpos++];
            while (substr($this->source, $srcpos, strlen(self::END_CSS_COMMENT)) !== self::END_CSS_COMMENT) {
              $statement .= $this->source[$srcpos++];
            }
            $srcpos += strlen(self::END_CSS_COMMENT);
            $token = $this->createToken($statement.self::END_CSS_COMMENT);
          } else {
            $statement .= $c;
          }
          break;
        case self::DOUBLE_QUOTE:
        case self::SINGLE_QUOTE:
          $statement .= $c;
          while (isset($this->source[$srcpos]) && $this->source[$srcpos] !== $c) {
            $statement .= $this->source[$srcpos++];
          }
          if (isset($this->source[$srcpos+1])) {
            $statement .= $this->source[$srcpos++];
          }
          break;
        case self::BEGIN_INTERPOLATION:
          $statement .= $c;
          if (substr($this->source, $srcpos-1, strlen(self::BEGIN_INTERPOLATION_BLOCK)) === self::BEGIN_INTERPOLATION_BLOCK) {
            while ($this->source[$srcpos] !== self::END_BLOCK) {
              $statement .= $this->source[$srcpos++];
            }
            $statement .= $this->source[$srcpos++];
          }
          break;
        case self::BEGIN_BLOCK:
        case self::END_BLOCK:
        case self::END_STATEMENT:
          $token = $this->createToken($statement . $c);
          if (is_null($token)) {
            $statement = '';
          }
          break;
        default:
          $statement .= $c;
          break;
      }
    }

    if (is_null($token)) {
      $srclen = $srcpos = 0;
    }

    return $token;
  }

  /**
   * Returns an object that contains the source statement and meta data about
   * it.
   * If the statement is just and end block we update the meta data and return null.
   * @param string source statement
   * @return SassToken
   */
  public function createToken($statement) {
    $this->line += substr_count($statement, "\n");
    $statement = trim($statement);
    if (substr($statement, 0, strlen(self::BEGIN_CSS_COMMENT)) !== self::BEGIN_CSS_COMMENT) {
      $statement = str_replace(array("\n","\r"), '', $statement);
    }
    $last = substr($statement, -1);
    // Trim the statement removing whitespace, end statement (;), begin block ({), and (unless the statement ends in an interpolation block) end block (})
    $statement = rtrim($statement, ' '.self::BEGIN_BLOCK.self::END_STATEMENT);
    $statement = (preg_match('/#\{.+?\}$/i', $statement) ? $statement : rtrim($statement, self::END_BLOCK));
    $token = ($statement ? (object) array(
      'source' => $statement,
      'level' => $this->_tokenLevel,
      'filename' => $this->filename,
      'line' => $this->line,
    ) : null);
    $this->_tokenLevel += ($last === self::BEGIN_BLOCK ? 1 : ($last === self::END_BLOCK ? -1 : 0));
    return $token;
  }

  /**
   * Parses a directive
   * @param SassToken token to parse
   * @param SassNode parent node
   * @return SassNode a Sass directive node
   */
  public function parseDirective($token, $parent)
  {
    switch (SassDirectiveNode::extractDirective($token)) {
      case '@content':
        return new SassContentNode($token);
        break;
      case '@extend':
        return new SassExtendNode($token);
        break;
      case '@function':
        return new SassFunctionDefinitionNode($token);
        break;
      case '@return':
        return new SassReturnNode($token);
        break;
      case '@media':
        return new SassMediaNode($token);
        break;
      case '@mixin':
        return new SassMixinDefinitionNode($token);
        break;
      case '@include':
        return new SassMixinNode($token);
        break;
      case '@import':
        if ($this->syntax == SassFile::SASS) {
          $i = 0;
          $source = '';
          while (sizeof($this->source) > $i && empty($source) && isset($this->source[$i + 1])) {
            $source = $this->source[$i++];
          }
          if (!empty($source) && $this->getLevel($source) > $token->level) {
            if ($this->debug) {
              throw new SassException('Nesting not allowed beneath @import directive', $token);
            }
          }
        }

        return new SassImportNode($token, $parent);
        break;
      case '@each':
        return new SassEachNode($token);
        break;
      case '@for':
        return new SassForNode($token);
        break;
      case '@if':
        return new SassIfNode($token);
        break;
      case '@else': // handles else and else if directives

        return new SassElseNode($token);
        break;
      case '@do':
      case '@while':
        return new SassWhileNode($token);
        break;
      case '@warn':
        return new SassWarnNode($token);
        break;
      case '@debug':
        return new SassDebugNode($token);
        break;
      default:
        return new SassDirectiveNode($token);
        break;
    }
  }

  /**
   * Determine the indent character and indent spaces.
   * The first character of the first indented line determines the character.
   * If this is a space the number of spaces determines the indentSpaces; this
   * is always 1 if the indent character is a tab.
   * Only used for .sass files.
   * @throws SassException if the indent is mixed or
   * the indent character can not be determined
   */
  public function setIndentChar()
  {
    foreach ($this->source as $l=>$source) {
      if (!empty($source) && in_array($source[0], $this->indentChars)) {
        $this->indentChar = $source[0];
        for  ($i = 0, $len = strlen($source); $i < $len && $source[$i] == $this->indentChar; $i++);
        if ($i < $len && in_array($source[$i], $this->indentChars)) {
          $this->line = ++$l;
          $this->source = $source;
          if ($this->debug) {
            throw new SassException('Mixed indentation not allowed', $this);
          }
        }
        $this->indentSpaces = ($this->indentChar == ' ' ? $i : 1);

        return;
      }
    } // foreach
    $this->indentChar = ' ';
    $this->indentSpaces = 2;
  }
}
