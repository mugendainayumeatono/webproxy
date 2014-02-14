<?php	 	
require 'includes/init.php';

define('DEBUG_MODE',0);


##########################################
## Check for page to proxify
##########################################

# Location of data depends on our options
$loadFrom = $options['encodeURL'] && optPATHINFO ? 'PATH_INFO' : 'QUERY_STRING';

# Load URL data into variable
$toLoad = ! empty($_SERVER[$loadFrom]) ? $_SERVER[$loadFrom] : false;

# If none, redirect to the form on the index page
if ( empty($toLoad) )
	localRedirect();

# Attempt to extract flag
if ( optPATHINFO && ! empty($_SERVER['PATH_INFO']) )
	$flag = preg_match('#/f([a-z]{1,10})/?$#', $toLoad, $matches ) ? $flag = $matches[1] : false;
else
	$flag = isset($_GET['f']) ? $_GET['f'] : false;


##########################################
## Prevent hotlinking
##########################################

if (  optSTOPHOTLINK ) {
	// Check referrer
	if ( isset($_SERVER['HTTP_REFERER']) && strpos($_SERVER['HTTP_REFERER'],'http') === 0 ) {
		// From our own site?
		if ( strpos($_SERVER['HTTP_REFERER'], str_replace(array('http://','https://','www.'),'',optURL)) !== false ) {
			$referrerOK = true;
		}
		// From an external site
		else {
			foreach( $allowedDomains as $domain ) {
				if ( strpos($_SERVER['HTTP_REFERER'], $domain) !== false ) {
					$referrerOK = true;
					break;
				}
			}
		}
		// Fail if not OK
		if ( ! isset($referrerOK) ) 
			error($phrases['noHotlink']);
	}
	// Check session
	if ( empty($referrerOK) && empty($_SESSION['viaIndex'] ) )
		error($phrases['noHotlink']);
}


##########################################
## Validate inputted URL
##########################################

## Extract URL
$toLoad = deproxifyURL($toLoad,$options['encodeURL'],true);

## Attempt to extract protocol, hostname and path
if ( ! preg_match('#^(https?)://([^/]+)/?([^?]*)#i',$toLoad,$tmp) ) {
	if ( $flag == 'frame' )
		die('<span style="font: 60% Verdana;">Unable to load URL. Return to <a href="' . optURL . '">index</a>.</span>');
	else
		error('Invalid URL');
}

## Ensure path has trailing slash (if path exists)
if ( ($path=$tmp[3]) && $path{strlen($path)-1} != '/' ) {
	if ( ($dirname=dirname($path)) == '.' )
		$dirname = '';
	$path = $dirname . '/';
}

## Define URL parts
define('urlPROTOCOL',$tmp[1]);
define('urlDOMAIN',$tmp[2]);
define('urlHOST',urlPROTOCOL.'://'.urlDOMAIN.'/');
define('urlPATH',$path);
define('urlFILENAME',basename($tmp[3]));

## Clear the tmp array
unset($tmp);


##########################################
## Check URL against permitted websites
##########################################

## If whitelist exists, deny unless current site is on list.
if ( ! empty($whiteList) ) {
	# Loop through all sites
	foreach ( $whiteList as $site ) {
		# If match found, set $accepted and exit loop
		if ( strpos($toLoad,$site) !== false ) {
			$accepted = true;
			break;
		}
	}
	# If no $accepted, site is not on whitelist
	if ( ! isset($accepted) )
		error( sprintf($phrases['bannedMsg'],urlDOMAIN) );
}

## If blacklist exists, deny if current site IS on list
if ( ! empty($blackList) ) {
	# Loop through all sites
	foreach ( $blackList as $site ) {
		# If match found, site is blocked so throw error
		if ( strpos($toLoad,$site) !== false ) {
			error( sprintf($phrases['bannedMsg'],$site) );
		}
	}
}

##########################################
## Ensure SSL is allowed
##########################################

if ( urlPROTOCOL == 'https' && empty($_SERVER['HTTPS']) && optSSLWARN && empty($_SESSION['sslwarned']) ) {
	// Store our requested page to return to (if we want)
	$_SESSION['return'] = currentURL();
	// Prevent caching
	sendNoCache();
	echo replaceTags(loadTemplate('sslwarning.page'));
	exit;
}


##########################################
## Check for cache
##########################################

