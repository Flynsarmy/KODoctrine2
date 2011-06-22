<?php defined('SYSPATH') or die('No direct script access.');

class Controller_Doctrine extends Controller
{
	//Location of CLI tool from application folder
	private $_cli;

	public function before()
	{
		parent::before();

		//Restrict controller to localhost or script access
		if ( isset($_SERVER['REMOTE_ADDR']) && !in_array($_SERVER['REMOTE_ADDR'], array('::1', '127.0.0.1')) )
		{
			echo "DENIED!";
			exit;
		}

		$this->_cli = MODPATH.'doctrine2/bin/doctrine default "'.DOCROOT.'" "'.APPPATH.'" ';
	}

	function action_index()
	{
		echo View::factory('doctrine/doctrine');

		foreach ( $_POST as $action => $str )
			$this->_process_action( $action );
	}

	/*
	 * Called by the Kohana CLI tool (which in turn was called by Doctrine CLI tool)
	 * Returns specified DB info as a string
	 */
	public function action_db( $conn_name, $info_name )
	{
		$dbconfs = Kohana::config('database');

		if ( $info_name == 'type' )
			exit(@$dbconfs[ $conn_name ][ $info_name ]);
		else
			exit(@$dbconfs[ $conn_name ]['connection'][ $info_name ]);
	}

	/*
	 * Process a requestand perform any necessary actions
	 */
	private function _process_action( $action )
	{
		switch ( $action )
		{
			//Validate DB schema
			case 'validate':
				$this->_exececho(
					'Validation Errors:',
					$this->_cli . " orm:validate-schema"
				);
				break;

			//Generate entities, proxies and repositories
			case 'schema':
				$this->_exececho(
					'Generating entities',
					$this->_cli . " orm:generate-entities --generate-annotations=1 " . APPPATH
				);

				$this->_exececho(
					'<br/><br/>Generating proxies',
					$this->_cli . " orm:generate-proxies ".APPPATH."models/proxies --quiet"
				);

				$this->_exececho(
					'<br/><br/>Generating repositories',
					$this->_cli . " orm:generate-repositories ".APPPATH
				);
				break;

			//Show SQL for creating/updating DB
			case 'tables-sql':
				$this->_exececho(
					'<br/><br/>Determining DB modifications',
					$this->_cli . " orm:schema-tool:update --dump-sql"
				);
				break;

			//Create/update DB
			case 'tables':
				$this->_exececho(
					'<br/><br/>Creating/Updating DB',
					$this->_cli . " orm:schema-tool:update --force"
				);
				break;

			/* //Load data fixtures - NOT AVAILABLE IN D2
			case 'data':
				Doctrine_Manager::connection()->execute("
					SET FOREIGN_KEY_CHECKS = 0
				");

				Doctrine::loadData(APPPATH . DIRECTORY_SEPARATOR . 'doctrine/fixtures/data');
				echo "Done!";
				break;
			*/

			default:
				echo 'Invalid action: ' . $action;
				break;
		}
	}

	//Perform specified command using the doctrine 2 CLI
	private function _exececho( $title, $command )
	{
		echo '<strong>',$title,'</strong><br/>';
		echo $command;
		exec( $command, $output );
		foreach ( $output as $line )
			echo $line, '<br/>';
	}

} // End Welcome
