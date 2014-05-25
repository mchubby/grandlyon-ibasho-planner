<?php
$loader = require 'vendor/autoload.php';
$loader->add("Zenon",__DIR__.'/src');

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Silex\Provider\FormServiceProvider;
use Silex\Provider\TwigServiceProvider;
use Silex\Provider\TranslationServiceProvider;
use Silex\Provider\ValidatorServiceProvider;

use Zenon\Form\Type\GeoPoiType; // GeoPoiType < AbstractType

$app = new \Silex\Application();
$app->register(new TranslationServiceProvider());
$app->register(new ValidatorServiceProvider());
$app->register(new FormServiceProvider());
$app->register(new TwigServiceProvider(), array('twig.path' => __DIR__ . '/tpl'));


//$app['debug'] = true;
//else
ini_set('display_errors', 0);

class AppLevelException extends Exception {}
$permissive_floatval = function ($coord) { return floatval($coord); };  // define converter which accepts a 2-var user call


$app->get('/', function () use ($app) {
  return $app->sendFile('static/index.html');
});



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
      $park['_id'] = (string)$park['_id'];
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
        and abs($lat) <= 0.0001) {
            throw new AppLevelException("Missing or invalid coordinates", 403);
        }
        $ts = new DateTime(date('Y-m-d H:i:s'), new DateTimeZone('UTC'));

        $doc = array_merge(GeoPoiType::blankDocument(), array(
          'mta_dateins' => new MongoDate($ts->getTimestamp()),
          'pos' => array( $lng, $lat ),
        ));

        try {
            $result = $ugc_coll->update(array( 'pos' => $doc['pos'] ), $doc, array('upsert'=>true));
    		if ( !isset($result['ok']) || !$result['ok'] ) {
    			throw new AppLevelException("Error saving to database (update), try again later", 503);
    		}
            $doc = $ugc_coll->findOne(array('pos' => $doc['pos']));
        } catch ( MongoResultException $e ) {
            throw new AppLevelException("Error saving to database, try again later", 503, $e);
        }

        try {
            $ugc_coll->ensureIndex(array( 'pos' => '2d'), array('unique'=>true));  // should actually do it ONCE -- createIndex on MongoDB 2.6+
        } catch ( MongoException $e ) {}
    } catch ( AppLevelException $e ) {
        return $app->json(array("error"=> $e->getMessage() . ($e->getPrevious()?' -- '.$e->getPrevious()->getMessage() : '')), $e->getCode());
    } catch ( MongoConnectionException $e ) {
        return $app->json(array("error"=>"Error connecting to database. The data source may need to be reconfigured by the site administrator, please try again later"), 503);
    } catch ( MongoException $e ) {
        return $app->json(array("error"=>"Error accessing database: ". $e->getMessage(). ". This might be a caused by a bug and/or a malformed request. You may want to notify the site administrator about it"), 500);
    } catch ( Exception $e ) {
        return $app->json(array("error"=>"The query failed for an unspecified reason. This might be a caused by a bug and/or a malformed request. You may want to notify the site administrator about it"), 500);
    }
    $doc['_id'] = (string)$doc['_id'];
    $form = $app['form.factory']->create(new GeoPoiType($doc), null, array('action'=> 'edit/'.$doc['_id']));
    $doc['cached_form'] = $app['twig']->render('ajaxform.twig', array(
		'form' => $form->createView()
	));

    return $app->json($doc);
})
->convert('lat', $permissive_floatval)
->convert('lng', $permissive_floatval);



$app->match('/edit/{reqid}', function (Request $request, $reqid) use ($app) {
    $db_connection = getenv('OPENSHIFT_MONGODB_DB_URL') ? getenv('OPENSHIFT_MONGODB_DB_URL') . getenv('OPENSHIFT_APP_NAME') : "mongodb://localhost:27017/";
    $client = new MongoClient($db_connection);
    $db = $client->selectDB(getenv('OPENSHIFT_APP_NAME') ? getenv('OPENSHIFT_APP_NAME') : "test");
    $ugc_coll = new MongoCollection($db, 'pending_ugc');
    try {
        $doc = $ugc_coll->findOne(array('_id' => new MongoId($reqid)));
        if ($doc === null) {
            throw new AppLevelException("Error cannot find requested id", 404);
        }

        $doc['_id'] = (string)$doc['_id'];
        $form = $app['form.factory']->create($geopoi = new GeoPoiType($doc), null, array('action'=> 'edit/'.$doc['_id']));
        if ($request->getMethod() == 'POST') {
            $form->handleRequest($request);
            if ($form->isSubmitted()) {
                $savdoc = $geopoi->merge($form->getData());
                $savdoc['_id'] = new MongoId($doc['_id']);
                try {
                    $result = $ugc_coll->save($savdoc);
                    if ( !isset($result['ok']) || !$result['ok'] ) {
                        throw new AppLevelException("Error saving to database (update), try again later", 503);
                    }
                    $doc = $ugc_coll->findOne(array('_id' => new MongoId($reqid)));
                } catch ( MongoResultException $e ) {
                    throw new AppLevelException("Error saving to database, try again later", 503, $e);
                }

                return $app->json($doc);
            }
            return $app->json(array("error"=>"Error in POST"), 500);
        }
    } catch ( AppLevelException $e ) {
        return $app->json(array("error"=> $e->getMessage() . ($e->getPrevious()?' -- '.$e->getPrevious()->getMessage() : '')), $e->getCode());
    } catch ( MongoConnectionException $e ) {
        return $app->json(array("error"=>"Error connecting to database. The data source may need to be reconfigured by the site administrator, please try again later"), 503);
    } catch ( MongoException $e ) {
        return $app->json(array("error"=>"Error accessing database: ". $e->getMessage(). ". This might be a caused by a bug and/or a malformed request. You may want to notify the site administrator about it"), 500);
    } catch ( Exception $e ) {
        return $app->json(array("error"=>"The query failed for an unspecified reason. This might be a caused by a bug and/or a malformed request. You may want to notify the site administrator about it"), 500);
    }
    $doc['cached_form'] = $app['twig']->render('ajaxform.twig', array(
		'form' => $form->createView()
	));
    return $app->json($doc);
});

$app->run();
?>