# Define variables
$cacheName = '';

# Check cache enabled & filetype for allowed cache extensions
$ext = substr($toLoad,strrpos($toLoad,'.')+1);
if ( optUSECACHE && in_array($ext,$cacheTypes) ) {

	# Is it cache all or site specific?
	if ( optCACHEALL ) {
		$useCache = true;
	} else {
		# Loop through the sites we want to use caching for
		foreach ( $cacheSites as $site ) {
			if ( strpos($toLoad,$site) !== false ) {
				$useCache = true;
				break;
			}
		}
	}

	# Look in our cached directory for an appropriate file
	if ( isset($useCache) ) {
		# Generate cache name - encrypt with sha1 to avoid filters but keep the file extension
		$cacheName = sha1($toLoad) . '.' . $ext;
		# Look for the cached file
		$foundCache = file_exists(optCACHEPATH . $cacheName) ? optCACHEURL . $cacheName : false;
	}

	# Check for no-cache in Cache-Control or Pragma header
	# If none found, send to our cached version
	if ( ! empty($foundCache) && ! (isset($_SERVER['HTTP_CACHE_CONTROL']) && strpos($_SERVER['HTTP_CACHE_CONTROL'],'no-cache') !== false ) && ! (isset($_SERVER['HTTP_PRAGMA']) && strpos($_SERVER['HTTP_PRAGMA'],'no-cache') !== false ) ) {
		header('Location: ' . $foundCache);
		exit;
	}

}


//=============================================================================
//   ===    manage the REQUEST    =============================================
//=============================================================================

##########################################
## Set default options
##########################################

$toSet[CURLOPT_CONNECTTIMEOUT] = optCONNECTTIMEOUT; // Time to wait for connection
$toSet[CURLOPT_TIMEOUT] = optTIMEOUT; // Time to wait for whole operation
$toSet[CURLOPT_RETURNTRANSFER] = true; // Return transfer instead of printing
$toSet[CURLOPT_FAILONERROR] = true; // Saves bandwidth (most 404s will be just images, etc.)
$toSet[CURLOPT_ENCODING] = ''; // Accept encoding in any format (allows compressed pages to be downloaded => less bandwidth)
$toSet[CURLOPT_HTTPHEADER] = array('Expect:'); // Send custom headers (an empty Expect should prevent 100 continue responses)

// Set max filesize
if ( defined('CURLOPT_MAXFILESIZE') && optMAXSIZE ) $toSet[CURLOPT_MAXFILESIZE] = optMAXSIZE;

// Forward the custom headers
if ( isset($_SERVER['HTTP_USER_AGENT']) ) $toSet[CURLOPT_USERAGENT] = $_SERVER['HTTP_USER_AGENT'];

// Show SSL without verifying
$toSet[CURLOPT_SSL_VERIFYPEER] = false;
$toSet[CURLOPT_SSL_VERIFYHOST] = false;

// Set referrer (but only if their browser sends referrer)
if ( ! empty($_SERVER['HTTP_REFERER']) && strpos($referrer=deproxifyURL($_SERVER['HTTP_REFERER'],$options['encodeURL']),optURL) === false && strpos($referrer,'http') === 0 )
	$toSet[CURLOPT_REFERER] = $referrer;


##########################################
## Manage basic auth
##########################################

if ( isset($_COOKIE['auth'][urlDOMAIN]) ) {
	$toSet[CURLOPT_HTTPAUTH] = CURLAUTH_BASIC;
	$toSet[CURLOPT_USERPWD] = stripEscape($_COOKIE['auth'][urlDOMAIN]);
}


##########################################
## Handle caching
##########################################

## If client sends "if modified since", pass it to cURL
## and (hopefully) we can send back a 304 response instead
## of the whole file
if ( ! empty($_SERVER['HTTP_IF_MODIFIED_SINCE']) ) {
	$toSet[CURLOPT_TIMECONDITION] = CURL_TIMECOND_IFMODSINCE;
	$toSet[CURLOPT_TIMEVALUE] = strtotime($_SERVER['HTTP_IF_MODIFIED_SINCE']);
}


##########################################
## COOKIES - Read and send in request
##########################################

