// .includes is used e.g. in organisation info
if (!String.prototype.includes) {
  String.prototype.includes = function includes(search, start) {
    'use strict';

    if (search instanceof RegExp) {
      throw TypeError('first argument must not be a RegExp');
    }
    return this.indexOf(search, typeof start !== 'undefined' ? start : 0) !== -1;
  };
}
