<?php

class Duoshuo_LocalServer{
	
	protected $response = array();
	
	protected $plugin;
	
	public function __construct($plugin){
		$this->plugin = $plugin;
	}
	
	public function sync_posts($input = array()){
		$this->response['response'] = $this->plugin->syncCommentsToLocal();
		$this->response['code'] = 0;
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
