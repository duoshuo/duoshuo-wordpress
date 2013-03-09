<link rel="stylesheet" href="<?php echo $this->pluginDirUrl; ?>styles.css" type="text/css" />
<?php
$params = array(
	'jwt'	=>	$this->jwt(),
);
$adminUrl = 'http://' . $this->shortName . '.' . self::DOMAIN . '/admin/statistics/?' . http_build_query($params, null, '&');
?>
<div class="wrap">
<?php screen_icon(); ?>
<h2>数据统计
<a class="add-new-h2" target="_blank" href="<?php echo $adminUrl;?>">在新窗口中打开</a></h2>
<iframe id="duoshuo-remote-window" src="<?php echo $adminUrl;?>" style="width:100%;height:920px"></iframe>
</div>
