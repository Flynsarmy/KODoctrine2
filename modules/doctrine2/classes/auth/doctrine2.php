<?php defined('SYSPATH') or die('No direct access allowed.');
/**
 * Auth Doctrine2 driver.
 *
 * @package    Kohana3Doctrine2Auth
 * @author     Flynsarmy
 * @copyright  (c) 2010 Flynsarmy
 * @license    http://www.apache.org/licenses/LICENSE-2.0 Apache Licence 2.0
 * @version    0.01
 */
class Auth_Doctrine2 extends Auth
{
	//Which session driver to use
	public $session_driver = 'doctrine2';
	//Required entities. Change these as needed
	public $entities = array(
		'role' => 'models\Role',
		'token' => 'models\UserToken',
		'user' => 'models\User'
	);

	/**
	 * Loads Session and configuration options.
	 *
	 * @return  void
	 */
	public function __construct($config = array())
	{
		// Clean up the salt pattern and split it into an array
		$config['salt_pattern'] = preg_split('/,\s*/', Kohana::config('auth')->get('salt_pattern'));

		// Save the config in the object
		$this->_config = $config;

		//$this->_session = Session::instance( $this->session_driver );
		$this->_session = Session::instance();
	}

	/**
	 * Checks if a session is active.
	 *
	 * @param   mixed    role name string, role ORM object, or array with role names
	 * @return  boolean
	 */
	public function logged_in($role = NULL, $all_required = TRUE)
	{
		$status = FALSE;

		// Get the user from the session
		$User = $this->get_user();

		if (is_object($User) /*AND $User instanceof Model_User AND $User->loaded()*/)
		{
			// Everything is okay so far
			$status = TRUE;

			if ( !empty($role) )
			{
				// Multiple roles to check
				if (is_array($role))
				{
					// set initial status
					$status = (bool)$all_required;

					// Check each role
					foreach ($role as $_role)
					{
						if ( !is_object($_role) )
							$_role = Doctrine::em()->getRepository( $this->entities['role'] )
								->findOneByName( $_role );

						if ( $_role && !$User->getRoles()->contains($_role) || !$_role )
						{
							// Set the status false and get outta here
							$status = FALSE;
							if ( $all_required )
								break;
						}
						elseif ( !$all_required )
						{
							$status = TRUE;
							break;
						}
					}
				}
				// Single role to check
				else
				{
					// Load the role
					if ( !is_object($role) )
						$role = Doctrine::em()->getRepository( $this->entities['role'] )
							->findOneByName( $role );

					// Check that the user has the given role
					if ( $role )
						$status = $User->getRoles()->contains( $role );
				}
			}
		}

		return $status;
	}


