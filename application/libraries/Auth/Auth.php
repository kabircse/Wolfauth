<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

/**
 * WolfAuth
 *
 * An open source driver based authentication library for Codeigniter
 *
 * @package   WolfAuth
 * @author    Dwayne Charrington
 * @copyright Copyright (c) 2012 Dwayne Charrington.
 * @link      http://ilikekillnerds.com
 * @license   http://www.apache.org/licenses/LICENSE-2.0.html
 * @version   2.0
 */
 
class Auth extends CI_Driver_Library {
	
	// Codeigniter instance
	public $CI;
	
	// An array of configuration values from config/wolfauth.php
	public $_config = array();
	
	// The currently in use driver
	public $_driver;
	
	public function __construct()
	{
		$this->CI =& get_instance();
		
		// Load needed helpers
		$this->CI->load->helper('form');
		$this->CI->load->helper('url');
		
		// Load the auth config file
		$this->CI->config->load('auth');
		
		// Get and store Wolfauth configuration values
		$this->_config = config_item('wolfauth');
		
		// Set the drivers defined in the auth config file
		$this->valid_drivers = $this->_config['allowed_drivers'];
		
		// Set the default driver
		$this->_driver = $this->_config['default_driver'];
	}

    /**
     * Sets a driver to use
     *
     * @param $name
     */
	public function set_driver($name)
	{
		// Set the driver name, trim whitespace
		$this->_driver = trim($name);
	}
	
	/**
	 * Base function for logging in a user
	 *
	 * @param $identity
	 * @param $password
	 * @param (bool) $remember
	 * @return int
	 */
	public function login($identity, $password, $remember = false)
	{
		// Call the child driver login method
		return $this->{$this->_driver}->login($identity, $password, $remember);
	}

	/**
	 * Force login for a particular username or email
	 *
	 * @param $identity (username or email)
	 * @return bool
	 *
	 */
	public function force_login($identity)
	{
		return $this->{$this->_driver}->force_login();
	}
	
    /**
     * Logs a user out
     *
     * @return bool
     */
	public function logout()
	{
		return $this->{$this->_driver}->logout();
	}
	
	/**
	 * Is a user currently logged in
	 *
	 * @return bool
	 *
	 */
	public function logged_in()
	{
		return $this->{$this->_driver}->logged_in();
	}

    /**
     * Get the currently logged in user info
     *
     * @return mixed
     */
    public function get_user()
    {
        return $this->{$this->_driver}->get_user();
    }

    /**
     * Register a user account
     *
     * @param $fields
     * @return mixed
     */
	public function register($fields)
	{
		// Call the child register function
		return $this->{$this->_driver}->register($fields);
	}

    /**
     * Utility function for sending emails
     * 
     * @param mixed $to
     * @param mixed $from_email
     * @param mixed $from_name
     * @param mixed $subject
     * @param mixed $message
     * @param mixed $template
     * @param array $data
     */
    public function _send_email($to, $from_email, $from_name, $subject, $message = '', $template = '', $data = array())
    {        
        // If we have an email template
        if ($template !== '')
        {
        	$message = $this->CI->load->view($template, $data, true);
        }
        
        $this->CI->email->clear();
        $this->CI->email->set_newline("\r\n");
        $this->CI->email->from($from_email, $from_name);
        $this->CI->email->to($to);
        $this->CI->email->subject($subject);
        $this->CI->email->message($message);
        
        if ( $this->CI->email->send() )
        {
            $this->set_message('Email was successfully sent');
            return TRUE;
        }
        else
        {
            $this->set_error('There was a problem whilst trying to send email');
            return FALSE;
        }  
    }
	
	/**
	 * Redirect all method calls not in this class to the child class
	 * set in the variable _adapter which is the default class.
	 *
	 * @param mixed $child
	 * @param mixed $arguments
	 * @return mixed
	 */
    public function __call($child, $arguments)
    {
        return call_user_func_array(array($this->{$this->_driver}, $child), $arguments);
    }
	
    /**
     * Static function wrapper for auth drivers
     *
     * @return object
     * 
     */
    public static function auth_instance()
    {
        static $ci;
        $ci = get_instance();
        
        return $ci->auth;
    }

}

/**
 * A pointer to the auth class for easier use, mimicks Codeigniter's
 * get_instance() function which effectively does the same thing
 *
 * @return object
 *
 */
function auth_instance()
{
    return Auth::auth_instance();
}