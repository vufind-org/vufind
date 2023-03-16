class AjaxRequestQueue {
  constructor({ run, success, failure, delay }) {
    this.isRunning = false;
    this.queue = [];
    this.payload = [];

    const noop = () => {};
    const promiseNoop = () => Promise.reject();

    this.runFn = run ? run : promiseNoop;
    this.successFn = success ? success : noop;
    this.failureFn = failure ? failure : noop;

    // TODO: move once Javascript is modularized
    function debounce(func, delay = 300) {
      let timeout;

      return function () {
        clearTimeout(timeout);

        const context = this;
        const args = arguments;

        timeout = setTimeout(function () {
          func.apply(context, args);
        }, delay);
      };
    }

    this.runPayload = debounce(function runPayloadCore() {
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
    }, delay);
  }

  add(obj) {
    if (this.isRunning) {
      this.queue.push(obj);
    } else {
      this.payload.push(obj);

      this.runPayload();
    }
  }
}
