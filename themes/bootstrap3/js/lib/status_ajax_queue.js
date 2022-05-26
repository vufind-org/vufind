class StatusAjaxQueue {
  isRunning = false;
  queue = [];
  payload = [];

  constructor({ run, success, failure, delay }) {
    this.runFn = run;
    this.successFn = success;
    this.failureFn = failure;

    // move once Javascript is modularized
    function debounce(func, delay) {
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

    this.runPayload = debounce(async function runPayloadCore() {
      this.isRunning = true;

      this.runFn(this.payload)
        .then((...res) => this.successFn(this.payload, ...res))

        .catch((...error) => this.failureFn(this.payload, ...error))

        .finally(() => {
          this.isRunning = false;

          this.payload = [];

          if (this.queue.length > 0) {
            this.payload = this.queue;

            this.queue = [];

            this.runPayload();
          }
        });
    }, delay ?? 300);
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
