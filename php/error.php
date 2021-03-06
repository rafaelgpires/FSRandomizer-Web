<?php
/*
 * function error()
 * 
 * Function:	Log errors and deal with them on a user level
 *		This function should be called from within index.php and assumes $html has been declared
 *		$die = true; assumes no output has been given to the user yet
 * 
 * Syntax:	function error(
 *			$message as string,	Text of the error message
 *			$die as bool,		Whether to return a complete error page and prevent further code execution or just trigger the error as usual
 *		)
 */
function error($message, $die) {
	
	if(!$die) { trigger_error($message); }
	else {
		global $html;
?>
<html lang="en">
<head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
	<?=$html->styles?>
	<title>FSRandomizer</title>
</head>
<body>
	<!-- Content -->
	<div class="container vh-100">
		<!-- Navbar -->
		<div class="row h-25 pt-4 align-items-top">
			<?=$html->navbar?>
		</div>
		
		<!-- Error -->
		<div class="row h-50 align-items-center">
			<div class="container">
				<div class="row d-flex justify-content-center">
					<h1 class="text-center">Uh oh... Error!<br/>
				</div>
				<div class="row mt-4 d-flex justify-content-center">
					<h1 class="text-center descriptor"><?=$message?></h1>
				</div>
				<div class="row mt-4 d-flex justify-content-center">
					<img src="./images/broken_guitar.png" />
				</div>
			</div>
		</div>
	</div>
	
	<!-- jQuery, Popper, bootstrap.js, Local scripts -->
	<?=$html->links?>
</body>
</html>
<?php
		exit;
	}
}
?>