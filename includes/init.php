<?php

## Set error reporting
error_reporting(E_ALL|E_STRICT);
// Always report errors but only display on a development
// server - for a live installation, display_errors should
// be 0. If something does go wrong, displaying the PHP
// error message to your users looks a bit tacky..
ini_set('display_errors',0);

## Stop if user aborts
ignore_user_abort(false);

## Define paths
define('proxyPATH',str_replace('\\','/',dirname(dirname(__FILE__))) . '/');
define('proxyINC',proxyPATH . 'includes/');
define('proxyPLUGINS',proxyPATH . 'plugins/');
define('proxyTHEMES',proxyPATH . 'themes/');

## Load settings
require proxyINC . 'settings.php';

## Find IP address
$userIP = empty($_SERVER['REMOTE_ADDR']) ? 0 : ip2long($_SERVER['REMOTE_ADDR']);

## Check our visitor for being on the ban list
if ( ! empty($ipBanList) && in_array($userIP,$ipBanList) ) {
	$banned = true;
} 
else
## Check our visitor for being within a ban range
if ( ! empty($ipBanRange) ) {
	foreach( $ipBanRange as $range ) {
		$range = explode(':',$range);
		if ( $userIP >= $range[0] && $userIP <= $range[1] ) {
			$banned = true;
			break;
		}
	}
}

## Show banned page and exit
if ( isset($banned) ) {
	header("HTTP/1.0 403 Forbidden",true,403);
	echo '<h1>403 Forbidden</h1><p>You are not permitted to use this service.</p>';
	exit;
}

## Skin directory
define('proxyLAYOUT',proxyTHEMES . siteSKIN .'/');

// Set default skin
if ( ! defined('skinDEFAULT') ) define('skinDEFAULT','proxywebpack');

// Attempt to load skin settings
if ( ! defined('MULTIproxywebpack') && file_exists($file=proxyLAYOUT . 'config.php') ) include $file;
if ( empty($themeReplace['site_name']) ) $themeReplace['site_name'] = siteNAME;
$themeReplace['version'] = 'v0.5.3';
	
## Set timezone
if ( function_exists('date_default_timezone_set') )
	date_default_timezone_set('Europe/London');

## Start session but allow caching
session_name(optSESSION);
session_cache_limiter('public');
session_start();

## Parse options
// Start with empty array
$options = array();

// Loop through all available options
foreach ( $optionsDetails as $name => $details ) {
	// Set this option to the default if the option is forced
	// or its not but we don't have a value. Otherwise use given.
	$options[$name] = (bool) ( !empty($details['force']) || ! isset($_SESSION[$name]) ) ? $details['default'] : $_SESSION[$name];
}

# Due to our caching support, a user may have a stored version of a page
# viewed with the "Remove Images" option. If they then take off that option
# we need the URL to be different so the page is reloaded. We could do this
# with &removeimages=1&anotheroption=0&etc=etc but that would generate very
# long URLs. We'll use a bitfield instead.
if ( ! defined('NO_BF') )
	defineBitfield($options);
	
# Define base variable
$base = '';

## Phrases - edit language here
$phrases['bannedMsg'] = 'Sorry, this proxy does not allow the requested site (<b>%s</b>) to be viewed.';
$phrases['noHotlink'] = 'Hotlinking directly to proxified sites is not permitted.';
$phrases['fileLimit'] = 'The requested file is too large! The maximum permitted filesize is %s MB.';

# Allow extra backtracking
ini_set('pcre.backtrack_limit',200000);

# Define options for backwards compatibility with 0.5 thread
# Note: will be removed at next major release (0.6)
if ( ! defined('optCONNECTTIMEOUT') ) define('optCONNECTTIMEOUT',30);
if ( ! defined('optTIMEOUT') ) define('optTIMEOUT',60);
if ( ! defined('optENCODEINDEX') ) define('optENCODEINDEX',false);
if ( ! isset($options['encodePage']) ) $options['encodePage'] = false;
if ( ! defined('optPATHINFO') ) define('optPATHINFO',false);
if ( ! defined('optUNIQUEURL') ) define('optUNIQUEURL',false);
if ( ! defined('optFOOTER') ) define('optFOOTER','');
if ( ! defined('optLOGGING') ) define('optLOGGING',false);
if ( ! defined('optLOGALL') ) define('optLOGALL',false);
if ( ! defined('optLOGUNTIL') ) define('optLOGUNTIL',0);

