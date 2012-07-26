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
		if(!isset($syncLock) || $syncLock > time()- 900){//正在或15分钟内发生过写回但没置0
			$response = array(
					'code'	=>	Duoshuo_Exception::SUCCESS,
					'response'=> '同步中，请稍候',
			);
			return;
		}
		
		try{
			$this->plugin->updateOption('sync_lock',  time());
			
			$last_sync = $this->plugin->getOption('last_sync');
			
			$limit = 50;
			
			$params = array(
				'since' => $last_sync,
				'limit' => $limit,
				'order' => 'asc',
			);
			
			$client = $this->plugin->getClient();
			
			$posts = array();
			$affectedThreads = array();
			$max_sync_date = 0;
			
			do{
				$response = $client->getLogList($params);
			
				$count = count($response['response']);
				
				foreach($response['response'] as $log){
					switch($log['action']){
						case 'create':
							$affected = $this->plugin->createPost($log['meta']);
							break;
						case 'approve':
							$affected = $this->plugin->approvePost($log['meta']);
							break;
						case 'spam':
							$affected = $this->plugin->spamPost($log['meta']);
							break;
						case 'delete':
							$affected = $this->plugin->deletePost($log['meta']);
							break;
						case 'delete-forever':
							$affected = $this->plugin->deleteForeverPost($log['meta']);
							break;
						case 'update'://现在并没有update操作的逻辑
						default:
							$affected = array();
					}
					//合并
					
					$affectedThreads = array_merge($affectedThreads, $affected);
				
					if ($log['date'] > $max_sync_date)
						$max_sync_date = $log['date'];
				}
				
				$params['since'] = $max_sync_date;
				
			} while ($count == $limit);//如果返回和最大请求条数一致，则再取一次
			
			if ($max_sync_date > $last_sync)
				$this->plugin->updateOption('last_sync', $max_sync_date);
			
			$this->plugin->updateOption('sync_lock',  0);
			
			//唯一化
			$aidList = array_unique($affectedThreads);
					
			//更新静态文件
			if ($this->plugin->getOption('sync_to_local') && $this->plugin->getOption('seo_enabled'))
				$this->plugin->refreshThreads($aidList);
		}
		catch(Exception $ex){
			$this->plugin->updateOption('sync_lock', $ex->getLine());
		}
		
		//$this->response['response']
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
