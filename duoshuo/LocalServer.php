<?php

class Duoshuo_LocalServer{
	
	protected $response = array();
	
	protected $plugin;
	
	public function __construct($plugin){
		$this->plugin = $plugin;
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
	 * @param array $input
	 */
	public function sync_log($input = array()){
		$syncLock = $this->plugin->getOption('sync_lock');//检查是否正在同步评论 同步完成后该值会置0
		if(!$syncLock || $syncLock > time()- 900){//正在或15分钟内发生过写回但没置0
			$this->response = array(
					'code'	=>	Duoshuo_Exception::SUCCESS,
					'response'=> '同步中，请稍候',
			);
			return;
		}
		
		try{
			$aidList = $this->plugin->syncLog();
			
			//更新静态文件
			if ($this->plugin->getOption('sync_to_local') && $this->plugin->getOption('seo_enabled'))
				$this->plugin->refreshThreads($aidList);
		}
		catch(Exception $ex){
			$this->plugin->updateOption('sync_lock', $ex->getLine());
		}
		
		$this->response['code'] = Duoshuo_Exception::SUCCESS;
	}
	
	public function update_option($input = array()){
		//duoshuo_short_name
		//duoshuo_secret
		//duoshuo_notice
		foreach($input as $optionName => $optionValue)
			if (substr($optionName, 0, 8) === 'duoshuo_'){
				update_option($_POST['option'], $_POST['value']);
			}
		$this->response['code'] = 0;
	}
	
	public function sendResponse(){
		echo json_encode($this->response);
	}
}
