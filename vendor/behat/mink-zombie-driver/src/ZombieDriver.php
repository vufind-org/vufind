<?php

/*
 * This file is part of the Behat\Mink.
 * (c) Konstantin Kudryashov <ever.zet@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Behat\Mink\Driver;

use Behat\Mink\Driver\NodeJS\Server\ZombieServer;
use Behat\Mink\Exception\DriverException;

/**
 * Zombie (JS) driver.
 *
 * @author Pascal Cremer <b00gizm@gmail.com>
 */
class ZombieDriver extends CoreDriver
{
    private $started = false;
    private $nativeRefs = array();
    private $server = null;

    /**
     * Constructor.
     *
     * @param ZombieServer|string $serverOrHost A server instance, or the host to connect to
     * @param int|null            $port         The port to connect to when using the host (default to 8124)
     */
    public function __construct($serverOrHost, $port = null)
    {
        if ($serverOrHost instanceof ZombieServer) {
            $this->server = $serverOrHost;

            return;
        }

        if (null === $port) {
            $port = 8124;
        }

        $this->server = new ZombieServer((string) $serverOrHost, $port);
    }

    /**
     * Returns Zombie.js server.
     *
     * @return ZombieServer
     */
    public function getServer()
    {
        return $this->server;
    }

    /**
     * {@inheritdoc}
     */
    public function start()
    {
        if ($this->server) {
            $this->server->start();
        }

        $this->started = true;
    }

    /**
     * {@inheritdoc}
     */
    public function isStarted()
    {
        return $this->started;
    }

    /**
     * {@inheritdoc}
     */
    public function stop()
    {
        if ($this->server) {
            $this->server->stop();
        }

        $this->started = false;
        $this->nativeRefs = array();
    }

    /**
     * {@inheritdoc}
     */
    public function reset()
    {
        $js = <<<JS
browser.destroy();
browser = null;
stream.end();
JS;

        $this->server->evalJS($js);
        $this->nativeRefs = array();
    }

    /**
     * {@inheritdoc}
     */
    public function visit($url)
    {
        // Cleanup cached references
        $this->nativeRefs = array();

        $js = <<<JS
pointers = [];
browser.visit("{$url}", function (err) {
  if (err) {
    stream.end(JSON.stringify(err.stack));
  } else {
    stream.end();
  }
});
JS;
        $this->server->evalJS($js);
    }

    /**
     * {@inheritdoc}
     */
    public function getCurrentUrl()
    {
        return $this->server->evalJS('browser.location.toString()', 'json');
    }

    /**
     * {@inheritdoc}
     */
    public function reload()
    {
        $this->visit($this->getCurrentUrl());
    }

    /**
     * {@inheritdoc}
     */
    public function forward()
    {
        $this->server->evalJS("browser.window.history.forward(); browser.wait(function () { stream.end(); })");
        $this->nativeRefs = array();
    }

    /**
     * {@inheritdoc}
     */
    public function back()
    {
        $this->server->evalJS("browser.window.history.back(); browser.wait(function () { stream.end(); })");
        $this->nativeRefs = array();
    }

    /**
     * {@inheritdoc}
     */
    public function setBasicAuth($user, $password)
    {
        if (false === $user) {
            $user = null;
            $password = null;
        }

        $userEscaped = json_encode($user);
        $passwordEscaped = json_encode($password);

        $js = <<<JS
var username = $userEscaped;
var password = $passwordEscaped;

if (browser.authenticate) {
    if (null === username) {
        browser.authenticate().reset();
    } else {
        browser.authenticate().basic(username, password);
    }
} else {
    browser.on('authenticate', function (authentication) {
        authentication.username = username;
        authentication.password = password;
    });
}
stream.end();
JS;

        $this->server->evalJS($js);
    }

    /**
     * {@inheritdoc}
     */
    public function switchToWindow($name = null)
    {
        if ($name === null) {
            $name = '';
        }

        $nameEscaped = json_encode($name);

        $this->server->evalJS("browser.tabs.current = {$nameEscaped}; stream.end();");
    }

    /**
     * {@inheritdoc}
     */
    public function setRequestHeader($name, $value)
    {
        $nameEscaped = json_encode($name);
        $valueEscaped = json_encode($value);

        if (strtolower($name) === 'user-agent') {
            $this->server->evalJS("browser.userAgent = {$valueEscaped};stream.end();");

            return;
        }

        $js = <<<JS
if (!browser.headers) {
  browser.headers = {};
}
browser.headers[{$nameEscaped}] = {$valueEscaped};
stream.end();
JS;
        $this->server->evalJS($js);
    }

