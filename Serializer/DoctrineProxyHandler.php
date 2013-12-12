<?php


namespace Outlandish\RestBundle\Serializer;


use Doctrine\ORM\EntityManager;
use Doctrine\ORM\PersistentCollection;
use JMS\Serializer\Context;
use JMS\Serializer\GraphNavigator;
use JMS\Serializer\Handler\SubscribingHandlerInterface;
use JMS\Serializer\JsonDeserializationVisitor;
use JMS\Serializer\JsonSerializationVisitor;
use Symfony\Component\DependencyInjection\Container;

class DoctrineProxyHandler implements SubscribingHandlerInterface {

	/**
	 * @var EntityManager
	 */
	protected $em;

	public function __construct($em) {
		$this->em = $em;
	}

	public static function getSubscribingMethods() {
		return array(
			array(
				'direction' => GraphNavigator::DIRECTION_SERIALIZATION,
				'format' => 'json',
				'type' => 'Outlandish/Virtual/Proxy', //not a real class name
				'method' => 'handleProxy',
			),
			array(
				'direction' => GraphNavigator::DIRECTION_SERIALIZATION,
				'format' => 'json',
				'type' => 'Outlandish/Virtual/PersistentCollection', //not a real class name
				'method' => 'handleCollection',
			),
			array(
				'direction' => GraphNavigator::DIRECTION_DESERIALIZATION,
				'format' => 'json',
				'type' => 'Outlandish/Virtual/Reference', //not a real class name
				'method' => 'handleReference',
			)
		);
	}

	/**
	 * Serialize an entity as a scalar ID string
	 */
	public function handleProxy(JsonSerializationVisitor $visitor, $entity, array $type, Context $context) {
		return $visitor->visitInteger($entity->getId(), $type, $context);
	}

	/**
	 * Serialize a collection of entities to an array of ID strings
	 */
	public function handleCollection(JsonSerializationVisitor $visitor, PersistentCollection $collection, array $type, Context $context) {
		$data = array();
		foreach ($collection as $entity) {
			$data[] = $this->handleProxy($visitor, $entity, $type, $context);
		}
		return $data;
	}

	/**
	 * Deserialize scalar IDs to Doctrine proxy objects
	 */
	public function handleReference(JsonDeserializationVisitor $visitor, $id, array $type, Context $context) {
		return $this->em->getReference($type['params']['originalType'], $id);
	}
}