<?php

/*******************************************************************************
*                                                                              *
*   Asinius\HTTP\URL                                                           *
*                                                                              *
*   Coordinates operations for http-like URLs.                                 *
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

namespace Asinius\HTTP;


/*******************************************************************************
*                                                                              *
*   \Asinius\HTTP\URL                                                          *
*                                                                              *
*******************************************************************************/

class URL
{

    protected static $_client = null;

    /**
     * Accept a URL object or string, and return a Datastream containing the
     * response for that URL.
     * 
     * @param   mixed       $url
     * 
     * @return \Asinius\Datastream
     */
    public static function open ($url)
    {
        $http_client = is_null(static::$_client) ? new Client() : static::$_client;
        $response = $http_client->get("$url");
        //  open:: has already been called upstream, so open the Datastream now.
        $response->open();
        return $response;
    }


    /**
     * Set or return the client object used for http requests.
     * 
     * @param   mixed       $client
     *
     * @throws  \RuntimeException
     * 
     * @return  void
     */
    public static function client ($client = false)
    {
        if ( $client === false ) {
            return static::$_client;
        }
        if ( is_null($client) || (is_object($client) && is_a($property, '\Asinius\HTTP\Client')) ) {
            static::$_client = $client;
        }
        else {
            throw new \RuntimeException("HTTP client must be an \Asinius\HTTP\Client or subclass");
        }
    }

}