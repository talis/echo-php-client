<?php
namespace echoclient;

use \Guzzle\Http\Client;
use \Monolog\Handler\StreamHandler;
use \Monolog\Logger;

/**
 * Sends events to Echo, if an echo server is enabled.
 */
class EchoClient
{
    const ECHO_API_VERSION = 1;

    private $personaClient;
    private static $logger;

    function __construct()
    {
        /*
         * The calling project needs to have already set these up.
         */

        if (!defined('OAUTH_USER'))
        {
            throw new \Exception('Missing define: OAUTH_USER');
        }

        if (!defined('OAUTH_SECRET'))
        {
            throw new \Exception('Missing define: OAUTH_SECRET');
        }

        if (!defined('PERSONA_HOST'))
        {
            throw new \Exception('Missing define: PERSONA_HOST');
        }

        if (!defined('PERSONA_OAUTH_ROUTE'))
        {
            throw new \Exception('Missing define: PERSONA_OAUTH_ROUTE');
        }

        if (!defined('PERSONA_TOKENCACHE_HOST'))
        {
            throw new \Exception('Missing define: PERSONA_TOKENCACHE_HOST');
        }

        if (!defined('PERSONA_TOKENCACHE_PORT'))
        {
            throw new \Exception('Missing define: PERSONA_TOKENCACHE_PORT');
        }

        if (!defined('PERSONA_TOKENCACHE_DB'))
        {
            throw new \Exception('Missing define: PERSONA_TOKENCACHE_DB');
        }
    }

    function createEvent($class, $source, array $props=array())
    {
        $baseUrl = $this->getBaseUrl();

        $eventUrl = $baseUrl.'/events';

        if (!$baseUrl)
        {
            /*
             * If no echo server is defined then log the event for debugging purposes and fail silently...
             */
            $this->getLogger()->warning('Echo server is not defined (missing ECHO_HOST define), not sending event - '.$class, $props);
            return;
        }

        $eventJson = $this->getEventJson($class, $source, $props);
        $arrPersonaToken = $this->getPersonaClient()->obtainNewToken(OAUTH_USER, OAUTH_SECRET);
        $personaToken = $arrPersonaToken['access_token'];

        /*
         * TODO Remove this debugging
         */
        $this->getLogger()->debug('Persona token = '.$personaToken);

        $headers = array(
            'Content-Type'=>'application/json',
            'Authorization'=>'Bearer '.$personaToken
        );

        $client = new Client();
        $request = $client->post($eventUrl);
        $request->setHeaders($headers);
        $request->setBody($eventJson);

        $response = $request->send();

        if ($response->isSuccessful())
        {
            $this->getLogger()->debug('Success sending event to echo - '.$class, $props);
        }
        else
        {
            $this->getLogger()->error('Failed sending event to echo - '.$class, array('responseCode'=>$response->getStatusCode(), 'responseBody'=>$response->getBody(true), 'requestProperties'=>$props));
        }
    }

    /**
     * Allow the calling project to use its own instance of a MonoLog Logger class.
     *
     * @param Logger $logger
     */
    public static function setLogger(Logger $logger)
    {
        self::$logger = $logger;
    }

    protected function getEventJson($class, $source, array $props=array())
    {
        return json_encode(array('class'=>$class, 'source'=>$source, 'props'=>$props));
    }

    protected function getBaseUrl()
    {
        if (!defined('ECHO_HOST'))
        {
            return FALSE;
        }

        return ECHO_HOST.'/'.self::ECHO_API_VERSION;
    }

    /**
     * For mocking
     * TODO This would be better as a trait as it's duplicated in BaseModel.
     *
     * @return \personaclient\PersonaClient
     */
    protected function getPersonaClient()
    {
        if (!isset($this->personaClient)) {
            $this->personaClient = new \personaclient\PersonaClient(array(
                'persona_host' => PERSONA_HOST,
                'persona_oauth_route' => PERSONA_OAUTH_ROUTE,
                'tokencache_redis_host' => PERSONA_TOKENCACHE_HOST,
                'tokencache_redis_port' => PERSONA_TOKENCACHE_PORT,
                'tokencache_redis_db' => PERSONA_TOKENCACHE_DB,
            ));
        }
        return $this->personaClient;
    }

    /**
     * @return Logger
     */
    protected function getLogger()
    {
        if (self::$logger == null)
        {
            /*
             * If an instance of the MonoLog Logger hasn't been passed in then default to stderr.
             */
            self::$logger = new Logger('echoclient');
            self::$logger->pushHandler(new StreamHandler('php://stderr', Logger::DEBUG));
        }

        return self::$logger;
    }
}