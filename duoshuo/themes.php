<link rel="stylesheet" href="<?php echo $this->pluginDirUrl; ?>styles.css" type="text/css" />
<div class="wrap">
<?php screen_icon(); ?>
<h2>多说评论主题设置</h2>

<form action="" method="post">
<?php wp_nonce_field('duoshuo-local-options');?>
	<ul class="ds-themes">
	</ul>
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
				+ '<img src="' + theme.screenshot + '" width="300" height="225" />'
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
