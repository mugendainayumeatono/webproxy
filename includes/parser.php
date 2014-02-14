<?php	
/*--------------------------------------------------
| HTML PARSER
--------------------------------------------------*/

# Main parsing function accepts input and returns proxified HTML
function parseHTML(&$in,$fullDoc=false) {
	global $parserOptions;

	// Remove and record (via callback) base tag
	$in = preg_replace_callback('#<base href=["\\\']?([^"\\\' >]{1,500})[\\\\\'"]?(>|/>|</base>)#i', 'html_stripBase', $in);

	// Proxify meta redirects
	$in = preg_replace_callback('#content=["\\\']([0-9]+)\s*;\s*url=(\\\')?([^"\\\' >]{1,1000})(?:\\\')?["\\\']?(.{1,1000})(>|/>)#is', 'html_metaRefresh', $in);

	// Replace inline CSS
	$in = preg_replace_callback('#<style([^>]{0,200})>(?:\s*<!--)?((?:(?:/\*)?<!\[CDATA)?[^<]*)</style>#is', 'html_inlineCSS', $in);
	$in = preg_replace_callback('#style=([\'"])((?(?<=\')[^>\']{1,1000}|[^>"]{1,1000}))\\1#is', 'html_elementCSS', $in);

	// Remove flash if desired
	if ( $parserOptions['stripFlash'] )
		$in = preg_replace('#<embed\s[^>]+\.swf[^>]+>#i','',$in);

	// Either remove or attempt to parse the javascript
	if ( $parserOptions['stripJS'] ) {

		// Remove all <script> tags, including contents
		$in = preg_replace('#<script.*?</script>#is','',$in);
		// Show content within <noscript>
		$in = str_ireplace(array('<noscript>','</noscript>'),'',$in);
		// Remove event handlers
		$in = preg_replace('#(onload|onclick|onchange|onfocus|onblur|onkeypress|onkeydown|onkeyup|onmouseover|onmouseout|onmousedown|onsubmit)=([\\\'"]).{1,1000}\\2#iU', '', $in);

	} else {

		// Replace <script> blocks
		$in = preg_replace_callback('#<script(.{0,500})>(.*)</script>#isU', 'html_inlineJS', $in);
		// Replace event handlers
		$in = preg_replace_callback('#(onload|onclick|onchange|onfocus|onblur|onkeypress|onkeydown|onkeyup|onmouseover|onmouseout|onsubmit)=([\\\'"])(.{1,1000})\\2#iU', 'html_eventJS', $in);

	}

	// Remove any elements that we should remove (replace with alt="" text if available)
	if ( $parserOptions['toRemove'] )
		$in = preg_replace('#<('.$parserOptions['toRemove'].')(?:[^>]+)?(?:alt=(["\\\'])?(?(1)(?(?<=\\\')[^\\\']+|[^"]+)|[^ >]+)\2)?.*?(?:>|</\1>)#iU', '$2', $in);

	// Replace ATTRIBUTE="URL"
	// Regex 'looks' horrible - but it's the result of many many days
	// studying/optimizing/experimenting so should be as fast as possible.
	$in = preg_replace_callback('#(?><[A-Z][A-Z0-9]{0,15})(?>\s+[^>\s]+)*?\s+(?>('.optREPLACE.')\s*=(?!\\\\)\s*)(?>([\\\'"])?)((?(2)(?(?<=")[^"]{1,1000}|[^\\\']{1,1000})|[^ >]{1,1000}))(?(2)\\2|)#i', 'html_replaceElement', $in);

	/*// Flash movie param tags
	$in = preg_replace_callback('#<param(?>\s+[^>\s]+)*?\s*(value)\s*=\s*(?>([\\\'"])?)((?(2)http[^\\\'"]{1,1000}|http[^ >]{1,1000}))(?(2)\\2|)#i','html_replaceElement',$in);
	*/
	
	// Process forms
	// We want to make multiple changes to the form, all of which
	// we only need to do if we have a form in the first place. Thus
	// to save unnecessary processing, we pass the entire form to our
	// callback and within the callback carry out the additional parsing
	$in = preg_replace_callback('#<form([^>]*)>(.*)</form>#isU', 'html_form', $in);

	// Add URL form
	if ( $fullDoc && $parserOptions['addForm'] ) {
		// Attempt to add it in after the <body> tag
		if ( strpos($in,'<body') )
			$in = preg_replace('#<body([^>]*)>#i', '<body$1>'.$parserOptions['addForm'], $in);
		else {
			// Check for frameset and if not, prepend form (no <body> tag found)
			if ( stripos($in,'<frameset') === false )
				$in = $parserOptions['addForm'] . $in;
		}
	}

	// Add injection JS
	if ( ! $parserOptions['stripJS'] && $fullDoc ) {
		// Insert our own javascript URL proxifying function
		if ( stripos($in,'<head') )
			$in = preg_replace('#<head([^>]*)>#i','<head$1>'.render_injectionJS(),$in);
		else
			$in = render_injectionJS() . $in;
	}
	
	// Add to footer
	if ( optFOOTER ) {
		if ( stripos($in,'</body') )
			$in = str_ireplace('</body',optFOOTER.'</body',$in);
		else
			$in .= optFOOTER;
	}

	// Return
	return $in;

}

