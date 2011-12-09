<pre><?php 
/*mongodb custom php session handler
copyright 2010 ryan day
modifications by Mike Preston (c) 2011
lgpl
*/

require_once 'mongodb_session_handler.php';


$mongo = new Mongo();
$mongo_session = new MongoSession($mongo);
session_start();

echo session_id()."\n";
echo SID."\n";


if(!isset($_SESSION['inc'])) $_SESSION['inc'] = 0;

$_SESSION['date']=gmdate('c');
$_SESSION['inc']++;

echo "<pre>";
echo var_export($_SESSION,true);
$mongo_session->printrow();
echo "</pre>";

//session_destroy();
?>
