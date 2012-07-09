<?php
/*
Plugin Name: 多说
Plugin URI: http://wordpress.org/extend/plugins/duoshuo/
Description: 追求最佳用户体验的社会化评论框，为中小网站提供“新浪微博、QQ、人人、豆瓣等多帐号登录并评论”功能。“多说”帮你搭建更活跃，互动性更强的评论平台，功能强大且永久免费。
Author: 多说网
Version: 0.7.2
Author URI: http://duoshuo.com/
*/

if (version_compare(PHP_VERSION, '5.0.0', '<')){
	if(is_admin()){
		function duoshuo_php_version_warning(){
			echo '<div class="updated"><p><strong>您的php版本低于5.0，请升级php到最新版，多说就能为您服务了。</strong></p></div>';
		}
		add_action('admin_notices', 'duoshuo_php_version_warning');
	}
	return;
}

if (version_compare( $wp_version, '2.8', '<' )){
	if(is_admin()){
		function duoshuo_wp_version_warning(){
			echo '<div class="updated"><p><strong>您的WordPress版本低于2.8，请升级WordPress到最新版，多说就能为您服务了。</strong></p></div>';
		}
		add_action('admin_notices', 'duoshuo_wp_version_warning');
	}
	return;
}

function duoshuo_get_available_transport(){
	if (extension_loaded('curl') && function_exists('curl_init') && function_exists('curl_exec'))
		return 'curl';
	
	if (function_exists('fopen') && function_exists('ini_get') && ini_get('allow_url_fopen'))
		return 'streams';
	
	if (function_exists('fsockopen') && (false === ($option = get_option( 'disable_fsockopen' )) || time() - $option >= 43200))
		return 'fsockopen';
	
	return false;
}

$transport = duoshuo_get_available_transport();
if ($transport === false){
	if(is_admin()){
		function duoshuo_transport_warning(){
			echo '<div class="updated"><p><strong>没有可用的 HTTP 传输器</strong>，请联系你的主机商，安装或开启curl</p></div>';
		}
		add_action('admin_notices', 'duoshuo_transport_warning');
	}
	return;
}

if (!extension_loaded('json'))
	include dirname(__FILE__) . '/compat-json.php';
	
include dirname(__FILE__) . '/compat-wp.php';


function rfc3339_to_mysql($string){
	if (method_exists('DateTime', 'createFromFormat')){	//	php 5.3.0
		return DateTime::createFromFormat(DateTime::RFC3339, $string)->format('Y-m-d H:i:s');
	}
	else{
		$timestamp = strtotime($string);
		return gmdate('Y-m-d H:i:s', $timestamp  + get_option('gmt_offset') * 3600);
	}
}

function rfc3339_to_mysql_gmt($string){
	if (method_exists('DateTime', 'createFromFormat')){	//	php 5.3.0
		return DateTime::createFromFormat(DateTime::RFC3339, $string)->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
	}
	else{
		$timestamp = strtotime($string);
		return gmdate('Y-m-d H:i:s', $timestamp);
	}
}

class Duoshuo_Exception extends Exception{
	const SUCCESS		= 0;
	const ENDPOINT_NOT_VALID = 1;
	const MISSING_OR_INVALID_ARGUMENT = 2;
	const ENDPOINT_RESOURCE_NOT_VALID = 3;
	const NO_AUTHENTICATED = 4;
	const INVALID_API_KEY = 5;
	const INVALID_API_VERSION = 6;
	const CANNOT_ACCESS = 7;
	const OBJECT_NOT_FOUND = 8;
	const API_NO_PRIVILEGE = 9;
	const OPERATION_NOT_SUPPORTED = 10;
	const API_KEY_INVALID = 11;
	const NO_PRIVILEGE = 12;
	const RESOURCE_RATE_LIMIT_EXCEEDED = 13;
	const ACCOUNT_RATE_LIMIT_EXCEEDED = 14;
	const INTERNAL_SERVER_ERROR = 15;
	const REQUEST_TIMED_OUT = 16;
	const NO_ACCESS_TO_THIS_FEATURE = 17;
	const INVALID_SIGNATURE = 18;
	
	const USER_DENIED_YOUR_REQUEST = 21;
	const EXPIRED_TOKEN = 22;
	const REDIRECT_URI_MISMATCH = 23;
	const DUPLICATE_CONNECTED_ACCOUNT = 24;
	
	const PLUGIN_DEACTIVATED = 30;
}

require dirname(__FILE__) . '/DuoshuoClient.php';;

class Duoshuo {
	const DOMAIN = 'duoshuo.com';
	const STATIC_DOMAIN = 'static.duoshuo.com';
	const VERSION = '0.7.2';
	
	/**
	 * 
	 * @var string
	 */
	static $shortName;
	
	/**
	 * 
	 * @var string
	 */
	static $secret;
	
	/**
	 * 
	 * @var string
	 */
	static $pluginDirUrl;
	
	/**
	 * 
	 * @var array
	 */
	static $errorMessages = array();
	
	static $EMBED = false;
	
	static $scriptsPrinted = false;
	
	static function setVariables(){
		self::$shortName = get_option('duoshuo_short_name');
		self::$secret = get_option('duoshuo_secret');
		
		$defaultOptions = array(
			'duoshuo_cron_sync_enabled'		=>	1,
			'duoshuo_seo_enabled'			=>	1,
			'duoshuo_social_login_enabled'	=>	1,
			'duoshuo_comments_wrapper_intro'=>	'',
			'duoshuo_comments_wrapper_outro'=>	'',
			'duoshuo_last_post_id'			=>	0,
		);
		
		foreach ($defaultOptions as $optionName => $value)
			if (get_option($optionName) === false)
				update_option($optionName, $value);
		
		self::$pluginDirUrl = plugin_dir_url(__FILE__);
	}
	
	static function adminInitialize(){
		global $wp_version;
		
		//在admin界面内执行的action
		// wordpress2.8 以后都支持这个过滤器
		add_filter('plugin_action_links_duoshuo/duoshuo.php', array('Duoshuo', 'pluginActionLinks'), 10, 2);
		
		if (empty(self::$shortName) || empty(self::$secret)){//你尚未安装这个插件。
			add_action('admin_notices', array('Duoshuo', 'warning'));
			return ;
		}
		
		add_action('admin_notices', array('Duoshuo', 'notices'));
		
		add_action('switch_theme', array('Duoshuo', 'updateSite'));
		//	support from WP 2.9
		add_action('updated_option', array('Duoshuo', 'updatedOption'));
		
		add_filter('post_row_actions', array('Duoshuo', 'actionsFilter'));
		
		//// backwards compatible (before WP 3.0)
		if (version_compare( $wp_version, '3.0', '<' ) && current_user_can('administrator')){
			function duoshuo_wp_version_notice(){
				echo '<div class="updated"><p>您的WordPress版本低于3.0，如果您能升级WordPress，多说就能更好地为您服务。</p></div>';
			}
			add_action('admin_notices', 'duoshuo_wp_version_notice');
		}
		
		if (function_exists('get_post_types')){//	support from WP 2.9
			$post_types = get_post_types( array('public' => true, 'show_in_nav_menus' => true), 'objects');
			
			foreach($post_types as $type => $object)
				add_meta_box('duoshuo-sidebox', '同时发布到', array('Duoshuo','syncOptions'), $type, 'side', 'high');
		}
		else{
			add_meta_box('duoshuo-sidebox', '同时发布到', array('Duoshuo','syncOptions'), 'post', 'side', 'high');
			add_meta_box('duoshuo-sidebox', '同时发布到', array('Duoshuo','syncOptions'), 'page', 'side', 'high');
		}
		//wp 3.0以下不支持此项功能
		/**
		 * TODO 
		if ($post !== null && 'publish' == $post->post_status || 'private' == $post->post_status)
			add_meta_box('duoshuo-comments', '来自社交网站的评论(多说)', array('Duoshuo','managePostComments'), 'post', 'normal', 'low');
		 */
		
		add_action('profile_update', array('Duoshuo', 'syncUserToRemote'));
		add_action('user_register', array('Duoshuo', 'syncUserToRemote'));
		
		add_action('wp_dashboard_setup', array('Duoshuo','addDashboardWidget'));
		
		add_action('load-edit-comments.php', array('Duoshuo','addOriginalCommentsNotice'));
		
		if (defined('DOING_AJAX')){
			add_action('wp_ajax_duoshuo_export', array('Duoshuo', 'export'));
		}
		
		self::commonInitialize();
	}
	
