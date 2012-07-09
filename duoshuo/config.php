<link rel="stylesheet" href="<?php echo self::$pluginDirUrl; ?>styles.css" type="text/css" />
<div class="wrap">
<?php echo screen_icon();?>
<?php if (!(self::$shortName && self::$secret)):
$params = self::packageOptions() + array(
	'system'	=>	'wordpress',
	'callback'	=>	admin_url('admin.php?page=duoshuo')
);

if (self::$shortName)
	$params['short_name'] = self::$shortName;
?>
<iframe src="<?php echo 'http://' . self::DOMAIN . '/connect-site/?'. http_build_query($params, null, '&');?>" width="100%" height="600"></iframe>
<?php else:?>
<h2>设置多说站点</h2>
<h3>数据同步</h3>
<div id="ds-export">
	<p class="message-start">安装成功了！只要一键将您的用户、文章和评论信息同步到多说，多说就可以开始为您服务了！<a href="javascript:void(0)" class="button-primary" onclick="fireExport();return false;">开始同步</a></p>
	<p class="status"></p>
	<p class="message-complete">同步完成，现在你可以<a href="<?php echo admin_url('admin.php?page=duoshuo-settings');?>">设置</a>或<a href="<?php echo admin_url('admin.php?page=duoshuo');?>">管理</a></p>
</div>
<?php include_once dirname(__FILE__) . '/common-script.html';?>
<?php endif;?>
</div>