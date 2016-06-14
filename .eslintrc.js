module.exports = {
  extends: "eslint:recommended",
  env: { "browser": true },
  globals: {
    "$": false,
    "jQuery": false
  },
  rules: {
    // errors
    "block-scoped-var": "error",
    "func-names": "error",
    "no-loop-func": "error",
    "no-param-reassign": "error",
    "no-shadow": "error",
    "no-unused-expressions": "error",

    // warnings
    "dot-notation": "warn",
    "eqeqeq": ["warn", "smart"],
    "guard-for-in": "warn",
    "key-spacing": ["warn", { "beforeColon": false, "afterColon": true }],
    "no-lonely-if": "warn",
    "no-console": ["warn", { "allow": ["warn", "error"] }],
    "no-unneeded-ternary": "warn",

    // fixed automatically
    "block-spacing": ["warn", "always"],
    "comma-spacing": ["warn", { "before": false, "after": true }],
    "indent": ["error", 2],
    "keyword-spacing": ["warn", { "before": true, "after": true }],
    "linebreak-style": ["error", "unix"],
    "no-multi-spaces": "warn",
    "semi-spacing": ["warn", { "before": false, "after": true }],
    "space-infix-ops": "warn"
  }
};
