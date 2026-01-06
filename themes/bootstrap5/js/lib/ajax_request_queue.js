/* exported AjaxRequestQueue */
class AjaxRequestQueue {
  /**
   * Constructor
   * @param {object}   options           The options for the queue.
   * @param {Function} options.run       A function that takes an array of items and returns a Promise.
   * @param {Function} [options.success] A callback function for a successful run.
   * @param {Function} [options.failure] A callback function for a failed run.
   * @param {number}   [options.delay]   The debounce delay in milliseconds (default = 300).
   */
  constructor({ run, success, failure, delay }) {
    // Status
    this.isRunning = false;
    this.queue = [];
    this.payload = [];

    // Debounce
    this.timeout = null;
    this.delay = delay || 300;

    // Function definitions
    const noop = () => {};
    const promiseNoop = () => Promise.reject();

    this.runFn = run ? run : promiseNoop;
    this.successFn = success ? success : noop;
    this.failureFn = failure ? failure : noop;
  }

  /**
   * Add an object to the request queue or payload.
   * @param {*} obj The item to add to the queue.
   */
  add(obj) {
    if (this.isRunning) {
      this.queue.push(obj);
    } else {
      this.payload.push(obj);

      this.runPayload();
    }
  }

  /**
   * Start or reset the debounce timer for running the payload.
   */
  runPayload() {
    clearTimeout(this.timeout);
    this.timeout = setTimeout(() => this.runPayloadImpl(), this.delay);
  }

  /**
   * Execute the payload, handling success and failure.
   */
  runPayloadImpl() {
    this.isRunning = true;

    this.runFn(this.payload)
      .then((...res) => this.successFn(this.payload, ...res))

      .catch((...error) => {
        console.error(...error);
        this.failureFn(this.payload, ...error);
      })

      .finally(() => {
        this.isRunning = false;

        this.payload = [];

        if (this.queue.length > 0) {
          this.payload = this.queue;

          this.queue = [];

          this.runPayload();
        }
      });
  }
}
