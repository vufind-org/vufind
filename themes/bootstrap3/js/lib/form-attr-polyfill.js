/**
 * From http://stackoverflow.com/questions/17742275/polyfill-html5-input-form-attribute/26696165#26696165
 * Recommended by https://github.com/Modernizr/Modernizr/wiki/HTML5-Cross-browser-Polyfills
 * Adapted to eslint styling and updated detection by Chris Hallberg (@crhallberg) for VuFind
 */
(function formAttrPolyfill($) {
  /**
   * polyfill for html5 form attr
   */

  // every browser supports except IE
  if (typeof document.documentMode == 'undefined') {
    // any other browser? skip
    return;
  }

  function resetFormAttr(form) {
    var $form = $(form);
    $form.find('.js-form-attr').remove();
    return $form;
  }
  function makeFieldElement(data) {
    return $('<input/>', data)
      .addClass('js-form-attr')
      .attr('type', 'hidden');
  }

  $(document).ready(function formAttrReady() {
    /**
     * Find all input fields with form attribute point to jQuery object
     *
     */
    $('form[id]').submit(function locateFormAttr(/*e*/) {
      // serialize data
      var data = $('[form=' + this.id + ']').serializeArray();
      // append data to form
      var $form = resetFormAttr(this);
      for (var i=0; i<data.length; i++) {
        $form.append(makeFieldElement(data[i]));
      }
      return true;
    }).each(function locateFormAttrEach() {
      var form = this,
        $fields = $('[form=' + this.id + ']');

      $fields.filter('button, input').filter('[type=reset],[type=submit]').click(function formAttrButtonSubmit() {
        var type = this.type.toLowerCase();
        if (type === 'reset') {
          // reset form
          form.reset();
          // for elements outside form
          $fields.each(function formAttrGatherFieldData() {
            this.value = this.defaultValue;
            this.checked = this.defaultChecked;
          }).filter('select').each(function formAttrGatherSelectData() {
            $(this).find('option').each(function formAttrGatherOptionData() {
              this.selected = this.defaultSelected;
            });
          });
        } else if (type.match(/^submit|image$/i)) {
          var $form = resetFormAttr(form);
          $form.append( makeFieldElement({name: this.name, value: this.value}) ).submit();
        }
      });
    });
  });

})(jQuery);
