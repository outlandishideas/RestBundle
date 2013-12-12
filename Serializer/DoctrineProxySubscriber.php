<?php


namespace Outlandish\RestBundle\Serializer;

use Doctrine\Common\Proxy\Proxy;
use Doctrine\ORM\PersistentCollection;
use JMS\Serializer\EventDispatcher\PreDeserializeEvent;
use JMS\Serializer\EventDispatcher\PreSerializeEvent;
use JMS\Serializer\EventDispatcher\Subscriber\DoctrineProxySubscriber as BaseSubscriber;

class DoctrineProxySubscriber extends BaseSubscriber
{
	protected $enabled = true;

	public static function getSubscribedEvents() {
		return array(
			array('event' => 'serializer.pre_serialize', 'method' => 'onPreSerialize'),
			array('event' => 'serializer.pre_deserialize', 'method' => 'onPreDeserialize'),
		);
	}

	public function onPreSerialize(PreSerializeEvent $event) {
		if ($this->enabled) {
			//by default behave same as parent class
			parent::onPreSerialize($event);
		} elseif ($event->getObject() instanceof Proxy) {
			//if enabled and class is a Doctrine proxy, set virtual type for custom handler
			$event->setType('Outlandish/Virtual/Proxy');
		} elseif ($event->getObject() instanceof PersistentCollection) {
			//if enabled and class is a Doctrine proxy, set virtual type for custom handler
			$event->setType('Outlandish/Virtual/PersistentCollection');
		}
	}

	public function onPreDeserialize(PreDeserializeEvent $event) {
		$type = $event->getType();
		$data = $event->getData();
		if (substr($type['name'], 0, strrpos($type['name'], '\\')) == 'Outlandish\SiteBundle\Entity' && is_scalar($data)) {
			$event->setType('Outlandish/Virtual/Reference', array('originalType' => $type['name']));
		}
	}

	/**
	 * @return boolean
	 */
	public function getEnabled() {
		return $this->enabled;
	}

	/**
	 * @param boolean $enabled
	 */
	public function setEnabled($enabled) {
		$this->enabled = $enabled;
	}
}
