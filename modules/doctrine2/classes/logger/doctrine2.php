<?php defined('SYSPATH') or die('No direct access allowed.');
/**
 * A SQL logger that logs to SQLLog.php in the Kohana logs directory
 *
 * @package    Kohana3Doctrine2
 * @author     Flynsarmy
 * @copyright  (c) 2011 Flynsarmy
 * @license    http://www.apache.org/licenses/LICENSE-2.0 Apache Licence 2.0
 * @version    0.01
 */
class Logger_Doctrine2 implements Doctrine\DBAL\Logging\SQLLogger
{
	public $fp_log;
	public $query_count = 0;

	public function __construct()
	{
		$this->fp_log = fopen( APPPATH.'logs/SQLLog.php', 'w' );
		fprintf($this->fp_log, "<?php defined('SYSPATH') or die('No direct script access.'); ?>".PHP_EOL.PHP_EOL);
		fprintf($this->fp_log, "SQL queries executed for %s".PHP_EOL.PHP_EOL, $this->get_url());
	}

	/**
     * {@inheritdoc}
     */
    public function startQuery($sql, array $params = null, array $types = null)
    {
		//Update query count
		$this->query_count++;

		//Print SQL followed by params and types
		$output = $sql . PHP_EOL;
		if ( $params )
		{
			$output .= 'Params:' . PHP_EOL;
			foreach ( $params as $key=>$var )
			{
				if ( is_object($var) )
					$var = $this->object_to_string( $var );
				elseif ( is_bool($var) )
					$var = $var ? '<bool> true' : '<bool> false';

				$output .= "$key : $var" . PHP_EOL;
			}
		}
		if ( $types )
		{
			$output .= 'Types:' . PHP_EOL;
			foreach ( $types as $key=>$var )
			{
				if ( is_object($var) )
					$var = $this->object_to_string( $var );
				elseif ( is_bool($var) )
					$var = $var ? 'true' : 'false';

				$output .= "$key : $var" . PHP_EOL;
			}
		}

		//Add a bit of spacing if we had types - keep things clean
		if ( $params || $types )
			$output .= PHP_EOL;

		fprintf($this->fp_log, "%s", $output);
    }

    /**
     * {@inheritdoc}
     */
    public function stopQuery()
    {

    }

	public function object_to_string( $Object )
	{
		$class = get_class( $Object );
		$str = '<'.$class.' object>';

		//Add extra info if we're able to
		switch ( get_class($Object) )
		{
			case 'DateTime': $str .= ': ' . $Object->format('c');
		}

		return $str;
	}

	public function get_url()
	{
		// Filter php_self to avoid a security vulnerability.
		$php_request_uri =
			htmlentities(
				substr($_SERVER['REQUEST_URI'], 0, strcspn($_SERVER['REQUEST_URI'], "\n\r")),
				ENT_QUOTES
			);

		if (isset($_SERVER['HTTPS']) && strtolower($_SERVER['HTTPS']) == 'on')
			$protocol = 'https://';
		else
			$protocol = 'http://';

		$host = $_SERVER['HTTP_HOST'];

		if (isset($_SERVER['HTTP_PORT']) && $_SERVER['HTTP_PORT'] != '' && (($protocol == 'http://' && $_SERVER['HTTP_PORT'] != '80') || ($protocol == 'https://' && $_SERVER['HTTP_PORT'] != '443')))
			$port = ':' . $_SERVER['HTTP_PORT'];
		else
			$port = '';

		return $protocol . $host . $port . $php_request_uri;
	}

	public function __destruct()
	{
		//Print query count
		fprintf($this->fp_log, PHP_EOL."Queries executed: %d", $this->query_count);

		fclose( $this->fp_log );
	}
}