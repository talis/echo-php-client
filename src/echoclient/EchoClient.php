<?php
namespace echoclient;

use \Guzzle\Http\Client;
use \Monolog\Handler\StreamHandler;
use \Monolog\Logger;

/**
 * Sends events to Echo, if an echo server is enabled.
 *
 * ------------------------------------------------------------------------------------------------------------------
 *
 * TODO
 *
 *   There are some things to think about before we can use this external client, the biggest being how we deal
 *   with PersonaClient in here.  We can either pass in a PersonaClient (setPersonaClient(xx)) or pass in some
 *   kind of PersonaClient config object or just pass in the constants maybe?  (Or just expect them to be there
 *   from the calling project).
 *
 *
 * ------------------------------------------------------------------------------------------------------------------
 *
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

        $params = array(
            'OAUTH_USER'=>OAUTH_USER,
            'OAUTH_SECRET'=>OAUTH_SECRET,
            'PERSONA_HOST'=>PERSONA_HOST,
            'PERSONA_OAUTH_ROUTE'=>PERSONA_OAUTH_ROUTE,
            'PERSONA_TOKENCACHE_HOST'=>PERSONA_TOKENCACHE_HOST,
            'PERSONA_TOKENCACHE_PORT'=>PERSONA_TOKENCACHE_PORT,
            'PERSONA_TOKENCACHE_DB'=>PERSONA_TOKENCACHE_DB
        );

        $this->getLogger()->warning('EchoClient config', $params);
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
            $this->getLogger()->info('Echo server is not defined - not sending event', array('class'=>$class, 'source'=>$source, 'props'=>$props));
            return;
        }

        $eventJson = $this->getEventJson($class, $source, $props);
        $arrPersonaToken = $this->getPersonaClient()->obtainNewToken(OAUTH_USER, OAUTH_SECRET);
        $personaToken = $arrPersonaToken['access_token'];

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
            $this->getLogger()->error('Sent event to echo successfully - code = '.$response->getStatusCode());
        }
        else
        {
            $this->getLogger()->error('Failed sending event to echo - code = '.$response->getStatusCode().' - '.$response->getBody(true));
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
     * TODO This would be better as a trait as duplicated in BaseModel.
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
             * If an instance of the MonoLog Logger hasn't been passed in then use our own to a file.
             */
            self::$logger = new Logger('echoclient');
            self::$logger->pushHandler(new StreamHandler('php://stderr', Logger::DEBUG));
        }

        return self::$logger;
    }

}