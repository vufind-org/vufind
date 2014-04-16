<?php

/**
 * PHP Sass tests.
 * @group sass
 */
class PHPSass_TestCase extends PHPUnit_Framework_TestCase
{
  /**
   * This is the path to a directory of SASS, SCSS and CSS files used in tests.
   */
  public $css_tests_path;

  /**
   * This is the location of the PHPSass library being used.
   */
  public $phpsass_library_path;

  protected function setUp()
  {
    parent::setUp();

    $this->requirePHPSassLibrary();
    $this->css_tests_path = dirname(__FILE__);
  }

  /**
   * Require the PHPSass Library.
   *
   * We try to include it from the local site if it's around, otherwise we try a
   * few known locations, and then failing all of that we fall back to
   * downloading it from the web.
   */
  protected function requirePHPSassLibrary()
  {
    // Allow people to specify the library before we are called.
    if (isset($this->phpsass_library_path)) {

    }
    // Try to use libraries first.
    elseif (($library_path = dirname(__FILE__) . '/..') && file_exists($library_path . '/SassParser.php')) {
      $this->phpsass_library_path = $library_path;
    }

    if (isset($this->phpsass_library_path)) {
      require_once($this->phpsass_library_path . '/SassParser.php');
    } else {
      throw new Exception('Could not find PHPSass compiler.');
    }
  }

  protected function runSassTest($input, $output = FALSE, $settings = array())
  {
    $name = $input;

    $path = $this->css_tests_path;
    $output = $path . '/' . ($output ? $output : preg_replace('/\..+$/', '.css', $input));
    $input = $path . '/' . $input;

    if (!file_exists($input)) {
      return $this->fail('Input file not found - ' . $input);
    }
    if (!file_exists($output)) {
      return $this->fail('Comparison file not found - ' . $output);
    }

    $syntax = explode('.', $input);
    $syntax = array_pop($syntax);
    $settings = $settings + array(
      'style' => 'nested',
      'cache' => FALSE,
      'syntax' => $syntax,
      'debug' => FALSE,
      'debug_info' => FALSE,
      'callbacks' => array(
        'debug' => array($this, 'sassParserDebug'),
        'warn' => array($this, 'sassParserWarning'),
      ),
    );
    $parser = new SassParser($settings);
    $result = $parser->toCss($input);

    $compare = file_get_contents($output);
    if ($compare === FALSE) {
      $this->fail('Unable to load comparison file - ' . $compare);
    }

    $_result = $this->trimResult($result);
    $_compare = $this->trimResult($compare);

    $this->assertEquals($_result, $_compare, 'Result for ' . $name . ' did not match comparison file');
  }

  /**
   * Logging callback for PHPSass debug messages.
   */
  public function sassParserDebug($message, $context)
  {
  }

  /**
   * Logging callback for PHPSass warning messages.
   */
  public function sassParserWarning($message, $context)
  {
  }

  protected function trimResult(&$input)
  {
    $trim = preg_replace('/[\s;]+/', '', $input);
    $trim = preg_replace('/\/\*.+?\*\//m', '', $trim);

    return $trim;
  }

  public function testAlt()
  {
    $this->runSassTest('alt.sass');
    $this->runSassTest('alt.scss');
  }

  public function testBasic()
  {
    $this->runSassTest('basic.sass');
  }


  public function testComments()
  {
    $this->runSassTest('comments.sass');
  }

  public function testCompact()
  {
    $this->runSassTest('compact.sass');
  }

  public function testComplex()
  {
    $this->runSassTest('complex.sass');
  }

  public function testCompressed()
  {
    $this->runSassTest('compressed.sass');
  }

  public function testContent()
  {
    $this->runSassTest('content.scss');
  }

  public function testCss3()
  {
    $this->runSassTest('css3.scss');
  }

  public function testDefault()
  {
    $this->runSassTest('default.sass');
  }

  public function testEach()
  {
    $this->runSassTest('each.scss');
  }

  public function testExpanded()
  {
    $this->runSassTest('expanded.sass');
  }

  public function testExtend()
  {
    $this->runSassTest('extend.sass');
  }

  public function testExtendPlaceholders()
  {
    $this->runSassTest('extend_placeholders.scss');
  }

  public function testFilters()
  {
    $this->runSassTest('filters.scss');
  }

