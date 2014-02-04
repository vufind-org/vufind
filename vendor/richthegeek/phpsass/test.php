<!-- Just load this in a browser and the tests will run! -->
<html>
  <head>
    <title>PHamlP Test Suite</title>
    <link rel="stylesheet" type="text/css" href="test.css">
  </head>
  <body>
    <?php

    /* Testing for Sassy.
     *  Looks in tests* and compiles any .sass/.scss files
     *  and compares to them to their twin .css files by
     *  filename.
     *
     *  That is, if we have three files:
     *     test.scss
     *     test.sass
     *     test.css
     *
     *  The tester will compile test.scss and test.sass seperately
     *  and compare their outputs both to each other and to test.css
     *
     *  Testing is eased by stripping out all whitespace, which may
     *  introduce bugs of their own.
     */
    include 'SassParser.php';

    $test_dir = './tests';

    $files = find_files($test_dir);

    $i = 0;

    foreach ($files['by_name'] as $name => $test) {
      if (isset($_GET['name']) && $name != $_GET['name']) {
        continue;
      }
      if (isset($_GET['skip']) && $name && preg_match('/(^|,)(' . preg_quote($name) . ')(,|$)/', $_GET['skip'])) {
        continue;
      }
      if (count($test) > 1) {
        $result = test_files($test, $test_dir);

        if ($result === TRUE) {
          print "\n\t<p class='pass'><em>PASS</em> $name</p>";
        }
        else {
          print "\n\t<p class='fail'><em>FAIL</em> $name</p>";
          print "<pre>$result</pre>";
        }
        flush();

        if ($i++ == 100) {
          die;
        }
      }
    }

    function test_files($files, $dir = '.') {
      sort($files);
      foreach ($files as $i => $file) {
        $name = explode('.', $file);
        $ext = array_pop($name);

        $fn = 'parse_' . $ext;
        if (function_exists($fn)) {
          try {
            $result = $fn($dir . '/' . $file);
          } catch (Exception $e) {
            $result = $e->__toString();
          }
          file_put_contents('/tmp/scss_test_' . $i, trim($result) . "\n");
        }
      }

      $diff = exec('diff -ibwB /tmp/scss_test_0 /tmp/scss_test_1', $out);
      if (count($out)) {
        if (isset($_GET['full'])) {
          $out[] = "\n\n\n" . $result;
        }
        return implode("\n", $out);
      } else {
        return TRUE;
      }
    }


    function parse_scss($file) {
      return __parse($file, 'scss');
    }
    function parse_sass($file) {
      return __parse($file, 'sass');
    }
    function parse_css($file) {
      return file_get_contents($file);
    }

    function __parse($file, $syntax, $style = 'nested') {
      $options = array(
        'style' => $style,
        'cache' => FALSE,
        'syntax' => $syntax,
        'debug' => FALSE,
        'callbacks' => array(
          'warn' => 'cb_warn',
          'debug' => 'cb_debug',
        ),
      );
      // Execute the compiler.
      $parser = new SassParser($options);
      return $parser->toCss($file);
    }

    function cb_warn($message, $context) {
      print "<p class='warn'>WARN : ";
      print_r($message);
      print "</p>";
    }
    function cb_debug($message) {
      print "<p class='debug'>DEBUG : ";
      print_r($message);
      print "</p>";
    }

    function find_files($dir) {
      $op = opendir($dir);
      $return = array('by_type' => array(), 'by_name' => array());
      if ($op) {
        while (false !== ($file = readdir($op))) {
          if (substr($file, 0, 1) == '.') {
            continue;
          }
          $name = explode('.', $file);
          $ext = array_pop($name);
          $return['by_type'][$ext] = $file;
          $name = implode('.', $name);
          if (!isset($return['by_name'][$name])) {
            $return['by_name'][$name] = array();
          }
          $return['by_name'][$name][] = $name . '.' . $ext;
        }
      }
      asort($return['by_name']);
      asort($return['by_type']);
      return $return;
    }
    ?>
  </body>
</html>
