/* jquery-rotate-0.1.js		August 4th 2011
 * A simple plugin to do cross-browser CSS rotations with backwards compatibility
 *
 * Written by Austen Hoogen
 * http://www.austenhoogen.com
 *
 * This plugin is Open Source Software released under GPL v3
 *
 * This software comes with no guarantees. Use at your own risk.
 * Use your attorney fees for good, not evil. Donate to a charity instead. 
 */
 
(function( $ ){
	$.fn.rotate = function(degrees){
		if ($.browser.msie) {
			// This fix unearthed from:
			// http://msdn.microsoft.com/en-us/library/ms533014%28v=vs.85%29.aspx
			// A simple explanation that [MXX] uses the sine and cosine of radians
			// instead of degrees would have sped up the search quite a bit... 
			// But why would we want adequate and verbose documentation??
			// Who enjoys actually getting work done anyway?? Srsly...
			deg2radians = Math.PI * 2 / 360;
			rad = degrees * deg2radians ;
			costheta = Math.cos(rad);
			sintheta = Math.sin(rad);
			 
			M11 = costheta;
			M12 = -sintheta;
			M21 = sintheta;
			M22 = costheta;
			
			msUglyStepdaughterCode = "progid:DXImageTransform.Microsoft.Matrix(";
			msUglyStepdaughterCode += "M11=" + M11 + ", M12=" + M12 + ", M21=" + M21 + ", M22=" + M22;
			msUglyStepdaughterCode += ", sizingMethod='auto expand')"
			
			this.css("-ms-transform","rotate(" + degrees + "deg)");
			this.css("filter",msUglyStepdaughterCode);
			this.css("zoom","1");
		} else if ($.browser.webkit) {
			this.css("-webkit-transform","rotate(" + degrees + "deg)");
		} else if ($.browser.opera) {
			this.css("-o-transform","rotate(" + degrees + "deg)");
		} else if ($.browser.mozilla) {
			this.css("-moz-transform","rotate(" + degrees + "deg)");
		} else {
			this.css("transform","rotate(" + degrees + "deg)");
		}
		return this;
	};
})( jQuery);