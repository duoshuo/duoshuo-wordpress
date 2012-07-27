<link rel="stylesheet" href="<?php echo $this->pluginDirUrl; ?>styles.css" type="text/css" />

<div class="wrap">
<?php screen_icon(); ?>
<h2>我的多说帐号</h2>
<?php 
$params = array(
	'template'	=>	'wordpress',
);
try{
	echo $this->getHtml('social-accounts', $params);
}
catch(Duoshuo_Exception $e){
	echo '<p>暂时无法连接到多说服务器，请检查你的DNS设置并稍后重试。</p>';
}

try{ ?>
<h3>个人信息设定</h3>
<form action="" method="post">
<?php
wp_nonce_field('duoshuo-profile');

$params = array(
	'template'	=>	'wordpress',
);
echo $this->getHtml('edit-profile', $params);
?>
<p class="submit"><input type="submit" name="submit" id="submit" class="button-primary" value="保存"></p>
</form>
<?php 
}	// end of try
catch(Duoshuo_Exception $e){
	echo '<p>暂时无法连接到多说服务器，请检查你的DNS设置并稍后重试。</p>';
}
?>
</div>
