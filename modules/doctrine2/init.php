<?php
	/*
	 * TODO:
	 * + Add support for table prefix
	 */

	use Doctrine\ORM\EntityManager,
		Doctrine\ORM\Configuration;

	//Register models for autoload
	spl_autoload_register(array('Doctrine', 'auto_load'));

	Route::set('doctrine2', 'doctrine(/<action>(/<arg1>(/<arg2>)))')
		->defaults(array(
			'controller' => 'doctrine',
			'action'     => 'index',
		));

	/**
	 *
	 *	@author Flynsarmy <www.flynsarmy.com>
	 *  @license http://www.apache.org/licenses/LICENSE-2.0 Apache Licence 2.0
	 */
	class Doctrine
	{
		private static $_instance = null;
		private $_application_mode = 'development';
		private $_em = array();

		/*
		 * Rename $conn_name to whatever you want your default DB connection to be
		 */
		public static function em( $conn_name = 'default' )
		{
			if ( self::$_instance === null )
				self::$_instance = new Doctrine();

			return isset(self::$_instance->em[ $conn_name ])
				? self::$_instance->em[ $conn_name ]
				: reset(self::$_instance->_em);
		}

		public function __construct()
		{
			//http://mackstar.com/blog/2010/07/29/doctrine-2-and-why-you-should-use-it
			require __DIR__.'/classes/vendor/doctrine/Doctrine/Common/ClassLoader.php';

			$classLoader = new \Doctrine\Common\ClassLoader('Doctrine', __DIR__.'/classes/vendor/doctrine');
			$classLoader->register();
			//This allows Doctrine-CLI tool & YAML mapping driver
			$classLoader = new \Doctrine\Common\ClassLoader('Symfony', __DIR__.'/classes/vendor/doctrine/Doctrine');
			$classLoader->register();
			//Load entities
			$classLoader = new \Doctrine\Common\ClassLoader('models', rtrim(APPPATH, '/'));
			$classLoader->register();

			//Set up caching method
			$cache = $this->_application_mode == 'development'
				? new \Doctrine\Common\Cache\ArrayCache
				: new \Doctrine\Common\Cache\ApcCache;

			$config = new Configuration;
			$config->setMetadataCacheImpl( $cache );
			$driver = $config->newDefaultAnnotationDriver( APPPATH.'doctrine/Entities' );
			$config->setMetadataDriverImpl( $driver );
			$config->setQueryCacheImpl( $cache );

			$config->setProxyDir( APPPATH.'models/proxies' );
			$config->setProxyNamespace('proxies');
			$config->setAutoGenerateProxyClasses( $this->_application_mode == 'development' );

			// Set up logger
			$config->setSqlLogger( new Logger_Doctrine2 );

			$dbconfs = Kohana::config('database');
			foreach ( $dbconfs as $conn_name => $dbconf )
			{
				//PDO doesn't have hostname and database in the connection array. Extract from DSN string
				if ( $dbconf['type'] == 'pdo' )
				{
					preg_match('/host=([^;]+)/', $dbconf['connection']['dsn'], $dbconf['connection']['hostname']);
					preg_match('/dbname=([^;]+)/', $dbconf['connection']['dsn'], $dbconf['connection']['database']);
					$dbconf['connection']['hostname'] = $dbconf['connection']['hostname'][1];
					$dbconf['connection']['database'] = $dbconf['connection']['database'][1];
				}

				$this->_em[ $conn_name ] = EntityManager::create(array(
					'dbname' 	=> $dbconf['connection']['database'],
					'user' 		=> $dbconf['connection']['username'],
					'password' 	=> $dbconf['connection']['password'],
					'host' 		=> $dbconf['connection']['hostname'],
					'driver' 	=> 'pdo_mysql',
				), $config);
			}
		}

		/*
		 * Loads model classes
		 * Accepts: string $class (models\<whatever>)
		 * Returns: bool True if found, else False
		 */
		public static function auto_load( $class )
		{
			if ( ($pos=strrpos($class, '\\')) !== false && substr($class, 0, $pos) == 'models' )
			{
				$file = substr($class, $pos+1);
				$path = str_replace('\\', '/', substr($class, 0, $pos));

				if ($path = Kohana::find_file($path, $file))
				{
					// Load the class file
					require $path;

					// Class has been found
					return TRUE;
				}
			}

			// Class is not in the filesystem
			return FALSE;
		}
	}
