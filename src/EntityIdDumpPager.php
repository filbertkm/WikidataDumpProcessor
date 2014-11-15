<?php

namespace WikidataDump;

use Doctrine\DBAL\Connection as DBALConnection;
use PDO;
use Wikibase\DataModel\Entity\ItemId;
use Wikibase\Repo\Store\EntityIdPager;

/**
 * @since 0.5
 *
 * @licence GNU GPL v2+
 * @author Daniel Kinzler
 */
class EntityIdDumpPager implements EntityIdPager {

	/**
	 * @var DBALConnection
	 */
	private $conn;

	/**
	 * @var int
	 */
	private $start = 0;

	/**
	 * @param DBALConnection $conn
	 */
	public function __construct( DBALConnection $conn ) {
		$this->conn = $conn;
	}

	/**
	 * @see EntityIdPager::fetchIds
	 */
	public function fetchIds( $limit ) {
		if ( $rows = $this->fetchRows( $limit ) ) {
			$this->setStart( $rows );
			return $this->getEntityIds( $rows );
		}

		return array();
	}

	private function fetchRows( $limit ) {
		$res = $this->query( $limit );

		if ( !$res ) {
			return array();
		}

		return $res->fetchAll( PDO::FETCH_ASSOC );
	}

	private function query( $limit ) {
		$sql = sprintf( 'SELECT id FROM items WHERE id > %d'
			. ' ORDER BY id LIMIT %d', $this->start, $limit );

		return $this->conn->query( $sql );
	}

	private function setStart( array $rows ) {
		$lastRow = end( $rows );
		$this->start = $lastRow['id'];
	}

	private function getEntityIds( array $rows ) {
		return array_map( function( $row ) {
			return ItemId::newFromNumber( $row['id'] );
		},
		$rows );
	}

}
