/* exported AjaxRequestQueue */
class AjaxRequestQueue {
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

  add(obj) {
    if (this.isRunning) {
      this.queue.push(obj);
    } else {
      this.payload.push(obj);

      this.runPayload();
    }
  }

  runPayload() {
    clearTimeout(this.timeout);
    this.timeout = setTimeout(() => this.runPayloadImpl(), this.delay);
  }

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
