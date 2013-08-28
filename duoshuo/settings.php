<link rel="stylesheet" href="<?php echo $this->pluginDirUrl; ?>styles.css" type="text/css" />
<div class="wrap">
<?php screen_icon(); ?>
<h2>多说评论框设置</h2>

<h3>高级设定</h3>
<form action="" method="post">
<?php wp_nonce_field('duoshuo-local-options');?>
	<table class="form-table">
		<tbody>
		<tr valign="top">
			<th scope="row">多说域名</th>
			<td><strong><?php echo get_option('duoshuo_short_name');?></strong>.duoshuo.com</td>
		</tr>
		<tr valign="top">
			<th scope="row">密钥</th>
			<td><?php echo get_option('duoshuo_secret');?></td>
		</tr>
		<tr valign="top">
			<th scope="row">多说API服务器</th>
			<td><?php $duoshuo_api_hostname = get_option('duoshuo_api_hostname');?>
				<ul>
					<li><label><input type="radio" name="duoshuo_api_hostname" value="api.duoshuo.com" <?php if ($duoshuo_api_hostname === 'api.duoshuo.com') echo ' checked="checked"';?>/>api.duoshuo.com</label> <span class="description">(如果你的博客服务器在国内，推荐)</span></li>
					<li><label><input type="radio" name="duoshuo_api_hostname" value="api.duoshuo.org" <?php if ($duoshuo_api_hostname === 'api.duoshuo.org') echo ' checked="checked"';?>/>api.duoshuo.org</label> <span class="description">(如果你的博客服务器在国外，推荐)</span></li>
					<li><label><input type="radio" name="duoshuo_api_hostname" value="118.144.80.201" <?php if ($duoshuo_api_hostname === '118.144.80.201') echo ' checked="checked"';?>/>118.144.80.201</label> <span class="description">(除非你的博客服务器DNS出现故障，否则不推荐)</span></li>
				</ul>
			</td>
		</tr>
		<tr valign="top">
			<th scope="row">本地数据备份</th>
			<td>
				<input type="hidden" name="duoshuo_cron_sync_enabled" value="0">
				<label><input type="checkbox" name="duoshuo_cron_sync_enabled" value="1" <?php if (get_option('duoshuo_cron_sync_enabled')) echo ' checked="checked"';?>/>定时从多说备份评论到本地</label></td>
		</tr>
		<tr valign="top">
			<th scope="row">SEO优化</th>
			<td>
				<input type="hidden" name="duoshuo_seo_enabled" value="0">
				<label><input type="checkbox" name="duoshuo_seo_enabled" value="1" <?php if (get_option('duoshuo_seo_enabled')) echo ' checked="checked"';?>/>搜索引擎爬虫访问网页时，显示静态HTML评论</label></td>
		</tr>
		<tr valign="top">
			<th scope="row">Pingback和Trackback</th>
			<td>
				<input type="hidden" name="duoshuo_sync_pingback_and_trackback" value="0">
				<label><input type="checkbox" name="duoshuo_sync_pingback_and_trackback" value="1" <?php if (get_option('duoshuo_sync_pingback_and_trackback')) echo ' checked="checked"';?>/>将接收到的Pingback和Trackback同步到多说</label></td>
		</tr>
		<tr valign="top">
			<th scope="row">脚本后置</th>
			<td>
				<input type="hidden" name="duoshuo_postpone_print_scripts" value="0">
				<label><input type="checkbox" name="duoshuo_postpone_print_scripts" value="1" <?php if (get_option('duoshuo_postpone_print_scripts')) echo ' checked="checked"';?> />在网页底部才插入多说核心脚本embed.js</label></td>
		</tr>
		<tr valign="top">
			<th scope="row">主题适配</th>
			<td>
				<input type="hidden" name="duoshuo_style_patch" value="0">
				<label><input type="checkbox" name="duoshuo_style_patch" value="1" <?php if (get_option('duoshuo_style_patch')) echo ' checked="checked"';?> />自动适配当前WordPress主题</label>
				<p class="description"></p>
			</td>
		</tr>
		<tr valign="top">
			<th scope="row">评论数修正</th>
			<td>
				<input type="hidden" name="duoshuo_cc_fix" value="0">
				<label><input type="checkbox" name="duoshuo_cc_fix" value="1" <?php if (get_option('duoshuo_cc_fix')) echo ' checked="checked"';?> />AJAX加载文章的评论数</label>
				<p class="description">如果你的主题模板没有显示评论数，或者没有按照WordPress的标准，你可能需要修改模板。参见：<a href="http://dev.duoshuo.com/docs/50d7ecc6b2dcd51d2f0002e7">WordPress主题中的文章评论数</a></p>
			</td>
		</tr>
		<tr valign="top">
			<th scope="row">社交帐号登录</th>
			<td>
				<input type="hidden" name="duoshuo_social_login_enabled" value="0">
				<label><input type="checkbox" name="duoshuo_social_login_enabled" value="1" <?php if (get_option('duoshuo_social_login_enabled')) echo ' checked="checked"';?>/>允许用社交帐号登录WordPress</label></td>
		</tr>
		<tr valign="top">
			<th scope="row">评论框前缀</th>
			<td><label><input type="text" class="regular-text code" name="duoshuo_comments_wrapper_intro" value="<?php echo esc_attr(get_option('duoshuo_comments_wrapper_intro'));?>" /></label><br /><span class="description">仅在主题和评论框的div嵌套不正确的情况下使用</span></td>
		</tr>
		<tr valign="top">
			<th scope="row">评论框后缀</th>
			<td><label><input type="text" class="regular-text code" name="duoshuo_comments_wrapper_outro" value="<?php echo esc_attr(get_option('duoshuo_comments_wrapper_outro'));?>" /></label><br /><span class="description">仅在主题和评论框的div嵌套不正确的情况下使用</span></td>
		</tr>
		<tr valign="top">
			<th scope="row">调试开关</th>
			<td>
				<input type="hidden" name="duoshuo_debug" value="0">
				<label><input type="checkbox" name="duoshuo_debug" value="1" <?php if (get_option('duoshuo_debug')) echo ' checked="checked"';?>/>Debug调试开关</label><br /><span class="description">仅在出现故障向多说汇报错误信息时打开</span></td>
		</tr>
		</tbody>
	</table>
	<p class="submit"><input type="submit" name="duoshuo_local_options" id="submit" class="button-primary" value="保存"></p>
