<?php
namespace LWS\Adminpanel\EditList;
if( !defined( 'ABSPATH' ) ) exit();
if( !class_exists("\\" . __NAMESPACE__ . "\\Source") ) :

class RowLimit
{
	/// the offset of the first row to return
	public $offset = 0;
	/// the number of row to return
	public $count = 10;
	/// @return a mysql sentence part to add to your sql query
	public function toMysql(){ return " LIMIT {$this->offset}, {$this->count}"; }
	public function valid(){ if($this->offset < 0){$this->count += $this->offset; $this->offset = 0;} return ($this->count>0); }
	public static function append($limit, $sql)
	{
		if(!is_null($limit) && is_a($limit, \get_class()) && $limit->valid() && is_string($sql))
			$sql .= $limit->toMysql();
		return $sql;
	}
}

/** can be returned by write to detail the action result */
class UpdateResult
{
	public $data; /// (array) the data array, as it should be updated in view
	public $success; /// (bool) success of operation
	public $message; /// (string) empty, error reason or success additionnal information to display.
	/** @return a success UpdateResult instance. */
	public static function ok($data, $message='')
	{
		$me = new self();
		$me->success = true;
		$me->data = is_array($data) ? $data : array();
		$me->message = is_string($message) ? $message : '';
		return $me;
	}
	/** @return an error UpdateResult instance. */
	public static function err($reason='')
	{
		$me = new self();
		$me->success = false;
		$me->data = null;
		$me->message = is_string($reason) ? trim($reason) : '';
		return $me;
	}
	/** @return (bool) is a UpdateResult instance. */
	public static function isA($instance)
	{
		return \is_a($instance, get_class());
	}
}

/** As post, display a list of item with on-the-fly edition. */
abstract class Source
{

	/** The edition inputs.
	 *	input[name] should refers to all $line array keys (use input[type='hidden'] for not editable elements).
	 * Readonly element can be displayed using <span data-name='...'></span> but this one will not be send
	 * back at validation, display is its only prupose (name can be the same as an hidden input if you want return)
	 *	@return a string with the form content. */
	abstract function input();

	/**	@return an array with the column which must be displayed in the list.
	 *	array ( $key => array($label [, $col_width]) )
	 * The width (eg. 10% or 45px) to apply to column is optionnal. */
	abstract function labels();

	/**	get the list content and return it as an array.
	 * @param $limit an instance of RowLimit class or null if deactivated (if EditList::setPageDisplay(false) called).
	 *	@return an array of line array. array( array( key => value ) ) */
	abstract function read($limit);

	/**	Save one edited line. If the index is not found, this function must create a new record.
	 * @param $row (array) the edited item to save.
	 * @return On success return the updated line, if failed, return false or a \WP_Error instance to add details. */
	abstract function write( $row );

	/**	Delete one edited line.
	 * @param $row (array) the item to remove.
	 * @return true if succeed. */
	abstract function erase( $row );

	/** this function to return the total number of record in source.
	 * @return the record count or -1 if not implemented or unavailable. */
	public function total()
	{
		return -1;
	}

	/** Override this function to specify default values (array) for a new edition form. */
	public function defaultValues()
	{
		return "";
	}

