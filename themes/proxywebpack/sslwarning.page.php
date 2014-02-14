<html>
<head>
	<title>Security Warning</title>
	<style type="text/css">
html, body {
	height: 100%;
	background: #ddd;
	text-align: center;
	font-family: Tahoma;
}
#wrapper {
	margin: 100px auto;
	width: 400px;
	text-align: left;
	background: #fff;
	padding: 10px;
}
	</style>
</head>
<body>
	<div id="wrapper">
		<h1>Warning!</h1>
		<p>The site you are attempting to browse is on a secure connection. This proxy is not on a secure connection. The target site may send sensitive data, which may be intercepted when the proxy sends it back to you.</p>
		<form action="includes/process.php" method="get"><input type="hidden" value="sslagree" name="action">
			<input type="submit" value="Continue anyway...">
		</form>
	</div>
</body>
</html>