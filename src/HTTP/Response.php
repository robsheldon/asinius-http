<?php

/*******************************************************************************
*                                                                              *
*   Asinius\HTTP\Response                                                      *
*                                                                              *
*   Encapsulates an http response from a server.                               *
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
    print_r($response->body);
*/


namespace Asinius\HTTP;

/*******************************************************************************
*                                                                              *
*   \Asinius\HTTP\Response                                                     *
*                                                                              *
*******************************************************************************/

class Response
{

    private $_raw           = [];
    private $_properties    = [];


    /**
     * Parse the content-type in the response and store a sanitized copy of it.
     *
     * @author  Rob Sheldon <rob@robsheldon.com>
     *
     * @return  void
     */
    private function _parse_content_type ()
    {
        $content_type_patterns = [
            '|^application/json(; .*)?$|'   => 'application/json',
            '|^text/html(; .*)?$|'          => 'text/html',
            '|^text/plain(; .*)?$|'         => 'text/plain',
            '|^.*$|'                        => $this->_raw['content_type'],
        ];
        foreach ($content_type_patterns as $pattern => $content_type) {
            if ( preg_match($pattern, $this->_raw['content_type']) === 1 ) {
                $this->_properties['content_type'] = $content_type;
                break;
            }
        }
    }


    /**
     * Parse the response body: return plain text or unaltered html and decode
     * JSON.
     *
     * @author  Rob Sheldon <rob@robsheldon.com>
     *
     * @throws  RuntimeException
     *
     * @internal
     *
     * @return  void
     */
    private function _parse_body ()
    {
        switch ($this->content_type) {
            case 'application/json':
                //  Parse a returned JSON string.
                if ( empty($this->_raw['body']) || is_null($json_body = json_decode($this->_raw['body'], true)) ) {
                    throw new \RuntimeException('Server returned an invalid JSON response');
                }
                $this->_properties['body'] = $json_body;
                break;
            case 'text/html':
            case 'text/plain':
            default:
                $this->_properties['body'] = $this->_raw['body'];
                break;
        }
    }


    /**
     * Create a new http_response object from a set of properties. Intended to
     * be called only by the http_client class.
     *
     * @author  Rob Sheldon <rob@robsheldon.com>
     * @param   array       $response_values
     *
     * @throws  RuntimeException
     *
     * @internal
     *
     * @return  null
     */
    public function __construct ($response_values)
    {
        \Asinius\Asinius::enforce_created_by('\Asinius\HTTP\Client');
        $this->_raw = $response_values;
        $this->_properties['code'] = $response_values['response_code'];
    }


    /**
     * Return the value of a response property.
     *
     * @author  Rob Sheldon <rob@robsheldon.com>
     *
     * @param   string      $property
     *
     * @throws  RuntimeException
     * 
     * @return  mixed
     */
    public function __get ($property)
    {
        switch ($property) {
            case 'body':
                if ( ! array_key_exists('body', $this->_properties) ) {
                    $this->_parse_body();
                }
                return $this->_properties['body'];
            case 'content_type':
                if ( ! array_key_exists('content_type', $this->_properties) ) {
                    $this->_parse_content_type();
                }
                return $this->_properties['content_type'];
            case 'raw':
                return $this->_raw;
            default:
                if ( array_key_exists($property, $this->_properties) ) {
                    return $this->_properties[$property];
                }
                if ( array_key_exists($property, $this->_raw) ) {
                    return $this->_raw[$property];
                }
                throw new \RuntimeException("Undefined property: $property");
        }
    }

}
