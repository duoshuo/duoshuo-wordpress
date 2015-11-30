<?php
$params = array(
	'jwt'	=>	$this->jwt(),
);
$settingsUrl = is_ssl()? 'https://' : 'http://';
$settingsUrl .= self::DOMAIN . '/settings/?' . http_build_query($params, null, '&');
?>
<link rel="stylesheet" href="<?php echo $this->pluginDirUrl; ?>styles.css" type="text/css" />

<div class="wrap">
<?php screen_icon(); ?>
<h2>我的多说帐号
	<a class="add-new-h2" target="_blank" href="<?php echo $settingsUrl;?>">在新窗口中打开</a>
</h2>
<iframe id="duoshuo-remote-window" src="<?php echo $settingsUrl;?>" style="width:960px;height:750px;"></iframe>
</div>
