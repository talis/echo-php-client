echo-php-client
===============

[![Dependency Status](https://dependencyci.com/github/talis/echo-php-client/badge)](https://dependencyci.com/github/talis/echo-php-client)

This is a PHP client library for Talis Echo, allowing you to submit events.

## Getting Started

Install the module via composer, by adding the following to your projects ``composer.json``

```javascript
{
    "repositories":[
        {
            "type": "vcs",
            "url": "https://github.com/talis/echo-php-client"
        },
    ],
    "require" :{
        "talis/echo-php-client": "~0.2"
    }
}
```
then update composer:

```bash
$ php composer.phar update
```

In your code, do the following to create events:

```php
$echoClient = new \echoClient\EchoClient(); // see constructor for mandatory constants
$echoClient->createEvent(
  "event.class",
  "event.source",
  array('some'=>'props')
);
```

## Contributing

A Dockerfile is provided to make it easy to get a local development environment
up and running to develop and test changes. Follow these steps:

```bash

# Build the development image

git clone https://github.com/talis/echo-php-client.git
cd echo-php-client
docker build -t "echo-php-client:dev" --build-arg git_oauth_token=<yout github oauth token> .

# When the above has build successfully you can run and connect to the container
docker run -v /path/to/echo-php-client:/var/echo-php-client -i -t echo-php-client:dev /bin/bash

# The inside the container

ant init
ant test
```