## Any at all?
if ( $options['allowCookies'] && ! empty($_COOKIE[optCOOKIE]) ) {

	# Possibly two cookies of same name - store in array so
	# we only send the one with the more complete tail match
	$toSend = array();

	# Loop through each cookie
	foreach ( $_COOKIE[optCOOKIE] as $host => $cookies ) {

		// Match current domain to cookie's domain
		// .domain.com needs to match just domain.com also
		if ( strpos(urlDOMAIN,$host) !== false || ($host{0}=='.' && strpos(urlDOMAIN,substr($host,1)) !== false ) ) {

			// Open cookie and sort variables
			foreach ( $cookies as $name => $rawCookie ) {

				// Strip slashes and return the array
				if ( get_magic_quotes_gpc() )
					$rawCookie = stripslashes($rawCookie);

				// Explode ';' and parse parts
				$cookie = explode(';',$rawCookie);

				// Check matching paths
				if ( strpos('/'.urlPATH,$cookie[1]) !== 0 )
					continue;

				// Only send secure cookies if site is secure
				if ( in_array('secure',$cookie) && urlPROTOCOL != 'https' )
					continue;

				// Check if this cookie already exists
				// Only overwrite if the domain is longer
				if ( isset($toSend[$name]) && strlen($toSend[$name]['domain']) > strlen($host) )
					continue;
		
				// All OK, send this cookie
				$toSend[$name] = array('value' => $cookie[0],'domain' => $host);
			}

		}

	}
	
	# Cookies found, tell cURL to send cookie string
	if ( count($toSend) ) {

		$tmp = '';
		foreach ( $toSend as $name => $cookieArray ) {
			$tmp .= recoverUnderscores($name) . '=' . $cookieArray['value'] . ';';
		}

		$toSet[CURLOPT_COOKIE] = $tmp;
	
	}

}

##########################################
## POST data - forward on to requested page
##########################################

if ( $_SERVER['REQUEST_METHOD'] == 'POST' && ! empty($_POST) ) {
	
	## Convert the POST array to query string
	$tmp = arrayToString($_POST,'recoverUnderscores', (get_magic_quotes_gpc() ? 'stripslashes' : false) );

	## GET conversion
	## Forms using GET complicate matters with our own
	## ?u=URL query string. The proxy parser rewrites
	## all GET forms to POST and adds convertGET input
	## to the form

	if ( isset($_POST['convertGET']) ) {
		# Remove convertGET from POST array and update our location
		$toLoad .= '?' . str_replace('convertGET=1','',$tmp);
	} else {
		# Genuine POST form so post it on to target
		$toSet[CURLOPT_POST] = 1;
		$toSet[CURLOPT_POSTFIELDS] = $tmp;
	}

}


##########################################
## Look for a plugin
##########################################

## Check configuration for plugin usage
if ( optPLUGINS ) {
	$domain = urlDOMAIN;
	# Attempt to strip any subdomains
	if ( preg_match('#(?:^|\.)([a-z0-9]+\.(?:[a-z]{2,}|[a-z.]{5,6}))$#i',$domain,$tmp) )
		$domain = $tmp[1];
	# Check for corresponding plugin
	$foundPlugin = file_exists(proxyPLUGINS . $domain . '.php') ? proxyPLUGINS . $domain . '.php' : false;
	# Load now for increased flexibility (i.e. allows changing of curlopts, etc)
	if ( $foundPlugin )
		include $foundPlugin;
}


##########################################
## Make the REQUEST
##########################################

## The following class allows us to read and record our header
## and body data on the fly - this allows for better performance
## since, if necessary, we don't need to wait for the whole transfer
## to complete before outputting or recognising a problem.

## We use return -1 instead of directly acting on errors. This tells
## cURL to abort the transfer whereas an exit command may leave connections open, etc.

class cURL {
	
	public $status = 0;					// HTTP status code
	public $headers = array();				// Record headers for later use
	public $error = false;					// Hold cURL error
	public $abort = false;					// Our errors / reason for abortion
	public $return;						// Store the return
	public $proxyOptions = array();				// Store our proxy browsing options
	public $docType = 0;					// Corresponding parser (from content type)
	public $cacheName;					// Name of cache file to save as (false for no cache)
	public $fileHandle;					// Handle for cache file
	public $length = 0;
	private $endHeaders = true;				// Flag for end of headers, checked in readBody
	
