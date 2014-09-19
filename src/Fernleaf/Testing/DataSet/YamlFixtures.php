<?php

namespace Fernleaf\Testing\DataSet;

/**
 * Trait YamlFixtures
 * @package Fernleaf\Testing\DataSet
 */
trait YamlFixtures {

	/**
	 * @throws \Exception
	 * @return \PHPUnit_Extensions_Database_DataSet_YamlDataSet
	 */
	protected function getDataSet() {
		$oYamlLoader = new YamlLoader();
		if ( method_exists( $this, 'onBeforeGetDataSet' ) ) {
			$this->onBeforeGetDataSet( $oYamlLoader );
		}

		$aClassFixtures = array();
		$aMethodFixtures = array();

		$bIgnoreClassFixtures = false;

		$oReflectionClass = new \ReflectionClass( $this );
		$sClassDoc = $oReflectionClass->getDocComment();
		if ( !empty( $sClassDoc ) ) {
			$oYamlLoader->addSourcePaths(
				$this->getPathsFromDocComment( dirname( $oReflectionClass->getFileName() ), $sClassDoc )
			);

			$oYamlLoader->addReplacements(
				$this->getReplacementsFromDocComment( $sClassDoc )
			);

			$aClassFixtures = $this->getFixturesFromDocComment( $sClassDoc );
		}

		$oReflectionMethod = $oReflectionClass->getMethod( $this->getName() );
		$sDocComment = $oReflectionMethod->getDocComment();
		if ( !empty( $sDocComment ) ) {
			$oYamlLoader->addSourcePaths(
				$this->getPathsFromDocComment( dirname( $oReflectionClass->getFileName() ), $sDocComment )
			);

			$oYamlLoader->addReplacements(
				$this->getReplacementsFromDocComment( $sClassDoc )
			);

			$bIgnoreClassFixtures = preg_match( '#@datasetignoreclassfixtures#i', $sDocComment, $aMatches );
			$aMethodFixtures = $this->getFixturesFromDocComment( $sDocComment );
		}

		if ( !$bIgnoreClassFixtures && !empty( $aClassFixtures ) ) {
			foreach ( $aClassFixtures as $sFixture ) {
				$oYamlLoader->addFixture( $sFixture );
			}
		}

		if ( !empty( $aMethodFixtures ) ) {
			foreach ( $aMethodFixtures as $sFixture ) {
				$oYamlLoader->addFixture( $sFixture );
			}
		}

		//$this->getDatabaseTester()->setDataSet( $oYamlLoader->getDataSet() );
		//$this->getDatabaseTester()->onSetUp();
		return $oYamlLoader->getDataSet();//$this->applyYamlDataSet( 'base' );
	}

	/**
	 * @param string $sCurrentDir
	 * @param string $sComment
	 * @return array
	 * @throws \Exception
	 */
	private function getPathsFromDocComment( $sCurrentDir, $sComment ) {
		$aPaths = array();
		if ( preg_match( '#@datasetpath\[([^\]]+)\]#i', $sComment, $aMatches ) ) {
			$aPaths = array_map( 'trim', explode( ',', $aMatches[1] ) );
		}
		foreach ( $aPaths as $nIndex => $sPath ) {
			if ( strpos( $sPath, '.' ) === 0 ) {
				$sPath = $sCurrentDir.ltrim( $sPath, '.' );
			}
			else if ( strpos( $sPath, '/' ) !== 0 ) {
				$sPath = MODULE_ROOT.'/'.$sPath;
			}

			if ( !is_dir( $sPath ) ) {
				throw new \Exception( sprintf( 'Source path does not exist: %s', $sPath ) );
			}
			$aPaths[$nIndex] = $sPath;
		}
		return $aPaths;
	}

	/**
	 * @param string $sComment
	 * @return array
	 */
	private function getReplacementsFromDocComment( $sComment ) {
		$aReplacements = array();
		if ( preg_match( '#@datasetreplacement\s*\[\s*((?:[a-z_]+=[^,]+(?:\s*,\s*)?)+)\]#i', $sComment, $aMatches ) ) {
			$aReplacements = array_map( 'trim', explode( ',', $aMatches[1] ) );
			foreach ( $aReplacements as $sReplacement ) {
				$aReplacements[] =explode( '=', $sReplacement );
			}
		}
		return $aReplacements;
	}

	/**
	 * @throws \Exception
	 * @param string $sComment
	 * @return array
	 */
	private function getFixturesFromDocComment( $sComment ) {
		$aFixtures = array();

		// simplest check ever
		$nExpectedDataSets = preg_match_all( '#\s*@dataset(?![a-z])#im', $sComment, $null, PREG_SET_ORDER );
		$nActualDatasets = preg_match_all( '#@dataset\s*\[(.*?)\]\s*$#im', $sComment, $aMatches, PREG_SET_ORDER );

		if ( $nExpectedDataSets !== $nActualDatasets ) {
			throw new \Exception( sprintf( 'Expected %s datasets, but found only %s', $nExpectedDataSets, $nActualDatasets ) );
		}

		if ( $nActualDatasets ) {
			foreach ( $aMatches as $aMatch ) {
				$sDataSets = $aMatch[1];
				if ( preg_match_all( '#([a-z_]+)((?:(?=(?:,|\(|\]))(?:\(.+?\)))?)#i', $sDataSets, $aMatches, PREG_SET_ORDER ) ) {
					foreach ( $aMatches as $aMatchSet ) {
						// 1 = basic_addon and 2 = (id=1)
						$aParams = array();
						if ( !empty( $aMatchSet[2] ) ) {
							//([a-z_]+)=((?:[0-9]+)|(?:("|')[.*?]+("|')))\s*
							if ( preg_match_all( '#([a-z_]+)=((?:[0-9]+(?:\.[0-9]+)?)|(?:("|\').+?(\3)))\s*#i', $aMatchSet[2], $aTempParams, PREG_SET_ORDER ) ) {
								foreach ( $aTempParams as $aTempParam ) {
									$aParams[$aTempParam[1]] = $aTempParam[2];
								}
							}
						}
						$aFixtures[] = array(
							'file' => trim( $aMatchSet[1] ),
							'params' => $aParams
						);
					}
				}
				else {
					throw new \Exception( 'You have a syntax error with your "@dataSet" annotation: '.PHP_EOL.$sComment );
				}
			}
		}
		return $aFixtures;
	}
}