  public function testFunctions()
  {
    $this->runSassTest('functions.scss');
  }

  public function testHolmes()
  {
    $this->runSassTest('holmes.sass');
  }

  public function testHSLFunction()
  {
    $this->runSassTest('hsl-functions.scss');
  }

  public function testIf()
  {
    $this->runSassTest('if.sass');
  }

  public function testImportedContent()
  {
    $this->runSassTest('import_content.sass');
  }

  public function testInterpolation()
  {
    $this->runSassTest('interpolation.scss');
  }

  public function testIntrospection()
  {
    $this->runSassTest('introspection.scss');
  }

  public function testImport()
  {
    $this->runSassTest('import.sass');
  }

  public function testLineNumbers()
  {
    $this->runSassTest('line_numbers.sass');
  }

  public function testList()
  {
    $this->runSassTest('list.scss');
  }

  public function testMedia()
  {
    $this->runSassTest('media.scss');
  }

  public function testMiscFunctions()
  {
    $this->runSassTest('misc-functions.scss');
  }

  public function testMisc()
  {
    $this->runSassTest('misc.scss');
  }

  public function testMixinContent()
  {
    $this->runSassTest('mixin-content.sass');
    $this->runSassTest('mixin-content.scss');
  }

  public function testMixinJa1()
  {
    $this->runSassTest('mixin-ja1.sass');
  }

  public function testMixinParams()
  {
    $this->runSassTest('mixin-params.scss');
  }

  public function testMixins()
  {
    $this->runSassTest('mixins.sass');
  }

  public function testMixinInMixin()
  {
    $this->runSassTest('mixin_in_mixin.scss');
  }

  public function testMultiline()
  {
    $this->runSassTest('multiline.sass');
  }

  public function testNestedImport()
  {
    $this->runSassTest('nested_import.sass');
  }

  public function testNested()
  {
    $this->runSassTest('nested.sass');
  }

  public function testNestedMedia()
  {
    $this->runSassTest('nested_media.scss');
  }

  public function testNestedPseudo()
  {
    $this->runSassTest('nested_pseudo.scss');
  }

  public function testNumber()
  {
    $this->runSassTest('number.scss');
  }

  public function testOpacity()
  {
    $this->runSassTest('opacity.scss');
  }

  public function testOtherColor()
  {
    $this->runSassTest('other-color.scss');
  }

  public function testParentRef()
  {
    $this->runSassTest('parent_ref.sass');
  }

  public function testProprietarySelector()
  {
    $this->runSassTest('proprietary-selector.scss');
  }

  public function testRGBFunctions()
  {
    $this->runSassTest('rgb-functions.scss');
  }

  public function testScssImportee()
  {
    $this->runSassTest('scss_importee.scss');
  }

  public function testScssImport()
  {
    $this->runSassTest('scss_import.scss');
  }

  public function testSplats()
  {
    $this->runSassTest('splats.scss');
  }

  public function testString()
  {
    $this->runSassTest('string.scss');
  }

  public function testUnits()
  {
    $this->runSassTest('units.sass');
  }

  public function testListVariable()
  {
    $this->runSassTest('list_variable.scss');
  }

  public function testMediaInFor()
  {
    $this->runSassTest('media_in_for.scss');
  }

  public function testMediaInMixin()
  {
    $this->runSassTest('media_in_mixin.scss');
  }

  public function testMediaInTwoMixins()
  {
    $this->runSassTest('media_in_mixin_in_mixin.scss');
  }

  public function testIfParentheses()
  {
    $this->runSassTest('if_parentheses.scss');
  }

  public function testListEmpty()
  {
    $this->runSassTest('list_empty.scss');
  }

  public function testWarnImported()
  {
    $this->markTestIncomplete('This test has not been implemented yet.');
    //$this->runSassTest('warn_imported.sass');
  }

  public function testWarn()
  {
    $this->runSassTest('warn.sass');
  }

  public function testColour()
  {
    $this->runSassTest('colour-nth.scss');
  }

  public function testMixinSetvar()
  {
    $this->runSassTest('mixin_setvar.scss');
  }

  public function testTokenLevelException() {
      try {
        $this->runSassTest('token_level.scss', false, array('debug' => true));
      } catch (SassException $e) {
          $message = 'Too many closing brackets';
          $this->assertEquals($message, substr($e->getMessage(), 0, strlen($message)));
      }
  }
}
