<?php
/**
*
* @ This file is created by http://DeZender.Net
* @ deZender (PHP7 Decoder for ionCube Encoder)
*
* @ Version			:	5.0.1.0
* @ Author			:	DeZender
* @ Release on		:	22.04.2022
* @ Official site	:	http://DeZender.Net
*
*/

/**
 * 	This class handles all the data you can get from a TVShowRole
 *
 * 	@author Alvaro Octal | <a href="https://twitter.com/Alvaro_Octal">Twitter</a>
 * 	@version 0.1
 * 	@date 11/01/2015
 * 	@link https://github.com/Alvaroctal/TMDB-PHP-API
 * 	@copyright Licensed under BSD (http://www.opensource.org/licenses/bsd-license.php)
 */
class TVShowRole extends Role
{
	private $_data = null;

	/**
     * 	Construct Class
     *
     * 	@param array $data An array with the data of a TVShowRole
     */
	public function __construct($data, $idPerson)
	{
		$this->_data = $data;
		parent::__construct($data, $idPerson);
	}

	/** 
     *  Get the TVShow's title of the role
     *
     *  @return string
     */
	public function getTVShowName()
	{
		return $this->_data['name'];
	}

	/** 
     *  Get the TVShow's id
     *
     *  @return int
     */
	public function getTVShowID()
	{
		return $this->_data['id'];
	}

	/** 
     *  Get the TVShow's original title of the role
     *
     *  @return string
     */
	public function getTVShowOriginalTitle()
	{
		return $this->_data['original_name'];
	}

	/** 
     *  Get the TVShow's release date of the role
     *
     *  @return string
     */
	public function getTVShowFirstAirDate()
	{
		return $this->_data['first_air_date'];
	}

	/**
     *  Get the JSON representation of the Episode
     *
     *  @return string
     */
	public function getJSON()
	{
		return json_encode($this->_data, JSON_PRETTY_PRINT);
	}
}

?>