    /**
     * {@inheritdoc}
     */
    public function getResponseHeaders()
    {
        $js = <<<JS
var response = browser.response || browser.window._response,
    headers = response.headers.toObject ? response.headers.toObject() : response.headers;

stream.end(JSON.stringify(headers));
JS;

        return json_decode($this->server->evalJS($js), true);
    }

    /**
     * {@inheritdoc}
     */
    public function setCookie($name, $value = null)
    {
        if ($value === null) {
            $this->deleteCookie($name);

            return;
        }

        $nameEscaped = json_encode($name);
        $valueEscaped = json_encode($value);

        $js = <<<JS
var cookieId = {name: {$nameEscaped}, domain: browser.window.location.hostname, path: '/'};

browser.setCookie(cookieId, {$valueEscaped});
stream.end();
JS;

        $this->server->evalJS($js);
    }

    private function deleteCookie($name)
    {
        $nameEscaped = json_encode($name);

        $js = <<<JS
var path = browser.window.location.pathname;

do {
    browser.deleteCookie({name: {$nameEscaped}, domain: browser.window.location.hostname, path: path});
    path = path.replace(/.$/, '');
} while (path);

stream.end();
JS;

        $this->server->evalJS($js);
    }

    /**
     * {@inheritdoc}
     */
    public function getCookie($name)
    {
        $nameEscaped = json_encode($name);

        $js = <<<JS
var cookieId = {name: {$nameEscaped}, domain: browser.window.location.hostname},
    cookieVal = browser.getCookie(cookieId, false);

if (cookieVal) {
    stream.end(JSON.stringify(decodeURIComponent(cookieVal)));
} else {
    stream.end(JSON.stringify(null));
}
JS;

        return json_decode($this->server->evalJS($js));
    }

    /**
     * {@inheritdoc}
     */
    public function getStatusCode()
    {
        return (int) $this->server->evalJS('browser.statusCode', 'json');
    }

    /**
     * {@inheritdoc}
     */
    public function getContent()
    {
        return $this->server->evalJS('browser.source', 'json');
    }

    /**
     * {@inheritdoc}
     */
    public function findElementXpaths($xpath)
    {
        $xpathEncoded = json_encode($xpath);
        $js = <<<JS
var node,
    refs = [],
    result = browser.xpath({$xpathEncoded});

while (node = result.iterateNext()) {
    if (node.nodeType !== 10) {
        pointers.push(node);
        refs.push(pointers.length - 1);
    }
}
stream.end(JSON.stringify(refs));
JS;

        $refs = json_decode($this->server->evalJS($js), true);

        if (!$refs) {
            return array();
        }

        $elements = array();
        foreach ($refs as $i => $ref) {
            $subXpath = sprintf('(%s)[%d]', $xpath, $i + 1);
            $this->nativeRefs[md5($subXpath)] = $ref;
            $elements[] = $subXpath;

            // first node ref also matches the original xpath
            if (0 === $i) {
                $this->nativeRefs[md5($xpath)] = $ref;
            }
        }

        return $elements;
    }

    /**
     * {@inheritdoc}
     */
    public function getTagName($xpath)
    {
        $ref = $this->getNativeRefForXPath($xpath);

        return strtolower($this->server->evalJS("{$ref}.tagName", 'json'));
    }

    /**
     * {@inheritdoc}
     */
    public function getText($xpath)
    {
        $ref = $this->getNativeRefForXPath($xpath);

        return trim($this->server->evalJS("{$ref}.textContent.replace(/\s+/g, ' ')", 'json'));
    }

    /**
     * {@inheritdoc}
     */
    public function getHtml($xpath)
    {
        $ref = $this->getNativeRefForXPath($xpath);

        return $this->server->evalJS("{$ref}.innerHTML", 'json');
    }

    /**
     * {@inheritdoc}
     */
    public function getOuterHtml($xpath)
    {
        $ref = $this->getNativeRefForXPath($xpath);

        return $this->server->evalJS("{$ref}.outerHTML", 'json');
    }

    /**
     * {@inheritdoc}
     */
    public function getAttribute($xpath, $name)
    {
        $ref = $this->getNativeRefForXPath($xpath);

        return $this->server->evalJS("{$ref}.getAttribute('{$name}')", 'json');
    }