	static function initialize(){
		if (empty(self::$shortName) || empty(self::$secret)){
			return;
		}
		
		if (get_option('duoshuo_social_login_enabled'))
			add_action('login_form', array('Duoshuo', 'loginForm'));
		//add_action('wp_login', array('Duoshuo', 'login'));
		
		// wp2.8 以后支持这个事件
		add_action('wp_print_scripts', array('Duoshuo', 'appendScripts'));
		//add_action('wp_head', array('Duoshuo', 'appendStyles'));
		
		//以下应该根据是否设置，选择是否启用
		add_filter('comments_template', array('Duoshuo','commentsTemplate'));
		
		//add_filter('comments_number')
		if (is_active_widget(false, false, 'recent-comments'))
			add_action('wp_footer', array('Duoshuo', 'outputFooterCommentJs'));
		
		add_filter('comments_number', array('Duoshuo', 'commentsText'));
			
		add_action('trackback_post', array('Duoshuo', 'exportOneComment'));
		add_action('pingback_post', array('Duoshuo', 'exportOneComment'));
		
		self::commonInitialize();
	}
	
	static function commonInitialize(){
		// 没有用cookie方式保持身份，所以不需要重定向
		//add_action('wp_logout', array('Duoshuo', 'logout'));
		add_filter('comments_open', array('Duoshuo', 'commentsOpen'));
		
		if (get_option('duoshuo_cron_sync_enabled')){
			add_action('duoshuo_sync_cron', array('Duoshuo', 'syncCron'));
			if (!wp_next_scheduled('duoshuo_sync_cron')){
				wp_schedule_event(time(), 'hourly', 'duoshuo_sync_cron');
			}
		}
	}
	
	static function oauthBind(){
		global $wpdb;
		
		if (!isset($_GET['code']))
			return false;
		
		$oauth = new DuoshuoClient(self::$shortName, self::$secret);
		
		$keys = array(
			'code'	=> $_GET['code'],
			'redirect_uri' => 'http://duoshuo.com/login-callback/weibo/',
		);
		
		$token = $oauth->getAccessToken('code', $keys);
		
		if ($token['code'] != 0)
			return false;
		
		$user_id = $wpdb->get_var("SELECT user_id FROM {$wpdb->usermeta} WHERE meta_value = '$token[user_id]' AND meta_key = 'duoshuo_user_id'");
		
		nocache_headers();
		if ($user_id === null){
			//	TODO
			//	如果站点开启注册
			//	如果站点不开启注册，则把用户带回入口页
			echo '用户不存在';
		}
		else{//登陆成功
			$user_id = (int) $user_id;
			
			self::updateUserMeta($user_id, 'duoshuo_access_token', $token['access_token']);
			
			wp_clear_auth_cookie();
			wp_set_auth_cookie($user_id, true, is_ssl());
			wp_set_current_user($user_id);
		}
		
		if (isset($_GET['redirect_to'])){
			// wordpress 采用的是redirect_to字段
			wp_redirect($_GET['redirect_to']);
			exit;
		}
	}
	
	static function oauthConnect(){
		global $wpdb, $error;
		
		if (!isset($_GET['code']))
			return false;
		
		$oauth = new DuoshuoClient(self::$shortName, self::$secret);
		
		$keys = array(
			'code'	=> $_GET['code'],
			'redirect_uri' => 'http://duoshuo.com/login-callback/',
		);
		
		$token = $oauth->getAccessToken('code', $keys);
		
		if ($token['code'] != 0)
			return false;
		
		$user_id = $wpdb->get_var("SELECT user_id FROM {$wpdb->usermeta} WHERE meta_value = '$token[user_id]' AND meta_key = 'duoshuo_user_id'");
		
		nocache_headers();
		if ($user_id === null){
			//	TODO
			//	如果站点开启注册
			//	如果站点不开启注册，则把用户带回入口页
			if (isset($_GET['redirect_to']) && $_GET['redirect_to'] !== admin_url()){
				wp_redirect($_GET['redirect_to']);
				exit;
			}
			else{	//如果是从wp-login页面发起的请求，就不触发重定向
				$error = '你授权的社交帐号没有和本站的用户帐号绑定；<br />如果你是本站注册用户，请先登录之后绑定社交帐号';
			}
		}
		else{//登陆成功
			$user_id = (int) $user_id;
			
			self::updateUserMeta($user_id, 'duoshuo_access_token', $token['access_token']);
			
			wp_clear_auth_cookie();
			wp_set_auth_cookie($user_id, true, is_ssl());
			wp_set_current_user($user_id);
			
			if (isset($_GET['redirect_to'])){
				// wordpress 采用的是redirect_to字段
				wp_redirect($_GET['redirect_to']);
				exit;
			}
		}
	}
	
	static function oauthDisconnect(){
		global $wpdb;
		
		if (!isset($_GET['code']))
			return false;
		
		$oauth = new DuoshuoClient(self::$shortName, self::$secret);
		
		$keys = array(
			'code'	=> $_GET['code'],
			'redirect_uri' => 'http://duoshuo.com/login-callback/weibo/',
		);
		
		$token = $oauth->getAccessToken('code', $keys);
		
		if ($token['code'] != 0)
			return false;
		
		$user_id = $wpdb->get_var("SELECT user_id FROM {$wpdb->usermeta} WHERE meta_value = '$token[user_id]' AND meta_key = 'duoshuo_user_id'");
		
		nocache_headers();
		if ($user_id === null){
			//	TODO
			//	如果站点开启注册
			//	如果站点不开启注册，则把用户带回入口页
			echo '用户不存在';
		}
		else{//登陆成功
			$user_id = (int) $user_id;
			
			self::updateUserMeta($user_id, 'duoshuo_access_token', $token['access_token']);
			
			wp_clear_auth_cookie();
			wp_set_auth_cookie($user_id, true, is_ssl());
			wp_set_current_user($user_id);
		}
		
		if (isset($_GET['redirect_to'])){
			// wordpress 采用的是redirect_to字段
			wp_redirect($_GET['redirect_to']);
			exit;
		}
	}
	
