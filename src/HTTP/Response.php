<?php

/*******************************************************************************
*                                                                              *
*   Asinius\HTTP\Response                                                      *
*                                                                              *
*   Encapsulates an http response from a server.                               *
*                                                                              *
*   LICENSE                                                                    *
*                                                                              *
*   Copyright (c) 2022 Rob Sheldon <rob@robsheldon.com>                        *
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

use RuntimeException;

/*******************************************************************************
*                                                                              *
*   \Asinius\HTTP\Response                                                     *
*                                                                              *
*******************************************************************************/

class Response implements \Asinius\Datastream
{

    private $_raw           = [];
    private $_immutable     = [];
    private $_properties    = [];
    private $_read_index    = 0;
    private $_state         = \Asinius\Datastream::STREAM_UNOPENED;


    /**
     * Parse the content-type in the response and store a sanitized copy of it.
     *
     * @return  void
     */
    private function _parse_content_type ()
    {
        $content_type_patterns = [
            '|^application/json(;.*)?$|'    => 'application/json',
            '|^text/html(;.*)?$|'           => 'text/html',
            '|^text/plain(;.*)?$|'          => 'text/plain',
            '|^.*$|'                        => $this->_raw['content_type'],
        ];
        foreach ($content_type_patterns as $pattern => $content_type) {
            if ( preg_match($pattern, $this->_raw['content_type']) === 1 ) {
                $this->_immutable['content_type'] = $content_type;
                break;
            }
        }
    }


    /**
     * Parse the response body: return plain text or unaltered html and decode
     * JSON.
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
                $this->_immutable['body'] = $json_body;
                break;
            case 'text/html':
            case 'text/plain':
            default:
                $this->_immutable['body'] = $this->_raw['body'];
                break;
        }
    }


    /**
     * Create a new http_response object from a set of properties. Intended to
     * be called only by the http_client class.
     *
     * @param   array       $response_values
     *
     * @throws  RuntimeException
     *
     * @internal
     *
     * @return  void
     */
    public function __construct ($response_values)
    {
        \Asinius\Asinius::assert_parent('\Asinius\HTTP\Client');
        $this->_raw = $response_values;
        $this->_immutable['code'] = $response_values['response_code'];
        if ( $this->_state !== \Asinius\Datastream::STREAM_ERROR ) {
            $this->_state = \Asinius\Datastream::STREAM_CONNECTED;
        }
    }


    /**
     * Support for __destruct() is required for Datastream classes, but there's
     * currently no cleanup to do here.
     *
     * @return  void
     */
    public function __destruct ()
    {
    }


    /**
     * Return the value of a response property.
     *
     * @param   string      $property
     *
     * @throws  RuntimeException
     * 
     * @return  mixed
     */
    public function __get (string $property)
    {
        switch ($property) {
            case 'body':
                if ( ! array_key_exists('body', $this->_immutable) ) {
                    $this->_parse_body();
                }
                return $this->_immutable['body'];
            case 'content_type':
                if ( ! array_key_exists('content_type', $this->_immutable) ) {
                    $this->_parse_content_type();
                }
                return $this->_immutable['content_type'];
            case 'raw':
                return $this->_raw;
            default:
                if ( array_key_exists($property, $this->_immutable) ) {
                    return $this->_immutable[$property];
                }
                if ( array_key_exists($property, $this->_raw) ) {
                    return $this->_raw[$property];
                }
                if ( array_key_exists($property, $this->_properties) ) {
                    return $this->_properties[$property];
                }
                throw new \RuntimeException("Undefined property: \"$property\"");
        }
    }


    /**
     * Support for __set() is required for Datastream classes. A Response object
     * is intended to be mostly immutable, but there's no reason not to allow
     * application code to set and retrieve custom properties.
     *
     * @param   string      $property
     * @param   mixed       $value
     *
     * @throws  \RuntimeException
     * 
     * @return  void
     */
    public function __set (string $property, $value)
    {
        if ( array_key_exists($property, $this->_immutable) || array_key_exists($property, $this->_raw) ) {
            throw new \RuntimeException("The $property is immutable");
        }
        $this->_properties[$property] = $value;
    }


    /**
     * __isset() is needed to complete support for __get() and __set().
     *
     * @param   string      $property
     *
     * @return  boolean
     */
    public function __isset (string $property) : bool
    {
        return array_key_exists($property, $this->_immutable) || array_key_exists($property, $this->_properties);
    }


    /**
     * An open() function is required for Datastream compatibility.
     * Nothing to do here.
     *
     * @return  void
     */
    public function open ()
    {
    }


    /**
     * Return true if the object is not in an error condition, false otherwise.
     *
     * @return  boolean
     */
    public function ready ()
    {
        //  TODO: This should capture any HTTP response errors and return false
        //  accordingly.
        return $this->_state === \Asinius\Datastream::STREAM_CONNECTED;
    }


    /**
     * Return any errors from the server. TODO.
     * 
     * @return  array
     */
    public function errors ()
    {
        return [];
    }


    /**
     * Required for Datastream compatibility. Not supported here.
     *
     * @return  void
     */
    public function search ($query)
    {
    }


    /**
     * Return true if there is nothing more to read(), false otherwise.
     */
    public function empty ()
    {
        return is_null($this->peek());
    }


    /**
     * Return the body of the HTTP response or the next JSON element of the
     * response if the content-type was application/json.
     *
     * @return  mixed 
     */
    public function read ()
    {
        $out = $this->peek();
        if ( ! is_null($out) ) {
            if ( is_string($out) ) {
                $this->_read_index += strlen($out);
            }
            else if ( is_array($out) ) {
                //  Need to advance our counter (for consistency) as well as PHP's
                //  internal array cursor.
                $this->_read_index++;
                next($this->_immutable['body']);
            }
        }
        return $out;
    }


    /**
     * Return the next chunk of data from the response, if any, without advancing
     * the index of the read buffer.
     *
     * @return  mixed
     */
    public function peek ()
    {
        if ( $this->_state === \Asinius\Datastream::STREAM_CONNECTED ) {
            if ( is_string($this->body) && $this->_read_index < strlen($this->body) ) {
                return substr($this->body, $this->_read_index);
            }
            else if ( is_array($this->body) && ! is_null(key($this->_immutable['body'])) ) {
                return [key($this->_immutable['body']) => current($this->_immutable['body'])];
            }
        }
        return null;
    }


    /**
     * Rewind the internal data buffer some numebr of bytes or elements (if JSON).
     *
     * @param   integer     $count
     * 
     * @return  void
     */
    public function rewind ($count = 0)
    {
        if ( $count < 0 || $this->_state !== \Asinius\Datastream::STREAM_CONNECTED ) {
            //  Tsk.
            return;
        }
        if ( is_string($this->body) ) {
            $this->_read_index = $count === 0 ? 0 : max($this->_read_index - $count, 0);
        }
        else if ( is_array($this->body) ) {
            if ( $count === 0 ) {
                reset($this->_immutable['body']);
                $this->_read_index = 0;
            }
            else {
                while ( $count-- > 0 ) {
                    prev($this->_immutable['body']);
                    $this->_read_index--;
                    if ( is_null(key($this->_immutable['body'])) ) {
                        break;
                    }
                }
            }
        }
    }


    /**
     * Write to the http response. This is unsupported for this object.
     *
     * @return  void
     */
    public function write ($data)
    {
    }


    /**
     * Close the Datastream and prevent any further reads or writes.
     * (Property access should still be okay.)
     *
     * @return  void
     */
    public function close ()
    {
        $this->_state = \Asinius\Datastream::STREAM_CLOSED;
    }
}
