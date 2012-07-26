<?php 
if (!isset($post))
	global $post;

$duoshuoPlugin = Duoshuo_WordPress::getInstance();
if (!did_action('wp_head')){
	$duoshuoPlugin->printScripts();
}

if ($intro = get_option('duoshuo_comments_wrapper_intro'))
	echo $intro;
?>
<a name="comments"></a>

<?php
if (current_user_can('moderate_comments')):
	$threadId = get_post_meta($post->ID, 'duoshuo_thread_id', true);
	if (empty($threadId)):?>
		<p>这篇文章的评论尚未同步到多说，<a href="<?php echo admin_url('admin.php?page=duoshuo-settings');?>">点此同步</a></p>
	<?php endif;
endif;
 
$data = array(
	'thread-key'=>	$post->ID,
	'author-key'=>	$post->post_author,
	'title'		=>	$post->post_title,
	'url'		=>	get_permalink(),
	//'order'		=>	'desc',
	//'limit'		=>	20,
);

$attribs = '';
foreach ($data as $key => $value)
	$attribs .= ' data-' . $key . '="' . esc_attr($value) . '"';
?>
<div class="ds-thread"<?php echo $attribs;?>></div>

<?php
static $threadInitialized = false;
if (!$threadInitialized):
	$threadInitialized = true;?>
<script type="text/javascript">
	if (typeof DUOSHUO !== 'undefined')
		DUOSHUO.EmbedThread('.ds-thread');
</script>
<?php endif;

if (get_option('duoshuo_seo_enabled')): //直接输出HTML评论
	require 'comments-seo.php';
endif;

if ($outro = get_option('duoshuo_comments_wrapper_outro'))
	echo $outro;