	/*
	static function logout(){
		$query = array(
			'redirect_uri'=> !empty( $_REQUEST['redirect_to'] ) ? $_REQUEST['redirect_to'] : site_url('wp-login.php') . '?loggedout=true',
			'sso'		=>	1,
		);
		$logoutUrl = 'http://' . self::$shortName . '.duoshuo.com/logout/?' . http_build_query($query, null, '&');
		
		wp_redirect( $logoutUrl );
		exit;
	}
	*/
	
	static function addPages() {
		if (empty(self::$shortName) || empty(self::$secret) || !is_numeric(get_option('duoshuo_synchronized'))){
			add_object_page(
				'安装',
				'多说评论',
				'moderate_comments',
				'duoshuo',
				array('Duoshuo','config'),
				self::$pluginDirUrl . 'images/menu-icon.png' 
			);
		}
		elseif (current_user_can('moderate_comments')){
			add_object_page(
				'多说评论管理',
				'多说评论',
				'moderate_comments',
				'duoshuo',
				array('Duoshuo','manage'),
				self::$pluginDirUrl . 'images/menu-icon.png' 
			);
			add_submenu_page(
		         'duoshuo',//$parent_slug
		         '评论框设置',//page_title
		         '评论框设置',//menu_title
		         'manage_options',//权限
		         'duoshuo-settings',//menu_slug
		         array('Duoshuo', 'settings')//function
		    );
		    if (self::getUserMeta(wp_get_current_user()->ID, 'duoshuo_user_id')){
			    add_submenu_page(
			         'duoshuo',//$parent_slug
			         '我的多说帐号',//page_title
			         '我的多说帐号',//menu_title
			         'level_0',//权限
			         'duoshuo-profile',//menu_slug
			         array('Duoshuo', 'profile')//function
			    );
		    }
		}
		elseif(current_user_can('level_0')){
		    if (self::getUserMeta(wp_get_current_user()->ID, 'duoshuo_user_id')){
			    add_submenu_page(
			         'profile.php',//$parent_slug
			         '绑定社交帐号',//page_title
			         '[多说]绑定社交帐号',//menu_title
			         'level_0',//权限
			         'duoshuo-profile',//menu_slug
			         array('Duoshuo', 'profile')//function
			    );
		    }
		}
	}
	
	static function addOriginalCommentsNotice(){
		function duoshuo_original_comments_notice(){
			echo '<div class="updated">'
				. '<p>多说正在努力地为您的网站提供强大的社会化评论服务，WordPress原生评论数据现在仅用于备份；</p>'
				. '<p>多说会将每一条评论实时写回本地数据库，但如果日后您在多说删除/修改了评论，并不会影响本地数据；</p>'
				. '<p>您在本页做的任何管理评论操作，都不会对多说评论框上的评论起作用，请访问<a href="http://' . Duoshuo::$shortName . '.' . Duoshuo::DOMAIN . '/admin/" target="_blank">评论管理后台</a>进行评论管理。</p>'
				. '</div>';
		}
		add_action('admin_notices', 'duoshuo_original_comments_notice');
	}
	
	static function addDashboardWidget(){
		wp_add_dashboard_widget('dashboard_duoshuo', '多说', array('Duoshuo', 'dashboardWidget'));
	}
	
	static function registerSettings(){
		register_setting('duoshuo', 'duoshuo_short_name');
		register_setting('duoshuo', 'duoshuo_secret');
		
		register_setting('duoshuo', 'duoshuo_cron_sync_enabled');
		register_setting('duoshuo', 'duoshuo_seo_enabled');
		register_setting('duoshuo', 'duoshuo_social_login_enabled');
		register_setting('duoshuo', 'duoshuo_comments_wrapper_intro');
		register_setting('duoshuo', 'duoshuo_comments_wrapper_outro');
	}
	
	/**
	 * 
	 * @return DuoshuoClient
	 */
	static function getClient($userId = 0){	//如果不输入参数，就是游客
		$remoteAuth = self::remoteAuth($userId);
		
		if ($userId !== null){
			$accessToken = self::getUserMeta($userId, 'duoshuo_access_token');
			
			if (is_string($accessToken))
				return new DuoshuoClient(self::$shortName, self::$secret, $remoteAuth, $accessToken);
		}
		return new DuoshuoClient(self::$shortName, self::$secret, $remoteAuth);
	}
	
	/**
	 * 
	 * @param $action
	 * @param $params
	 * @throws Duoshuo_Exception
	 * @return string
	 */
	static function getHtml($action, $params){
		$params['remote_auth'] = self::remoteAuth();
		$url = 'http://' . self::$shortName . '.duoshuo.com/' . $action . '/?' . http_build_query($params, null, '&');
		$args = array(
			'method' => 'GET',
			'timeout' => 15,
			'redirection' => 5,
			'httpversion' => '1.0',
			//'user-agent' => $this->userAgent,
		);
		
		$http = new WP_Http();
		$response = $http->request($url, $args);
		if (isset($response->errors))
            throw new Duoshuo_Exception('连接服务器失败,详细信息：' . json_encode($response->errors), Duoshuo_Exception::REQUEST_TIMED_OUT);
        
		return $response['body'];
	}
	
	static function showException($e){
		echo '<div class="updated fade"><p>' . $e->getMessage() . '</p></div>';
	}
	
	static function sendException($e){
		$response = array(
			'code'	=>	$e->getCode(),
			'errorMessage'=>$e->getMessage(),
		);
		echo json_encode($response);
		exit;
	}
	
	static function config(){
		/*if ($_SERVER['REQUEST_METHOD'] == 'POST' && !(self::$shortName && self::$secret)){
			self::registerSite();
		}*/
		include_once dirname(__FILE__) . '/config.php';
	}
	
	static function manage(){
		include_once dirname(__FILE__) . '/manage.php';
	}
	
	static function settings(){
		if ($_SERVER['REQUEST_METHOD'] == 'POST')
			try{
				$user = wp_get_current_user();
				$params = $_POST;
				
				$response = Duoshuo::getClient($user->ID)->request('POST', 'sites/settings', $params);
				
				if ($response['code'] != 0)
					echo '<div id="message" class="updated fade"><p><strong>' . $response['errorMessage'] . '</strong></p></div>';
			}
			catch(Duoshuo_Exception $e){
				Duoshuo::showException($e);
			}
		include_once dirname(__FILE__) . '/settings.php';
	}
	
	static function checkAccessToken(){
		$user = wp_get_current_user();
		
		if (isset($_GET['code'])){
			$oauth = new DuoshuoClient(self::$shortName, self::$secret);
			
			$keys = array(
				'code'	=> $_GET['code'],
				'redirect_uri' => 'http://duoshuo.com/login-callback/weibo/',
			);
			
			$token = $oauth->getAccessToken('code', $keys);
			
			if ($token['code'] != 0)
				return false;
			
			self::updateUserMeta($user->ID, 'duoshuo_user_id', $token['user_id']);
			self::updateUserMeta($user->ID, 'duoshuo_access_token', $token['access_token']);
			// TODO 这里缺少expires
			
			return true;
		}
		else{
			$duoshuoUserId = self::getUserMeta($user->ID, 'duoshuo_user_id');
			$accessToken = self::getUserMeta($user->ID, 'duoshuo_access_token');
			if (!$duoshuoUserId || !$accessToken){
				include_once dirname(__FILE__) . '/bind.php';
				return false;
			}
			return true;
		}
	}
	
