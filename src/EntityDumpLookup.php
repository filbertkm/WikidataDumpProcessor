<?php

namespace WikidataDump;

use Doctrine\DBAL\Connection as DBALConnection;
use PDO;
use Wikibase\DataModel\Entity\EntityId;
use Wikibase\Lib\Store\EntityLookup;

class EntityDumpLookup implements EntityLookup {

	/**
	 * @var DBALConnection
	 */
	private $conn;

	private $entityDeserializer;

	public function __construct( DBALConnection $conn, $entityDeserializer ) {
		$this->conn = $conn;
		$this->entityDeserializer = $entityDeserializer;
	}

	/**
	 * @param EntityId $entityId
	 *
	 * @return Entity
	 */
	public function getEntity( EntityId $entityId ) {
		$id = substr( $entityId->getSerialization(), 1 );
		$sql = sprintf( "SELECT content FROM items where id = %d", $id );
		$res = $this->conn->query( $sql );

		if ( $res ) {
			$rows = $res->fetchAll( PDO::FETCH_ASSOC );
			return $this->entityDeserializer->deserialize( json_decode( $rows[0]['content'], true ) );
		}

		return null;
	}

	/**
	 * @param EntityId $entityId
	 */
	public function hasEntity( EntityId $entityId ) {
		$sql = sprintf( "SELECT id from items where id = %d", $entityId->getNumericId() );
		$res = $this->conn->query( $sql );

		return $res ? true : false;
	}

}