    /**
     * {@inheritdoc}
     */
    public function getValue($xpath)
    {
        $ref = $this->getNativeRefForXPath($xpath);

        $js = <<<JS
var node = {$ref},
    tagName = node.tagName.toLowerCase(),
    value = null;
if (tagName == "input") {
  var type = node.type.toLowerCase();
  if (type == "checkbox") {
    value = node.checked ? node.value : null;
  } else if (type == "radio") {
    if (node.checked) {
      value = node.value;
    } else {
      var name = node.getAttribute('name');
      if (name) {
        var formElements = node.form.elements,
            element;
        for (var i = 0; i < formElements.length; i++) {
          element = formElements[i];
          if (element.type.toLowerCase() == 'radio' && element.getAttribute('name') === name && element.checked) {
            value = element.value;
            break;
          }
        }
      }
    }
  } else {
    value = node.value;
  }
} else if (tagName == "textarea") {
  value = node.value;
} else if (tagName == "select") {
  if (node.multiple) {
    value = [];
    for (var i = 0; i < node.options.length; i++) {
      if (node.options[i].selected) {
        value.push(node.options[ i ].value);
      }
    }
  } else {
    var idx = node.selectedIndex;
    if (idx >= 0) {
      value = node.options.item(idx).value;
    } else {
      value = null;
    }
  }
} else {
  value = node.value;
}
stream.end(JSON.stringify(value));
JS;

        return json_decode($this->server->evalJS($js));
    }

    /**
     * {@inheritdoc}
     */
    public function setValue($xpath, $value)
    {
        $ref = $this->getNativeRefForXPath($xpath);

        $value = json_encode($value);

        $js = <<<JS
var node = {$ref},
    value = {$value},
    tagName = node.tagName.toLowerCase(),
    type = node.type.toLowerCase();
if (tagName == 'select') {
  if (node.multiple) {
    var toSelect = [];
    var toUnselect = [];
    var option;
    for (var i = 0; i < node.options.length; i++) {
      option = node.options[i];
      if (option.selected && -1 === value.indexOf(option.value)) {
        toUnselect.push(option);
      } else if (!option.selected && -1 !== value.indexOf(option.value)) {
        toSelect.push(option);
      }
    }

    if (0 === toSelect.length && toUnselect.length > 0) {
      for (i = 1; i < toUnselect.length; i++) {
        toUnselect[i].selected = false;
      }
      browser.unselectOption(toUnselect[0]);
    } else if (toSelect.length) {
      for (i = 0; i < toUnselect.length; i++) {
        toUnselect[i].selected = false;
      }
      for (i = 1; i < toSelect.length; i++) {
        toUnselect[i].selected = true;
      }
      browser.selectOption(toSelect[0]);
    }
  } else {
    browser.select(node, value);
  }
} else if (type == 'checkbox') {
  value ? browser.check(node) : browser.uncheck(node);
} else if (type == 'radio') {
  if (node.value === value) {
    browser.choose(node);
  } else {
    var formElements = node.form.elements,
        name = node.getAttribute('name'),
        found = false,
        element;

    if (!name) {
      throw new Error('The radio button does not have the value "' + value + '"');
    }

    for (var i = 0; i < formElements.length; i++) {
      element = formElements[i];
      if (element.tagName.toLowerCase() == 'input' && element.type.toLowerCase() == 'radio' && element.name === name) {
        if (value === element.value) {
          found = true;
          browser.choose(element);
          break;
        }
      }
    }

    if (!found) {
      throw new Error('The radio group "' + name + '" does not have an option "' + value + '"');
    }
  }
} else {
  browser.fill(node, value);
}
stream.end();
JS;
        $this->server->evalJS($js);
    }

    /**
     * {@inheritdoc}
     */
    public function check($xpath)
    {
        $ref = $this->getNativeRefForXPath($xpath);

        $this->server->evalJS("browser.check({$ref});stream.end();");
    }

    /**
     * {@inheritdoc}
     */
    public function uncheck($xpath)
    {
        $ref = $this->getNativeRefForXPath($xpath);

        $this->server->evalJS("browser.uncheck({$ref});stream.end();");
    }

    /**
     * {@inheritdoc}
     */
    public function isChecked($xpath)
    {
        $ref = $this->getNativeRefForXPath($xpath);

        return (boolean) $this->server->evalJS("{$ref}.checked", 'json');
    }