	static function profile(){
		if (!self::checkAccessToken())
			return ;
		
		include_once dirname(__FILE__) . '/profile.php';
	}
	
	static function deactivate($network_wide = false){
		//	升级插件的时候也会停用插件
		//delete_option('duoshuo_synchronized');
	}
	
	static function uninstall(){
		//delete_option('duoshuo_short_name');
		delete_option('duoshuo_secret');
		delete_option('duoshuo_synchronized');
		
		if (function_exists('delete_metadata')){	//	TODO 需要测试
			delete_metadata('user', 0, 'duoshuo_access_token', '', true);
			delete_metadata('user', 0, 'duoshuo_user_id', '', true);
			delete_metadata('post', 0, 'duoshuo_thread_id', '', true);
			delete_metadata('comment', 0, 'duoshuo_parent_id', '', true);
			delete_metadata('comment', 0, 'duoshuo_post_id', '', true);
		}
		
		$redirect_url = add_query_arg('message', 'uninstalled', admin_url('admin.php?page=duoshuo'));
		wp_redirect($redirect_url);
		exit;
	}
	
	/**
	 * 关闭默认的评论，避免spammer
	 */
	static function commentsOpen($open, $post_id = null) {
	    if (self::$EMBED || get_post_meta($post_id, 'duoshuo_thread_id', true))
	    	return false;
	    return $open;
	}
	
	static function commentsTemplate($value){
	    global $post;
	    global $comments;
		
	    if ( !( is_singular() && ( have_comments() || 'open' == $post->comment_status ) ) ) {
	        return;
	    }
		/*
	    if ( !dsq_is_installed() || !dsq_can_replace() ) {
	        return $value;
	    }*/
	    
	    $threadId = get_post_meta($post->ID, 'duoshuo_thread_id', true);
	    
	    if (empty($threadId)){
	    	self::syncUserToRemote($post->post_author);
	    	self::syncPostToRemote($post->ID, $post);
		    try{
		    	self::syncPostComments($post);
		    }
		    catch(Duoshuo_Exception $e){
				self::showException($e);
			}
	    }
	    
		self::$EMBED = true;
		return dirname(__FILE__) . '/comments.php';
	    //	return $value;
	}
	
	static function commentsText($comment_text, $number = null){
	    global $post;
	    
	    $identifier = 'class="ds-comments-number" data-thread-identifier="' . htmlspecialchars($post->ID . ' ' . $post->guid) .'"';
	    if (preg_match('/^<([a-z]+)( .*)?>(.*)<\/([a-z]+)>$/i', $comment_text, $matches) && $matches[1] == $matches[4]){
	    	return "<$matches[1] $identifier$matches[2]>$matches[3]</$matches[4]>";
	    }
	    else
		    return "<var $identifier>$comment_text</var>";
	}
	
	static function warning(){
		echo '<div class="updated"><p><strong>只要再<a href="' . admin_url('admin.php?page=duoshuo') . '">配置一下</a>多说帐号，多说就能开始为您服务了。</strong></p></div>';
	}
	
	static function requestHandler(){
		if ($_SERVER['REQUEST_METHOD'] == 'POST'
			&& isset($_GET['page'])
			&& in_array($_GET['page'], array('duoshuo-settings', 'duoshuo')))
			switch(true){
				case isset($_POST['duoshuo_uninstall']):
					self::uninstall();
					break;
				case isset($_POST['duoshuo_local_options']):
					update_option('duoshuo_cron_sync_enabled', isset($_POST['duoshuo_cron_sync_enabled']) ? 1 : 0);
					update_option('duoshuo_seo_enabled', isset($_POST['duoshuo_seo_enabled']) ? 1 : 0);
					update_option('duoshuo_social_login_enabled', isset($_POST['duoshuo_social_login_enabled']) ? 1 : 0);
					update_option('duoshuo_comments_wrapper_intro', isset($_POST['duoshuo_comments_wrapper_intro']) ? stripslashes($_POST['duoshuo_comments_wrapper_intro']) : '');
					update_option('duoshuo_comments_wrapper_outro', isset($_POST['duoshuo_comments_wrapper_outro']) ? stripslashes($_POST['duoshuo_comments_wrapper_outro']) : '');
					break;
				default:
			}
		elseif ($_SERVER['REQUEST_METHOD'] == 'GET' && isset($_GET['page']) && $_GET['page'] == 'duoshuo'){
			switch(true){
				case isset($_GET['duoshuo_connect_site']):
					self::connectSite();
					break;
				default:
			}
		}
	}
	
	static function remoteAuth($userId = null){	// null 代表当前登录用户，0代表游客
		if ($userId === null)
			$current_user = wp_get_current_user();
		elseif($userId != 0)
			$current_user = get_user_by( 'id', $userId);
		
	    if (isset($current_user) && $current_user->ID) {
	        $avatar_tag = get_avatar($current_user->ID);
	        $avatar_data = array();
	        preg_match('/(src)=((\'|")[^(\'|")]*(\'|"))/i', $avatar_tag, $avatar_data);
	        $avatar = str_replace(array('"', "'"), '', $avatar_data[2]);
	        
	        $user_data = array(
	            'id' => $current_user->ID,
	            'name' => $current_user->display_name,
	            'avatar' => $avatar,
	            'email' => $current_user->user_email,
	        );
	    }
	    else{
	    	$user_data = array();
	    }
	    $message = base64_encode(json_encode($user_data));
	    $time = time();
	    return $message . ' ' . self::hmacsha1($message . ' ' . $time, self::$secret) . ' ' . $time;
	}
	
	static function buildQuery(){
		return array(
			'short_name'	=>	self::$shortName,
			'sso'	=>	array(
				'login'=>	site_url('wp-login.php', 'login') .'?action=duoshuo_login',
				'logout'=>	htmlspecialchars_decode(wp_logout_url(), ENT_QUOTES),
			),
			'remote_auth'	=>	self::remoteAuth(),
		);
	}
	
	static function appendScripts(){
		static $once = 0;
		if ($once ++)
			return;
?>
<script type="text/javascript">
var duoshuoQuery = <?php echo json_encode(self::buildQuery());?>;
duoshuoQuery.sso.login += '&redirect_to=' + encodeURIComponent(window.location.href);
duoshuoQuery.sso.logout += '&redirect_to=' + encodeURIComponent(window.location.href);
</script>
<?php 
		$duoshuo_shortname = 'static';
		$url = 'http://' . $duoshuo_shortname . '.' . self::DOMAIN . '/embed.js';
		//?pname=wordpress&pver=' . Duoshuo::VERSION
		wp_register_script('duoshuo-embed', $url, array(), null);
		
		wp_enqueue_script('duoshuo-embed');
	}
	
