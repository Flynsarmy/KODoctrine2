<?php
/**
 *
 *	@author Flynsarmy <www.flynsarmy.com>
 *	@version 1.02
 *  @license http://www.apache.org/licenses/LICENSE-2.0 Apache Licence 2.0
 */

//The script is given the DB connection, DOCROOT and APPPATH variables before the
//actual CLI arguments so retrieve and remove them
$script = array_shift($_SERVER['argv']);
define( 'DB_CONN', array_shift($_SERVER['argv']));
define( 'DOCROOT', array_shift($_SERVER['argv']));
define( 'APPPATH', array_shift($_SERVER['argv']));
array_unshift($_SERVER['argv'], $script); unset($script);

require_once __DIR__.'/../classes/vendor/doctrine/Doctrine/Common/ClassLoader.php';

$classLoader = new \Doctrine\Common\ClassLoader('Doctrine', __DIR__.'/../classes/vendor/doctrine');
$classLoader->register();
$classLoader = new \Doctrine\Common\ClassLoader('Symfony', __DIR__.'/../classes/vendor/doctrine/Doctrine');
$classLoader->register();
$classLoader = new \Doctrine\Common\ClassLoader('models', rtrim(APPPATH, '/'));
$classLoader->register();

$config = new \Doctrine\ORM\Configuration();
$config->setMetadataCacheImpl(new \Doctrine\Common\Cache\ArrayCache);
//$driver = $config->newDefaultAnnotationDriver( APPPATH.'models' );
$schema_paths = array(APPPATH.'models/fixtures/schema');
foreach ( get_modules() as $module_path )
{
	$schema_path = $module_path . 'models/fixtures/schema';
	if ( is_dir($schema_path) )
		$schema_paths[] = $module_path . 'models/fixtures/schema';
}
$driver = new \Doctrine\ORM\Mapping\Driver\YamlDriver($schema_paths);
$driver->setFileExtension('.yml');
$config->setMetadataDriverImpl( $driver );

$config->setProxyDir( APPPATH.'models/proxies' );
$config->setProxyNamespace('models\proxies');

$conn = json_decode(get_db_info(DB_CONN));
$connectionOptions = array(
    'driver' 	=> 'pdo_'.$conn->type,
	'dbname' 	=> $conn->connection->database,
	'user' 		=> $conn->connection->username,
	'password' 	=> $conn->connection->password,
	'host' 		=> $conn->connection->hostname,
);

$em = \Doctrine\ORM\EntityManager::create($connectionOptions, $config);

$helpers = array(
    'db' => new \Doctrine\DBAL\Tools\Console\Helper\ConnectionHelper($em->getConnection()),
    'em' => new \Doctrine\ORM\Tools\Console\Helper\EntityManagerHelper($em)
);

//Run the Kohana CLI tool to access our doctrine controller which returns DB info
function get_db_info( $conn_name ) {
	ob_start();
		passthru("php ".DOCROOT."index.php --uri=doctrine/get_db_info/$conn_name", $output);
	return ob_get_clean();
}
//Run the Kohana CLI tool to access our doctrine controller which returns DB info
function get_modules() {
	ob_start();
		passthru("php ".DOCROOT."index.php --uri=doctrine/get_modules/", $output);
	return explode("\n", ob_get_clean());
}