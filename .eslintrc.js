module.exports = {
  plugins: ["no-jquery"],
  ignorePatterns: [
    "themes/**/vendor/**",
    "themes/**/node_modules/**"
  ],
  extends: ["eslint:recommended", "plugin:no-jquery/deprecated"],
  env: {
    "browser": true,
    "es6": true,
    "jquery": true
  },
  rules: {
    // errors
    "block-scoped-var": "error",
    "func-names": ["error", "as-needed"],
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
    "space-infix-ops": "warn",

    // the following is required for Bootstrap 3 collapse:
    "no-jquery/no-support": "off"
  }
};
