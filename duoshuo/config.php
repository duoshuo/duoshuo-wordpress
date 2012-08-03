<div class="wrap">
<?php echo screen_icon();?><h2>注册站点</h2>
<?php
$user = wp_get_current_user();
$params = $this->packageOptions() + array(
	'system'	=>	'wordpress',
	'callback'	=>	admin_url('admin.php?page=duoshuo'),
	'user_key'	=>	$user->ID,
	'sync_log'	=>	1,
);

if ($this->shortName)
	$params['short_name'] = $this->shortName;
?>
<iframe id="duoshuo-remote-window" src="<?php echo 'http://' . self::DOMAIN . '/connect-site/?'. http_build_query($params, null, '&');?>" width="100%" height="600"></iframe>
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
</div>