/*----------------HTML CALLBACKS--------------------*/

## Strip but store BASE element: callback for the <meta content> replace
# Original regex: <base href=["\\\']?([^"\\\' >]{1,500})[\\\\\'"]?(>|/>|</base>)
function html_stripBase($input) {
	global $base;
	$base = $input[1];
	// Ensure base has trailing slash
	if ( $base{strlen($base)-1} != '/' ) $base .= '/';
	return '';
}

## Proxify meta redirets
# Original regex: content=["\\\']([0-9]+)\s*;\s*url=(\\\')?([^"\\\' >]{1,500})(?:\\\')?["\\\']?(.{1,500})(>|/>)
function html_metaRefresh($input) {
	return 'content="' . $input[1] . ';url=' . proxifyURL($input[3]) . '"' . $input[4] . '>';
}

## Proxify <style> blocks
# Original regex: <style([^>]{0,200})>(?:\s*<!--)?((?:(?:/\*)?<!\[CDATA)?[^<]*)</style>
function html_inlineCSS($input) {
	return '<style' . $input[1] . '>' . parseCSS($input[2]) . '</style>';
}

## Proxify style= attributes
# Original regex: style=([\'"])((?(?<=\')[^>\']{1,1000}|[^>"]{1,1000}))\\1
function html_elementCSS($input) {
	return 'style=' . $input[1] . parseCSS($input[2]) . $input[1];
}

## Proxify <script> blocks
# Original regex: <script(.{0,500})>(.*)</script>
function html_inlineJS($input) {
	return '<script' . $input[1] . '>' . ( $input[2] ? parseJS($input[2]) : '' ) . '</script>';
}

## Proxify onX= attributes
# Original regex: (onload|onclick|onchange|onfocus|onblur|onkeypress|onkeydown|onkeyup|onmouseover|onmouseout|onsubmit)=([\\\'"])(.{1,1000})\\2
function html_eventJS($input) {
	return $input[1] . '=' . $input[2] . parseJS($input[3]) . $input[2];
}

## Replace element ATTRIBUTE=URL, formatted as below
/* Original regex: (?><[A-Z][A-Z0-9]*)(?>\s+[^>\s]+)*?\s*(?>('.optREPLACE.')\s*=(?!\\\\)\s*)(?>([\\\'"])?)((?(2)[^\\\'"]{1,1000}|[^ >]{1,1000}))(?(2)\\2|) */
# [0] <element attr="value"
# [1] attr
# [2] ", ' or empty
# [3] value
function html_replaceElement($input) {
	// Count frames
	static $frames;
	// Check valid
	if ( preg_match('#^[^a-z0-9A-Z./\?]+$#',$input[3]) || stripos($input[3],'javascript:') !== false ) return $input[0];
	// Flag iframes so we don't show the form more than once
	$flag = stripos($input[0],'iframe') === 1  ? 'frame' : '';
	// Flag frames for the same reason
	if ( stripos($input[0],'frame') === 1 ) {
			if ( empty($frames) ) $frames = 1;
			else $frames++;
		if ( $frames > 1 )
			$flag = 'frame';
	}
	return str_replace($input[3],proxifyURL($input[3],$flag),$input[0]);
}

