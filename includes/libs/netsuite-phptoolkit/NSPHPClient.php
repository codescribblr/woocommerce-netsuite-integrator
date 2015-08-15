<?php

function arrayValuesAreEmpty ($array)
{
    if (!is_array($array))
    {
        return false;
    }

    foreach ($array as $key => $value)
    {
        if ( $value === false || ( !is_null($value) && $value != "" && !arrayValuesAreEmpty($value)))
        {
            return false;
        }
    }

    return true;
}

function array_is_associative ($array)
{
    if ( is_array($array) && ! empty($array) )
    {
        for ( $iterator = count($array) - 1; $iterator; $iterator-- )
        {
            if ( ! array_key_exists($iterator, $array) ) { return true; }
        }
        return ! array_key_exists(0, $array);
    }
    return false;
}

function setFields($object, array $fieldArray=null)
{
    // helper method that allows creating objects and setting their properties based on an associative array passed as argument. Mimics functionality from PHP toolkit
    $classname = get_class($object);
    // a static map that maps class parameters to their types. needed for knowing which objects to create
    $typesmap = $classname::$paramtypesmap;

    if (!isset ($typesmap)) {
        // if the class does not have paramtypesmap, consider it empty
        $typesmap = array();
    }

    if ($fieldArray == null)
    {
        // nothign to do
        return;
    }

    foreach ($fieldArray as $fldName => $fldValue)
    {
        if (((is_null($fldValue) || $fldValue == "") && $fldValue !== false) || arrayValuesAreEmpty($fldValue))
        {
            //empty param
            continue;
        }

        if (!isset($typesmap[$fldName])) {
            // the value is not a valid class atrribute
            trigger_error("SetFields error: parameter \"" .$fldName . "\" is not a valid parameter for an object of class \"" . $classname . "\", it will be omitted", E_USER_WARNING);
            continue;
        }

        if ($fldValue === 'false')
        {
            // taken from the PHP toolkit, but is it really necessary?
            $object->$fldName = FALSE;
        }
        elseif (is_object($fldValue))
        {
            $object->$fldName = $fldValue;
        }
        elseif (is_array($fldValue) && array_is_associative($fldValue))
        {
            // example: 'itemList'  => array('item' => array($item1, $item2), 'replaceAll'  => false)
            if (substr($typesmap[$fldName],-2) == "[]") {
                trigger_error("Trying to assign an object into an array parameter \"" .$fldName . "\" of class \"" . $classname . "\", it will be omitted", E_USER_WARNING);
                continue;
            }
            $obj = new $typesmap[$fldName]();
            setFields($obj, $fldValue);
            $object->$fldName = $obj;
        }
        elseif (is_array($fldValue) && !array_is_associative($fldValue))
        {
            // array type 
            if (substr($typesmap[$fldName],-2) != "[]") {
                // the type is not an array, skipping this value
                trigger_error("Trying to assign an array value into parameter \"" .$fldName . "\" of class \"" . $classname . "\", it will be omitted", E_USER_WARNING);
                continue;
            }

            // get the base type  - the string is of type <type>[]
            $basetype = substr($typesmap[$fldName],0,-2);

            // example: 'item' => array($item1, $item2)
            foreach ($fldValue as $item)
            {
                if (is_object($item))
                {
                    // example: $item1 = new nsComplexObject('SalesOrderItem');
                    $val[] = $item;
                }
                elseif ($typesmap[$fldName] == "string")
                {
                    // handle enums
                    $val[] = $item;
                }
                else
                {
                    // example: $item2 = array( 'item'      => new nsComplexObject('RecordRef', array('internalId' => '17')),
                    //                          'quantity'  => '3')
                    $obj = new $basetype();
                    setFields($obj, $item);
                    $val[] = $obj;
                }
            }

            $object->$fldName = $val;
        }
        else
        {
            $object->$fldName = $fldValue;
        }
    }
}

function milliseconds()
{
    $m = explode(' ',microtime());
    return (int)round($m[0]*10000,4);
}

function cleanUpNamespaces($xml_root)
{
    $xml_root = str_replace('xsi:type', 'xsitype', $xml_root);
    $record_element = new SimpleXMLElement($xml_root);

    foreach ($record_element->getDocNamespaces() as $name => $ns)
    {
        if ( $name != "" )
        {
            $xml_root = str_replace($name . ':', '', $xml_root);
        }
    }

    $record_element = new SimpleXMLElement($xml_root);

    foreach($record_element->children() as $field)
    {
        $field_element = new SimpleXMLElement($field->asXML());

        foreach ($field_element->getDocNamespaces() as $name2 => $ns2)
        {
            if ($name2 != "")
            {
                $xml_root = str_replace($name2 . ':', '', $xml_root);
            }
        }
    }

    return $xml_root;
}

