<link rel="stylesheet" href="<?php echo self::$pluginDirUrl; ?>styles.css" type="text/css" />
<?php
$params = array(
	'template'		=>	'wordpress',
	'remote_auth'	=>	Duoshuo::remoteAuth(),
);
$adminUrl = 'http://' . Duoshuo::$shortName . '.' . Duoshuo::DOMAIN.'/admin/?'.http_build_query($params);
?>
<div class="wrap">
<?php screen_icon(); ?>
<h2>多说评论管理
<a style="font-size:13px" target="_blank" href="<?php echo $adminUrl;?>">在新窗口中打开</a>
</h2>
<iframe id="duoshuo-remote-window" src="<?php echo $adminUrl;?>" style="width:100%;"></iframe>
</div>

<script>
jQuery(function(){
var $ = jQuery,
	iframe = $('#duoshuo-remote-window'),
	resetIframeHeight = function(){
		iframe.height($(window).height() - iframe.offset().top - 70);
	};
resetIframeHeight();
$(window).resize(resetIframeHeight);
});
</script>
