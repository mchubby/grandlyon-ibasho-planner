<?php
//Allow PHP's built-in server to serve our static content in local dev:
if (php_sapi_name() === 'cli-server' && is_file(__DIR__.'/static'.preg_replace('#(\?.*)$#','', $_SERVER['REQUEST_URI']))
   ) {
  return false;
}

require 'vendor/autoload.php';
use Symfony\Component\HttpFoundation\Response;
$app = new \Silex\Application();

//$app['debug'] = true;
//else
ini_set('display_errors', 0);

class AppLevelException extends Exception {}
$permissive_floatval = function ($coord) { return floatval($coord); };  // define converter which accepts a 2-var user call


$app->get('/', function () use ($app) {
  return $app->sendFile('static/index.html');
});

//$app->get('/hello/{name}', function ($name) use ($app) {
//  return new Response( "Hello, {$app->escape($name)}!");
//});
//
//// An alternative method for serving our static content via Silex:
//$app->get('/css/{filename}', function ($filename) use ($app){
//  if (!file_exists('static/css/' . $filename)) {
//    $app->abort(404);
//  }
//  return $app->sendFile('static/css/' . $filename, 200, array('Content-Type' => 'text/css'));
//});

//$app->get('/parks', function () use ($app) {
//  $db_connection = getenv('OPENSHIFT_MONGODB_DB_URL') ? getenv('OPENSHIFT_MONGODB_DB_URL') . getenv('OPENSHIFT_APP_NAME') : "mongodb://localhost:27017/";
//  $client = new MongoClient($db_connection);
//  $db = $client->selectDB(getenv('OPENSHIFT_APP_NAME') ? getenv('OPENSHIFT_APP_NAME') : "test");
//  $parks = new MongoCollection($db, 'tcl_metro_a');
//  $result = $parks->find();
//
//  $response = "[";
//  foreach ($result as $park){
//    $response .= json_encode($park);
//    if( $result->hasNext()){ $response .= ","; }
//  }
//  $response .= "]";
//  return $app->json(json_decode($response));
//});

$app->get('/parks/within', function () use ($app) {
  $db_connection = getenv('OPENSHIFT_MONGODB_DB_URL') ? getenv('OPENSHIFT_MONGODB_DB_URL') . getenv('OPENSHIFT_APP_NAME') : "mongodb://localhost:27017/";
  $client = new MongoClient($db_connection);
  $db = $client->selectDB(getenv('OPENSHIFT_APP_NAME') ? getenv('OPENSHIFT_APP_NAME') : "test");
  $parks = new MongoCollection($db, 'tcl_metro_a');

  #clean these input variables:
  $lat1 = floatval($app->escape(@$_GET['lat1']));
  $lat2 = floatval($app->escape(@$_GET['lat2']));
  $lon1 = floatval($app->escape(@$_GET['lon1']));
  $lon2 = floatval($app->escape(@$_GET['lon2']));
  
  if(!(is_float($lat1) && is_float($lat2) &&
       is_float($lon1) && is_float($lon2))){
    $app->json(array("error"=>"lon1,lat1,lon2,lat2 must be numeric values"), 500);
  }else{
    $parks->ensureIndex(array( 'pos' => '2d'));
    $result = $parks->find( 
      array( 'pos' => 
        array( '$within' => 
          array( '$box' =>
            array(
              array( $lon1, $lat1),
              array( $lon2, $lat2)
    )))));
  }
  try{ 
    $response = "[";
    foreach ($result as $park){
      $response .= json_encode($park);
      if( $result->hasNext()){ $response .= ","; }
    }
    $response .= "]";
    return $app->json(json_decode($response));
  } catch (Exception $e) {
    return $app->json(array("error"=>json_encode($e)), 500);
  }
});

$app->get('/list/within', function () use ($app) {
  $db_connection = getenv('OPENSHIFT_MONGODB_DB_URL') ? getenv('OPENSHIFT_MONGODB_DB_URL') . getenv('OPENSHIFT_APP_NAME') : "mongodb://localhost:27017/";
  $client = new MongoClient($db_connection);
  $db = $client->selectDB(getenv('OPENSHIFT_APP_NAME') ? getenv('OPENSHIFT_APP_NAME') : "test");
  $parks = new MongoCollection($db, 'pending_ugc');

  #clean these input variables:
  $lat1 = floatval($app->escape(@$_GET['lat1']));
  $lat2 = floatval($app->escape(@$_GET['lat2']));
  $lon1 = floatval($app->escape(@$_GET['lon1']));
  $lon2 = floatval($app->escape(@$_GET['lon2']));
  
  if(!(is_float($lat1) && is_float($lat2) &&
       is_float($lon1) && is_float($lon2))){
    $app->json(array("error"=>"lon1,lat1,lon2,lat2 must be numeric values"), 500);
  }else{
    $result = $parks->find( 
      array( 'pos' => 
        array( '$within' => 
          array( '$box' =>
            array(
              array( $lon1, $lat1),
              array( $lon2, $lat2)
    )))));
  }
  try{ 
    $response = "[";
    foreach ($result as $park){
      $response .= json_encode($park);
      if( $result->hasNext()){ $response .= ","; }
    }
    $response .= "]";
    return $app->json(json_decode($response));
  } catch (Exception $e) {
    return $app->json(array("error"=>json_encode($e)), 500);
  }
});

