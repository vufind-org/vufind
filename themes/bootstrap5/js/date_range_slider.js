/*global VuFind, noUiSlider*/
VuFind.register("dateRangeSlider", function dateRangeSlider() {
  const _updatedSliderEvent = new Event('updated-slider');

  let _defaultOptions = {
    connect: [false, true, false],
    step: 1,
    format: {
      to: (value) => Math.round(value),
      from: (value) => Number(value)
    },
    handleAttributes: [
      { 'aria-hidden': 'true', 'tabindex': '-1' },
      { 'aria-hidden': 'true', 'tabindex': '-1' },
    ]
  };

  /**
   * Parse the value of an input to an integer
   * @param {Element} input Input element
   * @returns {number} Value of input as integer
   */
  function _parseInput(input) {
    return parseInt(input.value, 10);
  }

  /**
   * Create a slider
   * @param {Element} sliderElement Slider base element
   * @param {object} extraOptions Additional options for the slider. Range and starting values have to be included.
   * @param {?Array} inputs Optional array that contains two input elements that should be connected to the slider.
   * @returns {object} Slider object
   */
  function create(sliderElement, extraOptions, inputs = null) {
    let options = Object.assign({}, _defaultOptions, extraOptions);
    let slider = noUiSlider.create(sliderElement, options);

    if (inputs === null) {
      return slider;
    }

    let inputMin = inputs[0];
    let inputMax = inputs[1];

    slider.on('slide', (values) => {
      if (_parseInput(inputMin) !== values[0] || _parseInput(inputMax) !== values[1]) {
        inputMin.value = values[0];
        inputMax.value = values[1];
        sliderElement.dispatchEvent(_updatedSliderEvent);
      }
    });

    /**
     * Set the slider handles to the values of the input.
     */
    function setRangeToInput() {
      let selectionMin = _parseInput(inputMin);
      let selectionMax = _parseInput(inputMax);
      if (selectionMin > selectionMax) {
        [selectionMin, selectionMax] = [selectionMax, selectionMin];
      }
      if (options.range.min <= selectionMin && selectionMin <= selectionMax && selectionMax <= options.range.max) {
        slider.set([selectionMin, selectionMax]);
      }
      sliderElement.dispatchEvent(_updatedSliderEvent);
    }
    inputMin.addEventListener('input', setRangeToInput);
    inputMax.addEventListener('input', setRangeToInput);

    /**
     * Switches the values of the inputs if they are out of order
     */
    function sortInput() {
      let selectionMin = _parseInput(inputMin);
      let selectionMax = _parseInput(inputMax);
      if (selectionMin > selectionMax) {
        inputMin.value = selectionMax;
        inputMax.value = selectionMin;
        setRangeToInput();
      }
    }
    inputMin.addEventListener('change', sortInput);
    inputMax.addEventListener('change', sortInput);

    return slider;
  }

  /**
   * Initializes the sliders in the provided container
   * @param {object} params Params (has to include a container element)
   */
  function updateContainer(params) {
    let container = params.container;
    container.querySelectorAll('.range-slider').forEach((sliderElement) => {
      if (sliderElement.dataset.options !== undefined && sliderElement.dataset.initialized !== "true") {
        sliderElement.dataset.initialized = "true";
        let options = JSON.parse(sliderElement.dataset.options);
        let inputMinId = sliderElement.dataset.inputMinId;
        let inputMaxId = sliderElement.dataset.inputMaxId;
        let inputs = null;
        if (inputMinId !== undefined && inputMaxId !== undefined) {
          inputs = [document.getElementById(inputMinId), document.getElementById(inputMaxId)];
        }
        create(sliderElement, options, inputs);
      }
    });
  }

  /**
   * Init date range sliders
   */
  function init() {
    updateContainer({container: document});
    VuFind.listen('VuFind.sidefacets.loaded', updateContainer);
  }

  return { init, create };
});
