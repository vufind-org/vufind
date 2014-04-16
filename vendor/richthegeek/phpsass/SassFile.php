<?php
/* SVN FILE: $Id$ */
/**
 * SassFile class file.
 * File handling utilites.
 * @author      Chris Yates <chris.l.yates@gmail.com>
 * @copyright   Copyright (c) 2010 PBM Web Development
 * @license      http://phamlp.googlecode.com/files/license.txt
 * @package      PHamlP
 * @subpackage  Sass
 */

/**
 * SassFile class.
 * @package      PHamlP
 * @subpackage  Sass
 */
class SassFile
{
  const CSS  = 'css';
  const SASS = 'sass';
  const SCSS = 'scss';
  // const SASSC = 'sassc'; # tests for E_NOTICE

  private static $extensions = array(self::SASS, self::SCSS);

  public static $path = FALSE;
  public static $parser = FALSE;

  /**
   * Returns the parse tree for a file.
   * @param string filename to parse
   * @param SassParser Sass parser
   * @return SassRootNode
   */
  public static function get_tree($filename, &$parser)
  {
    $contents = self::get_file_contents($filename, $parser);

    $options = array_merge($parser->options, array('line'=>1));

    # attempt at cross-syntax imports.
    $ext = substr($filename, strrpos($filename, '.') + 1);
    if ($ext == self::SASS || $ext == self::SCSS) {
      $options['syntax'] = $ext;
    }

    $dirname = dirname($filename);
    $options['load_paths'][] = $dirname;
    if (!in_array($dirname, $parser->load_paths)) {
      $parser->load_paths[] = dirname($filename);
    }

    $sassParser = new SassParser($options);
    $tree = $sassParser->parse($contents, FALSE);

    return $tree;
  }

  public static function get_file_contents($filename, $parser)
  {
    $contents = file_get_contents($filename) . "\n\n "; #add some whitespace to fix bug
    # strip // comments at this stage, with allowances for http:// style locations.
    $contents = preg_replace("/(^|\s)\/\/[^\n]+/", '', $contents);
    // SassFile::$parser = $parser;
    // SassFile::$path = $filename;
    // $contents = preg_replace_callback('/url\(\s*[\'"]?(?![a-z]+:|\/+)([^\'")]+)[\'"]?\s*\)/i', 'SassFile::resolve_paths', $contents);
    return $contents;
  }

  public static function resolve_paths($matches)
  {
    // Resolve the path into something nicer...
    return 'url("' . self::resolve_path($matches[1]) . '")';
  }

  public static function resolve_path($name)
  {
    $path = self::$parser->basepath . self::$path;
    $path = substr($path, 0, strrpos($path, '/')) . '/';
    $path = $path . $name;
    $last = '';
    while ($path != $last) {
      $last = $path;
      $path = preg_replace('`(^|/)(?!\.\./)([^/]+)/\.\./`', '$1', $path);
    }

    return $path;
  }

  /**
   * Returns the full path to a file to parse.
   * The file is looked for recursively under the load_paths directories
   * If the filename does not end in .sass or .scss try the current syntax first
   * then, if a file is not found, try the other syntax.
   * @param string filename to find
   * @param SassParser Sass parser
   * @return array of string path(s) to file(s) or FALSE if no such file
   */
  public static function get_file($filename, &$parser, $sass_only = TRUE)
  {
    $ext = substr($filename, strrpos($filename, '.') + 1);
    // if the last char isn't *, and it's not (.sass|.scss|.css)
    if ($sass_only && substr($filename, -1) != '*' && $ext !== self::SASS && $ext !== self::SCSS && $ext !== self::CSS) {
      $sass = self::get_file($filename . '.' . self::SASS, $parser);

      return $sass ? $sass : self::get_file($filename . '.' . self::SCSS, $parser);
    }
    if (file_exists($filename)) {
      return array($filename);
    }
    $paths = $parser->load_paths;
    if (is_string($parser->filename) && $path = dirname($parser->filename)) {
      $paths[] = $path;
      if (!in_array($path, $parser->load_paths)) {
        $parser->load_paths[] = $path;
      }
    }
    foreach ($paths as $path) {
      $filepath = self::find_file($filename, realpath($path));
      if ($filepath !== false) {
        if (!is_array($filepath)) {
          return array($filepath);
        }
        return $filepath;
      }
    }
    foreach ($parser->load_path_functions as $function) {
      if (is_callable($function) && $paths = call_user_func($function, $filename, $parser)) {
        return $paths;
      }
    }

    return FALSE;
  }

  /**
   * Looks for the file recursively in the specified directory.
   * This will also look for _filename to handle Sass partials.
   * @param string filename to look for
   * @param string path to directory to look in and under
   * @return mixed string: full path to file if found, false if not
   */
  public static function find_file($filename, $dir)
  {
    $partialname = str_replace(basename($filename), ('_'.basename($filename)), $filename);

    if (strstr($filename, DIRECTORY_SEPARATOR . '**')) {
      if (is_dir($dir . DIRECTORY_SEPARATOR . substr($filename, 0, strpos($filename, DIRECTORY_SEPARATOR . '**')))) {
        $paths = array();
        $files = scandir($dir . DIRECTORY_SEPARATOR . substr($filename, 0, strpos($filename, DIRECTORY_SEPARATOR . '**')));
        foreach ($files as $file) {
          if ($file === '..') continue;
          if (is_dir($dir . DIRECTORY_SEPARATOR . substr($filename, 0, strpos($filename, DIRECTORY_SEPARATOR . '**')) . DIRECTORY_SEPARATOR . $file)) {
            if ($file === '.') {
              $new_filename = str_replace(DIRECTORY_SEPARATOR . '**', '', $filename);
            }
            else {
              $new_filename = str_replace('**', $file, $filename);
            }
            $path = self::find_file($new_filename, $dir);
            if ($path !== false) {
              if (!is_array($path)) {
                $path = array($path);
              }
              $paths = array_merge($paths, $path);
            }
          }
        }
        return $paths;
      }
    }

    if (substr($filename, -2) == DIRECTORY_SEPARATOR . '*') {
      if (is_dir($dir . DIRECTORY_SEPARATOR . substr($filename, 0, strlen($filename) - 2))) {
        $dir = $dir . DIRECTORY_SEPARATOR . substr($filename, 0, strlen($filename) - 2);
        $paths = array();
        $files = scandir($dir);
        foreach ($files as $file) {
          if (($file === '.') || ($file === '..')) continue;
          $ext = substr($file, strrpos($file, '.') + 1);
          if (substr($file, -1) != '*' && ($ext == self::SASS || $ext == self::SCSS || $ext == self::CSS)) {
            $paths[] = $dir . DIRECTORY_SEPARATOR . $file;
          }
        }
        return $paths;
      }
    }

    foreach (array($filename, $partialname) as $file) {
      if (file_exists($dir . DIRECTORY_SEPARATOR . $file)) {
        return realpath($dir . DIRECTORY_SEPARATOR . $file);
      }
    }

    if (is_dir($dir)) {
      $files = scandir($dir);

      foreach ($files as $file) {
        if (($file === '.') || ($file === '..')) continue;
        if (substr($file, 0, 1) != '.' && is_dir($dir . DIRECTORY_SEPARATOR . $file)) {
          $path = self::find_file($filename, $dir . DIRECTORY_SEPARATOR . $file);
          if ($path !== false) {
            return $path;
          }
        }
      }
    }

    return false;
  }
}
