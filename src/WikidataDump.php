<?php

namespace WikidataDump;

use DataValues\Deserializers\DataValueDeserializer;
use Doctrine\DBAL\Configuration;
use Doctrine\DBAL\Connection as DBALConnection;
use Doctrine\DBAL\DriverManager;
use Elastica\Index;
use Wikibase\DataModel\Entity\BasicEntityIdParser;
use Wikibase\Elastic\Index\DocumentBuilder\StatementDocumentBuilder
use Wikibase\Elastic\Index\Indexer\EntityBatchIndexer;
use Wikibase\Elastic\Index\Indexer\EntityIndexer;
use Wikibase\Elastic\Logger;
use Wikibase\InternalSerialization\DeserializerFactory;
use Wikibase\Repo\Store\SQL\EntityPerPageIdPager;
use Wikibase\Repo\WikibaseRepo;
use Wikibase\Utils;
use Wikibot\Api\Modules\Wikibase\GetEntities;
use Wikibot\Api\Wikibase\EntityApiLookup;
use Wikibot\Api\Wikibase\PropertyDataTypeApiLookup;
use Wikibot\Api\WikibotApi;
use WikiClient\MediaWiki\ApiClient;
use WikiClient\MediaWiki\User;
use WikiClient\MediaWiki\Wiki;
use WikidataDump\EntityDumpLookup;
use WikidataDump\EntityIdDumpPager;

class WikidataDump {

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

	public function getEntityLookup( ApiClient $client ) {
		$getEntities = new GetEntities(
			$client,
			WikibotApi::getInstance()->getEntitySerializer()
		);

		return new EntityApiLookup( $getEntities );
	}

	private function getWikidataApiClient() {
		$wiki = new Wiki( 'wikidatawiki', 'https://www.wikidata.org/w/api.php' );
		return new ApiClient( $wiki, $GLOBALS['wikidataUser'] );
	}

	public function getPropertyDataTypeLookup() {
		$client = $this->getWikidataApiClient();

		return new PropertyDataTypeApiLookup(
			$this->getEntityLookup( $client )
		);
	}

	/**
	 * @return EntityDumpIndexer
	 */
	public function getEntityDumpIndexer( Index $index ) {
		$conn = $this->getDBALConnection();

		$batchIndexer = new EntityBatchIndexer(
			$index,
			new StatementDocumentBuilder( $this->getPropertyDataTypeLookup() ),
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
