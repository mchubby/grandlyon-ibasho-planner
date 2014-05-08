<?php
require 'vendor/autoload.php';
$secret_key = getenv('SECRET_KEY') ? getenv('SECRET_KEY') : 'Cth4nG3_M3';
$config = getenv('OPENSHIFT_MONGODB_DB_URL') . getenv('OPENSHIFT_APP_NAME');

$client = new MongoClient($config);
$db = $client->selectDB(getenv('OPENSHIFT_APP_NAME'));
$coll = new MongoCollection($db, 'tcl_metro_a');

$result = $coll->find();
exit ((int)($result->count() > 0));
?>