	public function __construct($toLoad,$curlOptions,$options,$cache) {
		// Set proxy options
		$this->proxyOptions = $options;
		// And cache name
		$this->cacheName = $cache;
		
		# Debug
		if ( DEBUG_MODE ) {
			$this->posted = isset($curlOptions[CURLOPT_POSTFIELDS]) ? $curlOptions[CURLOPT_POSTFIELDS] : '';
			$this->cookiesSent = isset($curlOptions[CURLOPT_COOKIE]) ? $curlOptions[CURLOPT_COOKIE] : array();
		}
		# End debug
		
		// Set our callbacks
		$curlOptions[CURLOPT_HEADERFUNCTION] = array(&$this,'readHeaders');
		$curlOptions[CURLOPT_WRITEFUNCTION] = array(&$this,'readBody');
		
		// Start curl handle
		$tmp = curl_init($toLoad);

		// Set our options
		if ( function_exists('curl_setopt_array') )
			curl_setopt_array($tmp,$curlOptions);
		else
			foreach ( $curlOptions as $option => $value )
				curl_setopt($tmp,$option,$value);

		// Set PHP timeouts
		set_time_limit(optTIMEOUT+10);

		// Get return
		curl_exec($tmp);
				
		// Close file handle
		if ( $this->fileHandle ) {

			fclose($this->fileHandle);
			$this->fileHandle = false;
	
			// Rename tmp to real name
			rename(optCACHEPATH . 'tmp.'.$this->cacheName, optCACHEPATH.$this->cacheName);
	 
		}
				
		// Record errors
		$this->error = curl_error($tmp);
			
		// Close cURL
		curl_close($tmp);
	}
	
	//=============================================================================
	//   ===    manage the RESPONSE    ============================================
	//=============================================================================
	
	##########################################
	## Read headers
	##########################################
	