class NSPHPClient
{
    public $client = null;
    public $passport = null;
    public $generated_from_endpoint = "";

    protected $classmap;

    private $nsversion = "2015_1r1";
    private $userequest = true;
    private $config;
    private $soapHeaders = array();

    /**
     * @param array $config
     * @param string $wsdl
     * @param array $options
     * @throws \Exception
     */
    public function __construct($config = array(), $wsdl = null, $options = array())
    {
        global $debuginfo;

        $this->setConfig($config);

        if (!isset($wsdl)) {
            if (!isset($this->config['host'])) {
                throw new \Exception('Webservice host must be specified');
            }
            if (!isset($this->config['endpoint'])) {
                throw new \Exception('Webservice endpoint must be specified');
            }
            $wsdl = $this->config['host'] . "/wsdl/v" . $this->config['endpoint'] . "_0/netsuite.wsdl";
        }

        if (! extension_loaded('soap')) {
            // check for loaded SOAP extension
            $soap_warning = 'The SOAP PHP extension is not loaded. '
                          . 'Please modify the extension settings in php.ini accordingly.';

            trigger_error($soap_warning, E_USER_WARNING);
        }

        if (! extension_loaded('openssl') && substr($wsdl, 0, 5) == "https") {
            // check for loaded SOAP extension
            $soap_warning = 'The Open SSL PHP extension is not loaded and you are trying to use HTTPS protocol. '
                          . 'Please modify the extension settings in php.ini accordingly.';

            trigger_error($soap_warning, E_USER_WARNING);
        }

        if ($this->generated_from_endpoint != $this->config['endpoint']) {
            // check for the endpoint compatibility failed, but it might still be compatible. Issue only warning
            $endpoint_warning = 'The NetSuiteService classes were generated from the '
                              . $this->generated_from_endpoint .' endpoint but you are running against '
                              . $this->config['endpoint'];

            trigger_error($endpoint_warning, E_USER_WARNING);
        }

        $options['classmap'] = $this->classmap;
        $options['trace'] = 1;
        $options['connection_timeout'] = 5;
        $options['cache_wsdl'] = WSDL_CACHE_BOTH;
        $httpheaders = "PHP-SOAP/" . phpversion() . " + NetSuite PHP Toolkit " . $this->nsversion;

        $options['location'] = $this->config['host'] . "/services/NetSuitePort_" . $this->config['endpoint'];
        $options['keep_alive'] = false; // do not maintain http connection to the server.
        $options['features'] = SOAP_SINGLE_ELEMENT_ARRAYS;

        if (isset($debuginfo)) {
            $httpheaders .= "\r\nDebug: true";
            $httpheaders .= "\r\nUser: " . $debuginfo["email"];
            $httpheaders .= "\r\nPassword: " . $debuginfo["password"];
            $httpheaders .= "\r\nIssue: " . $debuginfo["issue"];
        }

        $options['user_agent'] =  $httpheaders;
        $this->setPassport();

        $this->client = new \SoapClient($wsdl, $options);
    }

    /**
     * @param array $config
     */
    private function setConfig(array $config)
    {
        // Required config parameters.
        $required = array(
            'endpoint' => isset($config['endpoint']) ? $config['endpoint'] : getenv('NETSUITE_ENDPOINT'),
            'host'     => isset($config['host'])     ? $config['host']     : getenv('NETSUITE_HOST'),
            'email'    => isset($config['email'])    ? $config['email']    : getenv('NETSUITE_EMAIL'),
            'password' => isset($config['password']) ? $config['password'] : getenv('NETSUITE_PASSWORD'),
            'role'     => isset($config['role'])     ? $config['role']     : getenv('NETSUITE_ROLE'),
            'account'  => isset($config['account'])  ? $config['account']  : getenv('NETSUITE_ACCOUNT'),
        );

        // Verify all the required parameters are set.
        if (in_array(false, $required)) {
            throw new \InvalidArgumentException("You must provide all the required NetSuite configuration options.");
        }

        // Optional config parameters.
        $optional = array(
            'logging'  => isset($config['logging'])  ? $config['logging']  : getenv('NETSUITE_LOGGING'),
            'log_path' => isset($config['log_path']) ? $config['log_path'] : getenv('NETSUITE_LOG_PATH'),
        );

        $this->config = array_merge($required, $optional);
    }

    /**
     * @param bool $on
     */
    public function logRequests($on = true)
    {
        $this->config['logging'] = $on;
    }

    /**
     * @param string $logPath
     */
    public function setLogPath($logPath)
    {
        $this->config['log_path'] = $logPath;
    }

    /**
     * Set the Passport.
     */
    public function setPassport()
    {
        $this->passport = new Passport();
        $this->passport->account = $this->config['account'];
        $this->passport->email = $this->config['email'];
        $this->passport->password = $this->config['password'];
        $this->passport->role = new RecordRef();
        $this->passport->role->internalId = $this->config['role'];
    }