	/**
	 * 在wp_print_scripts 没有执行的时候执行最传统的代码
	 */
	static function printScripts(){
		$duoshuo_shortname = 'static';?>
<script type="text/javascript">
var duoshuoQuery = <?php echo json_encode(self::buildQuery());?>;
duoshuoQuery.sso.login += '&redirect_to=' + encodeURIComponent(window.location.href);
duoshuoQuery.sso.logout += '&redirect_to=' + encodeURIComponent(window.location.href);
(function() {
    var ds = document.createElement('script'); ds.type = 'text/javascript'; ds.async = true;
    ds.charset = 'UTF-8';
    ds.src = 'http://<?php echo $duoshuo_shortname;?>.duoshuo.com/embed.js';
    (document.getElementsByTagName('head')[0] || document.getElementsByTagName('body')[0]).appendChild(ds);
})();
</script><?php
		Duoshuo::$scriptsPrinted = true;
	}
	
	static function outputFooterCommentJs() {
		
if (!did_action('wp_head') && !Duoshuo::$scriptsPrinted){
	Duoshuo::printScripts();
}?>
<script type="text/javascript">
	DUOSHUO.RecentCommentsWidget('.widget_recent_comments #recentcomments', {template : 'wordpress'});
</script>
	<?php
	}
	
	// Register widgets.
	static function registerWidgets(){
		require_once dirname(__FILE__) . '/widgets.php';
		
		register_widget('Duoshuo_Widget_Recent_Comments');
		register_widget('Duoshuo_Widget_Recent_Visitors');
		register_widget('Duoshuo_Widget_Qqt_Follow');
	}
	
	/*
	 * 不再使用identifier的方法
	 * 而使用重定向的方法
	static function login($userLogin){
		$user = get_user_by('login', $userLogin);
		
		$accessToken = self::getUserMeta($user->ID, 'duoshuo_access_token');
		
		if (empty($accessToken))
			return;
		
		$query = array(
			'redirect_uri'=> !empty( $_REQUEST['redirect_to'] ) ? $_REQUEST['redirect_to'] : admin_url(),
			'access_token'=>$accessToken,
			'sso'		=>	1,
		);
		$redirectUrl = 'http://' . self::$shortName . '.duoshuo.com/local-login/?' . http_build_query($query, null, '&');
		
		wp_redirect( $redirectUrl );
		exit;
	}
	
	static function buildSignature($string){
		return base64_encode(hash_hmac('sha1', $string, self::$secret, true));
	}

	static function loginUser($userId, $unique){
		$params = array(
			'short_name'	=>	Duoshuo::$shortName,
			'local_identity'=>	$userId,
			'unique'		=>	$unique,
			'signature'		=>	self::buildSignature($userId)
		);
		
		try{
			$apiResponse = Duoshuo::getClient()->request('POST', 'users/localLogin', $params);
			
			if (isset($apiResponse['response']['user_id']))
				self::updateUserMeta($userId, 'duoshuo_user_id', $apiResponse['response']['user_id']);
		}
		catch(Duoshuo_Exception $e){
			
		}
	}*/
	
	static function loginForm(){
		$redirectUri = add_query_arg(array('action'=>'duoshuo_login', 'redirect_to'=>urlencode(admin_url())), site_url('wp-login.php', 'login'));?>
<div class="ds-login" style="height:40px;"></div>
<script>
if (window.duoshuoQuery && duoshuoQuery.sso)
	duoshuoQuery.sso.login = <?php echo json_encode($redirectUri);?>;
</script>
<?php /*
function updateDuoshuoUnique(unique){
	document.write('<p><label style="font-size:12px;"><input name="login_duoshuo" type="checkbox" value="' + unique + '" tabindex="85" checked="checked" /> 同时登录多说</label></p>');
}
<script src="http://<?php echo self::DOMAIN;?>/identifier.js?callback=updateDuoshuoUnique&<?php echo time();?>"></script>
*/
	}

	static function connectSite(){
		update_option('duoshuo_short_name', $_GET['short_name']);
		update_option('duoshuo_secret', $_GET['secret']);
		self::$shortName = $_GET['short_name'];
		self::$secret = $_GET['secret'];
		
		//需要将当前注册的用户多说帐号和wp帐号关联起来，否则马上导入的时候会出现重复帐号。
		self::checkAccessToken();
		
		$user = wp_get_current_user();
		self::joinSite($user);?>
<script>
window.parent.location = <?php echo json_encode(admin_url('admin.php?page=duoshuo'));?>;
</script>
<?php 
		exit;
	}
	
	static function export(){
		@set_time_limit(0);
		@ini_set('memory_limit', '256M');
		@ini_set('display_errors', 1);
		
		$progress = get_option('duoshuo_synchronized');
		
		if (!$progress || is_numeric($progress))//	之前已经完成了导出流程
			$progress = 'user/0';
		
		list($type, $offset) = explode('/', $progress);
		
		try{
			switch($type){
				case 'user':
					$limit = 30;
					$count = self::exportUsers($limit, $offset);
					break;
				case 'post':
					$limit = 10;
					$count = self::exportPosts($limit, $offset);
					break;
				case 'comment':
					$limit = 50;
					$count = self::exportComments($limit, $offset);
					break;
				default:
			}
			
			if ($count == $limit){
				$progress = $type . '/' . ($offset + $limit);
			}
			elseif($type == 'user')
				$progress = 'post/0';
			elseif($type == 'post')
				$progress = 'comment/0';
			elseif($type == 'comment')
				$progress = time();
			
			update_option('duoshuo_synchronized', $progress);
	        $response = array(
				'progress'=>$progress,
	        	'code'	=>	0
			);
			self::sendJsonResponse($response);
		}
		catch(Duoshuo_Exception $e){
			self::sendException($e);
		}
	}
	
	static function exportUsers($limit, $offset = 0){
		global $wpdb;
		
		// 不包括user_login, user_pass
		$columns = array('ID', 'user_nicename', 'user_email', 'user_url', 'user_registered', 'display_name');
		$users = $wpdb->get_results( $wpdb->prepare("SELECT " . implode(',', $columns) . "  FROM $wpdb->users order by ID asc limit $offset,$limit"));
		
		if (count($users) === 0)
			return 0;
		
		$params = array('users'=>array());
		$blog_prefix = self::get_blog_prefix();
	    foreach($users as $user){
		    $user->capabilities = self::getUserMeta($user->ID, $blog_prefix.'capabilities');
		    $params['users'][] = get_object_vars($user);
	    }
		
		$remoteResponse = self::getClient()->request('POST', 'import/wordpressUsers', $params);
	
		foreach($remoteResponse['response'] as $userId => $duoshuoUserId)
			self::updateUserMeta($userId, 'duoshuo_user_id', $duoshuoUserId);
		
		return count($users);
	}
	
	static function packageUser($user){
		global $wpdb;
		
		if ($user instanceof WP_User){	//	wordpress 3.3
			$userData = $user->data;
			unset($userData->user_pass);
			unset($userData->user_login);
			$capabilities = $user->caps;
		}
		else{
			$userData = $user;
			unset($userData->user_pass);
			unset($userData->user_login);
			$capabilities = self::getUserMeta($user->ID, self::get_blog_prefix().'capabilities', true);
		}
		
		$data = array(
			'source_user_id'=>	$userData->ID,
			'name'			=>	$userData->display_name,
			'email'			=>	$userData->user_email,
			'url'			=>	$userData->user_url,
			'created_at'	=>	$userData->user_registered,
		);
		
		$roleMap = array(
			'administrator'	=>	'administrator',
			'editor'		=>	'editor',
			'author'		=>	'author',
			'contributor'	=>	'user',
			'subscriber'	=>	'user',
		);
		
		foreach($roleMap as $wpRole => $role)
			if (isset($capabilities[$wpRole]) && $capabilities[$wpRole]){
				$data['role'] = $role;
				break;
			}
		
		return $data;
	}
	