	/**
	 * Logs a user in.
	 *
	 * @param   string   username
	 * @param   string   password
	 * @param   boolean  enable autologin
	 * @return  boolean
	 */
	protected function _login($user, $password, $remember)
	{
		if ( !is_object( $user ) )
			$user = Doctrine::em()->getRepository( $this->entities['user'] )->findOneBy(array(
				'username' => $user,
				'password' => $password,
			));

		if ( $user &&
		     $user->getRoles()->contains(Doctrine::em()->getRepository($this->entities['role'])->findOneByName('login')) )
		{
			if ( $remember )
			{
				/*
				 * Delete old tokens for user
				 * This is godawful - somebody fix it!
				 */
				//Clear the relations - this doesn't actually delete them from db
				$user->getTokens()->clear();

				//NOW delete them from DB
				Doctrine::em()
					->createQuery("
						DELETE FROM ".$this->entities['token']." t
						WHERE t.user_id=?1
					")
					->setParameter(1, $user->getId())
					->execute();

				// Create a new autologin token
				$token = new $this->entities['token']();
				$token = $this->update_token( $token, $user );
				//Add new token is in the DB
				Doctrine::em()->persist( $token );
				$user->addTokens( $token );

				// Set the autologin cookie
				Cookie::set('authautologin', $token->getToken(), $this->_config['lifetime']);
			}

			// Finish the login
			$this->complete_login( $user );

			return TRUE;
		}

		// Login failed
		return FALSE;
	}

	/**
	 * Forces a user to be logged in, without specifying a password.
	 *
	 * @param   mixed    username string, or user ORM object
	 * @return  boolean
	 */
	public function force_login($user)
	{
		if ( !is_object($user) )
			$user = Doctrine::em()->getRepository( $this->entities['user'] )->findOneByUsername( $user );

		// Mark the session as forced, to prevent users from changing account information
		$this->_session->set('auth_forced', TRUE);

		// Run the standard completion
		$this->complete_login( $user );
	}

	/**
	 * Logs a user in, based on the authautologin cookie.
	 *
	 * @return  mixed
	 */
	public function auto_login()
	{
		if ($Token = Cookie::get('authautologin'))
		{
			// Load the token and user
			$Token = Doctrine::em()->getRepository( $this->entities['token'] )->findOneByToken( $Token );

			//if ($Token->loaded() AND $Token->user->loaded())
			if ( $Token )
			{
				if ($Token->getUserAgent() === sha1(Request::$user_agent))
				{
					// Save the token to create a new unique token
					$Token = $this->update_token( $Token, $Token->getUser() );

					// Set the new token
					Cookie::set('authautologin', $Token->getToken(), $Token->getExpires() - time());

					// Complete the login with the found data
					$this->complete_login($Token->getUser());

					// Automatic login was successful
					return $Token->getUser();
				}

				// Token is invalid
				Doctrine::em()->remove( $Token );
				Doctrine::em()->flush();
			}
		}

		return FALSE;
	}

	/**
	 * Gets the currently logged in user from the session (with auto_login check).
	 * Returns FALSE if no user is currently logged in.
	 *
	 * @return  mixed
	 */
	public function get_user()
	{
		$user = parent::get_user();

		if ( $user !== FALSE && !is_object( $user ) )
			$user = Doctrine::em()->getRepository( $this->entities['user'] )->findOneById( $user );

		// check for "remembered" login
		if ( !$user )
			$user = $this->auto_login();

		return $user;
	}

	/**
	 * Log a user out and remove any autologin cookies.
	 *
	 * @param   boolean  completely destroy the session
	 * @param	boolean  remove all tokens for user
	 * @return  boolean
	 */
	public function logout($destroy = FALSE, $logout_all = FALSE)
	{
		// Set by force_login()
		$this->_session->destroy('auth_forced');

		if ($token = Cookie::get('authautologin'))
		{
			// Delete the autologin cookie to prevent re-login
			Cookie::delete('authautologin');

			// Clear the autologin token from the database
			Doctrine::em()
				->createQuery("
					DELETE FROM ".$this->entities['token']." t
					WHERE t.token=?1
				")
				->setParameter(1, $token)
				->execute();
		}

		return parent::logout($destroy);
	}

	/**
	 * Get the stored password for a username.
	 *
	 * @param   mixed   username string, or user ORM object
	 * @return  string
	 */
	public function password($user)
	{
		if ( !is_object($user) )
			$user = Doctrine::em()->getRepository( $this->entities['user'] )->findOneByUsername( $user );

		return $user ? $user->getPassword() : '';
	}

	public function update_token( $Token, $User )
	{
		// Set token data
		$Token->setUser( $User );
		$Token->setCreated( time() );
		$Token->setExpires( time() + $this->_config['lifetime'] );
		$Token->setUserAgent( sha1(Request::$user_agent) );
		while ( true )
		{
			$unique_tok = Text::random('alnum', 32);
			if ( !Doctrine::em()->getRepository( $this->entities['token'] )->findOneByToken( $unique_tok ) )
				break;
		}
		$Token->setToken( $unique_tok );

		return $Token;
	}

	/**
	 * Complete the login for a user by incrementing the logins and setting
	 * session data: user_id, username, roles.
	 *
	 * @param   object  user ORM object
	 * @return  void
	 */
	protected function complete_login($user)
	{
		//Sync with DB
		Doctrine::em()->flush();

		$result = parent::complete_login($user->getId());

		return $result;
	}

	/**
	 * Compare password with original (hashed). Works for current (logged in) user
	 *
	 * @param   string  $password
	 * @return  boolean
	 */
	public function check_password($password)
	{
		$User = $this->get_user();

		// nothing to compare
		if ($User === FALSE)
			return FALSE;

		$hash = $this->hash_password(
			$password,
			$this->find_salt( $User->getPassword() )
		);

		return $hash == $User->getPassword();
	}
} // End Auth ORM