	/** @param $array the associative array to validate.
	 * @param $format an associative array with same keys as $array and value describing format:
	 ** if the format starts by a
	 ***	s : string,
	 *** i : number
	 *** a slash, it is assumed to be a regex (eg. look for a php string case insensitive "/php/i").
	 *** A : an array, you could append a / with array value format.
	 ** it can be followed by options:
	 *** 0 : equal or greater than zero,
	 *** + : not empty string or number greater than zero,
	 *** o : optional,
	 *** a : add empty or 0 if not exists in $array.
	 * @param $strictFormat (bool) all key in array must be in format.
	 * @param $strictArray (bool) all key in format must be in array.
	 * @param $translations (array) use same key as $format, if isset, replace the key in error string.
	 * @return false if ok, or a string with error if not. */
	public static function invalidArray(&$array, $format, $strictFormat=true, $strictArray=true, $translations=array())
	{
		foreach( $array as $k => $v )
		{
			if( isset($format[$k]) )
			{
				$err = self::invalidValue($format[$k], $v, $translations);
				if( $err !== false )
					return $err;
			}
			else if( $strictFormat )
			{
				return sprintf(_x("Unknown entry %s", "Input array validation", 'lws-adminpanel'), isset($translations[$k]) ? $translations[$k] : $k);
			}
		}

		foreach( $format as $k => $descr )
		{
			$f = substr($descr, 0, 1);
			$opt = explode(':', substr($descr, 1));

			if( !isset($array[$k]) && strpos($opt[0], 'o') === false )
			{
				if( strpos($opt[0], 'a') !== false )
				{
					if( count($opt) > 1 )
						$array[$k] = ($f == 'i' ? intval($opt[1]) : $opt[1]);
					else
						$array[$k] = ($f == 'i' ? 0 : '');
				}
				else if( $strictArray )
					return sprintf(_x("Missing entry %s", "Input array validation", 'lws-adminpanel'), isset($translations[$k]) ? $translations[$k] : $k);
			}
		}

		return false;
	}

	/**	Use by invalidArray.
	 *	@format (string) @see invalidArray */
	private static function invalidValue($format, $v, $translations=array())
	{
		$f = substr($format, 0, 1);
		$opt = explode(':', substr($format, 1))[0];
		if( substr($f, 0, 1) == 'A' )
		{
			if( !is_array($v) )
				return sprintf(_x("%s is not an array", "Input array validation", 'lws-adminpanel'), isset($translations[$k]) ? $translations[$k] : $k);
			else
			{
				$sub = explode('/', $f);
				if( strpos($sub[0], '+') !== false && empty($v) )
					return sprintf(_x("At least one %s must be set", "Input array validation", 'lws-adminpanel'), isset($translations[$k]) ? $translations[$k] : $k);

				if( count($sub) > 1 )
				{
					foreach( $v as $item )
						return self::invalidValue($sub[1], $item);
				}
			}
		}
		else if( $f == "/" )
		{
			if( !preg_match($format, $v) )
				return sprintf(_x("%s is not valid", "Input array validation", 'lws-adminpanel'), isset($translations[$k]) ? $translations[$k] : $k);
		}
		else if( $f == 'i' )
		{
			if( strlen(trim($v))==0 )
			{
				if( strpos($opt, 'o') === false && strpos($opt, 'a') === false )
					return sprintf(_x("%s needs a value", "Input array validation", 'lws-adminpanel'), isset($translations[$k]) ? $translations[$k] : $k);
			}
			else if( !is_numeric($v) )
				return sprintf(_x("%s is not a number", "Input array validation", 'lws-adminpanel'), isset($translations[$k]) ? $translations[$k] : $k);
			else if( strpos($opt, '+') !== false && $v <= 0 )
				return sprintf(_x("%s must be greater than zero", "Input array validation", 'lws-adminpanel'), isset($translations[$k]) ? $translations[$k] : $k);
			else if( strpos($opt, '0') !== false && $v < 0 )
				return sprintf(_x("%s must be equal or greater than zero", "Input array validation", 'lws-adminpanel'), isset($translations[$k]) ? $translations[$k] : $k);
		}
		else
		{
			if( !is_string($v) )
				return sprintf(_x("%s is not a string", "Input array validation", 'lws-adminpanel'), isset($translations[$k]) ? $translations[$k] : $k);
			else if( strpos($opt, '+') !== false && empty($v) )
				return sprintf(_x("%s is empty", "Input array validation", 'lws-adminpanel'), isset($translations[$k]) ? $translations[$k] : $k);
		}
		return false;
	}

}

endif
?>