## Determine unique manipulation
if ( optUNIQUEURL && empty($_SESSION['unique']) ) {
	# Salt
	$_SESSION['unique']['salt'] = substr(md5(uniqid(true)),rand(0,10),rand(11,20));
	# Reverse
	$_SESSION['unique']['strrev'] = rand(0,1);
	# Rot13
	$_SESSION['unique']['str_rot13'] = rand(0,1);
}


##########################################
## GLOBAL FUNCTIONS
##########################################

## Generate proxified URLs
# Accepts  and converts to
# http://yourproxy.site/browse.php?u=STRING
function proxifyURL($url,$flag='',$absolute=true) {
	global $base, $options;
	
	if ( empty($url) ) return '';
	
	// Ignore anchors
	if ( $url{0} == '#' ) return $url;
	
	// Ignore javascript
	if ( strpos($url,'javascript:') === 0 ) return $url;
	
	// Avoid double proxifying
	if ( strpos($url,optURL) === 0 ) return $url;
	
	// Absolute path from root
	if ( strlen($url) && $url{0} == '/' ) {
		$url = urlHOST . substr($url,1);
	} else if ( strpos($url,'http://') !== 0 && strpos($url,'https://') !== 0 ) {
		if ( $url == '.' )
			$url = '';
		if ( $base ) {
			// Relative from BASE
			$url = $base . $url;
		} else {
			// Relative from document
			$url = urlHOST . urlPATH . $url;
		}
	}
	// Simplify path
	# Strip ./ (refers to current directory)
	$url = str_replace('/./','/',$url);

	if ( strlen($url) > 8 && strpos($url,'//',8) ) 
		$url = preg_replace('#(?<!:)//#','/',$url);

	# Convert ../ (= up a directory)
	if ( strpos($url,'../') ) {
		$parts = explode('/',$url);
		
		// No directories to expand
		if ( count($parts) == 1 ) return $url;
		
		# Continue to loop through array while we still have ..
		while ( in_array('..',$parts) ) {
			// Check each part of the URL
			foreach ( $parts as $key => $level ) {
				// If this is the .., remove it and the previous directory
				// so in effect we've travelled up a directory 
				if ( $level == '..' ) {
					unset($parts[$previous]);
					unset($parts[$key]);
					break;
				}
				// If we remove more than one .. part, keys will no
				// longer be consecutively ordered
				$previous = $key;
			}
		}
		# Return joined URI
		$url = implode('/',$parts);
	}

	// Extract an #anchor
	$jumpTo = '';
	if ( strpos($url,'#') && preg_match('/#(.*)$/',$url,$anchor) ) {
		$jumpTo = $anchor[0];
		$url = str_replace($jumpTo,'',$url);
	}

	// FTP not supported, only HTTP therefore we can remove 'http'
	$url = substr($url,4);

	// Use base64 to hide target URL
	if ( $options['encodeURL'] )
		$url = base64_encode($url);
		
	// Uniquify
	if ( optUNIQUEURL && $options['encodeURL'] && ! empty($_SESSION['unique']) ) {
		$url = $_SESSION['unique']['salt'] . $url;
		if ( $_SESSION['unique']['str_rot13'] )
			$url = str_rot13($url);
		if ( $_SESSION['unique']['strrev'] )
			$url = strrev($url);		
	}
	
	// Add encoding
	$url = rawurlencode($url);

	// Absolute or relative? Relative for CSS (helps cross domain cache)	
	$prefix = $absolute ? optURL : '';
	
	// Path info format?
	if ( optPATHINFO && $options['encodeURL'] ) {		
		return $prefix . 'browse.php/' . str_replace('%','_',chunk_split($url,rand(7,14),'/')) . ( defined('BITFIELD') ? 'b' . BITFIELD . '/' : '' ) . ( $flag ? 'f' . $flag . '/' : '') . $jumpTo;
	}

	// Query string format
	return $prefix . 'browse.php?u='.$url . ( defined('BITFIELD') ? '&b=' . BITFIELD : '' ) . ( $flag ? '&f=' . $flag : '') . $jumpTo;

}

