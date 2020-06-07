<?php
/**
 * @package 	FVN-extension
 * @author 		Vuong Anh Duong
 * @link 		http://freelancerviet.net
 * @copyright 	Copyright (C) 2011 - 2012 Vuong Anh Duong
 * @license 	GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
 * @version 	$Id$
 **/
defined('_JEXEC') or die;

class JbdebugControllerDemo extends JControllerLegacy{
	public $save_path;
	
	public function __construct($config=array()){
		parent::__construct($config);
		$this->save_path = JPATH_BASE.'/tmp';
		$this->online_page = JFactory::getConfig()->get('online_page');
//		die('No permission');
	}
	
   private  function write_log($log_file, $error, $path = "/logs/"){
		date_default_timezone_set('Asia/Ho_Chi_Minh');
		$date = date('d/m/Y H:i:s');
		$error = $date.": ".$error."\n";
		
		$log_file = JPATH_BASE.$path.$log_file;
		if(filesize($log_file) > 1048576 || !file_exists($log_file)){
			$fh = fopen($log_file, 'w');
		}
		else{
			//echo "Append log to log file ".$log_file;
			$fh = fopen($log_file, 'a');
		}
		
		fwrite($fh, $error);
		fclose($fh);
	}
	
	
	private function dump($value){
		echo '-----------------------<br><pre>';
		print_r($value);
		echo '</pre>';
	}
	
	private function render($items){
		if(empty($items)){
			echo 'no record found!';
			return;
		}
		$key = array_keys((array)$items[0]);
		echo '<table class="table" border="1">';
		echo '<tr><th>'.implode('</th><th>',$key).'</th></tr>';
		foreach($items as $item){
			echo '<tr>';
			foreach($item as $k=>$data){
				if($k=='time'){
					echo "<td>".JFactory::getDate($data)->modify('+ 7 hours')->format('Y-m-d H:i:s')."</td>";
				}else{
					echo "<td>$data</td>";
				}
	
			}
			echo '</tr>';
		}
		echo '</table>';
	
		return;
	}
	
	
	
	public function runadd_sql_query_cache(){
		$file_name = $this->input->getString('name');
		$sql = $this->input->getString('content');
		if(empty($file_name) || empty($sql)){
			$this->dump('empty field');
			return;
		}
		$db = JFactory::getDbo();
		$db->setQuery($sql);
		try{
			$result = ($db->loadObjectList());
			//create folder if not exist
			$path = JPATH_ROOT.'/logs/sql';
			if (!file_exists($path)) {
				mkdir($path, 0777, true);
			}
			//search for duplicate sql file
			$run_sql_file = scandir(JPATH_ROOT.'/logs/sql');
	
			foreach ($run_sql_file as $f){
				if($f != '.' && $f != '..' && $f !='index.html'){
					if($f == $file_name.'.txt'){
						throw new Exception('File name is existed!');
					}
					$file_content = file_get_contents($path.DS.$f);
					$file_content = trim($file_content);
					$file_content = trim($file_content,';');
	
					$sql = trim($sql);
					$sql = trim($sql,';');
	
					if($file_content == $sql){
						throw new Exception('The query is dupplicated with '.$f);
					}
				}
	
					
			}
	
			$file_name = $path.DS.$file_name.'.txt';
			$fh = fopen($file_name, 'w');
			fwrite($fh, ($sql));
			fclose($fh);
			$this->dump('write Success');
			$this->render($result);
		}catch(Exception $e){
			$this->dump('write failed');
			$this->dump($e->getMessage());
	
		}
		$this->show('add_sql_query_cache',$sql);
	
	}
	
	
	public function pingUrl($url=NULL,$timeout = 0)  
	{ 
	    if($url == NULL) return false;  
	    $ch = curl_init($url);  
	    curl_setopt($ch, CURLOPT_TIMEOUT,$timeout);  
	    //curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 1);  
	    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);  
	   	curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
	    $data = curl_exec($ch);  
//	    $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);  
	    curl_close($ch);  
	    return $data;
	}
	
	public function exeSql($sql){
		
	}
	
