<?php	 	
// Site name
define('siteNAME','Web Proxy');

// Site skin
define('siteSKIN','proxywebpack');

// URL to proxy directory
// By default we try and determine this automatically.
// If you're having problems with generated URLs, you
// can manually set the URL here (this should point to the
// directory in which the proxy was uploaded and should finish
// with a trailing slash
define('optURL', findURL() );

// Warn users before browsing an https site if the proxy is not also on https
define('optSSLWARN',true);

// Use gzip compression
define('optGZIP',true);

// Encode index page to avoid detection? Increases load and bandwidth usage
// but probably insignificantly compared with resources used by proxified pages
define('optENCODEINDEX',false);

// Prevent hotlinking - users who attempt to browse a proxified page without first
// visiting the index page will be redirected.
define('optSTOPHOTLINK',true);

// Allow the following domains to hotlink:
$allowedDomains = array();

// Max file size - preserve bandwidth by limiting the filesize of files downloaded
// / pages viewed through the proxy. This value is in bytes. Set to 0 for unlimited.
define('optMAXSIZE',0);

// Do we want to use any plugins?
define('optPLUGINS',true);

// URL encoding options
// Path info available? Allows browse.php/aHr3i0fsde/33rds/dtd/ style URLs
define('optPATHINFO', false);
// Generate unique URLs for each visitor
define('optUNIQUEURL', false);

// Footer include - add the following to the bottom of proxified pages
// Useful for Google Analytics tracking code
define('optFOOTER', '');

##########################################
## Cache options
##########################################

// Use caching for the predetermined site and file type lists?
// You must ensure the cache directory (as specified later in
// optCACHEPATH) is writable to
define('optUSECACHE',true);

// Cache for the file extensions listed below:
$cacheTypes = array('css','jpg','jpeg','png','gif','js','flv','zip');

// Cache all sites? Else use the list in the next option
define('optCACHEALL',true);

// Cache for sites listed below:
$cacheSites = array('myspace.com','google.com','facebook.com','bebo.com');

// Absolute path to cache directory. If you run multiple
// proxies on one server, you can have them all share one
// global cache.
define('optCACHEPATH',proxyPATH . 'cache/');
// Absolute URL to cache directory.
define('optCACHEURL',optURL . 'cache/');

##########################################
## Access options
##########################################

## Sites allowed

// You can control access to particular sites through your proxy through
// EITHER a whitelist or a blacklist. If you have any sites on the whitelist,
// these will be the ONLY sites accessible. If you have any sites on the blacklist,
// these will be the ONLY sites NOT accessible.
// The strings added to either array will be compared with the requested URL and
// a match can occur anywhere in the string. This is the extent of the access
// features - it is therefore advisable not to use a full URL unless desired
// since 'http://www.proxywebpack.com/proxy/' would not match 'http://proxywebpack.com/proxy/'
// Usage examples:
// 'proxywebpack.com' 				to block the whole proxywebpack.com site
// 'proxywebpack.com/downloads/'		to block every page within the 'downloads' directory
// 'sub.proxywebpack.com' 			to block only the subdomain "sub"
// 'proxywebpack.com/downloads/index.php' 	to block only the /downloads/index.php page

$whiteList = array();
$blackList = array();

// Add sites to either array by using the format:
// $listName[] = 'http://url.to/site';

## Users allowed
// You can IP ban users using the proper address format. (see ip2long() in php manual)
// It is recommended to set up IP bans through the setup wizard.
$ipBanList = array();
$ipBanRange = array();

##########################################
## Parser options
##########################################

// Proxify the following attributes. Within an element, if it contains
// any of these attributes, the value of that attribute will be changed
// so that the URL is fetched through the proxy.
define('optREPLACE','href|src|background');

// Remove all elements listed below. Separate with the pipe symbol |
// Suggested use to remove heavy bandwidth objects with little value
// i.e. movies, flash objects, etc.
define('optREMOVE','');

// When no content-type is sent, assume HTML or send original?
define('optPARSEALL',true);

// Some network filters read content and block keywords. Mask keywords
// by replacing with ###
$badWords = array();

##########################################
## Logging options
##########################################

// Full path to log. Set to file path for single file log. Set to directory path
// for one file per day log. Leave blank for no logging.
define('optLOGGING','');

// Log all vs log .html only
define('optLOGALL',false);

// Log time - set to 0 to log indefinitely, otherwise logging will occur only
// until this time. Format is a unix timestamp.
define('optLOGUNTIL',0);

##########################################
## Internal options
##########################################

// Session name
define('optSESSION','s');

// Cookie prefix for storing proxified cookies
define('optCOOKIE','a'); // the shorter it is, less wasted space in Cookie: header

// Time to wait for connection
define('optCONNECTTIMEOUT',30);

// Time to wait for whole transfer
define('optTIMEOUT',60);

##########################################
## User configurable options
##########################################

// This options can be set per-user via the original form or on the
// included form. You can change these below.
// title   = text displayed to user next to option
// default = the value to use unless overridden by the user
// force   = this allows you to remove the user choice and force the default

$optionsDetails = array(
        'encodeURL'     =>	array(
                                    'title'	=>  'Encode URL',
				    'default'	=>  true,
				    'desc'      =>  'This encodes the URL of the page you are viewing so that it does not contain the target site in plaintext. This helps to avoid blocking from network filters.',
				    'force'	=>  false
				    ),
	'encodePage'	=>	array(
                                    'title'	=>  'Encode Page (beta)',
                                    'default'	=>  false,
                                    'desc'	=>  'This helps avoid filters by encoding the page before sending it and decoding it with javascript once received. Existing scripts within pages may not function correctly in all browsers.',
                                    'force'	=>  false
                                    ),
	'showForm'	=>	array(
				    'title'	=>  'Show Form',
				    'default'	=>  true,
				    'desc'	=>  'This provides a mini form at the top of each page to allow you to quickly jump to another site without returning to our homepage.',
				    'force'	=>  true
                                    ),
	'allowCookies'	=>	array(
				    'title'	=>  'Allow Cookies',
				    'default'	=>  true,
                                    'desc'	=>  'Websites often require the use of cookies for actions such as logging in. If you need to access such websites, keep this enabled.',
                                    'force'	=>  false
                                    ),
	'tempCookies'	=>	array(
                                    'title'	=>  'Force Temporary Cookies',
                                    'default'	=>  true,
                                    'desc'	=>  'A lot of cookies are sent by ad servers and used to track your browsing habits. This options overrides the expiry date for all cookies and sets it to at the end of the session only - all cookies will be deleted when you shut your browser.',
                                    'force'	=>  true
                                    ),
	'stripJS'	=>	array(
                                    'title'	=>  'Remove Scripts',
                                    'default'	=>  true,
                                    'desc'	=>  'Most sites will downgrade properly when javascript is not available and display pages in an easier to parse format for the proxy. Unless you are having problems or know javascript is required, it is recommended to keep scripts disabled.',
                                    'force'	=>  false
                                    ),
	'stripImages'	=>	array(
                                    'title'	=>  'Remove Images',
                                    'default'	=>  false,
                                    'desc'	=>  'Want faster browsing? Remove inline images from webpages.',
                                    'force'	=>  false
                                    ),
	'stripFlash'	=>	array(
                                    'title'	=>  'Remove Flash',
                                    'default'	=>  false,
                                    'desc'	=>  'After the flash object has been downloaded through the proxy, it may communicate directly with the server and you will no longer be anonymous.',
                                    'force'	=>  false
                                    )
);

?>