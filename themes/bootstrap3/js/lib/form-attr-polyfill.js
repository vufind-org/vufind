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

  $(document).ready(function formAttrReady() {
    /**
     * Append a field to a form
     *
     */
    $.fn.appendField = function appendField(_data) {
      var data = (!$.isArray(_data) && _data.name && _data.value)
        ? [_data] // wrap data
        : _data;
      // for form only
      if (!this.is('form')) {
        return;
      }

      var $form = this;

      // attach new params
      $.each(data, function appendFieldEach(i, item) {
        $('<input/>')
          .attr('type', 'hidden')
          .attr('name', item.name)
          .val(item.value).appendTo($form);
      });

      return $form;
    };

    /**
     * Find all input fields with form attribute point to jQuery object
     *
     */
    $('form[id]').submit(function locateFormAttr(/*e*/) {
      // serialize data
      var data = $('[form=' + this.id + ']').serializeArray();
      // append data to form
      $(this).appendField(data);
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
          $(form).appendField({name: this.name, value: this.value}).submit();
        }
      });
    });
  });

})(jQuery);
