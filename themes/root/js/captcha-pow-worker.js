function hashChallenge(hashAlgo, challenge, nonce) {
  return new Promise((resolve, reject) => {
    let buffer = new TextEncoder().encode(`${challenge}:${nonce}`);
    crypto.subtle.digest(phpAlgoToJS(hashAlgo), buffer.buffer).then((result) => {
      // convert buffer to byte array
      const bytes = Array.from(new Uint8Array(result));

      // convert bytes to hex string
      const hex = bytes
          .map((byte) => byte.toString(16).padStart(2, "0"))
          .join("")

      resolve(hex);
    }, reject);
  });
}

function phpAlgoToJS(algo) {
  return {
    "sha1": "SHA-1",
    "sha256": "SHA-256",
    "sha384": "SHA-384",
    "sha512": "SHA-512",
  }[algo] ?? algo;
}


self.addEventListener("message", async (event) => {
  const { challenge, difficulty, hashAlgo, start } = event.data;
  const algo = phpAlgoToJS(hashAlgo);

  let nonce = start;
  const target = "0".repeat(difficulty);
  console.log(target);
  let attempt = await hashChallenge(algo, challenge, nonce);
  while (!attempt.startsWith(target)) {
    nonce += 1;
    attempt = await hashChallenge(algo, challenge, nonce);

    if (attempt.startsWith("0000")) {
      console.log(attempt);
    }
  }

  self.postMessage({
    nonce,
    iters: nonce - start,
  });
});
