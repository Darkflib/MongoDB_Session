<?php 
/*mongodb custom php session handler
copyright 2010 ryan day
modifications by Mike Preston (c) 2011
lgpl

Changelog:
fixed session id generator -- MP
fixed constructor to use configured values -- MP
*/

$mongo = new Mongo();
$mongo_session = new MongoSession($mongo);



echo $mongo_session->_id."<br/>";
echo $mongo_session->sid."<br/>";


if(!isset($_SESSION['inc'])) $_SESSION['inc'] = 0;

$_SESSION['inc']++;

echo "<pre>";
echo var_export($_SESSION,true);
echo "</pre>";






class mongoSession {
public static $config = array(
'db'=>'session',
'collection'=>'session',
'expire'=>86400,//day
'expire_remember'=>2592000//month
);

public static function setConfig($kv_or_k,$v = null){
if(!is_array($kv_or_k)){
$kv_or_k = array($kv_or_k=>$v);
}

self::$config = array_merge(self::$config,$kv_or_k);
}

private $collection;
private $row;

public $_id;
public $sid;

public function __construct($mongo){
$db = $mongo->selectDB(self::$config['db']);
$this->collection = new MongoCollection($db,self::$config['collection']);

$this->row = $this->getRow();

$_SESSION = $this->getData();

$this->_id = $this->row['_id'];
$this->sid = $this->row['sid'];
}

public function __destruct(){
$fields = array("update" => time());
if(count($_SESSION) || (count($row['data']) && !count($_SESSION)) ){
$fields["data"] = $_SESSION;
}
$new_data = array('$set' => $fields);
$this->collection->update(array('_id'=>$this->_id),$new_data);
}

private function getRow(){
extract($this->cookieVals());

$row = false;
if($_id){
$row = $this->collection->findOne(array('_id'=>new MongoId($_id)));

//ensure sid match to pk
if($row['sid'] != $sid){
$row = false;
}
}

if(!$row){
$row = array('sid'=>$sid,'update'=>time(),'created'=>time());
$this->collection->insert($row);
$_id = $row['_id'];
}

$this->setCookies($sid,$_id);

return $row;
}

public function getData(){
return isset($this->row['data'])?$this->row['data']:array();
}

private function gc(){
/*
session gc...
capped collection wont work because changing object size and if you ever had over the capped max active/remembered sessions
capped collection could work if you just stored a refrence to a session_data mongo id and you padded the value with 0's or something so the object would not change size

because this is just as much a lookup table as a write table the index on _id would be useful but with no gc needed you would not need an index on time
if you wanted to flag session as deactivated you could use the last update

load balanced delete
every few requests hit mongo with a delete for expired records
needs index on time

note: if you do a delete for all documents that have an expiry less than current time you will hold a lock while all delete.
      better to find each and delete one by one

      See cronjob for example
*/
}

public function setCookies($sid,$_id){
setcookie("sid", $sid, time()+self::$config['expire']);
setcookie("_id", $_id, time()+self::$config['expire']);
$_COOKIE['sid'] = $sid;
$_COOKIE['_id'] = $_id.'';//ensure toString is called on MongoId
}

public function cookieVals(){
$sid = isset($_COOKIE['sid'])?$_COOKIE['sid']:$this->generate_sid();
$_id = isset($_COOKIE['_id'])?$_COOKIE['_id']:false;
//$remember =
return array('sid'=>$sid,'_id'=>$_id);
}

public function generate_sid(){
// mersenne twister pseudo random number generator and microtime together only produce around 30-40 bits of entropy,
// sha256 is overkill here, but keeps the conspiracy geeks happy - requires PHP > 5.1.2
return hash('sha256',microtime(TRUE).mt_rand());
}
}

?>
