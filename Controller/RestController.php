<?php


namespace Outlandish\RestBundle\Controller;

use Doctrine\ORM\Query;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;


class RestController extends Controller
{

	public function getAllAction($entityType) {
		$em = $this->getDoctrine()->getManager();
		$serializer = $this->get('jms_serializer');
		$this->get('jms_serializer.doctrine_proxy_subscriber')->setEnabled(false);
		$classname = 'Outlandish\\SiteBundle\\Entity\\' . Container::camelize($entityType);

		$entities = $em->getRepository($classname)->findAll();

		$data = $serializer->serialize($entities, 'json');
		return new Response($data, 200, array('Content-type' => 'application/json'));
	}


	public function getOneAction($entityType, $id) {
		$em = $this->getDoctrine()->getManager();
		$serializer = $this->get('jms_serializer');
		$this->get('jms_serializer.doctrine_proxy_subscriber')->setEnabled(false);
		$classname = 'Outlandish\\SiteBundle\\Entity\\' . Container::camelize($entityType);

		$entity = $em->getRepository($classname)->find($id);

		$data = $serializer->serialize($entity, 'json');
		return new Response($data, 200, array('Content-type' => 'application/json'));
	}

	public function postAction($entityType, Request $request) {
		$em = $this->getDoctrine()->getManager();
		$serializer = $this->get('jms_serializer');
		$this->get('jms_serializer.doctrine_proxy_subscriber')->setEnabled(false);
		$classname = 'Outlandish\\SiteBundle\\Entity\\' . Container::camelize($entityType);

		$entity = $serializer->deserialize($request->getContent(), $classname, 'json');
		$em->persist($entity);
		$em->flush();

		$data = $serializer->serialize($entity, 'json');
		return new Response($data, 201, array('Content-type' => 'application/json'));
	}

	public function putAction($entityType, $id, Request $request) {
		$em = $this->getDoctrine()->getManager();
		$serializer = $this->get('jms_serializer');
		$this->get('jms_serializer.doctrine_proxy_subscriber')->setEnabled(false);
		$classname = 'Outlandish\\SiteBundle\\Entity\\' . Container::camelize($entityType);

		$entity = $serializer->deserialize($request->getContent(), $classname, 'json');
		$entity->setId($id);
		$em->merge($entity);
		$em->flush();

		//work around Doctrine detached entities issue
		$newEntity = $em->getRepository($classname)->find($id);
		foreach (array('TrainingCapability', 'Rooms', 'Trainers', 'Equipment') as $prop) {
			if (method_exists($newEntity, 'set' . $prop)) {
				$newEntity->{'set' . $prop}($entity->{'get' . $prop}());
			}
		}
		$em->flush();

		$data = $serializer->serialize($entity, 'json');
		return new Response($data, 200, array('Content-type' => 'application/json'));
	}

	public function deleteAction($entityType, $id) {
		$em = $this->getDoctrine()->getManager();
		$classname = 'Outlandish\\SiteBundle\\Entity\\' . Container::camelize($entityType);

		$entity = $em->getRepository($classname)->find($id);
		$em->remove($entity);
		$em->flush();

		return new Response('', 204, array('Content-type' => 'application/json'));
	}

} 