	static $optionsMap = array(
		'home'		=>	'url',
		'siteurl'	=>	'siteurl',
		'admin_email'=>	'admin_email',
		'timezone_string'=>'timezone',
		'use_smilies'=>	'use_smilies',
		'current_theme'=>'system_theme',
	);
	
	static function packageOptions(){
		$options = array(
			'name'	=>	html_entity_decode(get_option('blogname'), ENT_QUOTES, 'UTF-8'),
			'description'=>html_entity_decode(get_option('blogdescription'), ENT_QUOTES, 'UTF-8'),
		);
		foreach(self::$optionsMap as $key => $value)
			$options[$value] = get_option($key);
		
		if ($akismet_api_key = get_option('wordpress_api_key'))
			$options['akismet_api_key'] = $akismet_api_key;
		$options['plugin_dir_url'] = self::$pluginDirUrl;
		
		return $options;
	}
	
	static function updateSite(){
		$params = self::packageOptions();
		$user = wp_get_current_user();
		
		try{
			$response = Duoshuo::getClient($user->ID)->request('POST', 'sites/settings', $params);
			
			if ($response['code'] != 0)
				echo '<div id="message" class="updated fade"><p><strong>' . $response['errorMessage'] . '</strong></p></div>';
		}
		catch(Duoshuo_Exception $e){
			Duoshuo::showException($e);
		}
	}
	
	static function updatedOption($option, $oldvalue = null, $newvalue = null){
		if (isset(self::$optionsMap[$option]))
			self::updateSite();
		
		//'system_theme'=>get_current_theme(),
		//'plugin_dir_url'=>self::$pluginDirUrl,
	}
	
	/**
	 * 
	 */
	static function joinSite($user){
		//global $wpdb;
		
		$params = array(
			'user'		=>	self::packageUser($user),
			//'unique'	=>	$unique,
			//'short_name'=>	self::$shortName
		);
		try{
			$remoteResponse = self::getClient($user->ID)->request('POST', 'sites/join', $params);
			// 在joinSite之前就已经记录了duoshuo_user_id
			//if (isset($remoteResponse['response']))
			//	self::updateUserMeta($data['source_user_id'], 'duoshuo_user_id', $remoteResponse['response']['user_id']);
		}
		catch(Duoshuo_Exception $e){
			self::showException($e);
		}
	}
	
	static function syncUserToRemote($userId){
		global $wpdb;
		$user = get_userdata($userId);
		
		if ($user instanceof WP_User){	//	wordpress 3.3
			$userData = $user->data;
			unset($userData->user_pass);
			unset($userData->user_login);
			$userData->capabilities = $user->caps;
		}
		else{
			$userData = $user;
			unset($userData->user_pass);
			unset($userData->user_login);
			$blog_prefix = self::get_blog_prefix();
			$userData->capabilities = self::getUserMeta($user->ID, $blog_prefix.'capabilities', true);
		}
		
		$params = array('users'=>array(get_object_vars($userData)), 'short_name'=>self::$shortName);
		try{
			$remoteResponse = self::getClient()->request('POST','import/wordpressUsers', $params);
		
			if (isset($remoteResponse['response'])){
				foreach($remoteResponse['response'] as $userId => $duoshuoUserId)
					self::updateUserMeta($userId, 'duoshuo_user_id', $duoshuoUserId);
			}
		}
		catch(Duoshuo_Exception $e){
			self::showException($e);
		}
	}
	
	static function exportPosts($limit, $offset = 0){
		global $wpdb;
		
		$columns = array('ID', 'post_author', 'post_date', 'post_date_gmt', 'post_content', 'post_title', 'post_excerpt', 'post_status', 'comment_status', 'ping_status', 'post_name', 'post_modified_gmt', 'guid', 'post_type', 'post_parent');
		$posts = $wpdb->get_results( $wpdb->prepare("SELECT " . implode(',', $columns) . "  FROM $wpdb->posts where post_type not in ('nav_menu_item', 'revision') and post_status not in ('auto-draft', 'draft', 'trash') order by ID asc limit $offset,$limit") );// 'inherit' 也进行同步
		
		if (count($posts) === 0)
			return 0;
		
		$params = array(
			'threads'	=>	array(),
		);
		foreach($posts as $index => $post){
			$params['threads'][] = Duoshuo::packagePost($post);
		}
		
		$remoteResponse = self::getClient()->request('POST','import/wordpressPosts', $params);
		
		foreach($remoteResponse['response'] as $postId => $threadId)
			update_post_meta($postId, 'duoshuo_thread_id', $threadId);
		
		return count($posts);
	}
	
	/**
	 * 同步这篇文章到所有社交网站
	 * @param string $postId
	 */
	static function syncPostToRemote($postId, $post = null){
		if ($post == null)
			$post = get_post($postId);
		
		if (in_array($post->post_type, array('nav_menu_item', 'revision'))
			|| in_array($post->post_status, array('auto-draft', 'draft', 'trash')))	//'inherit' 也可以进行同步
			return ;
		
		$params = self::packagePost($post);
		
		if (isset($_POST['sync_to'])){
			if ($_POST['sync_to'][0] == 'placeholder')
				unset($_POST['sync_to'][0]);
			$params['sync_to'] = implode(',', $_POST['sync_to']);
		}
		
		try{
			$response = self::getClient($post->post_author)->request('POST', 'threads/sync', $params);
			
			if ($response['code'] == 0 && isset($response['response']))
				update_post_meta($post->ID, 'duoshuo_thread_id', $response['response']['thread_id']);
		}
		catch(Duoshuo_Exception $e){
			self::showException($e);
		}
	}
	
