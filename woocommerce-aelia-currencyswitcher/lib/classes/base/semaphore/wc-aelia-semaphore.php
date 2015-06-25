<?php
namespace Aelia\CurrencySwitcher;
if(!defined('ABSPATH')) exit; // Exit if accessed directly

use Aelia\CurrencySwitcher\Logger as Logger;

/**
 * Semaphore Lock Management.
 * Adapted from WP Social under the GPL. Thanks to Alex King (https://github.com/crowdfavorite/wp-social).
 *
 */
class Semaphore {
	// @var bool Indicates if the lock was broken.
	protected $lock_broke = false;
	// @var string Stores the text domain for localisation.
	protected $text_domain;
	// @var bool Indicates if the lock was obtained
	protected $lock_obtained = false;

	const DEFAULT_SEMAPHORE_LOCK_WAIT = 300;
	const SEMAPHORE_ROWS = 3;

	/**
	 * Logs a message.
	 *
	 * @param string message The message to log.
	 * @param bool debug Indicates if the message is for debugging. Debug messages
	 * are not saved if the "debug mode" flag is turned off.
	 */
	protected function log($message, $debug = true) {
		Logger::log($message, $debug);
	}

	/**
	 * Class constructor.
	 *
	 * @param string lock_name The name to assign to the lock.
	 * @param int The amount of seconds after which a "locked lock" is considered
	 * stuck and should be forcibly unlocked.
	 */
	public function __construct($lock_name) {
		$this->text_domain = AELIA_CS_PLUGIN_TEXTDOMAIN;
		if(empty($lock_name)) {
			throw new \InvalidArgumentException('Invalid lock name specified for semaphore.',
																					$this->text_domain);
		}
		$this->lock_name = $lock_name;
	}

	/**
	 * Initializes the semaphore object.
	 *
	 * @static
	 * @return Semaphore
	 */
	public static function factory($lock_name) {
		$result = new self($lock_name);
	}

	public function initialize() {
		global $wpdb;
	}

	/**
	 * Attempts to start the lock. If the rename works, the lock is started.
	 *
	 * @return bool
	 */
	public function lock() {
		global $wpdb;

		$lock_available = $wpdb->get_var("SELECT IS_FREE_LOCK('" . $this->lock_name . "')");
		if($lock_available) {
			$this->lock_obtained = $wpdb->get_var("SELECT GET_LOCK('" . $this->lock_name . "', 0)");
		}

		if(!$lock_available || !$this->lock_obtained) {
			$this->log(sprintf(__('Semaphore lock "%s" failed (line %s).', $this->text_domain),
												 $this->lock_name,
												 __LINE__));
			return false;
		}

		$this->log(sprintf(__('Semaphore lock "%s" obtained at %s.', $this->text_domain),
											 $this->lock_name,
											 gmdate('Y-m-d H:i:s')));
		return true;
	}

	/**
	 * Unlocks the process.
	 *
	 * @return bool
	 */
	public function unlock() {
		global $wpdb;

		if(!$this->lock_obtained) {
			return true;
		}

		$lock_released = $wpdb->get_var("SELECT RELEASE_LOCK('" . $this->lock_name . "')");

		if($lock_released) {
			$this->log(sprintf(__('Semaphore "%s" unlocked.', $this->text_domain), $this->lock_name));
		}
		return $lock_released;
	}
}