## Proxify forms - input is received as
# Original regex: <form(.*)>(.*)</form>
function html_form($input) {
	// Accept inputs
	$attr = $input[1];
	$contents = $input[2];
	// Convert GET->POST (we're already using the query string for our proxy inputs)
	// First, extract method
	if ( preg_match('#method=["\\\']?(post|get)["\\\']?#i', $attr, $tmp) ) {
		if ( strtolower($tmp[1]) == 'get' )
			$attr = preg_replace('#method=["\\\']?get["\\\']?#i','method="post"',$attr);
		else
			$noConvert = true;
	} else {
		$attr .= ' method="post"';
	}
	// Proxify form action
	$attr = preg_replace_callback('#action=(["\\\']?)((?(?<=(?:\\\'|"))(?(?<=\\\')[^\\\']*|[^"]*)|([^"\\\' >]+)))\\1#i', 'html_formAction', $attr);
	// PHP converts all . in incoming variable names to underscores
	// but if we're proxifying a request for a non-PHP script
	// that expects the . we need to convert back to the dot.
	// Convert REAL underscores to "US95" so we can tell the
	// difference between an original _ and .
	$contents = preg_replace_callback('#name=["\\\']?([^"\\\' >]+)["\\\']?#','html_inputName',$contents);
	return '<form'.$attr.'>' . ( isset($noConvert) ? '' : '<input type="hidden" name="convertGET" value="1">') . $contents . '</form>';
}

## Proxify form action=URL
# Original regex: action=["\\\']?([^"\\\' >]+)["\\\']?
function html_formAction($input) {
	return 'action=' . $input[1] . proxifyURL($input[2]) . $input[1] ;
}

## Preserve _ with unique string
# Original regex: name=["\\\']?([^"\\\' >]+)["\\\']?
function html_inputName($input) {
	return 'name="' . preserveUnderscores($input[1]) . '"';
}


/*--------------------------------------------------
| CSS PARSER
--------------------------------------------------*/

# Main parsing function accepts input and returns proxified CSS
function parseCSS(&$in) {
	// CSS needs proxifying the calls to url() and @import
	$in = preg_replace_callback('#url\s*\([\\\'"]?([^\\\'"\)]+)[\\\'"]?\)#i', 'css_URL', $in);
	$in = preg_replace_callback('#@import\s*[\\\'"]([^\\\'"\(\)]+)[\\\'"]#i', 'css_import', $in);
	// Apparently src='' is also valid CSS so proxify that too
	$in = preg_replace_callback('#src\s*=\s*([\\\'"])?([^)\\\'"]+)(?(1)\\1|)#i', 'css_src', $in);
	return $in;
}

/*----------------CSS CALLBACKS--------------------*/

## Proxify CSS url(LOCATION)
# Original regex: url\s*\([\\\'"]?([^\\\'"\)]+)[\\\'"]?\)
function css_URL($input) {
	return 'url(' . proxifyURL($input[1],'',optPATHINFO) . ')';
}

## Proxify CSS @import "URL"
# Original regex: @import\s*[\\\'"]([^\\\'"\(\)]+)[\\\'"]
function css_import($input) {
	return '@import "' . proxifyURL($input[1]) . '"';
}

## Proxify CSS src=
# Original regex: src\s*=\s*([\\\'"]?)([^)\\\'"]+)(?(1)\\1|)#i
function css_src($input) {
	return 'src=' . $input[1] . proxifyURL($input[2]) . $input[1];
}