	static function packagePost($post){
		$post->custom = get_post_custom($post->ID);
		$meta = clone ($post);
		unset($meta->post_title);
		unset($meta->post_content);
		unset($meta->post_excerpt);
		unset($meta->post_date_gmt);
		unset($meta->post_modified_gmt);
		unset($meta->post_name);
		unset($meta->post_status);
		unset($meta->comment_status);
		unset($meta->ping_status);
		unset($meta->guid);
		unset($meta->post_type);
		unset($meta->post_author);
		unset($meta->ID);
		
		$params = array(
			'title'		=>	html_entity_decode($post->post_title, ENT_QUOTES, 'UTF-8'),
			'content'	=>	$post->post_content,
			'excerpt'	=>	$post->post_excerpt,
			'created_at'=>	mysql2date('Y-m-d\TH:i:sP', $post->post_date_gmt),
			'updated_at'=>	mysql2date('Y-m-d\TH:i:sP', $post->post_modified_gmt),
			'ip'		=>	$_SERVER['REMOTE_ADDR'],
			'url'		=>	get_permalink($post),
			'slug'		=>	$post->post_name,
			'status'	=>	$post->post_status,
			'comment_status'=>	$post->comment_status,
			'ping_status'=>	$post->ping_status,
			'guid'		=>	$post->guid,
			'type'		=>	$post->post_type,
			'meta'		=>	json_encode($meta),
			'source'	=>	'wordpress',
			'source_author_id'=>$post->post_author,
			'source_thread_id'=>$post->ID,
		);
		
		if (!class_exists('nggLoader') || class_exists('nggRewrite'))
			$params['filtered_content'] = str_replace(']]>', ']]&gt;', apply_filters('the_content', $post->post_content));
		
		if (function_exists('get_post_thumbnail_id')){	//	WordPress 2.9开始支持
			$post_thumbnail_id = get_post_thumbnail_id( $post->ID );
			if ( $post_thumbnail_id ) {
				$params['thumbnail'] = wp_get_attachment_url($post_thumbnail_id);
				//$image = wp_get_attachment_image_src( $post_thumbnail_id, $size, false);
				//list($src, $width, $height) = $image;
				//$meta = wp_get_attachment_metadata($id);
				//'large-feature'
				//'post-thumbnail'
			}
		}
		
		$args = array(
			'post_parent' => $post->ID,
			'post_status' => 'inherit',
			'post_type'	=> 'attachment',
			'post_mime_type' => 'image',
			'order' => 'ASC',
			'orderby' => 'menu_order ID'
		);
		$images = array();
		$children = get_children($args);
		if (is_array($children))
			foreach($children as $attachment)
				$images[] = wp_get_attachment_url($attachment->ID);
		if (!empty($images))
			$params['images'] = json_encode($images);
		
		$authorId = self::getUserMeta($post->post_author, 'duoshuo_user_id', true);
		if (!empty($authorId))
			$params['author_id'] = $authorId;
		
		$threadId = get_post_meta($post->ID, 'duoshuo_thread_id', true);
		if (!empty($threadId))
			$params['thread_id'] = $threadId;
		return $params;
	}
	
	static function exportComments($limit, $offset = 0){
		global $wpdb;
		
		$comments = $wpdb->get_results( $wpdb->prepare("SELECT * FROM $wpdb->comments where comment_agent NOT LIKE 'Duoshuo/%%' order by comment_ID asc limit $offset,$limit"), ARRAY_A);
		
		if (count($comments) === 0)
			return 0;
		 
		$remoteResponse = self::getClient()->request('POST', 'import/wordpressComments', array('posts'=>$comments));
		
		return count($comments);
	}
	
	static function exportOneComment($comment_ID){
		$comment = get_object_vars(get_comment($comment_ID));
		
		$remoteResponse = self::getClient()->request('POST', 'import/wordpressComments', array('posts'=>array($comment)));
		
		return $remoteResponse;
	}
	
	static function syncPostComments($post){
		global $wpdb;
		
		$comments = $wpdb->get_results( $wpdb->prepare("SELECT * FROM $wpdb->comments where comment_post_ID = %d AND comment_agent NOT LIKE 'Duoshuo/%%' order by comment_ID asc", $post->ID), ARRAY_A);
	    
		if (count($comments) === 0)
			return 0;
		
		$params = array('posts'		=> $comments);
		
		$remoteResponse = self::getClient($post->post_author)->request('POST','import/wordpressComments', $params);
		
		return $remoteResponse;
	}
	
	static function syncCommentsToLocal(){
		update_option('_duoshuo_sync_lock', time());
		
		$last_post_id = get_option('duoshuo_last_post_id');
		
		$params = array(
			'start_id' => $last_post_id,
            'limit' => 20,
            'order' => 'asc',
			'sources'=>'duoshuo,anonymous'
		);
		
		$client = self::getClient();
		
		$response = $client->request('GET', 'sites/listPosts', $params);
		
		$imported = self::_syncCommentsToLocal($response['response']);
		$client->request('POST', 'posts/imported', $imported);
	
		$max_post_id = 0;
		foreach($response['response'] as $post)
			if ($post['post_id'] > $max_post_id)
				$max_post_id = $post['post_id'];
		
		if ($max_post_id > $last_post_id)
			update_option('duoshuo_last_post_id', $max_post_id);
		
		delete_option('_duoshuo_sync_lock');
		
		return $imported;
	}
	
	/**
	 * 从服务器pull评论到本地
	 * 
	 * @param array $posts
	 */
	static function _syncCommentsToLocal($posts){
		global $wpdb;
		
		$approvedMap = array(
			'pending'	=>	'0',
			'approved'	=>	'1',
			'deleted'	=>	'trash',
			'spam'		=>	'spam',
			'thread-deleted'=>'post-trashed',
		);
		
		$threadMap = array();
		$commentMap = array();
		$userMap = array();
		foreach($posts as $post){
			$threadMap[$post['thread_id']] = null;
			$commentMap[$post['parent_id']] = null;
			$userMap[$post['author_id']] = null;
			
		}
		unset($commentMap[0]);
		
		$thread_ids = "'" . implode("', '", array_keys($threadMap)) . "'";
		$results = $wpdb->get_results( "SELECT post_id, meta_value FROM $wpdb->postmeta WHERE meta_key = 'duoshuo_thread_id' AND meta_value IN ($thread_ids)");
	    foreach ($results as $result)
	        $threadMap[$result->meta_value] = $result->post_id;
		
	    $comment_ids = "'" . implode("', '", array_keys($commentMap)) . "'";
	    $results = $wpdb->get_results( "SELECT comment_id, meta_value FROM $wpdb->commentmeta WHERE meta_key = 'duoshuo_post_id' AND meta_value IN ($comment_ids)");
		foreach ($results as $result)
	        $commentMap[$result->meta_value] = $result->comment_id;
		
		$user_ids = "'" . implode("', '", array_keys($userMap)) . "'";
	    $results = $wpdb->get_results( "SELECT user_id, meta_value FROM $wpdb->usermeta WHERE meta_key = 'duoshuo_user_id' AND meta_value IN ($user_ids)");
		foreach ($results as $result)
	        $userMap[$result->meta_value] = $result->user_id;
	    
	    $imported = array();
	    foreach($posts as $post){
			
			if (!$threadMap[$post['thread_id']]){
				//skip
				continue;
			}
			
			$data = array(
				'comment_author'	=>	trim(strip_tags($post['author_name'])),
		 		'comment_author_email'=>$post['author_email'],
		 		'comment_author_url'=>	$post['author_url'], 
		 		'comment_author_IP'	=>	$post['ip'],
				'comment_date'		=>	rfc3339_to_mysql($post['created_at']), 
		 		'comment_date_gmt'	=>	rfc3339_to_mysql_gmt($post['created_at']),
				'comment_content'	=>	$post['message'], 
		 		'comment_approved'	=>	$approvedMap[$post['status']],
				'comment_agent'		=>	'Duoshuo/' . Duoshuo::VERSION . ':' . $post['post_id'],
				'comment_type'		=>	$post['type'],
				'comment_post_ID'	=>	$threadMap[$post['thread_id']],
				//'comment_karma'
			);
			
			if ($post['parent_id'] && $commentMap[$post['parent_id']])
				$data['comment_parent'] = $commentMap[$post['parent_id']];
			
			if ($post['author_id'] && isset($userMap[$post['author_id']]))
				$data['user_id'] = $userMap[$post['author_id']];
			
			if (isset($post['source_post_id']) && $post['source_post_id']){
				$data['comment_ID'] = $post['source_post_id'];
				wp_update_comment($data);
			}
			else{
				$data['comment_ID'] = wp_insert_comment($data);
			}
			$imported['post_' . $post['post_id']] = $data['comment_ID'];
			
			update_comment_meta($data['comment_ID'], 'duoshuo_parent_id', $post['parent_id']);
        	update_comment_meta($data['comment_ID'], 'duoshuo_post_id', $post['post_id']);
		}
		
		return $imported;
	}
	
