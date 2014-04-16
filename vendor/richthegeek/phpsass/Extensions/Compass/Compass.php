<?php
require_once dirname(__FILE__) . '/../ExtensionInterface.php';
class Compass implements ExtensionInterface
{

    public static $filesFolder = 'stylesheets';
    public static $filePaths = null;

    /**
     * List with alias functions in Compass
     * @var array
     */
    public static $functions = array(
        'resolve-path',
        'adjust-lightness',
        'scale-lightness',
        'adjust-saturation',
        'scale-saturation',
        'scale-color-value',
        'is-position',
        'is-position-list',
        'opposite-position',
        '-webkit',
        '-moz',
        '-o',
        '-ms',
        '-svg',
        '-pie',
        '-css2',
        'owg',
        'prefixed',
        'prefix',
        'elements-of-type',
        'enumerate',
        'font-files',
        'image-width',
        'image-height',
        'inline-image',
        'inline-font-files',
        'blank',
        'compact',
        '-compass-nth',
        '-compass-list',
        '-compass-list',
        '-compass-space-list',
        '-compass-list-size',
        '-compass-slice',
        'first-value-of',
        'nest',
        'append-selector',
        'headers',
        'pi',
        'sin',
        'cos',
        'tan',
        'comma-list',
        'prefixed-for-transition',
        'stylesheet-url',
        'font-url',
        'image-url'
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
                    if ($file == '.' || $file == '..' || substr($file,0,1)=='.') {
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

    /**
     * Resolves requires to the compass namespace (eg namespace/css3/border-radius)
     */
    public static function compassResolvePath($file)
    {
        if ($file{0} == '/') {
            return $file;
        }
        if (!$path = realpath($file)) {
            $path = SassScriptFunction::$context->node->token->filename;
            $path = substr($path, 0, strrpos($path, '/')) . '/';
            $path = $path . $file;
            $last = '';
            while ($path != $last) {
                $last = $path;
                $path = preg_replace('`(^|/)(?!\.\./)([^/]+)/\.\./`', '$1', $path);
            }
            $path = realpath($path);
        }
        if ($path) {
            return $path;
        }
        
        return false;
    }

    public static function compassImageWidth($file)
    {
        if ($info = self::compassImageInfo($file)) {
            return new SassNumber($info[0] . 'px');
        }

        return new SassNumber('0px');
    }

    public static function compassImageHeight($file)
    {
        if ($info = self::compassImageInfo($file)) {
            return new SassNumber($info[1] . 'px');
        }

        return new SassNumber('0px');
    }

    public static function compassImageInfo($file)
    {
        if ($path = self::compassResolvePath($file)) {
            if ($info = getimagesize($path)) {
                return $info;
            }
        }

        return false;
    }

    public static function compassInlineImage($file, $mime = null)
    {
        if ($path = self::compassUrl($file, true, false)) {
            $info = getimagesize($path);
            $mime = $info['mime'];
            $data = base64_encode(file_get_contents($path));
            # todo - do not return encoded if file size > 32kb

            return new SassString("url('data:$mime;base64,$data')");
        }

        return new SassString('');
    }

    public static function compassInlineFontFiles($file)
    {
        $args = func_get_args();
        $files = array();
        $mimes = array(
            'otf' => 'font.opentype',
            'ttf' => 'font.truetype',
            'woff' => 'font.woff',
            'off' => 'font.openfont',
        );

        while (count($args)) {
            $path = self::compassResolvePath(array_shift($args));
            $data = base64_encode(file_get_contents($path));
            $format = array_shift($args);

            $ext = array_pop(explode('.', $file));
            if (isset($mimes[$ext])) {
                $mime = $mimes[$ext];
            } else {
                continue;
            }

            $files[] = "url('data:$mime;base64,$data') format('$format')";
        }

        return new SassString(implode(', ', $files));
    }

    public static function compassBlank($object)
    {
        if (is_object($object)) {
            $object = $object->value;
        }
        $result = false;
        if (is_bool($object)) {
            $result = !$object;
        }
        if (is_string($object)) {
            $result = (strlen(trim($object, ' ,')) === 0);
        }

        return new SassBoolean($result);
    }

    public static function compassCompact()
    {
        $sep = ', ';

        $args = func_get_args();
        $list = array();

        // remove blank entries
        // append non-blank entries to list
        foreach ($args as $k => $v) {
            if (is_object($v)) {
                $string = (isset($v->value) ? $v->value : false);
            } else {
                $string = (string) $v;
            }
            if (empty($string) || $string == 'false') {
                unset($args[$k]);
                continue;
            }
            $list[] = $string;
        }

        return new SassString(implode($sep, $list));
    }

    public static function compassCompassNth()
    {
        $args = func_get_args();
        $place = array_pop($args);
        $list = array();
        foreach ($args as $arg) {
            $list = array_merge($list, self::compassList($arg));
        }

        if ($place == 'first') {
            $place = 0;
        }
        if ($place == 'last') {
            $place = count($list) - 1;
        }

        if (isset($list[$place])) {
            return current(SassScriptLexer::$instance->lex($list[$place], new SassContext()));
        }

        return new SassBoolean(false);
    }

    public static function compassCompassList()
    {
        $args = func_get_args();
        $list = array();
        foreach ($args as $arg) {
            $list = array_merge($list, self::compassList($arg));
        }

        return new SassString(implode(', ', $list));
    }

    public static function compassCompassSpaceList()
    {
        $args = func_get_args();
        $list = self::compassList($args, ',');

        return new SassString(implode(' ', $list));
    }

    public static function compassCompassListSize()
    {
        $args = func_get_args();
        $list = self::compassList($args, ',');

        return new SassNumber(count($list));
    }

    public static function compassCompassListSlice($list, $start, $end)
    {
        $args = func_get_args();
        $end = array_pop($args);
        $start = array_pop($args);
        $list = self::compassList($args, ',');

        return implode(',', array_slice($list, $start, $end));
    }

    public static function compassFirstValueOf()
    {
        $args = array();
        $args[] = 'first';

        return call_user_func_array('self::compassCompassNth', $args);
    }

    public static function compassList($list, $seperator = ',')
    {
        if (is_object($list)) {
            $list = $list->value;
        }
        if (is_array($list)) {
            $newlist = array();
            foreach ($list as $listlet) {
                $newlist = array_merge($newlist, self::compassList($listlet, $seperator));
            }
            $list = implode(', ', $newlist);
        }

        $out = array();
        $size = 0;
        $braces = 0;
        $stack = '';
        for ($i = 0; $i < strlen($list); $i++) {
            $char = substr($list, $i, 1);
            switch ($char) {
                case '(':
                    $braces++;
                    $stack .= $char;
                    break;
                case ')':
                    $braces--;
                    $stack .= $char;
                    break;
                case $seperator:
                    if ($braces === 0) {
                        $out[] = $stack;
                        $stack = '';
                        $size++;
                        break;
                    }

                default:
                    $stack .= $char;
            }
        }
        $out[] = $stack;

        return $out;
    }

// http://compass-style.org/reference/compass/helpers/selectors/#nest
    public static function compassNest()
    {
        $args = func_get_args();
        $output = explode(',', array_pop($args));

        for ($i = count($args) - 1; $i >= 0; $i--) {
            $current = explode(',', $args[$i]);
            $size = count($output);
            foreach ($current as $selector) {
                for ($j = 0; $j < $size; $j++) {
                    $output[] = trim($selector) . " " . trim($output[$j]);
                }
            }
            $output = array_slice($output, $size);
        }

        return new SassString(implode(', ', $output));
    }

    public static function compassAppendSelector($initial, $new)
    {
        $list = explode(',', $initial);
        foreach ($list as $k => $selector) {
            $list[$k] = trim($selector) . $new;
        }

        return new SassString(implode(', ', $list));
    }

    public static function compassHeaders($from = false, $to = false)
    {
        if (is_object($from)) {
            $from = $from->value;
        }
        if (is_object($to)) {
            $to = $to->value;
        }

        if (!$from || !is_numeric($from)) {
            $from = 1;
        }
        if (!$to || !is_numeric($to)) {
            $to = 6;
        }

        $from = (int) $from;
        $to = (int) $to;

        $output = array();
        for ($i = $from; $i <= $to; $i++) {
            $output[] = 'h' . $i;
        }

        return new SassString(implode(', ', $output));
    }

    public static function compassCommaList()
    {
        print_r(func_get_args());
        die;
    }
    
    public static function compassPrefixed ($prefix, $list) {
    	$list = static::compassList( $list );
    	$prefix = trim ( preg_replace ( '/[^a-z]/', '', strtolower ( $prefix ) ) );
    	
    	$reqs = array (
    			'pie' => array (
    					'border-radius',
    					'box-shadow',
    					'border-image',
    					'background',
    					'linear-gradient'
    			),
    			'webkit' => array (
    					'background-clip',
    					'background-origin',
    					'border-radius',
    					'box-shadow',
    					'box-sizing',
    					'columns',
    					'gradient',
    					'linear-gradient',
    					'text-stroke'
    			),
    			'moz' => array (
    					'background-size',
    					'border-radius',
    					'box-shadow',
    					'box-sizing',
    					'columns',
    					'gradient',
    					'linear-gradient'
    			),
    			'o' => array (
    					'background-origin',
    					'text-overflow'
    			)
    	);
    	foreach ( $list as $item ) {
    		$aspect = trim ( current ( explode ( '(', $item ) ) );
    		if (isset ( $reqs [$prefix] ) && in_array ( $aspect, $reqs [$prefix] )) {
    			return new SassBoolean ( TRUE );
    		}
    	}
    	return new SassBoolean ( FALSE );
    }
    
    public static function compassPrefix ($vendor, $input) {
    	if (is_object($vendor)) {
    		$vendor = $vendor->value;
    	}
    	
    	$list = static::compassList($input, ',');
    	$output = '';
    	foreach($list as $key=>$value) {
    		$list[$key] = '-' . $vendor . '-' . $value;
    	}
    	return new SassString(implode(', ', $list));
    }
    
    public static function compassWebkit ($input) {
    	return static::compassPrefix('webkit', $input);
    }
    
    public static function compassMoz ($input) {
    	return static::compassPrefix('moz', $input);
    }
    
    public static function compassO ($input) {
    	return static::compassPrefix('o', $input);
    }
    
    public static function compassMs ($input) {
    	return static::compassPrefix('ms', $input);
    }
    
    public static function compassSvg ($input) {
    	return static::compassPrefix('svg', $input);
    }
    
    public static function compassPie ($input) {
    	return static::compassPrefix('ms', $input);
    }
    
    public static function compassCss2 ($input) {
    	return static::compassPrefix('ms', $input);
    }
    
    public static function compassOwg ($input) {
    	return static::compassPrefix('ms', $input);
    }

    public static function compassPrefixedForTransition($prefix, $list)
    {
    	
    }

    public static function compassPi()
    {
        return pi();
    }

    public static function compassSin($number)
    {
        return new SassNumber(sin($number));
    }

    public static function compassCos($number)
    {
        return new SassNumber(sin($number));
    }

    public static function compassTan($number)
    {
        return new SassNumber(sin($number));
    }

# not sure what should happen with these

    public static function compassStylesheetUrl($path, $only_path = false)
    {
        return self::compassUrl($path, $only_path);
    }

    public static function compassFontUrl($path, $only_path = false)
    {
        return self::compassUrl($path, $only_path);
    }

    public static function compassImageUrl($path, $only_path = false)
    {
        return self::compassUrl($path, $only_path);
    }

    public static function compassUrl($path, $only_path = false, $web_path = true)
    {
        $opath = $path;
        if (!$path = SassFile::get_file($path, SassParser::$instance, false)) {
            throw new Exception('File not found: ' . $opath);
        }

        $path = $path[0];
        if ($web_path) {
            $webroot = realpath($_SERVER['DOCUMENT_ROOT']);
            $path = str_replace($webroot, '', $path);
        }

        if ($only_path) {
            return new SassString($path);
        }

        return new SassString("url('$path')");
    }

    public static function compassOppositePosition($from)
    {
        $ret = '';
        if ($from == 'top') {
            $ret = 'bottom';
        } elseif ($from == 'bottom') {
            $ret = 'top';
        } elseif ($from == 'left') {
            $ret = 'right';
        } elseif ($from == 'right') {
            $ret = 'left';
        } elseif ($from == 'center') {
            $ret = 'center';
        }

        return $ret;
    }
}