$app->get('/add/{lat}/{lng}', function (Silex\Application $app, $lat, $lng) {
	try {
	  $db_connection = getenv('OPENSHIFT_MONGODB_DB_URL') ? getenv('OPENSHIFT_MONGODB_DB_URL') . getenv('OPENSHIFT_APP_NAME') : "mongodb://localhost:27017/";
		$client = new MongoClient($db_connection);
	  $db = $client->selectDB(getenv('OPENSHIFT_APP_NAME') ? getenv('OPENSHIFT_APP_NAME') : "test");
	  $ugc_coll = new MongoCollection($db, 'pending_ugc');
	
		if (abs($lng) <= 0.0001
		and abs($lat) <= 0.0001){
			throw new AppLevelException("Missing or invalid coordinates", 403);
		}
		$ts = new DateTime(date('Y-m-d H:i:s'), new DateTimeZone('UTC'));

		$doc = array(
			'mta_title'    => '',
			'mta_url1'     => '',
			'mta_url2'     => '',
			'mta_notes'    => '',
			'mta_desc1'    => '',
			'mta_desc2'    => '',
			'mta_dateins'  => new MongoDate($ts->getTimestamp()),
			'mta_datepub'  => NULL,  // dateof first purported ad
			'mta_datemaj'  => NULL,  // dateof last refreshed
	
			'apt_addr'     => NULL,
			'apt_prix'     => NULL,
			'apt_surface'  => NULL,  // -> prix/m2, charg/m2
			'apt_anconstr' => NULL,  // annee/decennie
			'apt_numetage' => NULL,
			'apt_totetage' => NULL,
			'apt_cave'     => NULL,
			'apt_typgarag' => NULL,  // coll/aerien/box
			'apt_typcuisn' => NULL,  // 0/eqp/us
			'apt_typsdb'   => NULL,  // bain/douche/ita
			'apt_typwc'    => NULL,  // indiv/sdb/handi

			'apt_deducdep' => 0,  // eval depend. -> prix/m2
			
			'apt_charg'    => NULL,  // montant mens
			'apt_impotf'   => NULL,  // fonc
			'apt_impota'   => NULL,  // hab
	
			'eva_presta'   => NULL,  // presta, vetuste, situ ds imm/xpo
			'eva_prixrel'  => NULL,  // % a moy
			'eva_copro'    => NULL,  // niv charge, entret. comm
			'eva_envsitu'  => NULL,  // situ ds quart, desserte
			'eva_commod'   => NULL,  // prox commerce
			'eva_scolar'   => NULL,
	
		  'pos' => array( $lng, $lat ),
		);
		
	  $result = $ugc_coll->update(
      array( 'pos' => $doc['pos'] ),
	  	$doc,
			array('upsert'=>true)
		);
		if ( !isset($result['ok']) || !$result['ok'] ) {
			throw new AppLevelException("Error saving to database, try again later", 503);
		}
		try {
	  	$ugc_coll->ensureIndex(array( 'pos' => '2d'), array('unique'=>true));  // should actually do it ONCE -- createIndex on MongoDB 2.6+
		} catch ( MongoException $e ) {}
	} catch ( AppLevelException $e ) {
		return $app->json(array("error"=> $e->getMessage()), $e->getCode());
	} catch ( MongoConnectionException $e ) {
		return $app->json(array("error"=>"Error connecting to database. The data source may need to be reconfigured by the site administrator, please try again later"), 503);
	} catch ( MongoException $e ) {
		return $app->json(array("error"=>"Error accessing database: ". $e->getMessage(). ". This might be a caused by a bug and/or a malformed request. You may want to notify the site administrator about it"), 500);
	} catch ( Exception $e ) {
		return $app->json(array("error"=>"The query failed for an unspecified reason. This might be a caused by a bug and/or a malformed request. You may want to notify the site administrator about it"), 500);
	}
	return $app->json($doc);
})
->convert('lat', $permissive_floatval)
->convert('lng', $permissive_floatval);

$app->run();
?>
