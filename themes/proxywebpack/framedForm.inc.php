<div style="padding:4px;margin-bottom:2px;background:#eee;font:12px Verdana,Arial,Tahoma;color:#000;">
	<form action="<?php echo optURL;?>includes/process.php?action=update" method="post" style="padding:0;margin:0;">
		<b>URL:</b> <input type="text" name="u" size="40" value="<?php echo $url;?>" style="width:400px;border: 1px solid #447900;">
		<input type="submit" value="Go">
		[<a  style="color:#0000FF;" href="<?php	echo optURL;?>index.php">home</a>] [<a style="color:#0000FF;" href="<?php	echo optURL;?>includes/process.php?action=cookies&type=all&return=<?php	 echo $return;?>">clear cookies</a>]
		<br>
		<b>Options:</b>
	
<?php foreach($toShow as $details) 
//if($details['name']=='allowCookies' || $details['name']=='stripJS' || $details['name']=='encodePage')
//{
echo <<<OUT
		<input type="checkbox" name="{$details['name']}" id="{$details['name']}"{$details['checked']}>
		<label for="{$details['name']}" style="display:inline;">{$details['title']}</label>
OUT;
//}

?>
	
	</form>
</div>
<!--[proxywebpack:proxified]-->
