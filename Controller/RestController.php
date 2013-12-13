<?php


namespace Outlandish\RestBundle\Controller;

use Doctrine\ORM\Mapping\ClassMetadataInfo;
use Doctrine\ORM\Query;
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
	 */
	public function getAllAction($entityType) {
		$em = $this->getDoctrine()->getManager();
		$serializer = $this->get('jms_serializer');
		$this->get('jms_serializer.doctrine_proxy_subscriber')->setEnabled(false);
		$className = $this->getFQCN($entityType);

		$entities = $em->getRepository($className)->findAll();

		$data = $serializer->serialize($entities, 'json');
		return new Response($data, 200, array('Content-type' => 'application/json'));
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

} 