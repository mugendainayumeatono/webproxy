<?php	switch ( $_GET['action'] ) {

	##########################################
	## Receive a user/pass and set cookie
	## for basic HTTP authentication
	##########################################

	case 'authenticate':

		require 'init.php';
	
		## Check we have a URL to authenticate for
		if ( empty($_SESSION['auth']) || empty($_SESSION['authdomain']) )
			localRedirect();
		
		## Check we have login details
		if ( empty($_POST['pass']) ) {
			header('Location: ' . proxifyURL($_SESSION['auth']));
			exit;
		}
		
		## Save login details
		$user = empty($_POST['user']) ? '' : stripEscape($_POST['user']);
		$pass = stripEscape($_POST['pass']);
	
		setcookie('auth[' . $_SESSION['authdomain'] . ']',$user.':'.$pass, false, '/');
		header('Location: ' . proxifyURL($_SESSION['auth']) . '&refresh');
		
		break;
	

	##########################################
	## Cookie management - deletions
	##########################################

	case 'cookies':
		
		require 'init.php';
		
		## Delete all or specific?
		if ( isset($_GET['type']) && $_GET['type'] == 'all' ) {
			
			if ( ! empty($_COOKIE[optCOOKIE]) ) {
				foreach ( $_COOKIE[optCOOKIE] as $domain => $value ) {
					if ( ! empty($_COOKIE[optCOOKIE][$domain] ) ) {
						foreach( $_COOKIE[optCOOKIE][$domain] as $name => $value ) {
							setcookie(optCOOKIE .'[' . $domain . '][' . $name . ']',$value,time()-9000,'/');
						}
					}
				}
			}
			
			$return = empty($_GET['return']) ? 'index.php' : $_GET['return'];
			localRedirect($return . (optPATHINFO ? '?' : '&') . 'refresh='.rand(0,1000));
			
		} else {
			## Accept and delete cookies
			if ( ! empty($_POST['delete']) && is_array($_POST['delete']) ) {
			
				foreach ( $_POST['delete'] as $name => $domain ) {
					setcookie(optCOOKIE.'['.$domain.']['.$name.']',false,time()-9000,'/');
				}
			
			}
			
			## Return to cookie page
			localRedirect('cookies.php');
		}

		break;
	
	
	##########################################
	## Add our ssl verified flag to session
	##########################################

	case 'sslagree':

		require 'init.php';

		// Flag it
		$_SESSION['sslwarned'] = true;
		$toReturn = empty($_SESSION['return']) ? 'index.php' : $_SESSION['return'];
		// Return to page
		header('Location: ' . optURL . $toReturn);
		
		unset($_SESSION['return']);
		
		break;
	

	##########################################
	## Receive an update from the URL form or index page
	## and redirect to the browse script. Via here
	## to obey any desired encodings.
	##########################################

	case 'update':

		// Stop bitfield auto-defining because we may need to change it!
		define('NO_BF',true);
		require 'init.php';
		
		## Flag valid entry point (for prevention of hotlinking)
		$_SESSION['viaIndex'] = true;
		
		## Check we're submitted
		if ( empty($_POST['u']) )
			localRedirect();
		
		## Determine our options and set _SESSION as appropriate
		# Start with empty array
		$options = array();
		
		# Loop through all available options
		foreach ( $optionsDetails as $name => $details ) {
			# Set this option to the default if the option is forced.
			if ( ! empty($details['force']) )
				$_SESSION[$name] = $options[$name] = $details['default'];
		
			# Using checkboxes we only get a value set if box is checked
			# so assume not set = false
			else
				$_SESSION[$name] = $options[$name] = empty($_POST[$name]) ? false : true;
		}
		
		## Regenerate bitfield
		defineBitfield($options);
		
		## Validate URL
		$url = $_POST['u'];
		if ( strpos($url,'://') === false ) {
			$url = 'http://' . $url;
		}
		
		## Redirect to browse script
		header('Location: ' . proxifyURL($url) );

		break;
}

?>
