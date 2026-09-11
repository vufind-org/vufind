/*global VuFind, ZXing */
/**
 * Live camera barcode scanner for the self-checkout page.
 *
 * Reads Code 39 item barcodes with zxing-js, shows accepted reads in the
 * status line and puts them in the hidden #barcode field for #checkout-form
 * to post.
 *
 * VuFind.register() calls init(), which does nothing unless the page has a
 * #checkout-scanner element. That element is expected to contain
 * #scanner-toggle, #scanner-preview and #scanner-status, and takes one
 * optional attribute, data-barcode-pattern, for what a decode has to look
 * like to be accepted.
 */
VuFind.register('checkoutScanner', function checkoutScanner() {
  // A gap left free between frames rather than a frame rate: a phone spends
  // most of a fifth of a second on a frame, and a fixed rate would saturate
  // the main thread and make the preview stutter.
  var SCAN_GAP_MS = 60;
  // zxing reads 15 horizontal lines whatever the image height, so vertical
  // resolution is nearly free to give up. Squashing the frame keeps every
  // horizontal pixel -- which is what resolves the bars -- and costs no scan
  // lines, because their spacing scales with the height too.
  var MAX_PROCESS_HEIGHT = 720;
  // Code 39 carries no checksum, so zxing's start/stop and per-character
  // checks are all that stand behind a read. Requiring the same string twice
  // is cheap insurance against a one-off misread.
  var REQUIRED_MATCHES = 2;
  // How long to wait for the camera to report a frame size. getUserMedia can
  // resolve with a stream that never produces one, and nothing rejects: the
  // page would sit on "Starting camera..." indefinitely.
  var CAMERA_TIMEOUT_MS = 5000;
  // The most time that may elapse between one matching read and the next,
  // after which the count starts over. Two reads are only evidence if they
  // are of the same barcode still in front of the camera, within this
  // timeframe. Allows perhaps 10 frames of slack.
  var MATCH_WINDOW_MS = 1200;

  // 'idle', 'starting' while the camera is being opened, or 'running' once
  // the scan loop is going.
  var state = 'idle';
  var video = null;
  var frame = null;
  var frameCtx = null;
  var timer = null;
  // Kept between calls, and built lazily because ZXing may not have loaded
  // by the time this module is registered.
  var reader = null;
  var pendingCode = null;
  var pendingCount = 0;
  var pendingSince = 0;
  // Only a fallback shape. The real one comes from the server through
  // data-barcode-pattern, so that the browser and the checkout endpoint
  // agree on what a barcode looks like.
  var pattern = /^\d+$/;
  var elements = {};

  /**
   * Shape the preview to show only the strip of frame that gets decoded.
   *
   * Without TRY_HARDER, zxing samples 15 rows a thirty-second of the image
   * apart, working outwards from the middle row: seven steps either side, so
   * 15 rows but 14 gaps between them. Giving the preview that height, at the
   * frame's full width, makes what is on screen and what is scanned the same
   * thing.
   *
   * Nothing has to be positioned to line the two up, because the strip is
   * always centred on the frame -- the middle row plus and minus the same
   * seven steps -- and a centred crop is what the video's object-fit already
   * does.
   * @param {number} frameWidth Camera frame width
   * @param {number} frameHeight Camera frame height
   */
  function fitPreviewToStrip(frameWidth, frameHeight) {
    var step = Math.max(1, Math.floor(frameHeight / 32));
    elements.preview.style.aspectRatio = frameWidth + ' / ' + 14 * step;
  }

  /**
   * Run one decode attempt.
   *
   * The 1D readers only ever call getBlackRow(), which HybridBinarizer
   * inherits unchanged from GlobalHistogramBinarizer, so for our purposes
   * the two behave identically. Naming the simpler one says as much.
   * @param {object} source zxing LuminanceSource
   * @returns {string|null} The decoded text, or null if nothing decoded
   */
  function decode(source) {
    var hints = new Map();
    hints.set(ZXing.DecodeHintType.POSSIBLE_FORMATS, [ZXing.BarcodeFormat.CODE_39]);
    if (reader === null) {
      reader = new ZXing.MultiFormatOneDReader(hints);
    }
    try {
      return reader.decode(
        new ZXing.BinaryBitmap(new ZXing.GlobalHistogramBinarizer(source)), hints
      ).getText().trim();
    } catch {
      // NotFoundException on almost every frame, plus Format and Checksum
      // exceptions on a partial read. None are worth reporting.
      return null;
    }
  }

  /**
   * Show a message to the user.
   * @param {string} message Text to display
   * @param {boolean} isError Whether to style the message as an error
   */
  function setStatus(message, isError) {
    if (!elements.status) {
      return;
    }
    elements.status.textContent = message;
    elements.status.classList.toggle('error', isError === true);
  }

  /**
   * Switch the camera off.
   *
   * Nothing done to the video element releases the camera: it stays lit,
   * indicator and all, until every track of the stream is stopped
   * individually.
   * @param {MediaStream} media The stream to release
   */
  function releaseStream(media) {
    media.getTracks().forEach(function stopTrack(track) {
      track.stop();
    });
  }

  /**
   * Stop the camera and tear everything down.
   *
   * Safe to call at any point, including between the camera handing over a
   * stream and the video reporting its size: bailing out early unless the
   * scan loop had started would leave the camera on through that window.
   */
  function stop() {
    state = 'idle';
    pendingCode = null;
    pendingCount = 0;
    pendingSince = 0;
    if (timer !== null) {
      clearTimeout(timer);
      timer = null;
    }
    if (video !== null) {
      if (video.srcObject) {
        releaseStream(video.srcObject);
        video.srcObject = null;
      }
      video.parentNode.removeChild(video);
      video = null;
    }
    if (elements.scanner) {
      elements.scanner.classList.remove('scanning');
    }
    if (elements.toggle) {
      elements.toggle.textContent = VuFind.translate('checkout_scan_start');
    }
  }

  /**
   * Give up on the camera and let the barcode be typed instead.
   *
   * Every way the camera can fail comes here, because the remedy is the same
   * whatever the cause. The specific fault goes to the console rather than
   * on screen: telling a borrower which of them happened would not help
   * them, and each variant would be another string to translate.
   * @param {*} reason Whatever went wrong
   */
  function fallBackToTyping(reason) {
    console.error(reason);
    stop();
    elements.scanner.classList.add('typing', 'no-camera');
    setStatus(VuFind.translate('checkout_scan_failed'), true);
    if (elements.input) {
      // Only now that it is on screen: a required field that cannot be seen
      // blocks submission with nothing to focus and no way to fix it.
      elements.input.required = true;
      elements.input.focus();
    }
  }

  /**
   * Handle an accepted barcode: show it and put it where the form can post
   * it.
   * @param {string} code Decoded barcode
   */
  function accept(code) {
    if (elements.input) {
      elements.input.value = code;
    }
    setStatus(VuFind.translate('checkout_scan_success', {'%%code%%': code}), false);
    if (elements.status) {
      elements.status.prepend(VuFind.spinnerElement(), ' ');
    }
    // Release the camera before handing off, so it is not still running if
    // the post navigates away.
    stop();
    elements.scanner.classList.add('submitting');
    if (elements.form) {
      elements.form.submit();
    }
  }

  /**
   * Draw the current camera frame into the reusable frame canvas.
   * @returns {boolean} Whether a frame was available
   */
  function grabFrame() {
    var vw = video ? video.videoWidth : 0;
    var vh = video ? video.videoHeight : 0;
    if (!vw || !vh) {
      return false;
    }
    var height = Math.min(vh, MAX_PROCESS_HEIGHT);
    if (frame.width !== vw || frame.height !== height) {
      frame.width = vw;
      frame.height = height;
    }
    frameCtx.drawImage(video, 0, 0, vw, vh, 0, 0, vw, height);
    return true;
  }

  /**
   * Decode one frame and, once the same plausible barcode has come back
   * enough times in a row, accept it.
   */
  function scanFrame() {
    if (state !== 'running' || !grabFrame()) {
      return;
    }
    var code = decode(new ZXing.HTMLCanvasElementLuminanceSource(frame));
    if (code === null || !pattern.test(code)) {
      return;
    }
    var now = performance.now();
    if (code !== pendingCode || now - pendingSince > MATCH_WINDOW_MS) {
      pendingCode = code;
      pendingCount = 0;
    }
    pendingCount++;
    pendingSince = now;
    if (pendingCount >= REQUIRED_MATCHES) {
      accept(code);
    }
  }

  /**
   * Queue the next frame, leaving the main thread free in between.
   * Scheduling from the end of one frame rather than on a timer means a slow
   * device scans less often instead of falling behind.
   */
  function scheduleScan() {
    timer = setTimeout(function tick() {
      scanFrame();
      if (state === 'running') {
        scheduleScan();
      }
    }, SCAN_GAP_MS);
  }

  /**
   * Build the video element and start scanning once the camera hands us a
   * stream.
   * @param {MediaStream} media The camera stream
   */
  function onStream(media) {
    if (state !== 'starting') {
      // Stopped while the permission prompt was up, so this stream was never
      // attached to anything.
      releaseStream(media);
      return;
    }
    // Stays 'starting' until the scan loop is up, so that stop() knows there
    // is a camera to release and start() will not open a second one.
    video = document.createElement('video');
    // playsinline or iOS Safari takes the stream full screen; autoplay to
    // start it without a gesture, which browsers only allow while muted.
    video.setAttribute('playsinline', '');
    video.muted = true;
    video.autoplay = true;
    video.srcObject = media;
    elements.preview.appendChild(video);

    // The scan loop has not started, so `timer` is free to hold the watchdog:
    // a pending startup and a pending scan cannot both exist, and stop()
    // clears whichever it finds.
    timer = setTimeout(function cameraTimedOut() {
      fallBackToTyping('camera never reported a frame size');
    }, CAMERA_TIMEOUT_MS);

    video.addEventListener('loadedmetadata', function ready() {
      // Some browsers fire this again if the track renegotiates, which would
      // otherwise leave a second scan timer behind and scan twice a frame.
      // Anything but 'starting' also covers being stopped before the camera
      // settled, which is the other way this arrives too late to act on.
      if (state !== 'starting') {
        return;
      }
      // Before scheduleScan() takes `timer` over for the scan loop.
      clearTimeout(timer);
      state = 'running';
      frame = document.createElement('canvas');
      // getImageData runs on every frame, and without this hint the browser
      // keeps the surface on the GPU and reads it back each time.
      frameCtx = frame.getContext('2d', { willReadFrequently: true });
      fitPreviewToStrip(video.videoWidth, video.videoHeight);
      scheduleScan();
      elements.scanner.classList.add('scanning');
      elements.toggle.textContent = VuFind.translate('checkout_scan_stop');
      setStatus(VuFind.translate('checkout_scan_aim'), false);
    });
    video.play().catch(fallBackToTyping);
  }

  /**
   * Open the camera.
   */
  function start() {
    if (state !== 'idle') {
      return;
    }
    if (typeof ZXing === 'undefined') {
      fallBackToTyping('zxing-js did not load');
      return;
    }
    // getUserMedia is only exposed in a secure context.
    if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
      fallBackToTyping('this browser cannot reach the camera');
      return;
    }
    state = 'starting';
    setStatus(VuFind.translate('checkout_scan_starting'), false);
    navigator.mediaDevices.getUserMedia({
      audio: false,
      video: {
        // Pixels across the barcode are what decide whether it reads, so ask
        // for more than a laptop can give: `ideal` means a camera capped
        // lower just returns what it has.
        width: { ideal: 2560 },
        facingMode: { ideal: 'environment' },
        // An advanced set is applied in full or skipped, never rejected, so
        // asking firmly for continuous focus cannot cost us the camera.
        advanced: [{ focusMode: 'continuous' }]
      }
    }).then(onStream).catch(fallBackToTyping);
  }

  /**
   * Wire up the scanner if its markup is present on the page.
   */
  function init() {
    elements.scanner = document.querySelector('#checkout-scanner');
    if (!elements.scanner) {
      return;
    }
    elements.preview = elements.scanner.querySelector('#scanner-preview');
    elements.toggle = elements.scanner.querySelector('#scanner-toggle');
    elements.status = elements.scanner.querySelector('#scanner-status');
    elements.form = document.querySelector('#checkout-form');
    elements.input = document.querySelector('#barcode');
    if (!elements.preview || !elements.toggle) {
      return;
    }

    if (elements.scanner.dataset.barcodePattern) {
      pattern = new RegExp(elements.scanner.dataset.barcodePattern);
    }

    // getUserMedia needs a user gesture on iOS, so never autostart.
    elements.toggle.addEventListener('click', function toggleScanner() {
      if (state === 'idle') {
        start();
      } else {
        stop();
        setStatus('', false);
      }
    });
    // Release the camera if the user navigates away or backgrounds the tab.
    window.addEventListener('pagehide', stop);
  }

  return { init: init, start: start, stop: stop };
});
