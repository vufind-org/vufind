// Event stubs
document.createEvent = function() { return { initCustomEvent: function() {} } };
document.dispatchEvent = function() { return true; }
