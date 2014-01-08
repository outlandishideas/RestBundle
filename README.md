# REST API in a Box

The `OutlandishRestBundle` provides a REST-like API for the Doctrine entities in a Symfony application. It is highly
opinionated and requires no configuration which makes it an excellent choice for rapid prototyping with JavaScript
frameworks such as Angular, Ember and Backbone.

Features:

- automatically generates routes
- uses JMSSerializer bundle
- uses Symfony validator component
- serializes errors and exceptions
- supports Doctrine associations
- uses JSON only
- pagination
- simple data queries using FIQL
- authentication can be added using the security component

For example if you have `Acme\FooBundle\Entity\Bar` then you can do the following:

    GET /api/bar
    // returns [{"id":1, ...}, {"id":2, ...}]

    GET /api/bar/1
    // returns {"id":1, ...}

    POST /api/bar
    {"foo":"baz", ...}
    // returns {"id":3, "foo":"baz", ...}

    PUT /api/bar/2
    {"foo":"buzz", ...}
    // returns {"id":2, "foo":"buzz", ...}

    DELETE /api/bar/3
    // returns nothing

    GET /api/bar?id=gt=2&foo=baz
    // returns [{"id":3, ...}]

    GET /api/bar?per_page=2&offset=1
    // returns [{"id":2, ...}, {"id":3, ...}]

    GET /api/bar/0
    // returns [{"message":"Entity not found"}]

POST and PUT requests expect JSON encoded entity data in the request body.

On error, a status code of 400, 404 or 500 is returned and the response body is an array of messages.


## Installation

### 1. Add to `composer.json`

	"require": {
	    "outlandish/rest-bundle": "dev-master",
	},

### 2. Run `composer update`

### 3. Add to `AppKernel.php`

	public function registerBundles()
	{
	    $bundles = array(
			//...
	        new Outlandish\RestBundle\OutlandishRestBundle(),
	    );

	    return $bundles;
	}

### 5. Edit `app/config/routing.yml`

	#...

	outlandish_rest:
		resource: .
		type: outlandish_rest
		prefix: /api
