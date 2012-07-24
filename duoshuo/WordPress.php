<?php
class Duoshuo_WordPress extends Duoshuo_Abstract{
	
	const VERSION = '0.8';
	
	protected static $_instance = null;
	
	/**
	 * 
	 * @var string
	 */
	public $pluginDirUrl;
	
	/**
	 * 
	 * @var array
	 */
	protected $errorMessages = array();
	
	protected $EMBED = false;
	
	protected $scriptsPrinted = false;
	
	public $threadInitialized = false;
	
	public $shortName;
	
	public $secret;
	
	protected function __construct(){
		$this->shortName = $this->getOption('short_name');
		$this->secret = $this->getOption('secret');
		
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
		
		$this->pluginDirUrl = plugin_dir_url(__FILE__);
	}
	
	/**
	 * 
	 * @return Duoshuo_WordPress
	 */
	public static function getInstance(){
		if (self::$_instance === null)
			self::$_instance = new self();
		return self::$_instance;
	}
	
	public function timezone(){
		return get_option('gmt_offset');
	}
	
	public function getOption($key){
		return get_option('duoshuo_' . $key);
	}
	
	public function updateOption($key, $value){
		return update_option('duoshuo_' . $key, $value);
	}
	
	public function updateUserMeta($userId, $metaKey, $metaValue){
		if (function_exists('update_user_meta'))
			update_user_meta($userId, $metaKey, $metaValue);
		else
			update_usermeta($userId, $metaKey, $metaValue);
	}
	
	public function getUserMeta($userId, $metaKey, $single = false){
		//get_user_meta 从3.0开始有效: get_usermeta($user->ID, $blog_prefix.'capabilities', true);
		if (function_exists('get_user_meta'))
			return get_user_meta($userId, $metaKey, true);
		else
			return get_usermeta($userId, $metaKey);
	}
	
	public function get_blog_prefix(){
		global $wpdb;
		if (method_exists($wpdb,'get_blog_prefix'))
			return $wpdb->get_blog_prefix();
		else
			return $wpdb->prefix;
	}
	
