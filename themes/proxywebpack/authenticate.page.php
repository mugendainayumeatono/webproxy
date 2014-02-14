<?php	
sendNoCache();
?>
<html>
<head>
	<title>401 Authorization Required</title>
	<style type="text/css">
html, body {
	height: 100%;
	background: #ddd;
	text-align: center;
	font-size: small;
}
#wrapper {
	margin: 100px auto;
	width: 400px;
	text-align: left;
	background: #fff;
	padding: 10px;
}
label {
	font-weight: bold;
	display: block;
}
	</style>
</head>
<body>
	<div id="wrapper">
		<p>The page you are attempting to access requires a password. Enter your username and password below to proceed:</p>
		<form action="includes/process.php?action=authenticate" method="post">
			<label for="user">Username:</label>
			<input type="text" name="user" id="user">
			<label for="pass">Password:</label>
			<input type="password" name="pass" id="pass">
			<input type="submit" value="Submit">
		</form>
	</div>
</body>
</html>