	## Our header reading function actually processes the headers as well
	## so if possible (i.e. downloading a file) we can start the output without
	## loading the entire file into memory
	private function readHeaders($handle,$header) {

		## Check for end of headers
		if ( empty($header) ) {
			# Tell cURL end of headers
			return 0;
		}

		# Check for status code
		if ( ! $this->status && preg_match('#HTTP/1\.(?:\d|x)\s+(\d\d\d)#', $header, $tmp) ) {
			
			$this->status = (int) $tmp[1];
			
			##########################################
			## HTTP Status codes
			##########################################
			
			if ( $this->status == 304 ) 
				$this->abort = 'notmodified';

		}
		
		# Extract the header name and contents
		if ( strpos($header,':') ) {
			$parts = explode(':',$header,2);
			
			$headerName = strtolower($parts[0]);
			$headerValue = trim($parts[1]);
			
			if ( $headerName == 'set-cookie' ) {
				
				##########################################
				## Cookies - handle cookies in response
				##########################################
				
				if (  $this->proxyOptions['allowCookies'] ) {
					
					$cookieValue = '';
					
					## Explode ';' gives our parameters
					# Cookie responses are set in the format
					# name=val; expires=DATE; path=PATH; domain=DOMAIN; secure; httponly
					# where all but name=val is optional
			
					$args = explode(';',$headerValue);
			
					## The goal is to create a new string: value;path;options;
					## as the cookie value.
				
					# Firstly split the name/value.
					$tmp = explode('=',$args[0],2);
					
					// Store the name for later
					$thisCookie['name'] = preserveUnderscores(trim($tmp[0]));
			
					// Add value to string
					$cookieValue .= ( empty($tmp[1]) ? '' : trim($tmp[1]) ) . ';';
			
					## If we send cookies with the paths / domains set by server
					# they will be inaccessible to us so we need to store those
					# parameters and the cookie value inside the cookie itself
					# (we actually store the domain as part of the cookie name)
			
					# Loop through remaining parameters and parse options
			
					foreach ( $args as $arg ) {
			
							// If = found, explode it and set key/val
						if ( strpos($arg,'=') ) {
			
							$parts = explode('=',$arg,2);
							$thisCookie[strtolower(trim($parts[0]))] = trim($parts[1]);
			
						} else {
			
							// Otherwise just add the option to the array
							$thisCookie[] = trim($arg);
			
						}
			
					}
			
					## SSL handling
					# Mark secure cookies as secure only if we're on HTTPS ourselves
					$secure = in_array('secure',$thisCookie) && ! empty($_SERVER['HTTPS']);
			
					## Determine options to send
					// If explicitly set, use the given path / domain else use current
					$thisCookie['path'] = empty($thisCookie['path']) ? '/'.urlPATH : $thisCookie['path'];
					$thisCookie['domain'] = empty($thisCookie['domain']) ? str_replace('www.','',urlDOMAIN) : $thisCookie['domain'];
			
					// Add real path to cookie value
					$cookieValue .= $thisCookie['path'] . ';';
			
					## Determine name to save cookie as
					# Default format is: anon[cookiedomain][cookiename]
					$saveAs = optCOOKIE . '[' . $thisCookie['domain'] . '][' . rawurlencode($thisCookie['name']) . ']';
			
					## Set expiry date to end of session if not given
					$expires = empty($thisCookie['expires']) ? 0 : intval(strtotime($thisCookie['expires']));
			
					## Force end of session expiration (if not deletion)
					# We can't allow too many cookies to stay since all
					# have to be sent to us in the Cookie: header for every
					# page request and we'd soon reach the limit set by
					# the server (LimitRequestFieldSize for Apache) if not
					# the client first.
					if ( $this->proxyOptions['tempCookies'] && $expires > time() )
						$expires = 0;
			
					# Send the cookie
					# Set as httponly if applicable
					version_compare(PHP_VERSION,'5.2.0','>=') ?
						setcookie($saveAs,$cookieValue,$expires,'/',false,$secure,true) :
						setcookie($saveAs,$cookieValue,$expires,'/',false,$secure);
					
					# Save for debugging
					if ( DEBUG_MODE )
						$this->cookies[] = $headerValue;
			
				}
				
			} else {
				
				// Save the header
				$this->headers[$headerName] = $headerValue;
				
				##########################################
				## Handle response headers
				##########################################
				
				switch ( $headerName ) {
					
					## Handle a redirect
					case 'location':
						$this->abort = 'redirect';
						break;
						
					## Content length filesize check
					case 'content-length':
					
						# Check option enabled
						if ( ! optMAXSIZE )
							break;
							
						# Compare sizes
						if ( $headerValue > optMAXSIZE ) {
							# Log error
							$this->abort = 'filetoolarge';
						}
							
						break;
						
					## Content type - do we parse?
					case 'content-type':
						# Extract mime from charset (if sent)
						$tmp = explode(';',$headerValue);
						$mime = trim($tmp[0]);
						
						# Define content-type to parser type relations
						$contentType = array(
							'text/javascript'		=>	'javascript',
							'application/javascript'	=>	'javascript',
							'application/x-javascript'	=>	'javascript',
							'application/xhtml+xml'		=>	'html',
							'text/html'			=>	'html',
							'text/css'			=>	'css',
						);
						
						# Which type is the current document?
						$this->docType = isset($contentType[$mime]) ? $contentType[$mime] : false;

						break;

				}
				
			}

		} else {
			// Unable to split, save whole string
			$this->headers[] = trim($header);
		}

		# Return bytes
		return strlen($header);
	}

	##########################################
	## Process headers after all received
	##########################################
	
	private function endHeaders() {

		## We don't want to forward all headers but most of them. All
		## in the following array will be forwarded back to the client.
		$toForward = array(
											'Last-Modified',
											'Content-Disposition',
											'Content-Type',
											'Expires',
											'Cache-Control',
											'Pragma',
											);

		## If no changes, also forward content length
		if ( $this->docType == false ) {
			$toForward[] = 'Content-Length';
			# And stop default text/html header
			header('Content-Type:');
		}
		
		## Loop through and send
		foreach ( $toForward as $name ) {
			if ( isset($this->headers[strtolower($name)]) )
				header($name . ': ' . $this->headers[strtolower($name)], true);
		}

		## Send filename in content disposition if none sent
		if ( empty($this->headers['content-disposition']) ) {
			header('Content-Disposition: ' 
							. ( isset($this->headers['content-type']) && $this->headers['content-type'] == 'application/octet_stream' ? 'attachment' : 'inline' )
							.	'; filename="' . urlFILENAME . '"');
		}
		
		## If no parsing needed and we have a cache name, open file handle
		if ( ! $this->docType && $this->cacheName ) {
			# Choose our temp file name
			$tmp = optCACHEPATH . 'tmp.'.$this->cacheName;
			# Check for existing write in progress
			if ( ! file_exists($tmp) ) {
				// Attempt to create the file
				$this->fileHandle = fopen($tmp,'wb');
			}
		}
		
		## Set flag to false
		$this->endHeaders = false;
		
	}
	