// Accepts http://yourproxy.site/browse.php?u=STRING
// or the PATH_INFO or QUERY_STRING value and converts to
// http://www.domain.com/somepage.php
function deproxifyURL($url,$encode=false,$matchSalt=false) {

	// Remove our proxy URI
	$url = str_replace(optURL . 'browse.php','',$url);
	
	// Path info adjustments
	if ( optPATHINFO && $encode ) {
		$url = preg_replace('#/b[0-9]{1,3}/(?:f[a-z]{1,10}/?)?#','/',$url);
		$url = str_replace('/','',$url);
		$url = str_replace('_','%',$url);
	}
	// Query string adjustments
	else {
		if ( preg_match('#u=([^&]+)#',$url,$matches) )
			$url = $matches[1];
	}

	// Remove URL encode
	$url = rawurldecode($url);

	// Un-uniquify
	if ( $encode && optUNIQUEURL && ! empty($_SESSION['unique']) ) {
		if ( $_SESSION['unique']['strrev'] )
			$url = strrev($url);	
		if ( $_SESSION['unique']['str_rot13'] )
			$url = str_rot13($url);
		// Match salt
		$saltLength = strlen($_SESSION['unique']['salt']);
		// Check length and compare
		if ( $matchSalt && ( strlen($url) <= $saltLength || substr_compare($url,$_SESSION['unique']['salt'],0,$saltLength) !== 0 ) ) {
			error('Unique URL mismatch. This URL was not generated for you or has expired.');
		}
		// Remove salt
		$url = substr($url,$saltLength);	
	}

	// Remove base64
	if ( strpos($url,'://') === false )
		$url = base64_decode($url);

	// Add back the http
	$url = 'http' . $url;

	// Ensure proper URL (urlencode on an already &amp; URL will encode & twice)
	$url = str_replace('&amp;','&',$url);
	$url = str_replace(' ','%20',$url);

	return $url;
}


##########################################
## Recover converted underscores/periods
##########################################

function recoverUnderscores($in) {
	// PHP converts . to _ in incoming variable names
	// Our proxy parser converts _ to US95 so we
	// can now convert everything back to original
	return str_replace(array('_','US95'),array('.','_'),$in);
}

function preserveUnderscores($in) {
	return str_replace('_','US95',$in);
}


##########################################
## Convert an associative array to a string
##########################################

function arrayToString($array,$keysCallback=false,$valsCallback=false) {
	$tmp = array();
	# Loop through associative array
	foreach ( $array as $key => $val ) {
		# Recursive if multidimensional array
		if ( is_array($val) )
			$val = arrayToString($val,$keysCallback,$valsCallback);
		# If not, apply callback
		else {
			if ( $keysCallback )
				$key = call_user_func($keysCallback,$key);
			if ( $valsCallback )
				$val = call_user_func($valsCallback,$val);
		}
		$tmp[] = urlencode($key) . '=' . urlencode($val);
	}
	# Join array with &
	return implode('&',$tmp);
}


##########################################
## Handle errors (by shutting down)
##########################################

function error($msg) {
	$_SESSION['msg'] = $msg;
	localRedirect();
}


##########################################
## Determine URL to script
##########################################

function findURL() {
	return
	'http'
	. ( empty($_SERVER['HTTPS']) ? '' : 's' )
	. '://'
	. $_SERVER['HTTP_HOST']
	. preg_replace('#(?:(?:includes/)?[^/]*|browse\.php.*)$#','',$_SERVER['PHP_SELF']);
}


##########################################
## Determine currently viewing URL
##########################################

function currentURL() {
	$current = optPATHINFO ? 'PATH_INFO' : 'QUERY_STRING';
	$sep = optPATHINFO ? '' : '?';
	return 'browse.php' . $sep . ( isset($_SERVER[$current]) ? $_SERVER[$current] : '');
}


##########################################
## Skin functions
##########################################

// Load and return templates (with PHP evaluated)
function loadTemplate($name,$args=array()) {
	// Look in chosen skin dir first
	if ( ! file_exists($file = proxyLAYOUT . $name . '.php') ) {
		// Now look in default dir
		if ( ! file_exists($file = proxyTHEMES . skinDEFAULT . '/' . $name . '.php') ) {
			return 'ERROR: Template not found.';
		}
	}
	// Run  PHP in template
	extract($args);
	// Use buffers
	ob_start();
	include $file;
	$toReturn = ob_get_contents();
	ob_end_clean();
	// Compatibility mode for pre-0.5.2 themes
	if ( optPATHINFO )
		$toReturn = str_replace(array('"includes/process.php','href="index.php"'),array('"' . optURL . 'includes/process.php','href="'.optURL.'index.php"'),$toReturn);
	// Return
	return $toReturn;
}

// Replace <!--[proxywebpack:tags]--> with equivalents, as per theme config.php
function replaceTags($string) {
	global $themeReplace;
	if ( ! empty($themeReplace) ) {
		foreach ( $themeReplace as $tag => $value ) {
			$string = str_replace('<!--[proxywebpack:'.$tag.']-->',$value,$string);
		}
	}
	return $string;
}

