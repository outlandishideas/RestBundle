<?php


namespace Outlandish\RestBundle\Controller;

use Doctrine\ORM\Mapping\ClassMetadataInfo;
use Doctrine\ORM\Query;
use Doctrine\ORM\QueryBuilder;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;


class RestController extends Controller
{

	/**
	 * Find fully qualified class name for supplied short name
	 * 
	 * @param $entityType
	 * @return mixed
	 */
	protected function getFQCN($entityType) {
		$meta = $this->get('doctrine.orm.entity_manager')->getMetadataFactory()->getAllMetadata();
		foreach ($meta as $m) {
			$fqcn = $m->getName();
			$simpleName = Container::underscore(substr($fqcn, strrpos($fqcn, '\\') + 1));
			if ($simpleName == $entityType) {
				return $fqcn;
			}
		}
		
		return null;
	}

	/**
	 * Get array of all entities of type
	 *
	 * Supports simple queries with FIQL and pagination with 'offset' and 'per_page'
	 */
	public function getAllAction($entityType, Request $request) {
		$em = $this->get('doctrine.orm.entity_manager');
		$serializer = $this->get('jms_serializer');
		$this->get('jms_serializer.doctrine_proxy_subscriber')->setEnabled(false);
		$className = $this->getFQCN($entityType);

		//build basic DQL query
		$builder = $em->createQueryBuilder();
		$builder->setFirstResult($request->query->get('offset', 0));
		$defaultPageSize = 0; //todo: make this configurable
		$pageSize = $request->query->get('per_page', $defaultPageSize);
		if ($pageSize) {
			$builder->setMaxResults($pageSize);
		}
		$builder->select('e')->from($className, 'e');
		$this->parseFIQL($request, $builder);

		$fastSerialization = false; //todo: make this configurable
		if ($fastSerialization) {

			$meta = $em->getClassMetadata($className);
			$associationMappings = $meta->getAssociationMappings();
			$fieldNames = $meta->getFieldNames();

			//explicit joins and selects for associated IDs
			foreach ($associationMappings as $assocName => $assoc) {
				if (isset($assoc['joinTable'])) {
					$builder->leftJoin('e.'.$assocName, $assocName.'Table');
					$builder->add('select', "{$assocName}Table.id AS {$assocName}", true);
				} else {
					$builder->add('select', "IDENTITY(e.$assocName) AS $assocName", true);
				}
			}

			//fetch flat data
			$rows = $builder->getQuery()->execute(null, Query::HYDRATE_SCALAR);

			//unflatten rows with arrays of IDs for *-to-many associations
			$id = null;
			$data = array();
			$entity = array();
			foreach ($rows as $row) {
				if ($id != $row['e_id']) {
					//id has changed to is next row
					$data[] = $entity;
					$entity = array();

					//copy normal field data
					foreach ($fieldNames as $fieldName) {
						$entity[$fieldName] = $row['e_'.$fieldName];

						//serialize DateTime objects
						if ($entity[$fieldName] instanceof \DateTime) {
							$entity[$fieldName] = $entity[$fieldName]->format(\DateTime::ISO8601);
						}
					}
					$id = $entity['id'];
				}

				//copy associated fields
				foreach ($associationMappings as $assocName => $assoc) {
					if (isset($assoc['joinTable'])) {
						if (!isset($entity[$assocName])) $entity[$assocName] = array();
						if ($row[$assocName] != null) $entity[$assocName][] = $row[$assocName];
					} else {
						$entity[$assocName] = $row[$assocName];
					}
				}

			}
			//add last item
			$data[] = $entity;
			//remove first (empty) item
			array_shift($data);

			$text = json_encode($data);
		} else {
			//normal serialization
			$entities = $builder->getQuery()->getResult();
			$text = $serializer->serialize($entities, 'json');
		}

		return new Response($text, 200, array('Content-type' => 'application/json'));
	}


	/**
	 * Get single entity of type
	 */
	public function getOneAction($entityType, $id) {
		$em = $this->getDoctrine()->getManager();
		$serializer = $this->get('jms_serializer');
		$this->get('jms_serializer.doctrine_proxy_subscriber')->setEnabled(false);
		$className = $this->getFQCN($entityType);

		$entity = $em->getRepository($className)->find($id);

		if (!$entity) {
			$data = array(array('message' => 'Entity not found'));
			$code = 404;
		} else {
			$data = $entity;
			$code = 200;
		}

		$text = $serializer->serialize($data, 'json');
		return new Response($text, $code, array('Content-type' => 'application/json'));
	}