	##########################################
	## Read body
	##########################################
	
	public function readBody($handle,$data) {

		$length = strlen($data);		

		# On first run
		if ( $this->endHeaders ) {
			# Abort if reason to do so
			if ( $this->abort )
				return -1;
			# Auto-determine content type if none sent
			if ( optPARSEALL && $this->docType === 0 ) {
				# Sample 100 chars
				$length = strlen($data);
				$sample = $length < 150 ? $data : substr($data,rand(0,$length-100),100);
				if ( strlen(preg_replace('#[^A-Z0-9!"�$%\^&*\(\)=+\\\\|\[\]\{\};:\\\'@\#~,.<>/?-]#i','',$sample)) > 95 ) 
					$this->docType = 'html';
				else
					$this->docType = false;
			}
			# Call our end headers
			$this->endHeaders();
		}
		
		# End of file
		if ( empty($data) ) {
			// Tell cURL end of transfer
			return 0;
		}
			
		# If parsing, add to return
		if ( $this->docType )
			$this->return .= $data;
		# Else output the file
		else
			echo $data;
		
		# Write to cache file
		if ( $this->fileHandle )
			fwrite($this->fileHandle,$data);
						
		# Find length and check for size limit
		$this->length += $length;
		if ( optMAXSIZE && $this->length > optMAXSIZE ) {
			$this->abort = 'filetoolarge';
			return -1;
		}
		
		# Return strlen
		return $length;
		
	}
	
}

## Execute the request
$fetch = new cURL($toLoad,$toSet,$options,$cacheName);

## Check for abortion
if ( $fetch->abort ) {
	
	# Action depends on reason for abort
	switch ( $fetch->abort ) {
		
		# Redirect
		case 'redirect':
			if ( DEBUG_MODE ) {
				$fetch->goto = '<a href="'. proxifyURL($fetch->headers['location']).'">' . proxifyURL($fetch->headers['location']) . '</a>';
				break;
			}
			if ( $fetch->status == 301 )
				header("HTTP/1.1 301 Moved Permanently",true,301);
	
			# Returned status of 100 should still redirect so override to 302
			$status = $fetch->status == 100 ? 302 : $fetch->status;
	
			# Move on and exit
			header("Location: ". proxifyURL($fetch->headers['location']),true,$status);
			exit;
			break;
			
		# File too large
		case 'filetoolarge':
			sendNoCache();
			error( sprintf( $phrases['fileLimit'], round(optMAXSIZE/(1024*1024),4) ) );
			break;
			
		# 304 Not Modified
		case 'notmodified':
			header("HTTP/1.1 304 Not Modified",true,304);
			exit;
			break;
			
	}
	
}

# 401 Authorization Required
if ( $fetch->error == 'The requested URL returned error: 401' ) {
	## For now we're only supporting basic authentication
	## "The HTTP Authentication hooks in PHP are only available when
	##  it is running as an Apache module and is hence not available
	##  in the CGI version."		(from php manual)
	## Therefore we CANNOT rely on the normal browser method to authenticate
	$_SESSION['auth'] = $toLoad;
	$_SESSION['authdomain'] = urlDOMAIN;
	echo replaceTags(loadTemplate('authenticate.page'));
	exit;
}

## If our return is empty, check for error
if ( ! DEBUG_MODE && empty($fetch->return) && $fetch->error ) {
	die($fetch->error);
}

