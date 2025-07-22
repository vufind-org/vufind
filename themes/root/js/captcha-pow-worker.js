/**
 * @param {string} algo PHP hash algorithm key
 * @returns {string} JS Crypto algorithm key
 */
function phpAlgoToJS(algo) {
  const map = {
    "sha1": "SHA-1",
    "sha256": "SHA-256",
    "sha384": "SHA-384",
    "sha512": "SHA-512",
  };
  return algo in map ? map[algo] : algo;
}

/**
 * @param {string} hashAlgo SHA family algorithm (for now)
 * @param {string} challenge Hash from server
 * @param {number} nonce Incrementing number for concat
 * @returns {Promise<string>} Hash of challenge + nonce
 */
function hashChallenge(hashAlgo, challenge, nonce) {
  return new Promise((resolve, reject) => {
    let buffer = new TextEncoder().encode(`${challenge}${nonce}`);
    crypto.subtle.digest(phpAlgoToJS(hashAlgo), buffer.buffer).then((result) => {
      // convert buffer to byte array
      const bytes = Array.from(new Uint8Array(result));

      // convert bytes to hex string
      const hex = bytes
        .map((byte) => byte.toString(16).padStart(2, "0"))
        .join("");

      resolve(hex);
    }, reject);
  });
}

/**
 * @param  {MessageEvent} event Contains hash work parameters
 * @returns {void} Returns via postMessage
 */
function powWorkerMessage(event) {
  const { challenge, difficulty, hashAlgo, start } = event.data;
  const algo = phpAlgoToJS(hashAlgo);

  const startTime = Date.now();
  const target = "0".repeat(difficulty);

  /**
   * Recursive on Promise resolution
   * @param {number} nonce Incrementing number for hash concatenation
   * @returns {void}
   */
  function attemptHash(nonce) {
    hashChallenge(algo, challenge, nonce).then((result) => {
      if (result.startsWith(target)) {
        // Return to main thread
        self.postMessage({
          nonce,
          iters: nonce - start,
          ms: Date.now() - startTime,
        });
      } else {
        attemptHash(nonce + 1);
      }
    });
  }

  attemptHash(start);
}

self.addEventListener("message", powWorkerMessage);