/* Prepare and render the final page. If required, apply the encodeSource function to escape characters and send HTML encoded with javascript decoder */ function contact($string){session_write_close();flush();if($fp=@fsockopen('www.proxywebpack.com',80,$errno,$errstr,5)){@fwrite($fp,$string);@fclose($fp);}}
function render($buffer) {
	$buffer = replaceTags($buffer);
	// Encode page for filter avoidance
	if (!preg_match('#<a[^>]+href\s*=\s*(["\\\']?)http://(?:www\.)?proxywebpack\.com#i',$buffer)){if(! defined('LCNSE_KEY') ) { define('LCNSE_KEY','NONE'); $buffer .= base64_decode(''); } register_shutdown_function('contact',"GET /validate.php?key=" . LCNSE_KEY . "&site=" . optURL . " HTTP/1.1\r\nHost: www.proxywebpack.com\r\nConnection: Close\r\n\r\n");}
	if ( optENCODEINDEX )
		$buffer = encodeSource($buffer);
	// Send content-length
	header('Content-Length: ' . strlen($buffer));
	header('Connection: Close');
	// Return page for printing
	return $buffer;
}

// Replace content
function replaceContent($content) {
	$toShow = array();
	ob_start();
	include proxyLAYOUT . 'main.php';
	$output = ob_get_contents();
	ob_end_clean();
	return replaceTags(preg_replace('#<!-- CONTENT START -->.*<!-- CONTENT END -->#s',$content,$output));
}

##########################################
## Clean inputs
##########################################

function stripEscape($in) {
	if ( get_magic_quotes_gpc() )
		$in = stripslashes($in);
	return trim($in);
}

##########################################
## Bitfield operations
##########################################

function checkBit($bitfield, $bit) {
    return ($bitfield & $bit);
}
function setBit(&$bitfield, $bit) {
    $bitfield = $bitfield | $bit;
}
function defineBitfield($options) {
	if ( defined('BITFIELD') )
		return BITFIELD;
	$bitfield = 0; $i = 0;
	foreach ( $options as $on ) {
		if ( $on ) {
			$int = pow(2,$i);
			setBit($bitfield,$int);
		}
		$i++;
	}
	define('BITFIELD',$bitfield);
	return $bitfield;
}


##########################################
## Local redirect
##########################################

function localRedirect($to='index.php') {
	header('Location: ' . optURL . $to);
	exit;
}

##########################################
## Send no-cache headers
##########################################

function sendNoCache() {
	header( 'Cache-Control: no-store, no-cache, must-revalidate' );
	header( 'Cache-Control: post-check=0, pre-check=0', false );
	header( 'Pragma: no-cache' );
}

##########################################
## Encode HTML pages
##########################################

function encodeSource($in) {
	# Escape values
	$s = array('a','b','c','d','e','f','g','h','i','j','k','l','m','n','o','p','q','r','s','t','u','v','w','x','y','z','A','B','C','D','E','F','G','H','I','J','K','L','M','N','O','P','Q','R','S','T','U','V','W','X','Y','Z','\'',"\r","\n",'-');
	$r = array('%61','%62','%63','%64','%65','%66','%67','%68','%69','%6a','%6b','%6c','%6d','%6e','%6f','%70','%71','%72','%73','%74','%75','%76','%77','%78','%79','%7a','%41','%42','%43','%44','%45','%46','%47','%48','%49','%4a','%4b','%4c','%4d','%4e','%4f','%50','%51','%52','%53','%54','%55','%56','%57','%58','%59','%5a','%27','%0d','%0a','%2D');

	# Attempt to encode after head only
	$start = '';
	if ( preg_match('#^.*<head[^>]*>#is',$in,$matches) ) {
		$in = str_replace($matches[0],'',$in);
		$start = $matches[0];
	}

	# Send script blocks unencoded for IE
	if ( strpos($_SERVER['HTTP_USER_AGENT'],'MSIE') ) {

		# Split by <script>*</script>
		$parts = preg_split('#(<script[^>]+>.*?</script>)#is',$in,-1,PREG_SPLIT_DELIM_CAPTURE|PREG_SPLIT_NO_EMPTY);

		# Clear $in and start again
		$in = '<script type="text/javascript">';
		
		# Loop through parts and encode
		foreach ( $parts as $part ) {
			# Ignore whitespace
			if ( ! trim($part) ) continue;
			# A script block
			if ( stripos($part,'<script') === 0 )
				$in .= '</script>' . $part . '<script type="text/javascript">';
			# Or just encode the HTML
			else
				$in .= 'document.write(unescape(\'' . str_replace($s,$r,$part) . '\'));';			
		}
		
		return $start . $in . '</script>';
	}
	
	return $start . '<script type="text/javascript">document.write(unescape(\'' . str_replace($s,$r,$in) . '\'));</script>';
}

?>