echo-php-client
===============

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
        "talis/echo-php-client": "~0.1"
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
