<?php


namespace Outlandish\RestBundle\Routing;


use Doctrine\ORM\EntityManager;
use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\Config\Loader\LoaderResolverInterface;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

class RestLoader implements LoaderInterface {

	private $loaded = false;

	public function __construct(EntityManager $em) {
		$this->em = $em;
	}

	/**
	 * Dynamically create routes for entities
	 *
	 * @param mixed $resource
	 * @param string $type
	 * @throws \RuntimeException
	 * @return RouteCollection
	 */
	public function load($resource, $type = null) {
		if (true === $this->loaded) {
			throw new \RuntimeException('Do not add the "outlandish_rest" loader twice');
		}

		$classNames = array();
		$simpleNames = array();
		$classes = $this->em->getMetadataFactory()->getAllMetadata();
		foreach ($classes as $class) {
			$name = $class->getName();
			$classNames[] = $name;
			$simpleNames[] = Container::underscore(substr($name, strrpos($name, '\\') + 1));
		}

		$requirements = array('entityType' => implode('|', $simpleNames), 'id' => '\d+');

		$getAllRoute = new Route('/{entityType}', array('_controller' => 'OutlandishRestBundle:Rest:getAll'), $requirements, array(), '', array(), array('GET'));
		$getOneRoute = new Route('/{entityType}/{id}', array('_controller' => 'OutlandishRestBundle:Rest:getOne'), $requirements, array(), '', array(), array('GET'));
		$postRoute = new Route('/{entityType}', array('_controller' => 'OutlandishRestBundle:Rest:post'), $requirements, array(), '', array(), array('POST'));
		$putRoute = new Route('/{entityType}/{id}', array('_controller' => 'OutlandishRestBundle:Rest:put'), $requirements, array(), '', array(), array('PUT'));
		$deleteRoute = new Route('/{entityType}/{id}', array('_controller' => 'OutlandishRestBundle:Rest:delete'), $requirements, array(), '', array(), array('DELETE'));

		$routes = new RouteCollection();
		$routes->add('outlandish_rest.get_all', $getAllRoute);
		$routes->add('outlandish_rest.get_one', $getOneRoute);
		$routes->add('outlandish_rest.post', $postRoute);
		$routes->add('outlandish_rest.put', $putRoute);
		$routes->add('outlandish_rest.delete', $deleteRoute);

		$this->loaded = true;

		return $routes;
	}

	/**
	 * @param mixed $resource A resource
	 * @param string $type The resource type
	 * @return Boolean true if this class supports the given resource, false otherwise
	 */
	public function supports($resource, $type = null) {
		return $type == 'outlandish_rest';
	}

	public function getResolver() {}

	public function setResolver(LoaderResolverInterface $resolver) {}
}