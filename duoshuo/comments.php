<?php 
if (!isset($post))
	global $post;

if (!did_action('wp_head') && !Duoshuo::$scriptsPrinted){
	Duoshuo::printScripts();
}

if ($intro = get_option('duoshuo_comments_wrapper_intro'))
	echo $intro;
?>
<a name="comments"></a>
<?php
$threadId = get_post_meta($post->ID, 'duoshuo_thread_id', true);
	if (empty($threadId)):?>
	<p>在管理后台和多说同步数据后，多说就能为您服务啦。</p> 
<?php else:
	$data = array(
		'thread_id'	=>	$threadId,
		'title'		=>	$post->post_title,
		'url'		=>	get_permalink(),
		//'order'		=>	'desc',
		//'limit'		=>	20,
	);
	
	$attribs = '';
	foreach ($data as $key => $value)
		$attribs .= ' data-' . str_replace('_','-',$key) . '="' . esc_attr($value) . '"';
	?>
<div class="ds-thread"<?php echo $attribs;?>></div>
	<?php if (!defined('DUOSHUO_THREAD_INITIALIZED')):
		define('DUOSHUO_THREAD_INITIALIZED', true);?>
<script type="text/javascript">
	if (typeof DUOSHUO !== 'undefined')
		DUOSHUO.EmbedThread('.ds-thread');
</script>
	<?php endif;?>
<?php endif;

if (get_option('duoshuo_seo_enabled')): //直接输出HTML评论
	require 'comments-seo.php';
endif;

if ($outro = get_option('duoshuo_comments_wrapper_outro'))
	echo $outro;