    /**
     * {@inheritdoc}
     */
    public function selectOption($xpath, $value, $multiple = false)
    {
        $ref = $this->getNativeRefForXPath($xpath);
        $value = json_encode($value);
        $multiple = json_encode($multiple);
        $js = <<<JS
var node = {$ref},
    value = {$value},
    tagName = node.tagName.toLowerCase();
if (tagName == "select") {
  if (node.multiple && !{$multiple}) {
    var toSelect,
      option,
      toUnselect = [];
    for (var i = 0; i < node.options.length; i++) {
      option = node.options[i];
      if (option.selected && option.value !== value) {
        toUnselect.push(option);
      } else if (!option.selected && option.value === value) {
        toSelect = option;
      }
    }

    if (toSelect) {
      for (i = 0; i < toUnselect.length; i++) {
        toUnselect[i].selected = false;
      }
      browser.selectOption(toSelect);
    } else if (toUnselect.length) {
      for (i = 1; i < toUnselect.length; i++) {
        toUnselect[i].selected = false;
      }
      browser.unselectOption(toUnselect[0]);
    }
  } else {
    browser.select(node, value);
  }
} else if (tagName == "input" && node.type.toLowerCase() == 'radio') {
  if (node.value === value) {
    browser.choose(node);
  } else {
    var formElements = node.form.elements,
        name = node.getAttribute('name'),
        found = false,
        element;

    if (!name) {
      throw new Error('The radio button does not have the value "' + value + '"');
    }

    for (var i = 0; i < formElements.length; i++) {
      element = formElements[i];
      if (element.tagName.toLowerCase() == 'input' && element.type.toLowerCase() == 'radio' && element.name === name) {
        if (value === element.value) {
          found = true;
          browser.choose(element);
          break;
        }
      }
    }

    if (!found) {
      throw new Error('The radio group "' + name + '" does not have an option "' + value + '"');
    }
  }
} else {
  throw 'The element is not a select or radio input';
}
stream.end();
JS;
        $this->server->evalJS($js);
    }

    /**
     * {@inheritdoc}
     */
    public function isSelected($xpath)
    {
        $ref = $this->getNativeRefForXPath($xpath);

        return (boolean) $this->server->evalJS("{$ref}.selected", 'json');
    }

    /**
     * {@inheritdoc}
     */
    public function click($xpath)
    {
        $ref = $this->getNativeRefForXPath($xpath);

        $js = <<<JS
var node    = {$ref},
    tagName = node.tagName.toLowerCase(),
    type    = (node.type || '').toLowerCase();

if (tagName == "button" || (tagName == "input" && (type == "button" || type == "submit"))) {
  if (node.disabled) {
    stream.end('This button is disabled');
  }
}
stream.end();
JS;
        $out = $this->server->evalJS($js);
        if (!empty($out)) {
            throw new DriverException(sprintf('Error while clicking button: [%s]', $out));
        }

        $this->triggerBrowserEvent('click', $xpath);
        // Resets the cached references as the click action can go to a different page
        // This ensures we don't have outdated refs on the new page if the same XPath is requested
        // at the expense of loosing the know reference in case the click does not change page
        $this->nativeRefs = array();
    }

    /**
     * {@inheritdoc}
     */
    public function doubleClick($xpath)
    {
        $this->triggerBrowserEvent("dblclick", $xpath);
    }

    /**
     * {@inheritdoc}
     */
    public function rightClick($xpath)
    {
        $this->triggerBrowserEvent("contextmenu", $xpath);
    }

    /**
     * {@inheritdoc}
     */
    public function attachFile($xpath, $path)
    {
        $ref = $this->getNativeRefForXPath($xpath);
        $path = json_encode($path);
        $this->server->evalJS("browser.attach({$ref}, {$path});stream.end();");
    }

    /**
     * {@inheritdoc}
     */
    public function mouseOver($xpath)
    {
        $this->triggerBrowserEvent("mouseover", $xpath);
    }

    /**
     * {@inheritdoc}
     */
    public function focus($xpath)
    {
        $this->triggerBrowserEvent("focus", $xpath);
    }

    /**
     * {@inheritdoc}
     */
    public function blur($xpath)
    {
        $this->triggerBrowserEvent("blur", $xpath);
    }

    /**
     * {@inheritdoc}
     */
    public function keyPress($xpath, $char, $modifier = null)
    {
        $this->triggerKeyEvent("keypress", $xpath, $char, $modifier);
    }

    /**
     * {@inheritdoc}
     */
    public function keyDown($xpath, $char, $modifier = null)
    {
        $this->triggerKeyEvent("keydown", $xpath, $char, $modifier);
    }

    /**
     * {@inheritdoc}
     */
    public function keyUp($xpath, $char, $modifier = null)
    {
        $this->triggerKeyEvent("keyup", $xpath, $char, $modifier);
    }

    /**
     * {@inheritdoc}
     */
    public function executeScript($script)
    {
        $script = json_encode($this->fixSelfExecutingFunction($script));
        $this->server->evalJS("browser.evaluate({$script});stream.end();");
    }