	public function userBind($token){
		global $wpdb;
		
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
			
			$this->updateUserMeta($user_id, 'duoshuo_access_token', $token['access_token']);
			
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
	
	public function userLogin($token){
		global $wpdb, $error;
		
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
			
			$this->updateUserMeta($user_id, 'duoshuo_access_token', $token['access_token']);
			
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
	
	/*
	static function logout(){
		$query = array(
			'redirect_uri'=> !empty( $_REQUEST['redirect_to'] ) ? $_REQUEST['redirect_to'] : site_url('wp-login.php') . '?loggedout=true',
			'sso'		=>	1,
		);
		$logoutUrl = 'http://' . $this->shortName . '.duoshuo.com/logout/?' . http_build_query($query, null, '&');
		
		wp_redirect( $logoutUrl );
		exit;
	}
	*/
	
	public function originalCommentsNotice(){
		echo '<div class="updated">'
			. '<p>多说正在努力地为您的网站提供强大的社会化评论服务，WordPress原生评论数据现在仅用于备份；</p>'
			. '<p>多说会将每一条评论实时写回本地数据库，但如果日后您在多说删除/修改了评论，并不会影响本地数据；</p>'
			. '<p>您在本页做的任何管理评论操作，都不会对多说评论框上的评论起作用，请访问<a href="http://' . $this->shortName . '.' . self::DOMAIN . '/admin/" target="_blank">评论管理后台</a>进行评论管理。</p>'
			. '</div>';
	}
	
	/**
	 * 
	 * @return Duoshuo_Client
	 */
	public function getClient($userId = 0){	//如果不输入参数，就是游客
		$remoteAuth = $this->remoteAuth($this->userData($userId));
		
		if ($userId !== null){
			$accessToken = $this->getUserMeta($userId, 'duoshuo_access_token');
			
			if (is_string($accessToken))
				return new Duoshuo_Client($this->shortName, $this->secret, $remoteAuth, $accessToken);
		}
		return new Duoshuo_Client($this->shortName, $this->secret, $remoteAuth);
	}
	
	/**
	 * 
	 * @param $action
	 * @param $params
	 * @throws Duoshuo_Exception
	 * @return string
	 */
	public function getHtml($action, $params){
		$params['remote_auth'] = $this->remoteAuth($this->userData());
		$url = 'http://' . $this->shortName . '.duoshuo.com/' . $action . '/?' . http_build_query($params, null, '&');
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
	
	public function config(){
		/*if ($_SERVER['REQUEST_METHOD'] == 'POST' && !($this->shortName && $this->secret)){
			self::registerSite();
		}*/
		include_once dirname(__FILE__) . '/config.php';
	}
	
	public function manage(){
		include_once dirname(__FILE__) . '/manage.php';
	}
	
	public function settings(){
		if ($_SERVER['REQUEST_METHOD'] == 'POST')
			try{
				$user = wp_get_current_user();
				$params = $_POST;
				
				$response = $this->getClient($user->ID)->request('POST', 'sites/settings', $params);
				
				if ($response['code'] != 0)
					echo '<div id="message" class="updated fade"><p><strong>' . $response['errorMessage'] . '</strong></p></div>';
			}
			catch(Duoshuo_Exception $e){
				$this->showException($e);
			}
		include_once dirname(__FILE__) . '/settings.php';
	}
	
	public function checkAccessToken(){
		$user = wp_get_current_user();
		
		if (isset($_GET['code'])){
			$oauth = new Duoshuo_Client($this->shortName, $this->secret);
			
			$keys = array(
				'code'	=> $_GET['code'],
				'redirect_uri' => 'http://duoshuo.com/login-callback/weibo/',
			);
			
			$token = $oauth->getAccessToken('code', $keys);
			
			if ($token['code'] != 0)
				return false;
			
			$this->updateUserMeta($user->ID, 'duoshuo_user_id', $token['user_id']);
			$this->updateUserMeta($user->ID, 'duoshuo_access_token', $token['access_token']);
			// TODO 这里缺少expires
			
			return true;
		}
		else{
			$duoshuoUserId = $this->getUserMeta($user->ID, 'duoshuo_user_id');
			$accessToken = $this->getUserMeta($user->ID, 'duoshuo_access_token');
			if (!$duoshuoUserId || !$accessToken){
				include_once dirname(__FILE__) . '/bind.php';
				return false;
			}
			return true;
		}
	}
	
	public function profile(){
		if (!$this->checkAccessToken())
			return ;
		
		include_once dirname(__FILE__) . '/profile.php';
	}
	
	public function uninstall(){
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
	public function commentsOpen($open, $post_id = null) {
	    if ($this->EMBED || get_post_meta($post_id, 'duoshuo_thread_id', true))
	    	return false;
	    return $open;
	}
	
	public function commentsTemplate($value){
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
	    	$this->syncUserToRemote($post->post_author);
	    	$this->syncPostToRemote($post->ID, $post);
		    try{
		    	$this->syncPostComments($post);
		    }
		    catch(Duoshuo_Exception $e){
				$this->showException($e);
			}
	    }
	    
		$this->EMBED = true;
		return dirname(__FILE__) . '/comments.php';
	    //	return $value;
	}
	
	public function commentsText($comment_text, $number = null){
	    global $post;
	    
	    $identifier = 'class="ds-comments-number" data-thread-identifier="' . htmlspecialchars($post->ID . ' ' . $post->guid) .'"';
	    if (preg_match('/^<([a-z]+)( .*)?>(.*)<\/([a-z]+)>$/i', $comment_text, $matches) && $matches[1] == $matches[4]){
	    	return "<$matches[1] $identifier$matches[2]>$matches[3]</$matches[4]>";
	    }
	    else
		    return "<var $identifier>$comment_text</var>";
	}
	
	public function userData($userId = null){	// null 代表当前登录用户，0代表游客
		if ($userId === null)
			$current_user = wp_get_current_user();
		elseif($userId != 0)
			$current_user = get_user_by( 'id', $userId);
		
	    if (isset($current_user) && $current_user->ID) {
	        $avatar_tag = get_avatar($current_user->ID);
	        $avatar_data = array();
	        preg_match('/(src)=((\'|")[^(\'|")]*(\'|"))/i', $avatar_tag, $avatar_data);
	        $avatar = str_replace(array('"', "'"), '', $avatar_data[2]);
	        
	        return array(
	            'id' => $current_user->ID,
	            'name' => $current_user->display_name,
	            'avatar' => $avatar,
	            'email' => $current_user->user_email,
	        );
	    }
	    else{
	    	return array();
	    }
	}
	
	public function buildQuery(){
		return array(
			'short_name'	=>	$this->shortName,
			'sso'	=>	array(
				'login'=>	site_url('wp-login.php', 'login') .'?action=duoshuo_login',
				'logout'=>	htmlspecialchars_decode(wp_logout_url(), ENT_QUOTES),
			),
			'remote_auth'	=>	$this->remoteAuth($this->userData()),
		);
	}
	
	public function appendScripts(){
		static $once = 0;
		if ($once ++)
			return;
?>
<script type="text/javascript">
var duoshuoQuery = <?php echo json_encode($this->buildQuery());?>;
duoshuoQuery.sso.login += '&redirect_to=' + encodeURIComponent(window.location.href);
duoshuoQuery.sso.logout += '&redirect_to=' + encodeURIComponent(window.location.href);
</script>
<?php 
		$duoshuo_shortname = 'static';
		$url = 'http://' . $duoshuo_shortname . '.' . self::DOMAIN . '/embed.js';
		//?pname=wordpress&pver=' . self::VERSION
		wp_register_script('duoshuo-embed', $url, array(), null);
		
		wp_enqueue_script('duoshuo-embed');
	}
	
	/**
	 * 在wp_print_scripts 没有执行的时候执行最传统的代码
	 */
	public function printScripts(){
		$duoshuo_shortname = 'static';?>
<script type="text/javascript">
var duoshuoQuery = <?php echo json_encode($this->buildQuery());?>;
duoshuoQuery.sso.login += '&redirect_to=' + encodeURIComponent(window.location.href);
duoshuoQuery.sso.logout += '&redirect_to=' + encodeURIComponent(window.location.href);
(function() {
    var ds = document.createElement('script'); ds.type = 'text/javascript'; ds.async = true;
    ds.charset = 'UTF-8';
    ds.src = 'http://<?php echo $duoshuo_shortname;?>.duoshuo.com/embed.js';
    (document.getElementsByTagName('head')[0] || document.getElementsByTagName('body')[0]).appendChild(ds);
})();
</script><?php
		$this->scriptsPrinted = true;
	}
	
	public function outputFooterCommentJs() {
		
if (!did_action('wp_head') && !$this->scriptsPrinted){
	$this->printScripts();
}?>
<script type="text/javascript">
	DUOSHUO.RecentCommentsWidget('.widget_recent_comments #recentcomments', {template : 'wordpress'});
</script>
	<?php
	}
	
	/*
	 * 不再使用identifier的方法
	 * 而使用重定向的方法
	static function login($userLogin){
		$user = get_user_by('login', $userLogin);
		
		$accessToken = $this->getUserMeta($user->ID, 'duoshuo_access_token');
		
		if (empty($accessToken))
			return;
		
		$query = array(
			'redirect_uri'=> !empty( $_REQUEST['redirect_to'] ) ? $_REQUEST['redirect_to'] : admin_url(),
			'access_token'=>$accessToken,
			'sso'		=>	1,
		);
		$redirectUrl = 'http://' . $this->shortName . '.duoshuo.com/local-login/?' . http_build_query($query, null, '&');
		
		wp_redirect( $redirectUrl );
		exit;
	}
	
	static function buildSignature($string){
		return base64_encode(hash_hmac('sha1', $string, $this->secret, true));
	}

	static function loginUser($userId, $unique){
		$params = array(
			'short_name'	=>	$this->shortName,
			'local_identity'=>	$userId,
			'unique'		=>	$unique,
			'signature'		=>	self::buildSignature($userId)
		);
		
		try{
			$apiResponse = $this->getClient()->request('POST', 'users/localLogin', $params);
			
			if (isset($apiResponse['response']['user_id']))
				$this->updateUserMeta($userId, 'duoshuo_user_id', $apiResponse['response']['user_id']);
		}
		catch(Duoshuo_Exception $e){
			
		}
	}*/
	
	public function loginForm(){
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

	public function connectSite(){
		update_option('duoshuo_short_name', $_GET['short_name']);
		update_option('duoshuo_secret', $_GET['secret']);
		$this->shortName = $_GET['short_name'];
		$this->secret = $_GET['secret'];
		
		//需要将当前注册的用户多说帐号和wp帐号关联起来，否则马上导入的时候会出现重复帐号。
		$this->checkAccessToken();
		
		$user = wp_get_current_user();
		$this->joinSite($user);?>
<script>
window.parent.location = <?php echo json_encode(admin_url('admin.php?page=duoshuo'));?>;
</script>
<?php 
		exit;
	}
	
	public function export(){
		@set_time_limit(0);
		@ini_set('memory_limit', '256M');
		@ini_set('display_errors', 1);
		
		$progress = $this->getOption('synchronized');
		
		if (!$progress || is_numeric($progress))//	之前已经完成了导出流程
			$progress = 'user/0';
		
		list($type, $offset) = explode('/', $progress);
		
		try{
			switch($type){
				case 'user':
					$limit = 30;
					$count = $this->exportUsers($limit, $offset);
					break;
				case 'post':
					$limit = 10;
					$count = $this->exportPosts($limit, $offset);
					break;
				case 'comment':
					$limit = 50;
					$count = $this->exportComments($limit, $offset);
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
			$this->sendJsonResponse($response);
		}
		catch(Duoshuo_Exception $e){
			$this->sendException($e);
		}
	}
	
	public function exportUsers($limit, $offset = 0){
		global $wpdb;
		
		// 不包括user_login, user_pass
		$columns = array('ID', 'user_nicename', 'user_email', 'user_url', 'user_registered', 'display_name');
		$users = $wpdb->get_results( $wpdb->prepare("SELECT " . implode(',', $columns) . "  FROM $wpdb->users order by ID asc limit $offset,$limit"));
		
		if (count($users) === 0)
			return 0;
		
		$params = array('users'=>array());
		$blog_prefix = $this->get_blog_prefix();
	    foreach($users as $user){
		    $user->capabilities = $this->getUserMeta($user->ID, $blog_prefix.'capabilities');
		    $params['users'][] = get_object_vars($user);
	    }
		
		$remoteResponse = $this->getClient()->request('POST', 'import/wordpressUsers', $params);
	
		foreach($remoteResponse['response'] as $userId => $duoshuoUserId)
			$this->updateUserMeta($userId, 'duoshuo_user_id', $duoshuoUserId);
		
		return count($users);
	}
	
	public function packageUser($user){
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
			$capabilities = $this->getUserMeta($user->ID, $this->get_blog_prefix().'capabilities', true);
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
	
	public function packageOptions(){
		$options = array(
			'name'	=>	html_entity_decode(get_option('blogname'), ENT_QUOTES, 'UTF-8'),
			'description'=>html_entity_decode(get_option('blogdescription'), ENT_QUOTES, 'UTF-8'),
		);
		foreach(self::$optionsMap as $key => $value)
			$options[$value] = get_option($key);
		
		$akismet_api_key = get_option('wordpress_api_key');
		if ($akismet_api_key)
			$options['akismet_api_key'] = $akismet_api_key;
		$options['plugin_dir_url'] = $this->pluginDirUrl;
		
		return $options;
	}
	
	/**
	 * 通知多说服务器更新站点信息
	 */
	public function updateSite(){
		$params = $this->packageOptions();
		$user = wp_get_current_user();
		
		try{
			$response = $this->getClient($user->ID)->request('POST', 'sites/settings', $params);
			
			if ($response['code'] != 0)
				echo '<div id="message" class="updated fade"><p><strong>' . $response['errorMessage'] . '</strong></p></div>';
		}
		catch(Duoshuo_Exception $e){
			$this->showException($e);
		}
	}
	
	public function updatedOption($option, $oldvalue = null, $newvalue = null){
		if (isset(self::$optionsMap[$option]))
			$this->updateSite();
		
		//'system_theme'=>get_current_theme(),
		//'plugin_dir_url'=>$this->pluginDirUrl,
	}
	
	/**
	 * 
	 */
	public function joinSite($user){
		//global $wpdb;
		
		$params = array(
			'user'		=>	$this->packageUser($user),
			//'unique'	=>	$unique,
			//'short_name'=>	$this->shortName
		);
		try{
			$remoteResponse = $this->getClient($user->ID)->request('POST', 'sites/join', $params);
			// 在joinSite之前就已经记录了duoshuo_user_id
			//if (isset($remoteResponse['response']))
			//	$this->updateUserMeta($data['source_user_id'], 'duoshuo_user_id', $remoteResponse['response']['user_id']);
		}
		catch(Duoshuo_Exception $e){
			$this->showException($e);
		}
	}
	
	public function syncUserToRemote($userId){
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
			$blog_prefix = $this->get_blog_prefix();
			$userData->capabilities = $this->getUserMeta($user->ID, $blog_prefix.'capabilities', true);
		}
		
		$params = array('users'=>array(get_object_vars($userData)), 'short_name'=>$this->shortName);
		try{
			$remoteResponse = $this->getClient()->request('POST','import/wordpressUsers', $params);
		
			if (isset($remoteResponse['response'])){
				foreach($remoteResponse['response'] as $userId => $duoshuoUserId)
					$this->updateUserMeta($userId, 'duoshuo_user_id', $duoshuoUserId);
			}
		}
		catch(Duoshuo_Exception $e){
			$this->showException($e);
		}
	}
	
	public function exportPosts($limit, $offset = 0){
		global $wpdb;
		
		$columns = array('ID', 'post_author', 'post_date', 'post_date_gmt', 'post_content', 'post_title', 'post_excerpt', 'post_status', 'comment_status', 'ping_status', 'post_name', 'post_modified_gmt', 'guid', 'post_type', 'post_parent');
		$posts = $wpdb->get_results( $wpdb->prepare("SELECT " . implode(',', $columns) . "  FROM $wpdb->posts where post_type not in ('nav_menu_item', 'revision') and post_status not in ('auto-draft', 'draft', 'trash') order by ID asc limit $offset,$limit") );// 'inherit' 也进行同步
		
		if (count($posts) === 0)
			return 0;
		
		$params = array(
			'threads'	=>	array(),
		);
		foreach($posts as $index => $post){
			$params['threads'][] = $this->packagePost($post);
		}
		
		$remoteResponse = $this->getClient()->request('POST','import/wordpressPosts', $params);
		
		foreach($remoteResponse['response'] as $postId => $threadId)
			update_post_meta($postId, 'duoshuo_thread_id', $threadId);
		
		return count($posts);
	}
	
	/**
	 * 同步这篇文章到所有社交网站
	 * @param string $postId
	 */
	public function syncPostToRemote($postId, $post = null){
		if ($post == null)
			$post = get_post($postId);
		
		if (in_array($post->post_type, array('nav_menu_item', 'revision'))
			|| in_array($post->post_status, array('auto-draft', 'draft', 'trash')))	//'inherit' 也可以进行同步
			return ;
		
		$params = $this->packagePost($post);
		
		if (isset($_POST['sync_to'])){
			if ($_POST['sync_to'][0] == 'placeholder')
				unset($_POST['sync_to'][0]);
			$params['sync_to'] = implode(',', $_POST['sync_to']);
		}
		
		try{
			$response = $this->getClient($post->post_author)->request('POST', 'threads/sync', $params);
			
			if ($response['code'] == 0 && isset($response['response']))
				update_post_meta($post->ID, 'duoshuo_thread_id', $response['response']['thread_id']);
		}
		catch(Duoshuo_Exception $e){
			$this->showException($e);
		}
	}
	
	public function packagePost($post){
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
		
		$authorId = $this->getUserMeta($post->post_author, 'duoshuo_user_id', true);
		if (!empty($authorId))
			$params['author_id'] = $authorId;
		
		$threadId = get_post_meta($post->ID, 'duoshuo_thread_id', true);
		if (!empty($threadId))
			$params['thread_id'] = $threadId;
		return $params;
	}
	
	public function exportComments($limit, $offset = 0){
		global $wpdb;
		
		$comments = $wpdb->get_results( $wpdb->prepare("SELECT * FROM $wpdb->comments where comment_agent NOT LIKE 'Duoshuo/%%' order by comment_ID asc limit $offset,$limit"), ARRAY_A);
		
		if (count($comments) === 0)
			return 0;
		 
		$remoteResponse = $this->getClient()->request('POST', 'import/wordpressComments', array('posts'=>$comments));
		
		return count($comments);
	}
	
	public function exportOneComment($comment_ID){
		$comment = get_object_vars(get_comment($comment_ID));
		
		$remoteResponse = $this->getClient()->request('POST', 'import/wordpressComments', array('posts'=>array($comment)));
		
		return $remoteResponse;
	}
	
	public function syncPostComments($post){
		global $wpdb;
		
		$comments = $wpdb->get_results( $wpdb->prepare("SELECT * FROM $wpdb->comments where comment_post_ID = %d AND comment_agent NOT LIKE 'Duoshuo/%%' order by comment_ID asc", $post->ID), ARRAY_A);
	    
		if (count($comments) === 0)
			return 0;
		
		$params = array('posts'		=> $comments);
		
		$remoteResponse = $this->getClient($post->post_author)->request('POST','import/wordpressComments', $params);
		
		return $remoteResponse;
	}
	
	static function syncCommentsToLocal(){
		update_option('_duoshuo_sync_lock', time());
		
		$last_post_id = $this->getOption('last_post_id');
		
		$params = array(
			'start_id' => $last_post_id,
            'limit' => 20,
            'order' => 'asc',
			'sources'=>'duoshuo,anonymous'
		);
		
		$client = $this->getClient();
		
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
				'comment_date'		=>	$this->rfc3339_to_mysql($post['created_at']), 
		 		'comment_date_gmt'	=>	$this->rfc3339_to_mysql_gmt($post['created_at']),
				'comment_content'	=>	$post['message'], 
		 		'comment_approved'	=>	$approvedMap[$post['status']],
				'comment_agent'		=>	'Duoshuo/' . self::VERSION . ':' . $post['post_id'],
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
	
	public function notices(){
		foreach($this->errorMessages as $message)
			echo '<div class="updated"><p><strong>'.$message.'</strong></p></div>';
		
		$duoshuo_notice = $this->getOption('notice');
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
	
	public function showException($e){
		echo '<div class="updated fade"><p>' . $e->getMessage() . '</p></div>';
	}
	
	public function sendException($e){
		$response = array(
			'code'	=>	$e->getCode(),
			'errorMessage'=>$e->getMessage(),
		);
		echo json_encode($response);
		exit;
	}
	
	public function sendJsonResponse($response){
		if (!headers_sent()) {
			nocache_headers();
			header('Content-type: application/json; charset=UTF-8');
		}
		
        echo json_encode($response);
        exit;
	}
	
	//发布文章时候的同步设置
	public function syncOptions(){
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
			echo $this->getHtml('partials/sync-options', $params);
		}
		catch(Duoshuo_Exception $e){
			$this->showException($e);
		}
		echo '<p><a href="' . admin_url('admin.php?page=duoshuo-profile') . '">绑定更多社交网站</a></p>';
	}
	
	public function managePostComments($post){
		//这里应该嵌入一个iframe框
	}
	
	public function syncCron(){
		try{
			$this->syncCommentsToLocal();
		}
		catch(Duoshuo_Exception $e){
		}
	}
	
	public function actionsFilter($actions){
		/**
		 * TODO 
		$actions['ds-comments'] = '<a href="javascript:void(0);">管理评论</a>';
		 */
		return $actions;
	}
	
	public function pluginActionLinks($links, $file) {
		if (empty($this->shortName) || empty($this->secret) || !is_numeric($this->getOption('synchronized')))
	    	array_unshift($links, '<a href="' . admin_url('admin.php?page=duoshuo') . '">'.__('Install').'</a>');
		else
			array_unshift($links, '<a href="' . admin_url('admin.php?page=duoshuo-settings') . '">'.__('Settings').'</a>');
	    return $links;
	}
	
	public function dashboardWidget(){
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
	
	public function updateLocalOptions(){
		update_option('duoshuo_cron_sync_enabled', isset($_POST['duoshuo_cron_sync_enabled']) ? 1 : 0);
		update_option('duoshuo_seo_enabled', isset($_POST['duoshuo_seo_enabled']) ? 1 : 0);
		update_option('duoshuo_social_login_enabled', isset($_POST['duoshuo_social_login_enabled']) ? 1 : 0);
		update_option('duoshuo_comments_wrapper_intro', isset($_POST['duoshuo_comments_wrapper_intro']) ? stripslashes($_POST['duoshuo_comments_wrapper_intro']) : '');
		update_option('duoshuo_comments_wrapper_outro', isset($_POST['duoshuo_comments_wrapper_outro']) ? stripslashes($_POST['duoshuo_comments_wrapper_outro']) : '');
	}
}
