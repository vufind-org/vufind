<?php
/**
 * Zend Framework (http://framework.zend.com/)
 *
 * @link      http://github.com/zendframework/zf2 for the canonical source repository
 * @copyright Copyright (c) 2005-2014 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   http://framework.zend.com/license/new-bsd New BSD License
 */

namespace LosReCaptcha\Form\View\Helper\Captcha;

use Zend\Captcha\ReCaptcha as CaptchaAdapter;
use Zend\Form\ElementInterface;
use Zend\Form\Exception;
use Zend\Form\View\Helper\FormInput;

class ReCaptcha extends FormInput
{
    /**
     * Invoke helper as functor
     *
     * Proxies to {@link render()}.
     *
     * @param  ElementInterface $element
     * @return string
     */
    public function __invoke(ElementInterface $element = null)
    {
        if (!$element) {
            return $this;
        }

        return $this->render($element);
    }

    /**
     * Render ReCaptcha form elements
     *
     * @param  ElementInterface $element
     * @throws Exception\DomainException
     * @return string
     */
    public function render(ElementInterface $element)
    {
        $attributes = $element->getAttributes();
        $captcha = $element->getCaptcha();

        if ($captcha === null || !$captcha instanceof CaptchaAdapter) {
            throw new Exception\DomainException(sprintf(
                '%s requires that the element has a "captcha" attribute implementing Zend\Captcha\AdapterInterface; none found',
                __METHOD__
            ));
        }

        $name          = $element->getName();
        $id            = isset($attributes['id']) ? $attributes['id'] : $name;
        $responseName  = empty($name) ? 'recaptcha_response_field'  : $name . '[recaptcha_response_field]';
        $responseId    = $id . '-response';

        $markup = $captcha->getService()->getHtml($name);
        $hidden = $this->renderHiddenInput($responseName, $responseId);
        $js     = $this->renderJsEvents($responseId);

        return $hidden . $markup . $js;
    }

    /**
     * Render hidden input elements for the response
     *
     * @param  string $responseName
     * @param  string $responseId
     * @return string
     */
    protected function renderHiddenInput($responseName, $responseId)
    {
        $pattern        = '<input type="hidden" %s%s';
        $closingBracket = $this->getInlineClosingBracket();

        $attributes = $this->createAttributesString(array(
            'name' => $responseName,
            'id'   => $responseId,
        ));
        $response = sprintf($pattern, $attributes, $closingBracket);

        return $response;
    }

    /**
     * Create the JS events used to bind the response value to the submitted form.
     *
     * @param  string $challengeId
     * @param  string $responseId
     * @return string
     */
    protected function renderJsEvents($responseId)
    {
        $elseif = 'else if'; // php-cs-fixer bug
        $js =<<<EOJ
<script type="text/javascript" language="JavaScript">
function windowOnLoad(fn)
{
    var old = window.onload;
    window.onload = function () {
        if (old) {
            old();
        }
        fn();
    };
}
function zendBindEvent(el, eventName, eventHandler)
{
    if (el.addEventListener) {
        el.addEventListener(eventName, eventHandler, false);
    } $elseif (el.attachEvent) {
        el.attachEvent('on'+eventName, eventHandler);
    }
}
windowOnLoad(function () {
    zendBindEvent(
        document.getElementById("$responseId").form,
        'submit',
        function (e) {
            document.getElementById("$responseId").value = document.getElementById("g-recaptcha-response").value;
        }
    );
});
</script>
EOJ;
        return $js;
    }
}
