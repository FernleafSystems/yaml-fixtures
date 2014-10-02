<?php

namespace Fernleaf\Testing\DataSet;

/**
 * Class YamlLoader
 * @package Fernleaf\Testing\DataSet
 */
class YamlLoader {

	/**
	 * @var \PHPUnit_Extensions_Database_DataSet_YamlDataSet
	 */
	protected $oDataSet;

	/**
	 * @var array
	 */
	protected $aSourcePaths = array();

	/**
	 * @var callable[]
	 */
	protected $aFilters = array();

	/**
	 * @var array
	 */
	protected $aReplacements = array();

	/**
	 *
	 */
	public function __construct() {

	}

	/**
	 * @return \PHPUnit_Extensions_Database_DataSet_YamlDataSet
	 * @return \PHPUnit_Extensions_Database_DataSet_DefaultDataSet
	 */
	public function getDataSet() {
		if ( empty( $this->oDataSet ) ) {
			return new \PHPUnit_Extensions_Database_DataSet_DefaultDataSet();
		}
		return $this->oDataSet;
	}

	/**
	 * @throws \Exception
	 * @param callable $cFilter
	 * @return $this
	 */
	public function addFilter( callable $cFilter ) {
		if ( !is_callable( $cFilter ) ) {
			throw new \Exception( 'Invalid replacement filter provided, it should be callable.' );
		}
		$this->aFilters[] = $cFilter;
		return $this;
	}

	/**
	 * @param array $aFilters
	 * @return $this
	 */
	public function addFilters( $aFilters ) {
		foreach ( $aFilters as $cFilter ) {
			$this->addFilter( $cFilter );
		}
		return $this;
	}

	/**
	 * @param string $sFind
	 * @param string $sReplace
	 * @return $this
	 */
	public function addReplacement( $sFind, $sReplace ) {
		$this->aReplacements[] = array( $sFind, $sReplace );
		return $this;
	}

	/**
	 * @throws \Exception
	 * @param array $aReplacements
	 * @return $this
	 */
	public function addReplacements( $aReplacements ) {
		foreach ( $aReplacements as $aReplacement ) {
			$this->addReplacement( $aReplacement[0], $aReplacement[1] );
		}
		return $this;
	}

	/**
	 * @throws \Exception
	 * @param string $sPath
	 * @return $this
	 */
	public function addSourcePath( $sPath ) {
		if ( !is_dir( $sPath ) ) {
			throw new \Exception( sprintf( 'Source path "%s" is not a valid directory', $sPath ) );
		}
		$this->aSourcePaths[] = $sPath;
		return $this;
	}

	/**
	 * @throws \Exception
	 * @param array $aPaths
	 * @return $this
	 */
	public function addSourcePaths( $aPaths ) {
		foreach ( $aPaths as $sPath ) {
			$this->addSourcePath( $sPath );
		}
		return $this;
	}

	/**
	 * @throws \Exception
	 * @param array $aFixture
	 * @return $this
	 */
	public function addFixture( $aFixture ) {
		if ( empty( $this->aSourcePaths ) ) {
			throw new \Exception( 'A source path must be specified before adding a fixture.' );
		}

		if ( substr( $aFixture['file'], -4 ) == '.yml' ) {
			throw new \Exception( 'Incorrect use, please specify the fixtures without the .yml extension ' );
		}

		$sAbsFixture = false;
		foreach ( $this->aSourcePaths as $sPath ) {
			if ( is_file( $sPath.DIRECTORY_SEPARATOR.$aFixture['file'].'.yml' ) ) {
				$sAbsFixture = $sPath.DIRECTORY_SEPARATOR.$aFixture['file'].'.yml';
				break;
			}
		}
		if ( !$sAbsFixture ) {
			throw new \Exception( sprintf( 'Fixture "%s" was not found in any of the source paths', $aFixture['file'] ) );
		}

		$sTempFilename = tempnam( sys_get_temp_dir(), 'dataset_' );
		if ( $sTempFilename === false ) {
			throw new \Exception( 'Failed to create a temporary file' );
		}

		$sContents = file_get_contents( $sAbsFixture );

		// apply fixture specific filters
		foreach ( $aFixture['params'] as $sParamKey => $sParamValue ) {
			$sContents = preg_replace( '#\{\{'.strtoupper( $sParamKey ).'(=([^\}{]+))?\}\}#', $sParamValue, $sContents );
		}

		// apply any filters (in the form of closures)
		if ( !empty( $this->aFilters ) ) {
			foreach ( $this->aFilters as $cFilter ) {
				$sContents = $cFilter( $sContents );
			}
		}

		// simple string-like replacements
		if ( !empty( $this->aReplacements ) ) {
			foreach ( $this->aReplacements as $aReplacement ) {
				$sContents = preg_replace( '#:\s\{\{'.strtoupper( $aReplacement[0] ).'(=([^\{]+))?\}\}#', ': '.$aReplacement[1], $sContents );
			}
		}

		// add a default global filter to assign variables with default values
		$sContents = preg_replace( '#:\s\{\{[A-Z_]+=([^\{]+)\}\}#', ': $1', $sContents );

		if ( empty( $sContents ) ) {
			throw new \Exception( 'Empty data set' );
		}

		file_put_contents( $sTempFilename, $sContents );

		if ( empty( $this->oDataSet ) ) {
			$this->oDataSet = new \PHPUnit_Extensions_Database_DataSet_YamlDataSet( $sTempFilename );
		}
		else {
			$this->oDataSet->addYamlFile( $sTempFilename );
		}

		unlink( $sTempFilename );

		return $this;
	}
}