## Check doctype and skip parsing if already sent
if ( $fetch->docType ) {
	
	##########################################
	## Prepare our parser options
	##########################################
	
	$parserOptions = array();
	
	// Remove elements
	$parserOptions['toRemove'] = optREMOVE;
	
	if ( $options['stripImages'] )
		$parserOptions['toRemove'] .= ( optREMOVE ? '|' : '' ) . 'img';
	
	// Define remaining options
	$parserOptions['addForm'] = '';
	$parserOptions['stripJS'] = $options['stripJS'];
	$parserOptions['stripFlash'] = $options['stripFlash'];
	$parserOptions['encodeURL'] = $options['encodeURL'];
	
	
	##########################################
	## Parse and send output
	##########################################
	
	# Do the preparsing
	if ( ! empty($foundPlugin) && function_exists('preParse') )
		$fetch->return = preParse($fetch->return,$fetch->docType);
	
	# Load main parser
	require proxyINC.'parser.php';

	# Dependent on content
	switch ( $fetch->docType ) {

		# HTML document
		case 'html':
			// Is this a full document? Yes unless ajax
			$fullDoc = $flag != 'ajax';

header('Content-Type: text/html; charset=utf-8');


			// Add in the include form
			if ( $options['showForm'] && $flag != 'frame' && $flag != 'ajax' ) {
				// Determine options to display
				$toShow = array();
				foreach ( $optionsDetails as $name => $details ) {
					// Check we're allowed to choose
					if ( ! empty($details['force']) )
						continue;
					// Should the checkbox be checked
					if ( isset($options[$name]) )
						$checked = $options[$name] ? ' checked="checked"' : '';
					else
						$checked = $details['default'] ? ' checked="checked"' : '';
					// Add to array
					$toShow[] = array('name' => $name,'title' => $details['title'], 'checked' => $checked);
				}
				// Add the form
				$parserOptions['addForm'] = replaceTags(loadTemplate('framedForm.inc',array('url'=>$toLoad,'toShow'=>$toShow,'return'=>urlencode(currentURL()))));
			}
			// Parse HTML
			$fetch->return = parseHTML($fetch->return,$fullDoc);

			// Encode the whole page?
			if ( $options['encodePage'] )
				$fetch->return = encodeSource($fetch->return);
				
			break;
	
		# CSS document
		case 'css':
			$fetch->return = parseCSS($fetch->return);
			break;
	
		# Javascript document
		case 'javascript':
			$fetch->return = parseJS($fetch->return);
			break;
	
	}
	
	# Strip badwords
	$fetch->return = str_replace($badWords,'####',$fetch->return);

	# Post parsing
	if ( ! empty($foundPlugin) && function_exists('postParse') )
		$fetch->return = postParse($fetch->return,$fetch->docType);
	
	# Print debug info
	if ( DEBUG_MODE ) {
		echo '<pre>',print_r($fetch,true),'</pre>';
	## Send output
	} else {
		# Do we want to compress? Yes if option is set, browser supports it, and zlib is available but compression not automated
		if ( optGZIP && isset($_SERVER['HTTP_ACCEPT_ENCODING']) && strpos($_SERVER['HTTP_ACCEPT_ENCODING'],'gzip') !== false && extension_loaded('zlib') && !ini_get('zlib.output_compression') )
			echo ob_gzhandler($fetch->return,5);
		else {
			## Send content-length header
			header('Content-Length: '.strlen($fetch->return));
			echo $fetch->return;
		}
	}
}


##########################################
## Save cache
##########################################

if ( isset($useCache) ) {
	# Find filename
	$saveAs = optCACHEPATH . $cacheName;
	# Save file if not already
	if ( $fetch->docType ) {
		# Send any output so far; the rest of the script only affects
		# the server so we don't want to wait for this before displaying
		# the page
		session_write_close();
		flush();
		# Create file
		file_put_contents($saveAs,$fetch->return);
	}
	# Set modified date if possible
	if ( isset($fetch->headers['last-modified']) && ($touch = strtotime($fetch->headers['last-modified'])) ) {
		touch($saveAs,$touch);
	}
}


##########################################
## Do logging
##########################################

# Ensure logging is enabled and that we should log the current request
if ( optLOGGING && ( optLOGUNTIL == 0 || time() < optLOGUNTIL ) && ( optLOGALL || $fetch->docType == 'html') ) {
	# Write to log file 
	if ( is_dir(optLOGGING) )
		# Directory => date based filename
		$file = optLOGGING . gmdate("Y-m-d").'.log';
	else
		$file = optLOGGING;
	# Can we lock the file
	$flags = FILE_APPEND;
	if ( version_compare(PHP_VERSION,'5.1','>=') )
		$flags = FILE_APPEND|LOCK_EX;
	# Do the write
	file_put_contents($file, $_SERVER['REMOTE_ADDR'] . ', ' . gmdate("H:i:s d M Y") . ', ' . $toLoad . ', ' . $_SERVER['HTTP_HOST'] . "\r\n", $flags);

}

?>