<link rel="stylesheet" href="<?php echo $this->pluginDirUrl; ?>styles.css" type="text/css" />
<div class="wrap">
<a href="https://github.com/duoshuo/duoshuo-embed.css" target="_blank"><img style="position: absolute; top: 0; right: 0; border: 0;" src="https://s3.amazonaws.com/github/ribbons/forkme_right_orange_ff7600.png" alt="Fork me on GitHub"></a>
<?php screen_icon(); ?>
<h2>多说主题设置</h2>

<form action="" method="post">
<?php wp_nonce_field('duoshuo-local-options');?>
	<ul class="ds-themes"></ul>
	<p>多说的CSS样式已经开源啦！ <a href="https://github.com/duoshuo/duoshuo-embed.css" target="_blank">github:duoshuo/duoshuo-embed.css</a></p>
	<p>你可以打造属于自己的主题，在<a href="http://dev.duoshuo.com/discussion" target="_blank">开发者中心</a>分享你的主题，还有可能被官方推荐哟！</p>
</form>
<style>
.ds-themes li{
	display: inline-block;
	margin-right: 10px;
	overflow: hidden;
	padding: 20px 20px 20px 0;
	vertical-align: top;
	width: 300px;
}
</style>
<script>
function loadDuoshuoThemes(json){
	var $ = jQuery;
	
	$(function(){
		var html = '';
		$.each(json.response, function(key, theme){
			html += '<li>'
				+ '<img src="' + theme.screenshot + '" width="300" height="225" style="border:1px #CCC solid;" />'
				+ '<h3>' + theme.name + '</h3>'
				+ '<p>作者：<a href="' + theme.author_url + '" target="_blank">' + theme.author_name + '</a></p>'
				+ '<div class="action-links">'
					+ ( key == <?php echo json_encode($this->getOption('theme'));?>
						? '<span class="">当前主题</span>'
						: '<a href="admin.php?page=duoshuo-themes&amp;duoshuo_theme=' + key + '&amp;_wpnonce=<?php echo wp_create_nonce('set-duoshuo-theme'); ?>" class="activatelink" title="启用 “' + theme.name + '”">启用</a>')
				+ '</div>'
				+ '</li>';
		});
		$('.ds-themes').html(html);
	});
}
</script>

<script src="http://<?php echo $this->shortName;?>.duoshuo.com/api/sites/themes.jsonp?callback=loadDuoshuoThemes"></script>

</div>
