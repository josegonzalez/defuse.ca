<?php

$STUBSHARE_PROFIT = 1.0;

require_once('inc/db.inc.php');
require_once('inc/security.inc.php');
require_once('inc/stubshare.inc.php');

$user = Security::GetCurrentUser();

if($user === false)
{
	header('Location: login.php');
	die();
}

$userID = StubShare::GetUserID($user);

$product_id = -1;
$product_name = "";
$product_desc = "";
$product_owner = -1;
$product_price = -1.0;
$product_url = "";

//TODO: verify user owns the product

if(isset($_GET['id']))
{
	$safe_id = mysql_real_escape_string($_GET['id']);
	
	$q = mysql_query("SELECT * FROM products WHERE id='$safe_id'");

	if($q && mysql_num_rows($q) > 0)
	{
		$info = mysql_fetch_array($q);
		$product_id = (int)$info['id'];
		$product_name = $info['name'];
		$product_desc = $info['description'];
		$product_price = (double)$info['price'];
		$product_owner = (int)$info['owner'];
		$product_url = $info['url'];
	}

}
elseif(isset($_POST['add_stub']))
{
	$charity = (int)$_POST["charity"];
	$prod_id = (int)$_POST['prod_id'];
	$charity_portion = (double)$_POST['c_prof'];
	$sharer_portion = (double)$_POST['s_prof'];

	//TODO: validate correctness, make SURE the chairity value is a REAL charity by checking the database	
	//ALSO: make sure the product is a real one, OWNED BY THIS OWNER

	mysql_query("INSERT INTO stubs (owner, product, profit_owner, charity, profit_pct, charity_pct, stub_pct) VALUES('$userID', '$prod_id', '$userID', '$charity', '$sharer_portion', '$charity_portion', '$STUBSHARE_PROFIT')");
	header('Location: products.php');
	die();
}
else
{
	header('Location: userhome.php');
	die();
}
?>
<html>
<head>
	<title>Add a stub</title>
</head>
<body>
<h1>Add a Stub: <?php echo htmlspecialchars($product_name, ENT_QUOTES); ?></h1>
<b>Product cost: <?php echo htmlspecialchars($product_price, ENT_QUOTES); ?></b>
<form action="addstub.php" method="post" >
<input type="hidden" name="prod_id" value="<?php echo htmlspecialchars($product_id, ENT_QUOTES); ?>" />
<table>
<tr>
	<td>Charity:</td>
	<td><select name="charity">
	<?php
		$q = mysql_query("SELECT * FROM charities WHERE 1");
		if($q && mysql_num_rows($q) > 0)
		{
			while($info = mysql_fetch_array($q))
			{
				$safe_id = htmlspecialchars((int)$info['id'], ENT_QUOTES);
				$safe_name = htmlspecialchars($info['name'], ENT_QUOTES);
				$safe_desc = htmlspecialchars($info['description'], ENT_QUOTES);
				echo "<option value=\"$safe_id\">$safe_name - $safe_desc</option>";
			}
		}
	?>
	</select></td>
</tr>
<tr>
	<td>Charity's profit (% of product cost)</td>
	<td><input type="text" name="c_prof" value="5"/></td>
</tr>
<tr>
	<td>Sharer's profit (% of product cost)</td>
	<td><input type="text" name="s_prof" value="3"/></td>
</tr>
<tr>
	<td>StubShare's profit</td>
	<td><?php echo $STUBSHARE_PROFIT . "%"; ?></td>
</tr>
</table>
<input type="submit" name="add_stub" value="Add" />
</form>

</body>
</html>