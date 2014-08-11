/*
 *	CSSrefresh v1.0.1
 *
 *	Copyright (c) 2012 Fred Heusschen
 *	www.frebsite.nl
 *
 *	Dual licensed under the MIT and GPL licenses.
 *	http://en.wikipedia.org/wiki/MIT_License
 *	http://en.wikipedia.org/wiki/GNU_General_Public_License
 */

/*
 * MINIFIED
 */

(function(){var e={array_filter:function(e,t){var n={};for(var r in e){if(t(e[r])){n[r]=e[r]}}return n},filemtime:function(e){var t=this.get_headers(e,1);return t&&t["Last-Modified"]&&Date.parse(t["Last-Modified"])/1e3||false},get_headers:function(e,t){var n=window.ActiveXObject?new ActiveXObject("Microsoft.XMLHTTP"):new XMLHttpRequest;if(!n){throw new Error("XMLHttpRequest not supported.")}var r,i,s,o,u=0;try{n.open("HEAD",e,false);n.send(null);if(n.readyState<3){return false}r=n.getAllResponseHeaders();r=r.split("\n");r=this.array_filter(r,function(e){return e.toString().substring(1)!==""});i=t?{}:[];for(o in r){if(t){s=r[o].toString().split(":");i[s.splice(0,1)]=s.join(":").substring(1)}else{i[u++]=r[o]}}return i}catch(a){return false}}};var t=function(){this.reloadFile=function(t){for(var n=0,r=t.length;n<r;n++){var i=t[n],s=e.filemtime(this.getRandom(i.href));if(i.last){if(i.last!=s){i.elem.setAttribute("href",this.getRandom(i.href))}}i.last=s}setTimeout(function(){this.reloadFile(t)},1e3)};this.getHref=function(e){return e.getAttribute("href").split("?")[0]};this.getRandom=function(e){return e+"?x="+Math.random()};var t=document.getElementsByTagName("link"),n=[];for(var r=0,i=t.length;r<i;r++){var s=t[r],o=s.rel;if(typeof o!="string"||o.length==0||o=="stylesheet"){n.push({elem:s,href:this.getHref(s),last:false})}}this.reloadFile(n)};t()})()