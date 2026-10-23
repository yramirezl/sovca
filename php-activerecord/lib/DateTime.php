<?php
/**
 * @package ActiveRecord
 */
namespace ActiveRecord;

/**
 * An extension of PHP's DateTime class to provide dirty flagging and easier formatting options.
 * ... (documentación original omitida aquí por brevedad) ...
 *
 * @package ActiveRecord
 * @see http://php.net/manual/en/class.datetime.php
 */
class DateTime extends \DateTime implements DateTimeInterface
{
	/**
	 * Default format used for format() and __toString()
	 */
	public static $DEFAULT_FORMAT = 'rfc2822';

	/**
	 * Pre-defined format strings.
	 */
	public static $FORMATS = array(
		'db'      => 'Y-m-d H:i:s',
		'number'  => 'YmdHis',
		'time'    => 'H:i',
		'short'   => 'd M H:i',
		'long'    => 'F d, Y H:i',
		'atom'    => \DateTime::ATOM,
		'cookie'  => \DateTime::COOKIE,
		'iso8601' => \DateTime::ISO8601,
		'rfc822'  => \DateTime::RFC822,
		'rfc850'  => \DateTime::RFC850,
		'rfc1036' => \DateTime::RFC1036,
		'rfc1123' => \DateTime::RFC1123,
		'rfc2822' => \DateTime::RFC2822,
		'rfc3339' => \DateTime::RFC3339,
		'rss'     => \DateTime::RSS,
		'w3c'     => \DateTime::W3C);

	private $model;
	private $attribute_name;

	public function attribute_of($model, $attribute_name)
	{
		$this->model = $model;
		$this->attribute_name = $attribute_name;
	}

	/**
	 * Formats the DateTime to the specified format.
	 *
	 * @see FORMATS
	 * @see get_format
	 * @param string $format A format string accepted by get_format()
	 * @return string formatted date and time string
	 */
	#[\ReturnTypeWillChange]
	public function format($format=null)
	{
		return parent::format(self::get_format($format));
	}

	/**
	 * Returns the format string.
	 *
	 * @see FORMATS
	 * @param string $format A pre-defined string format or a raw format string
	 * @return string a format string
	 */
	public static function get_format($format=null)
	{
		// use default format if no format specified
		if (!$format)
			$format = self::$DEFAULT_FORMAT;

		// format is a friendly
		if (array_key_exists($format, self::$FORMATS))
			 return self::$FORMATS[$format];

		// raw format
		return $format;
	}

	/**
	 * This needs to be overriden so it returns an instance of this class instead of PHP's \DateTime.
	 * See http://php.net/manual/en/datetime.createfromformat.php
	 */
	#[\ReturnTypeWillChange]
	public static function createFromFormat($format, $time, $tz = null)
	{
		$phpDate = $tz ? parent::createFromFormat($format, $time, $tz) : parent::createFromFormat($format, $time);
		if (!$phpDate)
			return false;
		// convert to this class using the timestamp
		// (PHP 8.1+: pasar null a \DateTime::__construct() está obsoleto)
		$ourDate = new static('now', $phpDate->getTimezone());
		$ourDate->setTimestamp($phpDate->getTimestamp());
		return $ourDate;
	}

	public function __toString()
	{
		return $this->format();
	}

	/**
	 * Handle PHP object `clone`.
	 *
	 * @return void
	 */
	public function __clone()
	{
		$this->model = null;
		$this->attribute_name = null;
	}

	private function flag_dirty()
	{
		if ($this->model)
			$this->model->flag_dirty($this->attribute_name);
	}

	#[\ReturnTypeWillChange]
	public function setDate($year, $month, $day)
	{
		$this->flag_dirty();
		return parent::setDate($year, $month, $day);
	}

	#[\ReturnTypeWillChange]
	public function setISODate($year, $week , $day = 1)
	{
		$this->flag_dirty();
		return parent::setISODate($year, $week, $day);
	}

	#[\ReturnTypeWillChange]
	public function setTime($hour, $minute, $second = 0, $microseconds = 0)
	{
		$this->flag_dirty();
		return parent::setTime($hour, $minute, $second, $microseconds);
	}

	#[\ReturnTypeWillChange]
	public function setTimestamp($unixtimestamp)
	{
		$this->flag_dirty();
		return parent::setTimestamp($unixtimestamp);
	}

	#[\ReturnTypeWillChange]
	public function setTimezone($timezone)
	{
		$this->flag_dirty();
		return parent::setTimezone($timezone);
	}

	#[\ReturnTypeWillChange]
	public function modify($modify)
	{
		$this->flag_dirty();
		return parent::modify($modify);
	}

	#[\ReturnTypeWillChange]
	public function add($interval)
	{
		$this->flag_dirty();
		return parent::add($interval);
	}

	#[\ReturnTypeWillChange]
	public function sub($interval)
	{
		$this->flag_dirty();
		return parent::sub($interval);
	}
}