/*--------------------------------------------------
| JavaScript PARSER
--------------------------------------------------*/

# Main parsing function accepts input and returns proxified Javascript
function parseJS(&$in) {

		// document.write() can output HTML
		$in = preg_replace('#document\s*\.\s*write\s*\(#i', 'DocWrite(', $in);
		$in = preg_replace('#document\s*\.\s*writeln\s*\(#i', 'DocWriteln(', $in);

		// For certain aspects of JS, we need to rely on the end of line ;
		// so we first remove all ; that are in strings (not perfect since eval() screws it up)
		$in = preg_replace_callback('#([\\\'"])(?(?<=")((?<!\\\)\\\"|[^"\n\r])*|((?<!\\\)\\\\\'|[^\\\'\n\r])*)\\1#iU','js_maskDelimiters',$in);

		// Now proxify the .innerHTML all the way up to the first ; or end of line
		// UNLESS there is a symbol that implies the current "line" is actually spanning
		// multiple lines (e.g. a comma, etc)
		$in = preg_replace('#\.innerHTML\s*=(?!=)\s*((?:(?:\s*[\]+,)(]\s*)|[^;}\n\r])*)#i','.innerHTML = parseHTML($1)',$in);

		// Similarly, replace all url locations
		$in = preg_replace('#\.(action|src|location|href)\s*=(?!=)\s*((?:(?:\s*[\]+,)(]\s*)|[^;}\n\r])*)#i',".$1=parseURL($2)",$in);

		// Handle AJAX requests
		$in = preg_replace('#\b([a-z0-9_.\s]+(?<!window)\.open)\s*\(((?:(?:\s*[\]+,)(]\s*)|[^;}\n\r])+)\)#i','openajax(this,$1,$2)',$in);

		// And return the original ;
		$in = str_replace(array('__proxywebpackSC__','__proxywebpackCCB__','__proxywebpackDOT__'),array(';','}','.'),$in);

		// Location replace can give us a different URL
		$in = preg_replace('#location\.replace\s*\(\s*([^)]+)\s*\)#i', 'location.replace(parseURL($1))', $in);

		return $in;
}

/*--------------------------------------------------
| JavaScript callbacks
--------------------------------------------------*/

# Replace ; with __proxywebpackSC__
# Original regex: ([\\\'"])(?(?<=")(\\\"|[^"])|(\\\\\'|[^\\\']))*\\1
function js_maskDelimiters($input) {
	return str_replace(array(';','}','.'),array('__proxywebpackSC__','__proxywebpackCCB__','__proxywebpackDOT__'),$input[0]);
}

/*--------------------------------------------------
| INJECTION JS FOR ON-THE-FLY PARSING
--------------------------------------------------*/

function render_injectionJS() {
	global $base,$parserOptions;

	# Sort variables
	$hostname = urlHOST; $pathinfo = optPATHINFO; $path = urlPATH; $baseURL = optURL; $unique = optUNIQUEURL;
	$bitfield = defined('BITFIELD') ? (string) BITFIELD : 0;
	$uniqueDetails = optUNIQUEURL ? "salt='{$_SESSION['unique']['salt']}';rot='{$_SESSION['unique']['str_rot13']}';rev='{$_SESSION['unique']['strrev']}'" : '';

	# Define injection
	return <<<OUT
<script type="text/javascript">base='{$base}';host='{$hostname}';path='{$path}';URL='{$baseURL}';bitf={$bitfield};enc='{$parserOptions['encodeURL']}';flash='{$parserOptions['stripFlash']}';remove='{$parserOptions['toRemove']}';pi='{$pathinfo}';un='{$unique}';{$uniqueDetails}</script>
<script type="text/javascript" src="{$baseURL}includes/js/parse.js"></script>
OUT;

	# NB: The javascript parser is compressed. For the full version, look 
	#     at the "full.parse.js" file (in the extras folder of the original package)
}

?>
