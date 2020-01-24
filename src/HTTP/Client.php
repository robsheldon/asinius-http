<?php

/*******************************************************************************
*                                                                              *
*   Asinius\HTTP\Client                                                        *
*                                                                              *
*   Create and manage HTTP connections. Returns Asinius\HTTP\Response objects. *
*                                                                              *
*   LICENSE                                                                    *
*                                                                              *
*   Copyright (c) 2020 Rob Sheldon <rob@rescue.dev>                            *
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

//  User agent string to be used if none is specified.
const DEFAULT_USERAGENT = 'Mozilla/5.0 (cURL; x64) (KHTML, like Gecko) Asinius HTTP Client';

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
     * @author  Rob Sheldon <rob@rescue.dev>
     *
     * @param   string      $url
     *
     * @throws  RuntimeException
     *
     * @return  \Asinius\HTTP\Response
     */
    private function _exec ($url)
    {
        $response_values = [
            'user_agent'        => $this->_user_agent,
            'body'              => '',
            'response_code'     => '',
            'content_type'      => '',
            'response_string'   => '',
            'response_headers'  => [],
        ];
        curl_setopt($this->_curl, CURLOPT_USERAGENT, $response_values['user_agent']);
        //  If the URL begins with "https" and SSL is not disabled, then
        //  temporarily enable it.
        $ssl_mode = $this->_ssl_mode;
        if ( stripos($url, 'https://') === 0 && $ssl_mode == SSL_OFF ) {
            $this->ssl_mode(SSL_ON);
        }
        curl_setopt($this->_curl, CURLOPT_URL, $url);
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
        //  Restore the SSL mode.
        if ( $ssl_mode != $this->_ssl_mode ) {
            $this->ssl_mode($ssl_mode);
        }
        return new Response($response_values);
    }


    /**
     * Return a new http client.
     *
     * @author  Rob Sheldon <rob@rescue.dev>
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
     * @author  Rob Sheldon <rob@rescue.dev>
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
     * @author  Rob Sheldon <rob@rescue.dev>
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
            throw new \RuntimeException('Not a supported SSL mode: ' . gettype($mode), \Asinius\EINVAL);
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
     * @author  Rob Sheldon <rob@rescue.dev>
     *
     * @param   string|int  $user_agent
     *
     * @throws  RuntimeException
     * 
     * @return  void
     */
    public function user_agent ($user_agent)
    {
        if ( ! is_string($user_agent) ) {
            throw new \RuntimeException('Not a supported user agent type: ' . gettype($user_agent), \Asinius\EINVAL);
        }
        $this->_user_agent = $user_agent;
    }


    /**
     * Send an http GET request and return the body of the response, if any.
     *
     * @author  Rob Sheldon <rob@rescue.dev>
     *
     * @param   string      $url
     * @param   mixed       $parameters
     * @param   array       $headers
     *
     * @throws  RuntimeException
     * 
     * @return  \Asinius\HTTP\Response
     */
    public function get ($url, $parameters = false, $headers = [])
    {
        if ( is_null($this->_curl) ) {
            throw new \RuntimeException('The internal curl object has disappeared');
        }
        curl_setopt($this->_curl, CURLOPT_HTTPGET, true);
        return $this->_exec($url);
    }


    /**
     * Send an http POST request and return the body of the response, if any.
     *
     * @author  Rob Sheldon <rob@rescue.dev>
     *
     * @param   string      $url
     * @param   mixed       $parameters
     * @param   array       $headers
     *
     * @throws  RuntimeException
     * 
     * @return  \Asinius\HTTP\Response
     */
    public function post ($url, $parameters = false, $headers = [])
    {
        if ( is_null($this->_curl) ) {
            throw new \RuntimeException('The internal curl object has disappeared');
        }
        curl_setopt($this->_curl, CURLOPT_POST, true);
        if ( $parameters !== false ) {
            if ( is_array($parameters) ) {
                $parameters = http_build_query($parameters);
            }
            curl_setopt($this->_curl, CURLOPT_POSTFIELDS, $parameters);
        }
        return $this->_exec($url);
    }

}
