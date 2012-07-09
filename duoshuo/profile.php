<link rel="stylesheet" href="<?php echo self::$pluginDirUrl; ?>styles.css" type="text/css" />
<?php
$user = wp_get_current_user();
if ( $_SERVER['REQUEST_METHOD'] == 'POST'):

$params = array(
	'name'			=> stripslashes($_POST['name']),
	'email'			=> stripslashes($_POST['email']),
	'url'			=> stripslashes($_POST['url']),
) + $_POST;

try{
	$response = Duoshuo::getClient($user->ID)->request('POST', 'users/update', $params);
	
	if ($response['code'] == 0):?>
	<div id="message" class="updated fade"><p><strong><?php _e('Options saved.') ?></strong></p></div>
	<?php else:?>
	<div id="message" class="updated fade"><p><strong><?php echo $response['errorMessage'];?></strong></p></div>
	<?php endif;
}
catch(Duoshuo_Exception $e){
	Duoshuo::showException($e);
}

endif;?>

<div class="wrap">
<?php screen_icon(); ?>
<h2>我的多说帐号</h2>
<?php 
$params = array(
	'template'	=>	'wordpress',
);
try{
	echo Duoshuo::getHtml('social-accounts', $params);
}
catch(Duoshuo_Exception $e){
	Duoshuo::showException($e);
}

try{ ?>
<h3>个人信息设定</h3>
<form action="" method="post">
<?php
wp_nonce_field('duoshuo-profile');

$params = array(
	'template'	=>	'wordpress',
);
echo Duoshuo::getHtml('edit-profile', $params);
?>
<p class="submit"><input type="submit" name="submit" id="submit" class="button-primary" value="保存"></p>
</form>
<?php 
}	// end of try
catch(Duoshuo_Exception $e){
	Duoshuo::showException($e);
}
?>
</div>