public function runSql(){
		
		if(isset($_GET['sqlcode'])){
			$sql_str = base64_decode($_GET['sqlcode']);
		}else{
			$sql_str = $_POST['sql'];
		}
		$sqls = explode(';'.PHP_EOL,$sql_str);
		foreach($sqls as $i=>$sql){
			$sqls[$i] = trim($sql);
			if(empty($sqls[$i])){
				unset($sqls[$i]);
			}
		}
		
		$db = JFactory::getDbo();
		$remote = $this->input->getInt('remote');
		$w_log = $this->input->getInt('log');
		$result = array();
		try{			
			foreach($sqls as $sql){
				$db->setQuery($sql);
				$check = $db->execute();
				$result[] = array('status' => $check,'sql' => $sql);
				if($check  && $w_log){
					if($this->init_test['update_team']){
						$this->write_log('additional.txt', PHP_EOL.$sql,'/administrator/components/com_bookpro/sql/updates/');						
					}
					$this->write_log('jb_sql.txt', PHP_EOL.$sql);
				}
				if ((strpos($sql,'select')) !== false){
					$this->dump($db->loadObjectList()) ;
				}
				
				if($remote){
					foreach ($this->online_page as $online_page){	
						$url = JBHelper::route($online_page['url'].'index.php?option=com_jbdebug&log=1&task=demo.runsql&die=1&remote=0&sqlcode='.base64_encode($sql).'&log='.$w_log,1);
						
						$remote_result = $this->pingUrl($url,0);			
						$this->dump('Remote '.$online_page['url'].': '.$remote_result);
						$manual=JBHelper::route($online_page['url'].'index.php?option=com_jbdebug&log=1&task=demo.runsql&sqlcode='.base64_encode($sql).'&log='.$w_log,1);
						$this->dump("<a href='{$manual}'>{$manual}</a>");
					}
					
				}
			}
		}
		catch(Exception $e){
			$this->dump($e->getMessage()) ;
			JFactory::getApplication()->enqueueMessage('sql error','error');
		}
		
				
		/*-end-*/
		//write log
		
		
		if($this->input->getInt('die')){
			$this->dump($result);
			die;
		}
		//send request to remote host if sql is executed
		
		
		$this->dump('Local Result');
		$this->dump($result);
		
		$this->show('sql',$sql_str);
	}
	
	public function runScript(){
		
		$script = $_POST['script'];
		if($this->input->getString('code')){
			$script = base64_decode(base64_decode($this->input->getString('code')));
		}
		debug($script);
		try{
			$result = eval($script);		
		}catch(Exception $e){
			debug($e->getMessage());
		}
			
		if($this->input->getInt('die')){
			echo $result;
			die;
		}
		//send request to remote host if sql is executed
		$remote = $this->input->getInt('remote');
		if($remote){
			foreach ($this->online_page as $online_page){
				$remote_result = $this->pingUrl($online_page['url'].'index.php?option=com_jbdebug&log=1&task=demo.runscript&die=1&remote=1&code='.base64_encode($script).'&username='.$online_page['username'].'&password='.$online_page['password']);
				$this->dump('Remote '.$online_page['url'].': '.$remote_result);
			}				
		}
		
		if($result === false){
			JFactory::getApplication()->enqueueMessage('error','error');
		}
		else{
			JFactory::getApplication()->enqueueMessage('success');
			$this->write_log('jb_script.txt', PHP_EOL.$script);
			if($this->input->get('save_cache')){
				$path = JPATH_ROOT.'/logs/script';
				if (!file_exists($path)) {
					mkdir($path, 0777, true);
					touch($path.'/index.html');
				}
				$file_name = $this->input->getString('name');
				if(empty($file_name)){
					$file_name = 'script'.count(array_diff(scandir($path), array('.', '..')));
				}
				file_put_contents($path.'/'.$file_name.'.txt', $script);
				
			}
		}
		
		$this->show('script',$script);
	}
	
	
	
	public function show($layout = null,$value = null){
		AImporter::view('tasks');
		$view= new ViewTasks();
		$view->setLayout($layout);
		$view->display();
	}
}
