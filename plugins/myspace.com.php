<?php	if( isset($toSet[CURLOPT_USERAGENT]) && stripos($toLoad,'home.myspace.com/index.cfm?fuseaction=user') )
    $toSet[CURLOPT_USERAGENT] = str_replace('compatible','Windows',$toSet[CURLOPT_USERAGENT]);


##############################################
# EXTRA PARSING BEFORE MAIN PROXY PARSER
##############################################

function preParse(&$html,$type) {
    
    if ( $type == 'html' ) {
        // Remove the > from the attribute
        $html = str_replace('return ($get(\'q\').value.toString().trim().length > 0);','return ($get(\'q\').value.toString().trim().length &gt; 0);',$html);
    }

    return $html;
}


##############################################
# EXTRA PARSING AFTER MAIN PROXY PARSER
##############################################

function postParse(&$html,$type) {
    
    if ( $type == 'html' ) {
        // Replace the > from the attribute
        $html = str_replace('return ($get(\'q\').value.toString().trim().length &gt; 0);','return ($get(\'q\').value.toString().trim().length > 0);',$html);
    }
    
    return $html;
}


?>
