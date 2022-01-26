<?php

/*******************************************************************************
*                                                                              *
*   Asinius\HTTP\Client                                                        *
*                                                                              *
*   Create and manage HTTP connections. Returns Asinius\HTTP\Response objects. *
*                                                                              *
*   LICENSE                                                                    *
*                                                                              *
*   Copyright (c) 2022 Rob Sheldon <rob@rescue.dev>                            *
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
    private $_user_agent    = DEFAULT_USERAGENT;
    private $_curl          = null;
    private $_ssl_mode      = SSL_ON;
    private $_cookies       = [];
    private $_last_request  = [];


    /**
     * Execute the current request and parse the response.
     *
     * @param   string      $url
     * @param   array       $headers
     *
     * @throws  RuntimeException
     *
     * @return  \Asinius\HTTP\Response
     */
    private function _exec ($url, $headers = [])
    {
        $response_values = [
            'url'               => $url,
            'user_agent'        => $this->_user_agent,
            'response_code'     => '',
            'content_type'      => '',
            'response_string'   => '',
            'response_headers'  => [],
            'body'              => '',
        ];
        curl_setopt($this->_curl, CURLOPT_USERAGENT, $response_values['user_agent']);
        //  If the URL begins with "https" and SSL is not disabled, then
        //  temporarily enable it.
        $ssl_mode = $this->_ssl_mode;
        if ( stripos($url, 'https://') === 0 && $ssl_mode == SSL_OFF ) {
            $this->ssl_mode(SSL_ON);
        }
        //  Include any optional http headers from the application.
        if ( ! empty($headers) ) {
            $headers = array_map(function($header_key, $header_value){
                return "$header_key: $header_value";
            }, array_keys($headers), $headers);
            curl_setopt($this->_curl, CURLOPT_HTTPHEADER, $headers);
        }
        //  Add cookies.
        if ( ! empty($this->_cookies) ) {
            curl_setopt($this->_curl, CURLOPT_COOKIE, implode('; ', array_map(function($cookie_name, $cookie_value){
                return "$cookie_name=$cookie_value";
            }, array_keys($this->_cookies), $this->_cookies)));
        }
        curl_setopt($this->_curl, CURLOPT_URL, $url);
        $response_values['body'] = curl_exec($this->_curl);
        $this->_last_request = curl_getinfo($this->_curl);
        switch ( curl_errno($this->_curl) ) {
            case 0:
                //  No error.
                break;
            case 6:
                //  Failed to resolve host. Is there a network connection?
                $connection = \Asinius\Network::test();
                throw new \RuntimeException("cURL could not connect to the server for $url. A network test has been completed. " . $connection['message']);
                break;
            case 22:
                //  Server returned a status code >= 400.
                //  This gets passed back to the application.
                break;
            default:
                throw new \RuntimeException(curl_error($this->_curl), curl_errno($this->_curl));
                break;
        }
        if ( $response_values['body'] === false ) {
            throw new \RuntimeException('Unhandled error when sending http(s) request');
        }
        $response_values['response_code'] = curl_getinfo($this->_curl, CURLINFO_RESPONSE_CODE);
        $response_values['content_type']  = curl_getinfo($this->_curl, CURLINFO_CONTENT_TYPE);
        //  Separate the returned headers from the returned body by looking
        //  for the first blank line.
        while ( true ) {
            //  Loop here because 301 redirects can cause mutliple sets of
            //  headers to be returned.
            list($headers, $body) = preg_split('/((\r\n){2})|(\r{2})|(\n{2})/', $response_values['body'], 2);
            $response_values['body'] = $body;
            $headers = preg_split('/(\r\n)|\r|\n/', $headers);
            $response_values['response_string'] = array_shift($headers);
            foreach ($headers as $header) {
                list($label, $value) = explode(': ', $header, 2);
                if ( $label === 'Set-Cookie' ) {
                    $cookie_params = explode('; ', $value);
                    foreach ($cookie_params as $param) {
                        //  This is the wrong way to parse cookies. It's a
                        //  quick hack until cookie parsing is completed.
                        //  TODO.
                        list($param_name, $param_value) = explode('=', $param, 2);
                        $this->_cookies[$param_name] = $param_value;
                        break;
                    }
                }
                $response_values['response_headers'][$label] = $value;
            }
            //  Try to parse the response string, and if it contains a code
            //  that implies more headers, then keep processing them.
            //  TODO: There should probably be some fancier sanity-checking
            //  here to ensure that there actually is another chunk of headers
            //  to process.
            if ( preg_match('|^HTTP/\d\.\d\s+(?<code>\d+)|', $response_values['response_string'], $parts) == 1 ) {
                switch ($parts['code']) {
                    case '301':
                        continue 2;
                }
            }
            break;
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
     * @return  \Asinius\HTTP\Client
     */
    public function __construct ()
    {
        $this->_curl = curl_init();
        //  Set some sensible defaults.
        curl_setopt_array($this->_curl, [
            CURLOPT_AUTOREFERER     => true,
            CURLOPT_FOLLOWLOCATION  => true,
            CURLOPT_MAXREDIRS       => 5,
            CURLOPT_RETURNTRANSFER  => true,
            CURLOPT_HEADER          => true,
            CURLINFO_HEADER_OUT     => true,
        ]);
    }


    /**
     * Destroy the current curl object.
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
     * Set an optional value for the curl object.
     *
     * @param   mixed       $option
     * @param   mixed       $value
     * 
     * @return  void
     */
    public function setopt ($option, $value)
    {
        curl_setopt($this->_curl, $option, $value);
    }



    /**
     * Return the cookies currently stored in this client.
     *
     * @return  array
     */
    public function cookies ()
    {
        return $this->_cookies;
    }


    /**
     * Send an http GET request and return the body of the response, if any.
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
        return $this->_exec($url, $headers);
    }


    /**
     * Send an http POST request and return the body of the response, if any.
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
        return $this->_exec($url, $headers);
    }


    /**
     * Send an http PUT request and return the body of the response, if any.
     *
     * @param   string      $url
     * @param   mixed       $parameters
     * @param   array       $headers
     *
     * @throws  RuntimeException
     * 
     * @return  \Asinius\HTTP\Response
     */
    public function put ($url, $parameters = false, $headers = [])
    {
        if ( is_null($this->_curl) ) {
            throw new \RuntimeException('The internal curl object has disappeared');
        }
        curl_setopt($this->_curl, CURLOPT_CUSTOMREQUEST, 'PUT');
        if ( $parameters !== false ) {
            if ( is_array($parameters) ) {
                $parameters = http_build_query($parameters);
            }
            curl_setopt($this->_curl, CURLOPT_POSTFIELDS, $parameters);
        }
        return $this->_exec($url, $headers);
    }


    /**
     * Send an http DELETE request and return the body of the response, if any.
     *
     * @param   string      $url
     * @param   mixed       $parameters
     * @param   array       $headers
     *
     * @throws  RuntimeException
     * 
     * @return  \Asinius\HTTP\Response
     */
    public function delete ($url, $parameters = false, $headers = [])
    {
        if ( is_null($this->_curl) ) {
            throw new \RuntimeException('The internal curl object has disappeared');
        }
        curl_setopt($this->_curl, CURLOPT_CUSTOMREQUEST, 'DELETE');
        if ( $parameters !== false ) {
            if ( is_array($parameters) ) {
                $parameters = http_build_query($parameters);
            }
            curl_setopt($this->_curl, CURLOPT_POSTFIELDS, $parameters);
        }
        return $this->_exec($url, $headers);
    }

}
