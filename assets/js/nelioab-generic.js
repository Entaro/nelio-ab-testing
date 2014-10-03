if(typeof String.prototype.trim !== 'function') {
  String.prototype.trim = function() {
    return this.replace(/^\s+|\s+$/g, '');
  }
}

function nelioab_areCookiesEnabled() {
	document.cookie = "__verify=1;path=/";
	var supportsCookies = document.cookie.length > 1 &&
		document.cookie.indexOf("__verify=1") > -1;
	delete_cookie("__verify");
	return supportsCookies;
}

function nelioab_get_cookie_by_name(name) {
	var allCookies = document.cookie.split(';');
	for (var i = 0; i <= allCookies.length; ++i) {
		var cookie = allCookies[i];
		if (cookie == undefined)
			continue;
		var cookieName = cookie.substr(0, cookie.indexOf('=')).trim();
		var cookieVal = cookie.substr(cookie.indexOf('=')+1, cookie.length).trim();
		if (cookieName == name)
			return cookieVal;
	}
	return undefined;
}

function nelioab_get_local_cookies() {
	var result = "{";
	var allCookies = document.cookie.split(';');
	for (var i = 0; i <= allCookies.length; ++i) {
		var cookie = allCookies[i];
		if (cookie == undefined)
			continue;
		var cookieName = cookie.substr(0, cookie.indexOf('=')).trim();
		var cookieVal = cookie.substr(cookie.indexOf('=')+1, cookie.length).trim();
		if (cookieName.indexOf("nelioab_") == 0)
			result += " " + JSON.stringify(cookieName) + ":" +
				JSON.stringify(cookieVal) + ",";
	}
	if ( result[result.length-1] == "," )
		result = result.substring(0, result.length-1) + " ";
	result += "}";
	return JSON.parse(result);
}

function clean_cookies() {
	var allCookies = document.cookie.split(';');
	for (var i = 0; i <= allCookies.length; ++i) {
		var cookie = allCookies[i];
		if (cookie == undefined)
			continue;
		var cookieName = cookie.substr(0, cookie.indexOf('=')).trim();
		if (cookieName.indexOf("nelioab_") == 0)
			if (cookieName.indexOf("userid") == -1)
				delete_cookie(cookieName);
	}
}

function delete_cookie( name ) {
	var thePast = new Date(1985, 1, 1);
	document.cookie = name + "=1;path=/;expires=" + thePast.toUTCString();
}

function nelioab_add_hidden_fields_on_forms($) {
	$('input[name="input_nelioab_form_cookies"]').attr('name', 'nelioab_form_cookies');
	$('input[name="input_nelioab_form_current_url"]').attr('name', 'nelioab_form_current_url');

	$('input[name="nelioab_form_cookies"]').attr('value',
		encodeURIComponent( JSON.stringify( nelioab_get_local_cookies() )
			.replace( "'", "%27") )
		);
	$('input[name="nelioab_form_current_url"]').attr('value',
		encodeURIComponent( JSON.stringify( document.URL )
			.replace( "'", "%27") )
		);
}

function nelioab_nav($) {
	$.ajax({
		type:  'POST',
		async: true,
		url:   NelioABGeneric.ajaxurl,
		data: {
			action: 'nelioab_send_navigation',
			current_url: document.URL,
			ori_url: document.referrer,
			dest_url: window.location.href,
			nelioab_cookies: nelioab_get_local_cookies(),
		},
	});
}

function nelioab_nav_to_external_page($, external_page_link) {
	$.ajax({
		type:  'POST',
		async: true,
		url:   NelioABGeneric.ajaxurl,
		data: {
			action: 'nelioab_send_navigation',
			current_url: document.URL,
			ori_url: window.location.href,
			dest_url: external_page_link,
			nelioab_cookies: nelioab_get_local_cookies(),
			is_external_page: 'yes',
		},
	});
}

jQuery(document).ready(function() {
	if ( typeof nelioabActivateGoogleTagMgr == 'function' ) {
		var aux = setTimeout(nelioabActivateGoogleTagMgr,2000);
	}
});