    /**
     * {@inheritdoc}
     */
    public function evaluateScript($script)
    {
        $script = preg_replace('/^return\s+/', '', $script);

        $script = json_encode($this->fixSelfExecutingFunction($script));

        return $this->server->evalJS("browser.evaluate({$script})", 'json');
    }

    /**
     * {@inheritdoc}
     */
    public function wait($timeout, $condition)
    {
        $conditionEscaped = json_encode($condition);

        $js = <<<JS
(function () {
  var checkCondition = function () {
    return browser.evaluate($conditionEscaped);
  };

  browser.wait({function: checkCondition, duration: $timeout}, function () {
    stream.end(JSON.stringify(checkCondition()));
  });
}());
JS;

        return json_decode($this->server->evalJS($js));
    }

    /**
     * Returns last error.
     *
     * @return array
     */
    protected function getLastError()
    {
        return $this->server->evalJS('browser.lastError', 'json');
    }

    /**
     * {@inheritdoc}
     */
    public function submitForm($xpath)
    {
        $ref = $this->getNativeRefForXPath($xpath);

        $this->server->evalJS("{$ref}.submit();stream.end();");
        $this->nativeRefs = array();
    }

    /**
     * Triggers (fires) a Zombie.js browser event.
     *
     * @param string $event The name of the event
     * @param string $xpath The xpath of the element to trigger this event
     *
     * @throws DriverException
     */
    protected function triggerBrowserEvent($event, $xpath)
    {
        $ref = $this->getNativeRefForXPath($xpath);

        $js = <<<JS
browser.fire({$ref}, "{$event}", function (err) {
  if (err) {
    stream.end(JSON.stringify(err.stack));
  } else {
    stream.end();
  }
});
JS;
        $out = $this->server->evalJS($js);
        if (!empty($out)) {
            throw new DriverException(sprintf("Error while processing event '%s': %s", $event, $out));
        }
    }

    /**
     * Triggers a keyboard event
     *
     * @param string $name     The event name
     * @param string $xpath    The xpath of the element to trigger this event on
     * @param mixed  $char     could be either char ('b') or char-code (98)
     * @param string $modifier keyboard modifier (could be 'ctrl', 'alt', 'shift' or 'meta')
     */
    protected function triggerKeyEvent($name, $xpath, $char, $modifier)
    {
        $ref = $this->getNativeRefForXPath($xpath);

        $char = is_numeric($char) ? $char : ord($char);

        $isCtrlKeyArg  = ($modifier == 'ctrl')  ? "true" : "false";
        $isAltKeyArg   = ($modifier == 'alt')   ? "true" : "false";
        $isShiftKeyArg = ($modifier == 'shift') ? "true" : "false";
        $isMetaKeyArg  = ($modifier == 'meta')  ? "true" : "false";

        $js = <<<JS
var node = {$ref},
    window = browser.window,
    e = window.document.createEvent("UIEvents");
e.initUIEvent("{$name}", true, true, window, 1);
e.ctrlKey = {$isCtrlKeyArg};
e.altKey = {$isAltKeyArg};
e.shiftKey = {$isShiftKeyArg};
e.metaKey = {$isMetaKeyArg};
e.keyCode = {$char};
node.dispatchEvent(e);
stream.end();
JS;
        $this->server->evalJS($js);
    }

    /**
     * Tries to fetch a native reference to a node that might have been cached
     * by the server. If it can't be found, the method performs a search.
     *
     * Searching the native reference by the MD5 hash of its xpath feels kinda
     * hackish, but it'll boost performance and prevents a lot of boilerplate
     * Javascript code.
     *
     * @param string $xpath
     *
     * @return string
     *
     * @throws DriverException when there is no element matching the XPath
     */
    protected function getNativeRefForXPath($xpath)
    {
        $hash = md5($xpath);
        if (!isset($this->nativeRefs[$hash])) {
            $res = $this->find($xpath);
            if (empty($res)) {
                throw new DriverException(sprintf('There is no element matching XPath "%s"', $xpath));
            }
        }

        return sprintf('pointers[%s]', $this->nativeRefs[$hash]);
    }

    /**
     * Fixes self-executing functions to allow evaluating them.
     *
     * The self-executing function must be wrapped in braces to work.
     *
     * @param string $script
     *
     * @return string
     */
    private function fixSelfExecutingFunction($script)
    {
        if (preg_match('/^function[\s\(]/', $script)) {
            $script = preg_replace('/;$/', '', $script);
            $script = '(' . $script . ')';
        }

        return $script;
    }
}
