<?php	function preParse($html,$type) {
    // Javascript changes
    if ( $type == 'javascript' ) {
        // Objects within arguments confuse the parser, e.g.
        // el.innerHTML = someFunction('pie',{'cheese':0,'end':'not at curly bracket'});
        //
        // The parser has no way of distinguishing the } from a line delimiter, e.g.
        // if() { el.innerHTML = 'this statement DOES end at the closing curly bracket' }
        $html = preg_replace('#\{(\\\'[^}]+)\}#i','__proxywebpackOCB__$1__proxywebpackCCB__',$html);
        
        // Replace ajax URLs with proxified URL
        function facebook_replaceURL($input) {
            return str_replace($input[1],proxifyURL($input[1],'ajax'),$input[0]);
        }
        $html = preg_replace_callback('#ajaxUrl=\\\'([^\\\']+)\\\'#', 'facebook_replaceURL', $html);
        $html = preg_replace_callback('#\.setURI\(\\\'([^\\\']+)\\\'\)#', 'facebook_replaceURL', $html);
        
        // NB: The above Ajax related code does not appear to solve anything yet. This plugin is still
        // a work-in-progress and will continue to be updated. If/when a working plugin is created, it
        // will be posted on the proxywebpack.com website.
    }
    
    // HTML changes
    if ( $type == 'html' ) {
        // "Fix" the missing space in <a class="find_friends_icon"href="https://register.facebook.com/findfriends.php">
        $html = str_replace('<a class="find_friends_icon"href="https://register.facebook.com/findfriends.php">','<a class="find_friends_icon" href="https://register.facebook.com/findfriends.php">',$html);
    }
    
    return $html;
}

##############################################
# EXTRA PARSING AFTER MAIN PROXY PARSER
##############################################

function postParse($html,$type) {

    if ( $type == 'javascript' ) {
        $html = str_replace('__proxywebpackOCB__','{',$html);
    }

    return $html;
}

?>