</form>

<h3>数据同步</h3>
<div id="ds-export">
	<p class="message-start"><a href="javascript:void(0)" class="button" onclick="fireExport();return false;">同步本地数据库中的评论到多说</a></p>
	<p class="status"></p>
	<p class="message-complete">同步完成</p>
</div>
<div id="ds-sync">
	<p class="message-start"><a href="javascript:void(0)" class="button" onclick="fireSyncLog();return false;">备份多说中的评论到本地数据库</a></p>
	<p class="status"></p>
</div>
<?php include_once dirname(__FILE__) . '/common-script.html';?>

<div>
<h3>清空多说站点配置</h3>
<form action="" method="post" onsubmit="return confirm('你确定要清空多说站点配置吗？');">
	<input type="hidden" name="action" value="duoshuo_reset" />
	<p>如果你希望本博客和其他多说站点进行绑定，或者创建新的多说站点，点此 <input type="submit" class="button" value="清空配置" name="duoshuo_reset" /></p>
</form>
</div>

<div>
	<h3>环境依赖检查</h3>
	<table class="ds-dependencies">
		<thead>
			<tr>
				<th>依赖</th>
				<th>状态</th>
				<th>结果</th>
			</tr>
		</thead>
		<tbody>
		<?php
		$dependencies = array(
			'php'		=>	'php版本',
			'wordpress'	=>	'WordPress版本',
			'json'		=>	'json扩展',
			'curl'		=>	'curl扩展',
			'fopen'		=>	'fopen()',
			'fsockopen'	=>	'fsockopen()',
			'hash_hmac'	=>	'hash_hmac()',
		);
		foreach($dependencies as $key => $name):
			list($status, $result) = $this->checkDependency($key);?>
			<tr>
				<th><?php echo $name;?></th>
				<td><?php echo $status === true ? '支持' : $status;?></td>
				<td><?php echo $result === true ? '<span class="ds-icon-yes">OK</span>' : $result;?></td>
			</tr>
		<?php endforeach;?>
		</tbody>
	</table>
	<p class="description">curl扩展、fopen()、fsockopen()只需支持一个即可，推荐使用curl扩展</p>
</div>

<h3>常见问题和参考链接</h3>
<ul>
	<li><a href="http://dev.duoshuo.com/docs/513b65c57c33a8320d003335" target="_blank">我的主题模板是自己开发的，启用多说之后评论框没有被替换怎么办？</a></li>
	<li><a href="http://dev.duoshuo.com/docs/50d7ecc6b2dcd51d2f0002e7" target="_blank">多说WordPress插件常见问题</a></li>
</ul>

<h3>意见反馈</h3>
<p>你的意见是多说成长的原动力，<a href="http://dev.duoshuo.com/wordpress-plugin" target="_blank">欢迎给我们留言</a>，或许你想要的功能下一个版本就会实现哦！</p>
<p>多说正在招人！如果你相信改变世界不是资本而是技术；如果你不只是想完成任务，还希望你的巧妙构思实现意想不到的好处；如果你希望和跟你一样聪明的人一起工作。<a href="http://dev.duoshuo.com/threads/5138474ea7e92e7b60010bb9" target="_blank">那么你不妨加入我们！</a></p>
<p>
	<iframe width="120" height="23" frameborder="0" allowtransparency="true" marginwidth="0" marginheight="0" scrolling="no" frameborder="No" border="0" src="http://widget.weibo.com/relationship/followbutton.php?language=zh_cn&width=120&height=24&uid=2468548203&style=2&btn=red&dpc=1"></iframe>
	<iframe id="previewmc" src="http://follow.v.t.qq.com/index.php?c=follow&a=quick&name=duo-shuo&style=3&t=1327999237149&f=1" allowtransparency="true" style="margin:0 auto;" frameborder="0" height="23" scrolling="no" width="100"></iframe>
</p>
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
			window.open('http://<?php echo $this->shortName . '.' . self::DOMAIN;?>/share-proxy/?service=' + service + '&url=' + encodeURIComponent(url) + '&message=' + encodeURIComponent(message) + '&title=' + encodeURIComponent(title) + '&images=' + image,
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
