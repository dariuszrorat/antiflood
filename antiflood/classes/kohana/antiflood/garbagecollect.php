<?php defined('SYSPATH') or die('No direct script access.');
/**
 * Garbage Collection interface for antiflood that have no GC methods
 * of their own.
 *
 * @package    Kohana/Antiflood
 * @category   Security
 * @version    1.0
 * @author     Dariusz Rorat
 * @copyright  (c) 2015 Dariusz Rorat
 * @license    GPL 2.0
 */
interface Kohana_Antiflood_GarbageCollect {
	/**
	 * Garbage collection method that cleans any expired
	 * antiflood entries from the file or database.
	 *
	 * @return void
	 */
	public function garbage_collect();
}