    /**
     * @param $option
     */
    public function useRequestLevelCredentials($option)
    {
        $this->userequest = $option;
    }

    /**
     * Set preferences.
     *
     * @param bool $warningAsError
     * @param bool $disableMandatoryCustomFieldValidation
     * @param bool $disableSystemNotesForCustomFields
     * @param bool $ignoreReadOnlyFields
     */
    public function setPreferences(
        $warningAsError = false,
        $disableMandatoryCustomFieldValidation = false,
        $disableSystemNotesForCustomFields = false,
        $ignoreReadOnlyFields = false
    ) {
        $sp = new Preferences();
        $sp->warningAsError  = $warningAsError;
        $sp->disableMandatoryCustomFieldValidation = $disableMandatoryCustomFieldValidation;
        $sp->disableSystemNotesForCustomFields = $disableSystemNotesForCustomFields;
        $sp->ignoreReadOnlyFields = $ignoreReadOnlyFields;

        $this->addHeader("preferences", $sp);
    }

    /**
     * Clear preferences.
     */
    public function clearPreferences()
    {
        $this->clearHeader("preferences");
    }

    /**
     * @param bool $bodyFieldsOnly
     * @param int $pageSize
     * @param bool $returnSearchColumns
     */
    public function setSearchPreferences($bodyFieldsOnly = true, $pageSize = 50, $returnSearchColumns = true)
    {
        $sp = new SearchPreferences();
        $sp->bodyFieldsOnly = $bodyFieldsOnly;
        $sp->pageSize = $pageSize;
        $sp->returnSearchColumns = $returnSearchColumns;

        $this->addHeader("searchPreferences", $sp);
    }

    /**
     * Clear the search preferences.
     */
    public function clearSearchPreferences()
    {
        $this->clearHeader("searchPreferences");
    }

    /**
     * @param string $header_name
     * @param mixed $header
     */
    public function addHeader($header_name, $header)
    {
        $this->soapHeaders[$header_name] = new \SoapHeader("ns", $header_name, $header);
    }

    /**
     * @param string $header_name
     */
    public function clearHeader($header_name)
    {
        unset($this->soapHeaders[$header_name]);
    }

    /**
     * @param string $operation
     * @param mixed $parameter
     * @return mixed
     */
    protected function makeSoapCall($operation, $parameter)
    {
        if ($this->userequest) {
            // use request level credentials, add passport as a SOAP header
            $this->addHeader("passport", $this->passport);
            // SoapClient, even with keep-alive set to false, keeps sending the JSESSIONID cookie back to
            // the server on subsequent requests. Unsetting the cookie to prevent this.
            $this->client->__setCookie("JSESSIONID");
        } else {
            $this->clearHeader("passport");
        }

        $response = $this->client->__soapCall($operation, array($parameter), null, $this->soapHeaders);

        $this->logSoapCall($operation);

        return $response;
    }

    /**
     * Log the last soap call as request and response XML files.
     *
     * @param string $operation
     */
    private function logSoapCall($operation)
    {
        if ($this->config['logging'] && file_exists($this->config['log_path'])) {
            $fileName = "fungku-netsuite-php-" . date("Ymd.His") . "-" . $operation;
            $logFile = $this->config['log_path'] ."/". $fileName;

            // REQUEST
            $request = $logFile . "-request.xml";
            $Handle = fopen($request, 'w');
            $Data = $this->client->__getLastRequest();
            $Data = cleanUpNamespaces($Data);

            $xml = simplexml_load_string($Data, 'SimpleXMLElement', LIBXML_NOCDATA);

            $privateFieldXpaths = array(
                '//password',
                '//password2',
                '//currentPassword',
                '//newPassword',
                '//newPassword2',
                '//ccNumber',
                '//ccSecurityCode',
                '//socialSecurityNumber',
            );

            $privateFields = $xml->xpath(implode(" | ", $privateFieldXpaths));

            foreach ($privateFields as &$field) {
                (string) $field[0] = "[Content Removed for Security Reasons]";
            }

            $stringCustomFields = $xml->xpath("//customField[@xsitype='StringCustomFieldRef']");

            foreach ($stringCustomFields as $field) {
                (string) $field->value = "[Content Removed for Security Reasons]";
            }

            $xml_string = str_replace('xsitype', 'xsi:type', $xml->asXML());

            fwrite($Handle, $xml_string);
            fclose($Handle);

            // RESPONSE
            $response = $logFile . "-response.xml";
            $Handle = fopen($response, 'w');
            $Data = $this->client->__getLastResponse();

            fwrite($Handle, $Data);
            fclose($Handle);
        }
    }
}