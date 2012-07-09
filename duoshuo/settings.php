<link rel="stylesheet" href="<?php echo self::$pluginDirUrl; ?>styles.css" type="text/css" />
<div class="wrap">
<?php screen_icon(); ?>
<h2>多说评论框设置</h2>

<?php try{
$params = array('template'	=>	'wordpress');
$content = Duoshuo::getHtml('settings', $params);?>

<form action="" method="post">
<?php wp_nonce_field('duoshuo-options');?>
<?php echo $content;?>
<p class="submit"><input type="submit" name="submit" id="submit" class="button-primary" value="保存"></p>
</form>

<?php
}	// end of try
catch(Duoshuo_Exception $e){
	Duoshuo::showException($e);
}?>

<h3>高级设定</h3>
<form action="" method="post">
<?php wp_nonce_field('duoshuo-local-options');?>
	<table class="form-table">
		<tbody>
		<tr valign="top">
			<th scope="row">本地数据备份</th>
			<td><input type="checkbox" name="duoshuo_cron_sync_enabled" value="1" <?php if (get_option('duoshuo_cron_sync_enabled')) echo ' checked="checked"';?>/>定时从多说备份评论到本地</td>
		</tr>
		<tr valign="top">
			<th scope="row">SEO优化</th>
			<td><input type="checkbox" name="duoshuo_seo_enabled" value="1" <?php if (get_option('duoshuo_seo_enabled')) echo ' checked="checked"';?>/>搜索引擎爬虫访问网页时，显示静态HTML评论</td>
		</tr>
		<tr valign="top">
			<th scope="row">社交帐号登录</th>
			<td><input type="checkbox" name="duoshuo_social_login_enabled" value="1" <?php if (get_option('duoshuo_social_login_enabled')) echo ' checked="checked"';?>/>允许用社交帐号登录WordPress</td>
		</tr>
		<tr valign="top">
			<th scope="row">评论框前缀</th>
			<td><input type="text" class="regular-text code" name="duoshuo_comments_wrapper_intro" value="<?php echo esc_attr(get_option('duoshuo_comments_wrapper_intro'));?>" /><span class="description">仅在主题和评论框的div嵌套不正确的情况下使用</span></td>
		</tr>
		<tr valign="top">
			<th scope="row">评论框后缀</th>
			<td><input type="text" class="regular-text code" name="duoshuo_comments_wrapper_outro" value="<?php echo esc_attr(get_option('duoshuo_comments_wrapper_outro'));?>" /><span class="description">仅在主题和评论框的div嵌套不正确的情况下使用</span></td>
		</tr>
		</tbody>
	</table>
	<p class="submit"><input type="submit" name="duoshuo_local_options" id="submit" class="button-primary" value="保存"></p>
</form>

<h3>数据同步</h3>
<div id="ds-export">
	<p class="message-start"><a href="javascript:void(0)" class="button" onclick="fireExport();return false;">同步评论到多说</a></p>
	<p class="status"></p>
	<p class="message-complete">同步完成</p>
</div>
<?php include_once dirname(__FILE__) . '/common-script.html';?>

<div style="display:none">
<h3>卸载</h3>
<form action="" method="post" onsubmit="return confirm('你确定要卸载多说评论插件吗？');">
	<input type="hidden" name="action" value="duoshuo_uninstall" />
	<p class="submit"><input type="submit" class="button" value="卸载" name="duoshuo_uninstall" /></p>
</form>
</div>

<h3>意见反馈</h3>
<p>你的意见是多说成长的原动力，<a href="http://blog.duoshuo.com/feedback-wordpress-<?php echo str_replace('.','-',Duoshuo::VERSION);?>/" target="_blank">欢迎给我们留言</a>，或许你想要的功能下一个版本就会实现哦！</p>

<?php
$services = array(
	'qzone'	=>	'QQ空间',
	'weibo'	=>	'新浪微博',
	'qqt'	=>	'腾讯微博',
	'renren'=>	'人人网',
	'kaixin'=>	'开心网',
	'douban'=>	'豆瓣网',
	'netease'=>	'网易微博',
	'sohu'	=>	'搜狐微博',
);

?>
<h3>我们永远相信，分享是一种美德</h3>
<p style="width:100%;overflow: hidden;">把多说分享给你的朋友：</p>
<ul class="ds-share ds-service-icon">
<?php foreach($services as $service => $serviceName):?>
	<li><a class="ds-<?php echo $service;?>" title="<?php echo $serviceName;?>"></a></li>
<?php endforeach;?>
</ul>
<script>
jQuery(function(){
	var $ = jQuery,
		duoshuoName = {
			weibo	: '@多说网',
			qzone	: '@多说网',
			qqt		: '@多说网',
			renren	: '多说',
			kaixin	: '多说',
			douban	: '多说',
			netease	: '@多说网',
			sohu	: '@多说网'
		},
		handler = function(e){
			var service = this.className.match(/ds\-(\w+)/)[1],
				message = <?php echo json_encode('我的' . get_option('blogname') . '（' .get_option('siteurl') . '）装了');?> + duoshuoName[service] + ' 评论插件，用微博、QQ、人人帐号就能登录评论了，很给力。来试试吧！',
				image = 'http://static.duoshuo.com/images/top.jpg',
				title = '多说评论插件',
				url = 'http://duoshuo.com';
			window.open('http://<?php echo self::$shortName . '.' . Duoshuo::DOMAIN;?>/share-proxy/?service=' + service + '&url=' + encodeURIComponent(url) + '&message=' + encodeURIComponent(message) + '&title=' + encodeURIComponent(title) + '&images=' + image,
				'_blank',
				'height=550,width=600,top=0,left=0,toolbar=no,menubar=no,resizable=yes,location=yes,status=no');
			return false;
		};
	$.fn.delegate
		? $('.ds-share').delegate('a', 'click', handler)
		: $('.ds-share a').click(handler);
});
</script>

</div>
