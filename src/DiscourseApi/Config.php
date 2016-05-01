<?php

namespace DiscourseApi;

class Config {
	const USERAGENT = 'https://github.com/nnrd/discourseapi.git - DiscourseApi';
	const TIMEOUT = 5;

	private $config;

	public function __construct( $array_data )
	{
		$this->config = self::validate( $array_data );
	}

	public function __get( $key )
	{
		return $this->config[$key];
	}

	public static function buildFromJSON( $file )
	{
		return new Config( json_decode( file_get_contents( $file ), true ) );
	}

	private static function validate( $array_data )
	{
		if ( !is_array( $array_data ) ) {
			throw new \Exception( "Bad JSON data" );
		}

		if ( !array_key_exists( 'base_url', $array_data ) || empty( $array_data['base_url'] ) ) {
			throw new \Exception('A valid base URL is required. Example: https://test.discourse.local');
		}

		if ( !array_key_exists( 'username', $array_data ) || empty( $array_data['username'] ) ) {
			throw new \Exception('A valid username is required and must match the API key owner.');
		}

		if ( !array_key_exists( 'key', $array_data ) || empty( $array_data['key'] ) ) {
			throw new \Exception('A valid key is required and must match the API user.');
		}

		if ( !array_key_exists( 'passthrough', $array_data ) || empty( $array_data['passthrough'] ) ) {
			$array_data['passthrough'] = false;
		} else {
			$array_data['passthrough'] = true;
			if ( !array_key_exists( 'xff', $array_data ) || empty( $array_data['xff'] ) ) {
				$array_data['xff'] = $_SERVER['REMOTE_ADDR'];
			}
		}

		if ( array_key_exists( 'timeout', $array_data ) || !empty( $array_data['timeout'] ) ) {
			$array_data['timeout'] = (double) $array_data['timeout'];
		} else {
			$array_data['timeout'] = Config::TIMEOUT;
		}

		if ( !array_key_exists( 'useragent', $array_data ) || empty( $array_data['useragent'] ) ) {
			$array_data['useragent'] = Config::USERAGENT;
		}

		return $array_data;
	}
}
