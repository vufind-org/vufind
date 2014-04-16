<?php
require_once dirname(__FILE__) . '/../ExtensionInterface.php';
class Own implements ExtensionInterface
{

    public static $filesFolder = 'css';
    public static $filePaths = null;

    /**
     * List with alias functions in Compass
     * @var array
     */
    public static $functions = array(
        'demo-function',
    );

    public static function getFunctions($namespace)
    {

        $output = array();
        foreach (self::$functions as $function) {
            $originalFunction = $function;
            $function[0] = strtoupper($function[0]);
            $func = create_function('$c', 'return strtoupper($c[1]);');
            $function = preg_replace_callback('/-([a-z])/', $func, $function);
            $output[$originalFunction] = $namespace . strtolower(__CLASS__) . $function;
        }

        return $output;
    }

    /**
     * Returns an array with all files in $root path recursively and assign each array Key with clean alias
     * @param $root
     * @return array
     */
    public static function getFilesArray($root)
    {

        $alias = array();
        $directories = array();
        $last_letter = $root[strlen($root) - 1];
        $root = ($last_letter == '\\' || $last_letter == '/') ? $root : $root . DIRECTORY_SEPARATOR;

        $directories[] = $root;

        while (sizeof($directories)) {
            $dir = array_pop($directories);
            if ($handle = opendir($dir)) {
                while (false !== ($file = readdir($handle))) {
                    if ($file == '.' || $file == '..') {
                        continue;
                    }
                    $file = $dir . $file;
                    if (is_dir($file)) {
                        $directory_path = $file . DIRECTORY_SEPARATOR;
                        array_push($directories, $directory_path);
                    } elseif (is_file($file)) {
                        $key = basename($file);
                        $alias[substr($key, 1, strpos($key, '.') - 1)] = $file;
                    }
                }
                closedir($handle);
            }
        }

        return $alias;
    }

    /**
     * Implementation of hook_resolve_path_NAMESPACE().
     */
    public static function resolveExtensionPath($callerImport, $parser, $syntax = 'scss')
    {
        $alias = str_replace('/_', '/', str_replace(array('.scss', '.sass'), '', $callerImport));
        if (strrpos($alias, '/') !== false) {
            $alias = substr($alias, strrpos($alias, '/') + 1);
        }
        if (self::$filePaths == null) {
            self::$filePaths = self::getFilesArray(dirname(__FILE__) . '/' . self::$filesFolder . '/');
        }
        if (isset(self::$filePaths[$alias])) {
            return self::$filePaths[$alias];
        }
    }

    public static function ownDemoFunction()
    {
        return new SassString("'This is my own Demo Function'");
    }
}
