<?php 
/*mongodb custom php session handler
copyright 2010 ryan day
modifications by Mike Preston (c) 2011
lgpl

Changelog:
fixed session id generator -- MP
fixed constructor to use configured values -- MP
*/

class mongoSession {
// 3600 - hour
// 86400 - day
// 604800 - week
// 2419200 - 4 weeks
// 2592000 - 30 days
// 2678400 - 31 days

public $config = array(
	'db'=>'session',
	'collection'=>'session',
	'expire'=> 604800, //week
	'expire_remember'=>2678400, //month - unused in new implementation
	'cookie_path'   => '/',
	'cookie_domain' => '', // '' = vhost
	'session_name' => 'sid',
	'session_hash' => 'sha224', // 0 - md5, 1 - sha1, sha224, sha256, sha512 etc
	'gc_enabled' => TRUE
);

public function setConfig($kv_or_k,$v = null){
	if(!is_array($kv_or_k)){
		$kv_or_k = array($kv_or_k=>$v);
	}
	self::$config = array_merge(self::$config,$kv_or_k);
}

private $db;
private $collection;
private $row;

public function __construct($mongo){
	$this->db = $mongo->selectDB($this->config['db']);
	$this->collection = new MongoCollection($this->db,$this->config['collection']);
	
	ini_set('session.name',			$this->config['session_name']);
        ini_set('session.auto_start',           0);
        ini_set('session.gc_probability',       1);
        ini_set('session.gc_divisor',           100);
        ini_set('session.gc_maxlifetime',       $this->config['expire']);
        ini_set('session.referer_check',        '');
        ini_set('session.entropy_file',         '/dev/urandom');
        ini_set('session.entropy_length',       16);
        ini_set('session.use_cookies',          1);
        ini_set('session.use_only_cookies',     1);
        ini_set('session.use_trans_sid',        0);
        ini_set('session.hash_function',        'sha224');
        ini_set('session.hash_bits_per_character',  5);
	ini_set('session.cache_limiter',	'nocache');
        ini_set('session.cookie_domain',        $this->config['cookie_domain']);
        ini_set('session.cookie_path',          $this->config['cookie_path']);
        ini_set('session.cookie_lifetime',	$this->config['expire']);

        session_set_save_handler(
            array(&$this, 'open'),
            array(&$this, 'close'),
            array(&$this, 'read'),
            array(&$this, 'write'),
            array(&$this, 'destroy'),
            array(&$this, 'gc')
        );


}

public function __destruct(){

}


function gc($maxlifetime){
/*
note: if you do a delete for all documents that have an expiry less than current time you will hold a lock while all delete.
      better to find each and delete one by one
      See cronjob/gearman process for example
      With gearman use coalescing to make sure multiple tasks don't run at once...
*/
  if ($this->config['gc_enabled']) {
    $this->collection->remove(array('expiry' => array('$lt' => time())));
  }
}

function printrow() {
  print_r($this->row);
}

function open($save_path, $session_name){
  return(true);
}

function close(){
  return(true);
}

function read($id){
  $this->row = $this->collection->findOne(array('_id'=>$id));
  if (!$this->row) {
    $this->collection->insert(array('_id'=>$id, 'active'=> 1, 'data'=>'', 'expiry'=>(time()+$this->config['expire'])));
  }
  return $this->row['data'];
}

function write($id, $sess_data){
  $expiry=time()+$this->config['expire'];
  $fields = array('update' => time(),'expiry' => $expiry );
  $fields["data"] = $sess_data;
  $new_data = array('$set' => $fields);
  $this->collection->ensureIndex(array("expiry" => 1)); // not needed *every* time... but on dev systems...
  $this->collection->update(array('_id'=>$id),$new_data);
}

function destroy($id){
  $this->collection->remove(array('_id'=>$id));
  return(true);
}

}