	static function notices(){
		foreach(self::$errorMessages as $message)
			echo '<div class="updated"><p><strong>'.$message.'</strong></p></div>';
		
		$duoshuo_notice = get_option('duoshuo_notice');
		if (!empty($duoshuo_notice)){//系统推送的通知
			echo '<div class="updated">'.$duoshuo_notice.'</div>';
		}
		elseif ($duoshuo_notice === false){
			update_option('duoshuo_notice', '');
		}
		
		$messages = array(
			'registered'	=>'<strong>注册成功，请同步数据</strong>',
			'uninstalled'	=>'<strong>已卸载</strong>',
		);
		if (isset($_GET['message']) && isset($messages[$_GET['message']]))
			echo '<div class="updated"><p>'.$messages[$_GET['message']].'</p></div>';
	}
	
	static function sendJsonResponse($response){
		if (!headers_sent()) {
			nocache_headers();
			header('Content-type: application/json; charset=UTF-8');
		}
		
        echo json_encode($response);
        exit;
	}
	
	//发布文章时候的同步设置
	static function syncOptions(){
		global $post;
		
		switch($post->post_status){
			case 'auto-draft':
			case 'inherit':
			case 'draft':
			case 'trash':
				break;
			case 'publish':
				break;
			default:
		}
		$user = wp_get_current_user();
		$params = array(
			'template'	=>	'wordpress',
		);
		
		$threadId = get_post_meta($post->ID, 'duoshuo_thread_id', true);
		if ($threadId)
			$params['thread_id'] = $threadId;
		
		try{
			echo self::getHtml('partials/sync-options', $params);
		}
		catch(Duoshuo_Exception $e){
			Duoshuo::showException($e);
		}
		echo '<p><a href="' . admin_url('admin.php?page=duoshuo-profile') . '">绑定更多社交网站</a></p>';
	}
	
	static function managePostComments($post){
		//这里应该嵌入一个iframe框
	}
	
	static function syncCron(){
		try{
			self::syncCommentsToLocal();
		}
		catch(Duoshuo_Exception $e){
		}
	}
	
	// from: http://www.php.net/manual/en/function.sha1.php#39492
	// Calculate HMAC-SHA1 according to RFC2104
	// http://www.ietf.org/rfc/rfc2104.txt
	static function hmacsha1($data, $key) {
		if (function_exists('hash_hmac'))
			return hash_hmac('sha1', $data, $key);
		
	    $blocksize=64;
	    $hashfunc='sha1';
	    if (strlen($key)>$blocksize)
	        $key=pack('H*', $hashfunc($key));
	    $key=str_pad($key,$blocksize,chr(0x00));
	    $ipad=str_repeat(chr(0x36),$blocksize);
	    $opad=str_repeat(chr(0x5c),$blocksize);
	    $hmac = pack(
	                'H*',$hashfunc(
	                    ($key^$opad).pack(
	                        'H*',$hashfunc(
	                            ($key^$ipad).$data
	                        )
	                    )
	                )
	            );
	    return bin2hex($hmac);
	}
	
	static function updateUserMeta($userId, $metaKey, $metaValue){
		if (function_exists('update_user_meta'))
			update_user_meta($userId, $metaKey, $metaValue);
		else
			update_usermeta($userId, $metaKey, $metaValue);
	}
	
	static function get_blog_prefix(){
		global $wpdb;
		if (method_exists($wpdb,'get_blog_prefix'))
			return $wpdb->get_blog_prefix();
		else
			return $wpdb->prefix;
	}
	
	static function getUserMeta($userId, $metaKey, $single = false){
		//get_user_meta 从3.0开始有效: get_usermeta($user->ID, $blog_prefix.'capabilities', true);
		if (function_exists('get_user_meta'))
			return get_user_meta($userId, $metaKey, true);
		else
			return get_usermeta($userId, $metaKey);
	}
	
	static function actionsFilter($actions){
		/**
		 * TODO 
		$actions['ds-comments'] = '<a href="javascript:void(0);">管理评论</a>';
		 */
		return $actions;
	}
	
	static function pluginActionLinks($links, $file) {
		if (empty(self::$shortName) || empty(self::$secret) || !is_numeric(get_option('duoshuo_synchronized')))
	    	array_unshift($links, '<a href="' . admin_url('admin.php?page=duoshuo') . '">'.__('Install').'</a>');
		else
			array_unshift($links, '<a href="' . admin_url('admin.php?page=duoshuo-settings') . '">'.__('Settings').'</a>');
	    return $links;
	}
	
	static function dashboardWidget(){
		if ( !$widget_options = get_option( 'dashboard_widget_options' ) )
			$widget_options = array();
	
		if ( !isset($widget_options['dashboard_recent_comments']) )
			$widget_options['dashboard_recent_comments'] = array();
	
		if ( 'POST' == $_SERVER['REQUEST_METHOD'] && isset($_POST['widget-recent-comments']) ) {
			$number = absint( $_POST['widget-recent-comments']['items'] );
			$widget_options['dashboard_recent_comments']['items'] = $number;
			update_option( 'dashboard_widget_options', $widget_options );
		}
	
		$number = isset( $widget_options['dashboard_recent_comments']['items'] ) ? (int) $widget_options['dashboard_recent_comments']['items'] : '';
	
		echo '<p><label for="comments-number">' . __('Number of comments to show:') . '</label>';
		echo '<input id="comments-number" name="widget-recent-comments[items]" type="text" value="' . $number . '" size="3" /></p>';
	}
}

Duoshuo::setVariables();

if(is_admin()){//在admin界面内执行的action
	register_deactivation_hook(__FILE__, array('Duoshuo', 'deactivate'));
	add_action('admin_menu', array('Duoshuo','addPages'), 10);
	add_action('admin_init', array('Duoshuo','requestHandler'));
	add_action('admin_init', array('Duoshuo','registerSettings'));
	add_action('admin_init', array('Duoshuo','adminInitialize'));
}
else{
	add_action('init', array('Duoshuo','initialize'));
	add_action('login_form_duoshuo_login', array('Duoshuo','oauthConnect'));
	add_action('login_form_duoshuo_logout', array('Duoshuo','oauthDisconnect'));
}

add_action('widgets_init', array('Duoshuo','registerWidgets'));

add_action('save_post', array('Duoshuo', 'syncPostToRemote'));

/*
if (function_exists('get_post_types')){	//	cron jobs runs in common mode, sometimes
	foreach(get_post_types() as $type)
		if ($type !== 'nav_menu_item' && $type !== 'revision')
			add_action('publish_' . $type, array('Duoshuo','syncPostToRemote'));
}
else{
	add_action('publish_post', array('Duoshuo','syncPostToRemote'));
	add_action('publish_page', array('Duoshuo','syncPostToRemote'));
}
// 感谢“我爱水煮鱼”的建议
get_post_types(array('public'   => true, '_builtin' => false), 'names', 'and');
*/