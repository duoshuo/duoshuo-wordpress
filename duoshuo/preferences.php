<link rel="stylesheet" href="<?php echo $this->pluginDirUrl; ?>styles.css" type="text/css" />
<div class="wrap">
<?php screen_icon(); ?>
<h2>多说评论框设置</h2>

<?php try{
$params = array('template'	=>	'wordpress');
$content = $this->getHtml('settings', $params);?>

<form action="" method="post">
<?php wp_nonce_field('duoshuo-options');?>
<?php echo $content;?>
<p class="submit"><input type="submit" name="submit" id="submit" class="button-primary" value="保存"></p>
</form>

<?php
}	// end of try
catch(Duoshuo_Exception $e){
	$this->showException($e);
}?>

</div>
