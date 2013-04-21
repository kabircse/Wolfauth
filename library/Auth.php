<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class Auth {

	protected $CI;
	protected $_permissions;

	public function __construct()
	{
		$this->CI = &get_instance();

		$this->CI->config->load('auth');

		$this->CI->load->database();
		$this->CI->load->library('session');
		$this->CI->load->library('encrypt');

		$this->CI->load->model('user_model');
		$this->CI->load->model('permission_model');

		$this->CI->load->helper('cookie');
		$this->CI->load->helper('auth');

		// Check if we remember this user
		$this->do_you_remember_me();

		if ($this->is_logged())
		{
			$this->_permissions = $this->permission_model->get_role_permissions($this->role_id());
		}
	}


	/**
	 * Is Logged
	 *
	 * This function will return TRUE or FALSE if a user is logged in
	 * and has a valid session and user_id.
	 *
	 */
	public function is_logged()
	{
		return ($this->CI->session->userdata('user_id') !== FALSE) ? TRUE : FALSE;
	}


	/**
	 * User ID
	 *
	 * Return the ID of the currently logged in user (if a user is logged in)
	 *
	 */
	public function user_id()
	{
		return $this->CI->session->userdata('user_id');
	}


	/**
	 * Role ID
	 *
	 * Return the role ID of the current user (if a user is logged in)
	 *
	 */
	public function role_id()
	{
		return $this->CI->session->userdata('role_id');
	}


	/**
	 * Is Role
	 *
	 * Does the currently logged in user have this role?
	 *
	 */
	public function is_role($role)
	{
		$role_name = $this->CI->session->userdata('role_name');

		return ($role_name == $role) ? TRUE : FALSE;
	}


	/**
	 * User Can
	 *
	 * Checks if a user has been assigned a particular permission
	 *
	 * @return bool 
	 *
	 */
	public function user_can($permission)
	{
		return (in_array($permission, $this->_permissions) !== FALSE) ? TRUE : FALSE;
	}


	/**
	 * Login
	 *
	 * Doesn't get any more simple than this, logs a user in.
	 *
	 * @param $email string - The email address of the user logging
	 * @param $password string - The password of the user logging in
	 *
	 */
	public function login($email, $password)
	{
		$user = $this->CI->user_model->get($email);

		if ($user)
		{
			if ($this->CI->user_model->hash_password($password) == $user->password)
			{
				$role_name	= $user->role_name;
				$user_id	= $user->id;

				$this->CI->session->set_userdata(array(
					'user_id'	=> $user_id,
					'role_id'   => $user->role_id,
					'role_name'	=> $group_id,
					'email'		=> $user->email
				));

				// Remember me?
				if ($this->CI->input->post('remember_me') == 'yes')
				{
					$this->set_remember_me($user_id);
				}

				return $user_id;
			}
		}

		return FALSE;
	}


	/**
	 * Logout
	 *
	 * Nothing fancy here, logs a user out and destroys any trace of
	 * the user ever being here.
	 *
	 */
	public function logout()
	{
		$user_id = $this->CI->session->userdata('user_id');

		$this->CI->session->sess_destroy();
		delete_cookie('rememberme');

		$user_data = array(
			'id' => $this->CI->session->userdata('user_id'),
			'remember_me' => ''
		);

		$this->CI->user_model->update($user_data);
	}


	/**
	 * Send Password Reset
	 *
	 * Send a password email to a user.
	 *
	 * @param string $email - The email address we're sending the reset to
	 *
	 */
	public function send_password_reset($email, $variables = array())
	{
		// Load our password reset email HTML code
		$html = $this->CI->load->view('auth/email/password_reset.php', $variables, TRUE);

		// We have HTML for our email
		if ($html)
		{

		}
	}


	/**
	 * Set Remember Me
	 *
	 * Sets a remember me cookie, updates a row in the user table
	 *
	 * @access private
	 * @param $user_id int - The user ID we're remmebering
	 *
	 */
	private function set_remember_me($user_id)
	{
		$token = md5(uniqid(rand(), TRUE));
		$timeout = 60 * 60 * 24 * 7; // One week

		$remember_me = $this->CI->encrypt->encode($user_id.':'.$token.':'.(time() + $timeout));

		// Set the cookie and database
		$cookie = array(
			'name'		=> 'rememberme',
			'value'		=> $remember_me,
			'expire'	=> $timeout
		);

		set_cookie($cookie);
		$this->CI->user_model->update(array('id' => $user_id, 'remember_me' => $remember_me));
	}


	/**
	 * Do You Remember me?
	 *
	 * Checks for the existence of remembered cookie data in cookies and database
	 *
	 */
	public function do_you_remember_me()
	{
		if( $cookie_data = get_cookie('rememberme') )
		{
			$user_id = '';
			$token   = '';
			$timeout = '';

			$cookie_data = $this->CI->encrypt->decode($cookie_data);
			
			if (strpos($cookie_data, ':') !== FALSE)
			{
				$cookie_data = explode(':', $cookie_data);
				
				if (count($cookie_data) == 3)
				{
					list($user_id, $token, $timeout) = $cookie_data;
				}
			}

			if ( (int) $timeout < time() )
			{
				return FALSE;
			}

			if ( $data = $this->CI->user_model->get_user_by_id($user_id) )
			{
				// Fill the session and renew the remember me cookie
				$this->CI->session->set_userdata(array(
					'user_id'	=> $user_id,
					'role_name'	=> $data->role_name,
					'role_id'   => $data->role_id,
					'email'     => $data->email
				));

				$this->set_remember_me($user_id);

				return TRUE;
			}

			delete_cookie('rememberme');
		}

		return FALSE;
	}

	/**
	 * Send Email
	 *
	 * A private email utility class for sending email easily without
	 * having to configure any settings. Supply the to/from address
	 * the subject and body HTML.
	 *
	 * @access private
	 * @param $to string - Who is the email being sent to
	 * @param $subject string - What is the subject of the email
	 * @param $body - The contents of the email
	 * @param $from - Who is the email being sent from?
	 *
	 */
	private function _send_email($to, $subject, $body, $from)
	{

	}


}