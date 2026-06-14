/* global VuFind */

VuFind.register('searchbox_speech', function SearchboxSpeech() {
  let _recognition = null;
  let _isListening = false;

  /**
   * Initialize speech recognition on the search box.
   */
  function init() {
    const SpeechRecognition = window.SpeechRecognition || window.webkitSpeechRecognition;
    if (!SpeechRecognition) {
      return;
    }

    const textInput = document.getElementById('searchForm_lookfor');
    const micButton = document.getElementById('searchForm-speech');
    if (!textInput || !micButton) {
      return;
    }

    _recognition = new SpeechRecognition();
    _recognition.continuous = false;
    _recognition.interimResults = false;
    _recognition.lang = document.documentElement.lang || 'en';

    _recognition.onstart = () => {
      _isListening = true;
      micButton.classList.add('listening');
      micButton.setAttribute('aria-pressed', 'true');
    };
    _recognition.onend = () => {
      _isListening = false;
      micButton.classList.remove('listening');
      micButton.setAttribute('aria-pressed', 'false');
    };
    _recognition.onerror = () => {
      _isListening = false;
      micButton.classList.remove('listening');
      micButton.setAttribute('aria-pressed', 'false');
    };
    _recognition.onresult = (event) => {
      textInput.value = event.results[0][0].transcript;
      textInput.dispatchEvent(new Event('input'));
    };

    micButton.classList.remove('hidden');
    textInput.classList.add('with-speech');

    micButton.addEventListener('click', (e) => {
      e.preventDefault();
      if (_isListening) {
        _recognition.stop();
      } else {
        _recognition.start();
      }
    });
  }

  return { init };
});
