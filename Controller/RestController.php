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
		$meta = $this->getDoctrine()->getManager()->getMetadataFactory()->getAllMetadata();
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
	 * Supports simple queries with FIQL and pagination with 'start' and 'per_page'
	 */
	public function getAllAction($entityType, Request $request) {
		$em = $this->get('doctrine.orm.entity_manager');
		$serializer = $this->get('jms_serializer');
		$this->get('jms_serializer.doctrine_proxy_subscriber')->setEnabled(false);
		$className = $this->getFQCN($entityType);

		$builder = $em->createQueryBuilder();
		$builder->setMaxResults($request->query->get('per_page', 1000))->setFirstResult($request->query->get('start', 0));
		$builder->select('e')->from($className, 'e');
		$this->parseFIQL($request, $builder);

		$entities = $builder->getQuery()->getResult();

		$text = $serializer->serialize($entities, 'json');
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

		//process query parts
		foreach ($request->query->all() as $name => $value) {
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
			$builder->andWhere('e.'.$camelName . $operatorMap[$operator].':'.$name);
			$builder->setParameter($name, $value);
		}
	}
} 