	/**
	 * Create new entity of type and return it
	 */
	public function postAction($entityType, Request $request) {
		$em = $this->getDoctrine()->getManager();
		$serializer = $this->get('jms_serializer');
		$validator = $this->get('validator');
		$this->get('jms_serializer.doctrine_proxy_subscriber')->setEnabled(false);
		$className = $this->getFQCN($entityType);

		$entity = $serializer->deserialize($request->getContent(), $className, 'json');
		$constraintViolations = $validator->validate($entity);

		if (count($constraintViolations)) {
			$data = $constraintViolations;
			$code = 400;
		} else {
			$em->persist($entity);
			$em->flush();

			$data = $entity;
			$code = 201;
		}

		$text = $serializer->serialize($data, 'json');
		return new Response($text, $code, array('Content-type' => 'application/json'));
	}

	/**
	 * Update single entity of type and return it
	 */
	public function putAction($entityType, $id, Request $request) {
		$em = $this->getDoctrine()->getManager();
		$serializer = $this->get('jms_serializer');
		$validator = $this->get('validator');
		$this->get('jms_serializer.doctrine_proxy_subscriber')->setEnabled(false);
		$className = $this->getFQCN($entityType);

		$entity = $serializer->deserialize($request->getContent(), $className, 'json');
		$constraintViolations = $validator->validate($entity);

		if (count($constraintViolations)) {
			$data = $constraintViolations;
			$code = 400;
		} else {
			$entity->setId($id);
			$em->merge($entity);
			$em->flush();

			$data = $entity;
			$code = 200;
		}

		//work around Doctrine detached entities issue with many-to-many associations
		$reloadedEntity = $em->getRepository($className)->find($id);
		$meta = $em->getClassMetadata($className);
		foreach ($meta->getAssociationMappings() as $mapping) {
			if ($mapping['type'] == ClassMetadataInfo::MANY_TO_MANY) {
				$reflection = $meta->getReflectionProperty($mapping['fieldName']);
				$reflection->setValue($reloadedEntity, $reflection->getValue($entity));
			}
		}
		$em->flush();

		$text = $serializer->serialize($data, 'json');
		return new Response($text, $code, array('Content-type' => 'application/json'));
	}

	/**
	 * Delete single entity of type
	 */
	public function deleteAction($entityType, $id) {
		$em = $this->getDoctrine()->getManager();
		$className = $this->getFQCN($entityType);

		$entity = $em->getRepository($className)->find($id);

		if (!$entity) {
			$text = json_encode(array(array('message' => 'Entity not found')));
			$code = 404;
		} else {
			$em->remove($entity);
			$em->flush();

			$text = '';
			$code = 204;
		}

		return new Response($text, $code, array('Content-type' => 'application/json'));
	}

	/**
	 * Parse simple data queries such as foo=baz&bar=lt=10
	 *
	 * It's kind of a subset of FIQL http://cxf.apache.org/docs/jax-rs-search.html
	 *
	 * @param Request $request
	 * @param QueryBuilder $builder
	 */
	protected function parseFIQL(Request $request, QueryBuilder $builder) {
		//allowed operators
		$operatorMap = array('' => '=', 'ne' => '!=', 'lt' => '<', 'gt' => '>', 'le' => '<=', 'ge' => '>=');

		//load class metadata
		$em = $builder->getEntityManager();
		$classNames = $builder->getRootEntities();
		$metadata = $em->getClassMetadata($classNames[0]);

		//use raw query string to allow for multiple instances of same param, e.g. foo=gt=1&foo=lt=3
		$queryParts = $_SERVER['QUERY_STRING'] ? explode('&', $_SERVER['QUERY_STRING']) : array();

		//process query parts
		foreach ($queryParts as $index => $part) {
			list($name, $value) = explode('=', $part, 2);

			//default operator
			$operator = '';

			//check for negation
			if (substr($name, -1) == '!') {
				$operator = 'ne';
				$name = substr($name, 0, -1);
			}

			//convert query_property to queryProperty
			$camelName = Container::camelize($name);
			$camelName[0] = strtolower($camelName[0]);

			//check queried property exists
			if (!isset($metadata->columnNames[$camelName]) && !isset($metadata->associationMappings[$camelName])) {
				continue;
			}

			//look for explicit operator
			if (strpos($value, '=') !== false) {
				list($operator, $value) = explode('=', $value, 2);
			}

			//ensure operator is valid
			if (!isset($operatorMap[$operator])) {
				continue;
			}

			//add to query
			$builder->andWhere('e.'.$camelName . $operatorMap[$operator].':'.$name.$index);
			$builder->setParameter($name.$index, $value);
		}
	}
} 