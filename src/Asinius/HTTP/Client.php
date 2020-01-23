<?php

/*******************************************************************************
*                                                                              *
*   Asinius\HTTP\Client                                                        *
*                                                                              *
*   Create and manage HTTP connections. Returns Asinius\HTTP\Response objects. *
*                                                                              *
*   LICENSE                                                                    *
*                                                                              *
*   Copyright (c) 2019 Rob Sheldon <rob@robsheldon.com>                        *
*                                                                              *
*   Permission is hereby granted, free of charge, to any person obtaining a    *
*   copy of this software and associated documentation files (the "Software"), *
*   to deal in the Software without restriction, including without limitation  *
*   the rights to use, copy, modify, merge, publish, distribute, sublicense,   *
*   and/or sell copies of the Software, and to permit persons to whom the      *
*   Software is furnished to do so, subject to the following conditions:       *
*                                                                              *
*   The above copyright notice and this permission notice shall be included    *
*   in all copies or substantial portions of the Software.                     *
*                                                                              *
*   THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS    *
*   OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF                 *
*   MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT.     *
*   IN NO EVENT SHALL THE AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY       *
*   CLAIM, DAMAGES OR OTHER LIABILITY, WHETHER IN AN ACTION OF CONTRACT,       *
*   TORT OR OTHERWISE, ARISING FROM, OUT OF OR IN CONNECTION WITH THE          *
*   SOFTWARE OR THE USE OR OTHER DEALINGS IN THE SOFTWARE.                     *
*                                                                              *
*   https://opensource.org/licenses/MIT                                        *
*                                                                              *
*******************************************************************************/

/*******************************************************************************
*                                                                              *
*   Examples                                                                   *
*                                                                              *
*******************************************************************************/

/*
    $http = new \Asinius\HTTP\Client();
    $response = $http->get($url);
*/


namespace Asinius\HTTP;

/*******************************************************************************
*                                                                              *
*   Constants                                                                  *
*                                                                              *
*******************************************************************************/

//  Error codes familiar to C programmers.
//  Invalid function argument
defined('EINVAL')   or define('EINVAL', 22);

//  User agent string to be used if none is specified.
const DEFAULT_USERAGENT = 'Mozilla/5.0 (cURL; x64) (KHTML, like Gecko) Asinius HTTP Client';

//  Use this to select a random user agent from the list below.
const RANDOM_USERAGENT  = -1;

//  Some common user agent strings as of March 2019.
const COMMON_USERAGENTS = [
    'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/72.0.3626.121 Safari/537.36',
    'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:65.0) Gecko/20100101 Firefox/65.0',
    'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_14_3) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/12.0.3 Safari/605.1.15',
    'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_14_3) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/72.0.3626.121 Safari/537.36',
];

//  Supported SSL modes: "on" enables full host checking & etc., "off" disables it.
const SSL_ON           = 1;
const SSL_OFF          = 0;
const SSL_DISABLE      = -1;


/*******************************************************************************
*                                                                              *
*   \Asinius\HTTP\Client                                                       *
*                                                                              *
*******************************************************************************/

class Client
{
    private $_user_agent = DEFAULT_USERAGENT;
    private $_curl = null;
    private $_ssl_mode = SSL_ON;


    /**
     * Execute the current request and parse the response.
     *
     * @author  Rob Sheldon <rob@robsheldon.com>
     *
     * @throws  RuntimeException
     *
     * @return  mixed
     */
    private function _exec ()
    {
        $response_values = array();
        if ( $this->_user_agent === RANDOM_USERAGENT ) {
            $response_values['user_agent'] = COMMON_USERAGENTS[array_rand(COMMON_USERAGENTS)];
        }
        else {
            $response_values['user_agent'] = $this->_user_agent;
        }
        curl_setopt($this->_curl, CURLOPT_USERAGENT, $response_values['user_agent']);
        $response_values['body'] = curl_exec($this->_curl);
        if ( ($error_number = curl_errno($this->_curl)) !== 0 ) {
            if ( $error_number == 6 ) {
                //  Failed to resolve host. Is there a network connection?
                //  TODO: Verify the network connection status here.
                ;
            }
            throw new \RuntimeException(curl_error($this->_curl), $error_number);
        }
        if ( $response_values['body'] === false ) {
            throw new \RuntimeException('Unhandled error when sending http(s) request');
        }
        $response_values['response_code'] = curl_getinfo($this->_curl, CURLINFO_RESPONSE_CODE);
        $response_values['content_type']  = curl_getinfo($this->_curl, CURLINFO_CONTENT_TYPE);
        //  Separate the returned headers from the returned body by looking
        //  for the first blank line.
        list($headers, $body) = preg_split('/((\r\n){2})|(\r{2})|(\n{2})/', $response_values['body'], 2);
        $response_values['body'] = $body;
        $headers = preg_split('/(\r\n)|\r|\n/', $headers);
        $response_values['response_string'] = array_shift($headers);
        foreach ($headers as $header) {
            list($label, $value) = explode(': ', $header, 2);
            $response_values['response_headers'][$label] = $value;
        }
        return $response_values;
    }


