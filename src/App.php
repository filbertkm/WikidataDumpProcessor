<?php

namespace WikidataDump;

use DataValues\Deserializers\DataValueDeserializer;
use Doctrine\DBAL\Configuration;
use Doctrine\DBAL\Connection as DBALConnection;
use Doctrine\DBAL\DriverManager;
use Elastica\Client;
use Elastica\Index;
use Wikibase\DataModel\Entity\BasicEntityIdParser;
use Wikibase\Elastic\Connection;
use Wikibase\Elastic\Dump\EntityDumpLookup;
use Wikibase\Elastic\Dump\EntityIdDumpPager;
use Wikibase\Elastic\Index\Indexer\EntityBatchIndexer;
use Wikibase\Elastic\Index\Indexer\EntityIndexer;
use Wikibase\Elastic\Index\Indexer\StatementDocumentBuilder;
use Wikibase\Elastic\Logger;
use Wikibase\InternalSerialization\DeserializerFactory;
use Wikibase\Repo\Store\SQL\EntityPerPageIdPager;
use Wikibase\Repo\WikibaseRepo;
use Wikibase\Utils;

class App {

	private $internalDeserializer;

	private $wikibaseRepo;

	public function getInternalEntityDeserializer() {
		if ( !isset( $this->internalDeserializer ) ) {
			$dataValueMap = $this->getDataValueMap();

			$deserializerFactory = new DeserializerFactory(
				new DataValueDeserializer( $dataValueMap ),
				new BasicEntityIdParser()
			);

			$this->internalDeserializer = $deserializerFactory->newEntityDeserializer();
		}

		return $this->internalDeserializer;
	}

	/**
	 * @return array
	 */
	private function getDataValueMap() {
		return array(
			'globecoordinate' => 'DataValues\GlobeCoordinateValue',
			'monolingualtext' => 'DataValues\MonolingualTextValue',
			'multilingualtext' => 'DataValues\MultilingualTextValue',
			'quantity' => 'DataValues\QuantityValue',
			'time' => 'DataValues\TimeValue',
			'wikibase-entityid' => 'Wikibase\DataModel\Entity\EntityIdValue',
			'string' => 'DataValues\StringValue'
		);
	}

	/**
	 * @return EntityDumpLookup
	 */
	public function getEntityDumpLookup() {
		return new EntityDumpLookup(
			$this->getDBALConnection(),
			$this->getInternalEntityDeserializer()
		);
	}

	/**
	 * @return EntityDumpIndexer
	 */
	public function getEntityDumpIndexer( Index $index ) {
		$conn = $this->getDBALConnection();

		$batchIndexer = new EntityBatchIndexer(
			$index,
			$this->getEntityDumpLookup(),
			new Logger(),
			Utils::getLanguageCodes()
		);

		return new EntityIndexer(
			new EntityIdDumpPager( $conn ),
			$batchIndexer
		);
	}

	/**
	 * @return DBALConnection
	 */
	public function getDBALConnection() {
		return \Doctrine\DBAL\DriverManager::getConnection(
			$GLOBALS['wgWBDumpDbConfig'],
			new \Doctrine\DBAL\Configuration()
		);
	}

	/**
	 * @return WikibaseRepo
	 */
	public function getWikibaseRepo() {
		if ( !isset( $this->wikibaseRepo ) ) {
			$this->wikibaseRepo = WikibaseRepo::getDefaultInstance();
		}

		return $this->wikibaseRepo;
	}

	/**
	 * @return App
	 */
	public static function getDefaultInstance() {
		return new self();
	}

}
