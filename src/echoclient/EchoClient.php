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
    private $debugEnabled = false;
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

    /**
     * Add an event to echo
     * @param $class
     * @param $source
     * @param array $props
     * @return bool
     */
    public function createEvent($class, $source, array $props=array())
    {
        $baseUrl = $this->getBaseUrl();

        if (!$baseUrl)
        {
            // fail silently when creating events, should not stop user interaction as echo events are collected on a best-endeavours basis
            return false;
        }

        $eventUrl = $baseUrl.'/events';
        $eventJson = $this->getEventJson($class, $source, $props);

        try
        {
            $client = $this->getHttpClient();
            $request = $client->post($eventUrl, $this->getHeaders(), $eventJson, array('connect_timeout'=>2));
            $response = $request->send();

            if ($response->isSuccessful())
            {
                $this->getLogger()->debug('Success sending event to echo - '.$class, $props);
                return true;
            }
            else
            {
                $this->getLogger()->warning('Failed sending event to echo - '.$class, array('responseCode'=>$response->getStatusCode(), 'responseBody'=>$response->getBody(true), 'requestProperties'=>$props));
                return false;
            }
        }
        catch (\Exception $e)
        {
            // For any exception issue, just log the issue and fail silently.  E.g. failure to connect to echo server, or whatever.
            $this->getLogger()->warning('Failed sending event to echo - '.$class, array('exception'=>get_class($e), 'message'=>$e->getMessage(), 'requestProperties'=>$props));
            return false;
        }
    }

    /**
     * Get hits analytics from echo
     * @param $class
     * @param $opts optional params as per the echo docs @ http://docs.talisecho.apiary.io/#analytics
     *
     * @return mixed
     * @throws \Exception
     */
    public function getHits($class, $opts = array())
    {
        return $this->getAnalytics($class,'hits',$opts);
    }

    /**
     * Get sum analytics from echo
     * @param $class
     * @param $opts optional params as per the echo docs @ http://docs.talisecho.apiary.io/#analytics
     *
     * @return mixed
     * @throws \Exception
     */
    public function getSum($class, $opts = array())
    {
        return $this->getAnalytics($class,'sum',$opts);
    }

    /**
     * Get max analytics from echo
     * @param $class
     * @param $opts optional params as per the echo docs @ http://docs.talisecho.apiary.io/#analytics
     *
     * @return mixed
     * @throws \Exception
     */
    public function getMax($class, $opts = array())
    {
        return $this->getAnalytics($class,'max',$opts);
    }

    /**
     * Get average analytics from echo
     * @param $class
     * @param $opts optional params as per the echo docs @ http://docs.talisecho.apiary.io/#analytics
     *
     * @return mixed
     * @throws \Exception
     */
    public function getAverage($class, $opts = array())
    {
        return $this->getAnalytics($class,'average',$opts);
    }

    /**
     * @param $class
     * @param $type
     * @param array $opts optional params as per the echo docs @ http://docs.talisecho.apiary.io/#analytics
     * @return mixed
     * @throws \Exception
     */
    protected function getAnalytics($class,$type,$opts=array())
    {
        if (!in_array($type,array("hits","sum","max","average")))
        {
            throw new \Exception("You must supply a valid analytics type");
        }
        if (empty($class))
        {
            throw new \Exception("You must supply a class");
        }

        $baseUrl = $this->getBaseUrl();

        if (!$baseUrl)
        {
            // fail noisily!
            throw new \Exception("Could not determine echo base URL");
        }

        $eventUrl = $baseUrl.'/analytics/'.$type.'?class='.urlencode($class);
        foreach($opts as $key=>$value)
        {
            if (!empty($value))
            {
                $eventUrl .= '&'.$key.'='.urlencode($value);
            }
        }

        $client = $this->getHttpClient();
        $request = $client->get($eventUrl, $this->getHeaders(), array('connect_timeout'=>10));
        $response = $request->send();

        if ($response->isSuccessful())
        {
            $this->getLogger()->debug('Success getting analytics from echo - '.$type, $opts);
            $json = json_decode($response->getBody(true),true);
            if ($json)
            {
                return $json;
            }
            else
            {
                $this->getLogger()->warning('Failed getting analytics from echo, json did not decode - '.$class, array('body'=>$response->getBody(true),'responseCode'=>$response->getStatusCode(), 'responseBody'=>$response->getBody(true), 'requestClass'=>$class, 'requestType'=>$type, 'requestOpts'=>$opts));
                throw new \Exception("Could not get analytics from echo, json did not decode: ".$response->getBody(true));
            }
        }
        else
        {
            $this->getLogger()->warning('Failed getting analytics from echo - '.$class, array('responseCode'=>$response->getStatusCode(), 'responseBody'=>$response->getBody(true), 'requestClass'=>$class, 'requestType'=>$type, 'requestOpts'=>$opts));
            throw new \Exception("Could not get analytics from echo, statusCode: ".$response->getStatusCode());
        }
    }

    /**
     * Enable debug mode for this client.  If this is enabled we log things like the Persona client.
     * Only use in development, not production!
     * @param bool $bDebugEnabled Whether debug mode should be enabled or not (default = false)
     */
    public function setDebugEnabled($bDebugEnabled)
    {
        $this->debugEnabled = $bDebugEnabled;
    }

    /**
     * Is debugging enabled for this class?
     * We log out things like the Persona token etc.  (Only to be used in development!)
     * @return bool
     */
    public function isDebugEnabled()
    {
        return $this->debugEnabled;
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

    /**
     * Setup the header array for any request to echo
     * @return array
     */
    protected function getHeaders()
    {
        $arrPersonaToken = $this->getPersonaClient()->obtainNewToken(OAUTH_USER, OAUTH_SECRET);
        $personaToken = $arrPersonaToken['access_token'];

        $headers = array(
            'Content-Type'=>'application/json',
            'Authorization'=>'Bearer '.$personaToken
        );

        return $headers;
    }

    protected function getBaseUrl()
    {
        if (!defined('ECHO_HOST'))
        {
            /*
             * If no echo server is defined then log the event for debugging purposes...
             */
            $this->getLogger()->warning('Echo server is not defined (missing ECHO_HOST define)');
            return false;
        }

        return ECHO_HOST.'/'.self::ECHO_API_VERSION;
    }

    /**
     * To allow mocking of the Guzzle client for testing.
     *
     * @return Client
     */
    protected function getHttpClient()
    {
        return new Client();
    }

    /**
     * To allow mocking of the PersonaClient for testing.
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
     * Get the current Logger instance.
     *
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
            self::$logger->pushHandler(new StreamHandler('/tmp/echo-client.log', Logger::DEBUG));
        }

        return self::$logger;
    }
}