    /**
     * Return a new http client.
     *
     * @author  Rob Sheldon <rob@robsheldon.com>
     *
     * @return  http
     */
    public function __construct ()
    {
        $this->_curl = curl_init();
        //  Set some sensible defaults.
        curl_setopt($this->_curl, CURLOPT_FAILONERROR, true);
        curl_setopt($this->_curl, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($this->_curl, CURLOPT_MAXREDIRS, 5);
        curl_setopt($this->_curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($this->_curl, CURLOPT_HEADER, true);
        curl_setopt($this->_curl, CURLINFO_HEADER_OUT, true);
    }


    /**
     * Destroy the current curl object.
     *
     * @author  Rob Sheldon <rob@robsheldon.com>
     *
     * @return  void
     */
    public function __destruct ()
    {
        curl_close($this->_curl);
        $this->_curl = null;
    }


    /**
     * Set the SSL mode for the current client. SSL_ON will enable SSL verification
     * for all subsequent calls through this client; SSL_OFF will turn off SSL
     * verification by default except for URLs beginning in "https://"; SSL_DSIABLE
     * will turn off SSL verification under all circumstances.
     *
     * @author  Rob Sheldon <rob@robsheldon.com>
     *
     * @param   int         $mode
     *
     * @throws  RuntimeException
     * 
     * @return  void
     */
    public function ssl_mode ($mode)
    {
        if ( ! in_array($mode, [SSL_ON, SSL_OFF], true) ) {
            throw new \RuntimeException('Not a supported SSL mode: ' . gettype($mode), EINVAL);
        }
        if ( $mode !== $this->_ssl_mode ) {
            switch ($mode) {
                case SSL_OFF:
                case SSL_DISABLE:
                    curl_setopt($this->_curl, CURLOPT_SSL_VERIFYHOST, false); 
                    curl_setopt($this->_curl, CURLOPT_SSL_VERIFYPEER, false);
                    break;
                case SSL_ON:
                    curl_setopt($this->_curl, CURLOPT_SSL_VERIFYHOST, 2); 
                    curl_setopt($this->_curl, CURLOPT_SSL_VERIFYPEER, true);
                    break;
            }
            $this->_ssl_mode = $mode;
        }
    }


    /**
     * Set the user agent string for the current client.
     *
     * @author  Rob Sheldon <rob@robsheldon.com>
     *
     * @param   string|int  $user_agent
     *
     * @throws  RuntimeException
     * 
     * @return  void
     */
    public function user_agent ($user_agent)
    {
        if ( ! is_string($user_agent) && $user_agent !== RANDOM_USERAGENT ) {
            throw new \RuntimeException('Not a supported user agent type: ' . gettype($user_agent), EINVAL);
        }
        $this->_user_agent = $user_agent;
    }


    /**
     * Send an http GET request and return the body of the response, if any.
     *
     * @author  Rob Sheldon <rob@robsheldon.com>
     *
     * @param   string      $url
     * @param   mixed       $parameters
     * @param   array       $headers
     *
     * @throws  RuntimeException
     * 
     * @return  mixed
     */
    public function get ($url, $parameters = false, $headers = [])
    {
        if ( is_null($this->_curl) ) {
            throw new \RuntimeException('The internal curl object has disappeared');
        }
        $ssl_mode = $this->_ssl_mode;
        if ( stripos($url, 'https://') === 0 && $ssl_mode == SSL_OFF ) {
            $this->ssl_mode(SSL_ON);
        }
        curl_setopt($this->_curl, CURLOPT_HTTPGET, true);
        curl_setopt($this->_curl, CURLOPT_URL, $url);
        $response = new \Asinius\HTTP\Response($this->_exec());
        if ( $ssl_mode != $this->_ssl_mode ) {
            $this->ssl_mode($ssl_mode);
        }
        return $response;
    }


    /**
     * Send an http POST request and return the body of the response, if any.
     *
     * @author  Rob Sheldon <rob@robsheldon.com>
     *
     * @param   string      $url
     * @param   mixed       $parameters
     * @param   array       $headers
     *
     * @throws  RuntimeException
     * 
     * @return  mixed
     */
    public function post ($url, $parameters = false, $headers = [])
    {
        if ( is_null($this->_curl) ) {
            throw new \RuntimeException('The internal curl object has disappeared');
        }
        $ssl_mode = $this->_ssl_mode;
        if ( stripos($url, 'https://') === 0 && $ssl_mode == SSL_OFF ) {
            $this->ssl_mode(SSL_ON);
        }
        curl_setopt($this->_curl, CURLOPT_POST, true);
        curl_setopt($this->_curl, CURLOPT_URL, $url);
        if ( $parameters !== false ) {
            if ( is_array($parameters) ) {
                $parameters = http_build_query($parameters);
            }
            curl_setopt($this->_curl, CURLOPT_POSTFIELDS, $parameters);
        }
        $response = new \Asinius\HTTP\Response($this->_exec());
        if ( $ssl_mode != $this->_ssl_mode ) {
            $this->ssl_mode($ssl_mode);
        }
        return $response;
    }

}
