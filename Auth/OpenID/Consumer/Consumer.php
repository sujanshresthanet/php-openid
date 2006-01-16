<?php

/**
 * This module documents the main interface with the OpenID consumer
 * libary. The only part of the library which has to be used and isn't
 * documented in full here is the store required to create an
 * OpenIDConsumer instance. More on the abstract store type and
 * concrete implementations of it that are provided in the
 * documentation for the constructor of the OpenIDConsumer class.
 *
 * OVERVIEW
 *
 * The OpenID identity verification process most commonly uses the
 * following steps, as visible to the user of this library:
 *
 * 1. The user enters their OpenID into a field on the consumer's
 *    site, and hits a login button.
 * 2. The consumer site checks that the entered URL describes an
 *    OpenID page by fetching it and looking for appropriate link tags
 *    in the head section.
 * 3. The consumer site sends the browser a redirect to the identity
 *    server.  This is the authentication request as described in the
 *    OpenID specification.
 * 4. The identity server's site sends the browser a redirect back to
 *    the consumer site.  This redirect contains the server's response
 *    to the authentication request.
 *
 * The most important part of the flow to note is the consumer's site
 * must handle two separate HTTP requests in order to perform the full
 * identity check.
 *
 * LIBRARY DESIGN
 *
 * This consumer library is designed with that flow in mind.  The goal
 * is to make it as easy as possible to perform the above steps
 * securely.
 *
 * At a high level, there are two important parts in the consumer
 * library.  The first important part is this module, which contains
 * the interface to actually use this library.  The second is the
 * Auth_OpenID_Interface class, which describes the interface to use if
 * you need to create a custom method for storing the state this
 * library needs to maintain between requests.
 *
 * In general, the second part is less important for users of the
 * library to know about, as several implementations are provided
 * which cover a wide variety of situations in which consumers may
 * use the library.
 *
 * This module contains a class, Auth_OpenID_Consumer, with methods
 * corresponding to the actions necessary in each of steps 2, 3, and 4
 * described in the overview.  Use of this library should be as easy
 * as creating a Auth_OpenID_Consumer instance and calling the methods
 * appropriate for the action the site wants to take.
 *
 * STORES AND DUMB MODE
 *
 * OpenID is a protocol that works best when the consumer site is able
 * to store some state.  This is the normal mode of operation for the
 * protocol, and is sometimes referred to as smart mode.  There is
 * also a fallback mode, known as dumb mode, which is available when
 * the consumer site is not able to store state.  This mode should be
 * avoided when possible, as it leaves the implementation more
 * vulnerable to replay attacks.
 *
 * The mode the library works in for normal operation is determined by
 * the store that it is given.  The store is an abstraction that
 * handles the data that the consumer needs to manage between http
 * requests in order to operate efficiently and securely.
 *
 * Several store implementation are provided, and the interface is
 * fully documented so that custom stores can be used as well.  See
 * the documentation for the Auth_OpenID_Consumer class for more
 * information on the interface for stores.  The concrete
 * implementations that are provided allow the consumer site to store
 * the necessary data in several different ways: in the filesystem, in
 * a MySQL database, or in an SQLite database.
 *
 * There is an additional concrete store provided that puts the system
 * in dumb mode.  This is not recommended, as it removes the library's
 * ability to stop replay attacks reliably.  It still uses time-based
 * checking to make replay attacks only possible within a small
 * window, but they remain possible within that window.  This store
 * should only be used if the consumer site has no way to retain data
 * between requests at all.
 *
 * IMMEDIATE MODE
 *
 * In the flow described above, the user may need to confirm to the
 * identity server that it's ok to authorize his or her identity.  The
 * server may draw pages asking for information from the user before
 * it redirects the browser back to the consumer's site.  This is
 * generally transparent to the consumer site, so it is typically
 * ignored as an implementation detail.
 *
 * There can be times, however, where the consumer site wants to get a
 * response immediately.  When this is the case, the consumer can put
 * the library in immediate mode.  In immediate mode, there is an
 * extra response possible from the server, which is essentially the
 * server reporting that it doesn't have enough information to answer
 * the question yet.  In addition to saying that, the identity server
 * provides a URL to which the user can be sent to provide the needed
 * information and let the server finish handling the original
 * request.
 *
 * USING THIS LIBRARY
 *
 * Integrating this library into an application is usually a
 * relatively straightforward process.  The process should basically
 * follow this plan:
 *
 * Add an OpenID login field somewhere on your site.  When an OpenID
 * is entered in that field and the form is submitted, it should make
 * a request to the your site which includes that OpenID URL.
 *
 * When your site receives that request, it should create an
 * Auth_OpenID_Consumer instance, and call beginAuth on it.  If
 * beginAuth completes successfully, it will return an
 * Auth_OpenID_AuthRequest instance.  Otherwise it will provide some
 * useful information for giving the user an error message.
 *
 * Now that you have the Auth_OpenID_AuthRequest object, you need to
 * preserve the value in its $token field for lookup on the user's
 * next request from your site.  There are several approaches for
 * doing this which will work.  If your environment has any kind of
 * session-tracking system, storing the token in the session is a good
 * approach.  If it doesn't you can store the token in either a cookie
 * or in the return_to url provided in the next step.
 *
 * The next step is to call the constructRedirect method on the
 * Auth_OpenID_Consumer object.  Pass it the Auth_OpenID_AuthRequest
 * object returned by the previous call to beginAuth along with the
 * return_to and trust_root URLs.  The return_to URL is the URL that
 * the OpenID server will send the user back to after attempting to
 * verify his or her identity.  The trust_root is the URL (or URL
 * pattern) that identifies your web site to the user when he or she
 * is authorizing it.
 *
 * Next, send the user a redirect to the URL generated by
 * constructRedirect.
 *
 * That's the first half of the process.  The second half of the
 * process is done after the user's ID server sends the user a
 * redirect back to your site to complete their login.
 *
 * When that happens, the user will contact your site at the URL given
 * as the return_to URL to the constructRedirect call made above.  The
 * request will have several query parameters added to the URL by the
 * identity server as the information necessary to finish the request.
 *
 * When handling this request, the first thing to do is check the
 * 'openid.return_to' parameter.  If it doesn't match the URL that
 * the request was actually sent to (the URL the request was actually
 * sent to will contain the openid parameters in addition to any in
 * the return_to URL, but they should be identical other than that),
 * that is clearly suspicious, and the request shouldn't be allowed to
 * proceed.

 * Otherwise, the next step is to extract the token value set in the
 * first half of the OpenID login.  Create a Auth_OpenID_Consumer
 * object, and call its completeAuth method with that token and a
 * dictionary of all the query arguments.  This call will return a
 * status code and some additional information describing the the
 * server's response.  See the documentation for completeAuth for a
 * full explanation of the possible responses.
 *
 * At this point, you have an identity URL that you know belongs to
 * the user who made that request.  Some sites will use that URL
 * directly as the user name.  Other sites will want to map that URL
 * to a username in the site's traditional namespace.  At this point,
 * you can take whichever action makes the most sense.
 *
 * PHP versions 4 and 5
 *
 * LICENSE: See the COPYING file included in this distribution.
 *
 * @package OpenID
 * @author JanRain, Inc. <openid@janrain.com>
 * @copyright 2005 Janrain, Inc.
 * @license http://www.gnu.org/copyleft/lesser.html LGPL
 */

/**
 * Require utility classes and functions for the consumer.
 */
require_once("Auth/OpenID/CryptUtil.php");
require_once("Auth/OpenID/KVForm.php");
require_once("Auth/OpenID/OIDUtil.php");
require_once("Auth/OpenID/Association.php");
require_once("Auth/OpenID/DiffieHellman.php");
require_once("Auth/OpenID/Consumer/Parse.php");
require_once("Auth/OpenID/Consumer/Fetchers.php");

/**
 * This is the status code returned when either the of the beginAuth
 * or completeAuth methods return successfully.
 */
$Auth_OpenID_SUCCESS = 'success';

/**
 * This is the status code completeAuth returns when the value it
 * received indicated an invalid login.
 */
$Auth_OpenID_FAILURE = 'failure';

/**
 * This is the status code completeAuth returns when the
 * Auth_OpenID_Consumer instance is in immediate mode, and the identity
 * server sends back a URL to send the user to to complete his or her
 * login.
 */
$Auth_OpenID_SETUP_NEEDED = 'setup needed';

/**
 * This is the status code beginAuth returns when it is unable to
 * fetch the OpenID URL the user entered.
 */
$Auth_OpenID_HTTP_FAILURE = 'http failure';

/**
 * This is the status code beginAuth returns when the page fetched
 * from the entered OpenID URL doesn't contain the necessary link tags
 * to function as an identity page.
*/
$Auth_OpenID_PARSE_ERROR = 'parse error';

/**
 * This is the characters that the nonces are made from.
 */
$_Auth_OpenID_NONCE_CHRS = $GLOBALS['_Auth_OpenID_letters'] .
    $GLOBALS['_Auth_OpenID_digits'];

/**
 * This is the number of seconds the tokens generated by this library
 * will be valid for.  If you want to change the lifetime of a token,
 * set this value to the desired lifespan, in seconds.
 */
$_Auth_OpenID_TOKEN_LIFETIME = 60 * 5; // five minutes

/**
 * This is the number of characters in the generated nonce for each
 * transaction.
 */
$_Auth_OpenID_NONCE_LEN = 8;

/**
 * This class is the interface to the OpenID consumer logic.
 * Instances of it maintain no per-request state, so they can be
 * reused (or even used by multiple threads concurrently) as needed.
 *
 * @package OpenID
 */
class Auth_OpenID_Consumer {

    /**
     * This method initializes a new Auth_OpenID_Consumer instance to
     * access the library.
     *
     * @param Auth_OpenID_OpenIDStore $store This must be an object
     * that implements the interface in Auth_OpenID_Store.  Several
     * concrete implementations are provided, to cover most common use
     * cases.  For stores backed by MySQL, PostgreSQL, or SQLite, see
     * the Auth_OpenID_SQLStore class and its sublcasses.  For a
     * filesystem-backed store, see the Auth_OpenID_FileStore module.
     * As a last resort, if it isn't possible for the server to store
     * state at all, an instance of Auth_OpenID_DumbStore can be used.
     * This should be an absolute last resort, though, as it makes the
     * consumer vulnerable to replay attacks over the lifespan of the
     * tokens the library creates.
     *
     * @param Auth_OpenID_HTTPFetcher $fetcher This is an optional
     * reference to an instance of Auth_OpenID_HTTPFetcher.  If
     * present, the provided fetcher is used by the library to fetch
     * users' identity pages and make direct requests to the identity
     * server.  If it is not present, a default fetcher is used.  The
     * default fetcher uses curl if the Curl bindings are available,
     * and uses a raw socket POST if not.
     *
     * @param bool $immediate This is an optional boolean value.  It
     * controls whether the library uses immediate mode, as explained
     * in the module description.  The default value is False, which
     * disables immediate mode.
     */
    function Auth_OpenID_Consumer(&$store, $fetcher = null, $immediate = false)
    {
        if ($store === null) {
            trigger_error("Must supply non-null store to create consumer",
                          E_USER_ERROR);
            return null;
        }

        $this->store =& $store;

        if ($fetcher === null) {
            $this->fetcher = Auth_OpenID_getHTTPFetcher();
        } else {
            $this->fetcher =& $fetcher;
        }

        if ($immediate) {
            $this->mode = 'checkid_immediate';
        } else {
            $this->mode = 'checkid_setup';
        }

        $this->immediate = $immediate;
    }

    /**
     * This method is called to start the OpenID login process.
     *
     * First, the user's claimed identity page is fetched, to
     * determine their identity server.  If the page cannot be fetched
     * or if the page does not have the necessary link tags in it,
     * this method returns one of $Auth_OpenID_HTTP_FAILURE or
     * $Auth_OpenID_PARSE_ERROR, depending on where the process failed.
     *
     * Second, unless the store provided is a dumb store, it checks to
     * see if it has an association with that identity server, and
     * creates and stores one if not.
     *
     * Third, it generates a signed token for this authentication
     * transaction, which contains a timestamp, a nonce, and the
     * information needed in Step 4 (above) in the module overview.
     * The token is used by the library to make handling the various
     * pieces of information needed in Step 4 (above) easy and secure.
     *
     * The token generated must be preserved until Step 4 (above),
     * which is after the redirect to the OpenID server takes place.
     * This means that the token must be preserved across http
     * requests.  There are three basic approaches that might be used
     * for storing the token.  First, the token could be put in the
     * return_to URL passed into the constructRedirect method.
     * Second, the token could be stored in a cookie.  Third, in an
     * environment that supports user sessions, the session is a good
     * spot to store the token.
     *
     * @param string $user_url This is the url the user entered as
     * their OpenID.  This call takes care of normalizing it and
     * resolving any redirects the server might issue.
     *
     * @return array $array This method returns an array containing a
     * status code and additional information about the code.
     *
     * If there was a problem fetching the identity page the user
     * gave, the status code is set to $Auth_OpenID_HTTP_FAILURE, and
     * the additional information value is either set to null if the
     * HTTP transaction failed or the HTTP return code, which will be
     * in the 400-500 range. This additional information value may
     * change in a future release.
     *
     * If the identity page fetched successfully, but didn't include
     * the correct link tags, the status code is set to
     * $Auth_OpenID_PARSE_ERROR, and the additional information value
     * is currently set to null.  The additional information value may
     * change in a future release.
     *
     * Otherwise, the status code is set to $Auth_OpenID_SUCCESS, and
     * the additional information is an instance of
     * Auth_OpenID_AuthRequest.  The $token attribute contains the
     * token to be preserved for the next HTTP request.  The
     * $server_url might also be of interest, if you wish to blacklist
     * or whitelist OpenID servers.  The other contents of the object
     * are information needed in the constructRedirect call.
     */
    function beginAuth($user_url)
    {
        global $Auth_OpenID_SUCCESS;

        list($status, $info) = $this->_findIdentityInfo($user_url);
        if ($status != $Auth_OpenID_SUCCESS) {
            return array($status, $info);
        }

        list($consumer_id, $server_id, $server_url) = $info;
        return $this->_gotIdentityInfo($consumer_id, $server_id, $server_url);
    }

    /**
     * This method is called to construct the redirect URL sent to the
     * browser to ask the server to verify its identity.  This is
     * called in Step 3 (above) of the flow described in the overview.
     * The generated redirect should be sent to the browser which
     * initiated the authorization request.
     *
     * @param Auth_OpenID_AuthRequest $auth_request This must be a
     * Auth_OpenID_AuthRequest instance which was returned from a
     * previous call to beginAuth.  It contains information found
     * during the beginAuth call which is needed to build the redirect
     * URL.
     *
     * @param string $return_to This is the URL that will be included
     * in the generated redirect as the URL the OpenID server will
     * send its response to.  The URL passed in must handle OpenID
     * authentication responses.
     *
     * @param string $trust_root This is a URL that will be sent to
     * the server to identify this site.  The OpenID spec at
     * http://www.openid.net/specs.bml#mode-checkid_immediate has more
     * information on what the trust_root value is for and what its
     * form can be.  While the trust root is officially optional in
     * the OpenID specification, this implementation requires that it
     * be set.  Nothing is actually gained by leaving out the trust
     * root, as you can get identical behavior by specifying the
     * return_to URL as the trust root.
     *
     * @return string $url This method returns a string containing the
     * URL to redirect to when such a URL is successfully constructed.
    */
    function constructRedirect($auth_request, $return_to, $trust_root)
    {
        $assoc = $this->_getAssociation($auth_request->server_url,
                                        $replace = 1);
        // Because _getAssociation is asynchronous if the association is
        // not already in the store.

        if ($assoc === null) {
            trigger_error("Could not get association for redirection",
                          E_USER_WARNING);
            return null;
        }

        return $this->_constructRedirect($assoc, $auth_request,
                                         $return_to, $trust_root);
    }

    /**
     * Given an array of CGI data from PHP, this method replaces
     * "openid_" with "openid." in the CGI key strings (NOT the
     * values).  This is to work around the fact that PHP will mangle
     * the CGI key strings to protect against register_globals
     * problems.
     */
    function fixResponse($arr)
    {
        // Depending on PHP settings, the query data received may have
        // been modified so that incoming "." values in the keys have
        // been replaced with underscores.  Look specifically for
        // "openid_" and replace it with "openid.".
        $result = array();

        foreach ($arr as $key => $value) {
            $new_key = str_replace("openid_", "openid.", $key);
            $result[$new_key] = $value;
        }

        return $result;
    }

    /**
     * This method is called to interpret the server's response to an
     * OpenID request.  It is called in Step 4 of the flow described
     * in the overview.
     *
     * The return value is a pair, consisting of a status and
     * additional information.  The status values are strings, but
     * should be referred to by their symbolic values:
     * $Auth_OpenID_SUCCESS, $Auth_OpenID_FAILURE, and
     * $Auth_OpenID_SETUP_NEEDED.
     *
     * When $Auth_OpenID_SUCCESS is returned, the additional
     * information returned is either null or a string.  If it is
     * null, it means the user cancelled the login, and no further
     * information can be determined.  If the additional information
     * is a string, it is the identity that has been verified as
     * belonging to the user making this request.
     *
     * When $Auth_OpenID_FAILURE is returned, the additional
     * information is either null or a string.  In either case, this
     * code means that the identity verification failed.  If it can be
     * determined, the identity that failed to verify is returned.
     * Otherwise null is returned.
     *
     * When $Auth_OpenID_SETUP_NEEDED is returned, the additional
     * information is the user setup URL.  This is a URL returned only
     * as a response to requests made with openid.mode=immediate,
     * which indicates that the login was unable to proceed, and the
     * user should be sent to that URL if they wish to proceed with
     * the login.
     *
     * @param string $token This is the token for this authentication
     * transaction, generated by the call to beginAuth.
     *
     * @param array $query This is a dictionary-like object containing
     * the query parameters the OpenID server included in its redirect
     * back to the return_to URL.  The keys and values should both be
     * url-unescaped.
     *
     * @return array $array Returns the status of the response and any
     * additional information, as described above.
     */
    function completeAuth($token, $query)
    {
        global $Auth_OpenID_SUCCESS, $Auth_OpenID_FAILURE;

        $query = $this->fixResponse($query);

        $mode = Auth_OpenID_array_get($query, 'openid.mode', '');

        if ($mode == 'cancel') {
            return array($Auth_OpenID_SUCCESS, null);
        } else if ($mode == 'error') {

            $error = Auth_OpenID_array_get($query, 'openid.error', null);

            if ($error !== null) {
                Auth_OpenID_log($error);
            }
            return array($Auth_OpenID_FAILURE, null);
        } else if ($mode == 'id_res') {
            return $this->_doIdRes($token, $query);
        } else {
            return array($Auth_OpenID_FAILURE, null);
        }
    }

    /**
     * @access private
     */
    function _gotIdentityInfo($consumer_id, $server_id, $server_url)
    {
        global $Auth_OpenID_SUCCESS, $_Auth_OpenID_NONCE_CHRS,
            $_Auth_OpenID_NONCE_LEN;

        $nonce = Auth_OpenID_CryptUtil::randomString($_Auth_OpenID_NONCE_LEN,
                                                    $_Auth_OpenID_NONCE_CHRS);

        $token = $this->_genToken($nonce, $consumer_id,
                                  $server_id, $server_url);
        return array($Auth_OpenID_SUCCESS,
                     new Auth_OpenID_AuthRequest($token, $server_id,
                                                $server_url, $nonce));
    }

    /**
     * @access private
     */
    function _constructRedirect($assoc, $auth_req, $return_to, $trust_root)
    {
        $redir_args = array(
                            'openid.identity' => $auth_req->server_id,
                            'openid.return_to' => $return_to,
                            'openid.trust_root' => $trust_root,
                            'openid.mode' => $this->mode,
                            );

        if ($assoc !==  null) {
            $redir_args['openid.assoc_handle'] = $assoc->handle;
        }

        $this->store->storeNonce($auth_req->nonce);
        return strval(Auth_OpenID_appendArgs($auth_req->server_url,
                                            $redir_args));
    }

    /**
     * @access private
     */
    function _doIdRes($token, $query)
    {
        global $Auth_OpenID_FAILURE, $Auth_OpenID_SETUP_NEEDED,
            $Auth_OpenID_SUCCESS;

        $ret = $this->_splitToken($token);
        if ($ret === null) {
            return array($Auth_OpenID_FAILURE, null);
        }

        list($nonce, $consumer_id, $server_id, $server_url) = $ret;

        $return_to = Auth_OpenID_array_get($query, 'openid.return_to', null);
        $server_id2 = Auth_OpenID_array_get($query, 'openid.identity', null);
        $assoc_handle = Auth_OpenID_array_get($query,
                                             'openid.assoc_handle', null);

        if (($return_to === null) ||
            ($server_id === null) ||
            ($assoc_handle === null)) {
            return array($Auth_OpenID_FAILURE, $consumer_id);
        }

        if ($server_id != $server_id2) {
            return array($Auth_OpenID_FAILURE, $consumer_id);
        }

        $user_setup_url = Auth_OpenID_array_get($query,
                                               'openid.user_setup_url', null);

        if ($user_setup_url !== null) {
            return array($Auth_OpenID_SETUP_NEEDED, $user_setup_url);
        }

        $assoc = $this->store->getAssociation($server_url);

        if (($assoc === null) ||
            ($assoc->handle != $assoc_handle) ||
            ($assoc->getExpiresIn() <= 0)) {
            // It's not an association we know about.  Dumb mode is
            // our only possible path for recovery.
            return array($this->_checkAuth($nonce, $query, $server_url),
                         $consumer_id);
        }

        // Check the signature
        $sig = Auth_OpenID_array_get($query, 'openid.sig', null);
        $signed = Auth_OpenID_array_get($query, 'openid.signed', null);
        if (($sig === null) ||
            ($signed === null)) {
            return array($Auth_OpenID_FAILURE, $consumer_id);
        }

        $signed_list = explode(",", $signed);
        $v_sig = $assoc->signDict($signed_list, $query);

        if ($v_sig != $sig) {
            return array($Auth_OpenID_FAILURE, $consumer_id);
        }

        if (!$this->store->useNonce($nonce)) {
            return array($Auth_OpenID_FAILURE, $consumer_id);
        }

        return array($Auth_OpenID_SUCCESS, $consumer_id);
    }

    /**
     * @access private
     */
    function _checkAuth($nonce, $query, $server_url)
    {
        global $Auth_OpenID_FAILURE, $Auth_OpenID_SUCCESS;

        // XXX: send only those arguments that were signed?
        $signed = Auth_OpenID_array_get($query, 'openid.signed', null);
        if ($signed === null) {
            return $Auth_OpenID_FAILURE;
        }

        $whitelist = array('assoc_handle', 'sig',
                           'signed', 'invalidate_handle');

        $signed = array_merge(explode(",", $signed), $whitelist);

        $check_args = array();

        foreach ($query as $key => $value) {
            if (in_array(substr($key, 7), $signed)) {
                $check_args[$key] = $value;
            }
        }

        $check_args['openid.mode'] = 'check_authentication';
        $post_data = Auth_OpenID_http_build_query($check_args);

        $ret = $this->fetcher->post($server_url, $post_data);
        if ($ret === null) {
            return $Auth_OpenID_FAILURE;
        }

        $results = Auth_OpenID_KVForm::kvToArray($ret[2]);
        $is_valid = Auth_OpenID_array_get($results, 'is_valid', 'false');

        if ($is_valid == 'true') {
            $invalidate_handle = Auth_OpenID_array_get($results,
                                                      'invalidate_handle',
                                                      null);
            if ($invalidate_handle !== null) {
                $this->store->removeAssociation($server_url,
                                                $invalidate_handle);
            }

            if (!$this->store->useNonce($nonce)) {
                return $Auth_OpenID_FAILURE;
            }

            return $Auth_OpenID_SUCCESS;
        }

        $error = Auth_OpenID_array_get($results, 'error', null);
        if ($error !== null) {
            Auth_OpenID_log(sprintf("Error message from server during " .
                                   "check_authentication: %s", error));
        }

        return $Auth_OpenID_FAILURE;
    }

    /**
     * @access private
     */
    function _getAssociation($server_url, $replace = false)
    {
        global $_Auth_OpenID_TOKEN_LIFETIME;

        if ($this->store->isDumb()) {
            return null;
        }

        $assoc = $this->store->getAssociation($server_url);

        if (($assoc === null) ||
            ($replace && ($assoc->getExpiresIn() <
                          $_Auth_OpenID_TOKEN_LIFETIME))) {
            $dh = new Auth_OpenID_DiffieHellman();
            $body = $this->_createAssociateRequest($dh);
            $assoc = $this->_fetchAssociation($dh, $server_url, $body);
        }

        return $assoc;
    }

    /**
     * @access private
     */
    function _genToken($nonce, $consumer_id, $server_id, $server_url)
    {
        $timestamp = strval(time());
        $elements = array($timestamp, $nonce,
                          $consumer_id, $server_id, $server_url);

        $joined = implode("\x00", $elements);
        $sig = Auth_OpenID_CryptUtil::hmacSha1($this->store->getAuthKey(),
                                              $joined);

        return Auth_OpenID_toBase64($sig . $joined);
    }

    /**
     * @access private
     */
    function _splitToken($token)
    {
        global $_Auth_OpenID_TOKEN_LIFETIME;

        $token = Auth_OpenID_fromBase64($token);
        if (strlen($token) < 20) {
            return null;
        }

        $sig = substr($token, 0, 20);
        $joined = substr($token, 20);
        if (Auth_OpenID_CryptUtil::hmacSha1(
              $this->store->getAuthKey(), $joined) != $sig) {
            return null;
        }

        $split = explode("\x00", $joined);
        if (count($split) != 5) {
            return null;
        }

        $ts = intval($split[0]);
        if ($ts == 0) {
            return null;
        }

        if ($ts + $_Auth_OpenID_TOKEN_LIFETIME < time()) {
            return null;
        }

        return array_slice($split, 1);
    }

    /**
     * @access private
     */
    function _findIdentityInfo($identity_url)
    {
        global $Auth_OpenID_HTTP_FAILURE;

        $url = Auth_OpenID_normalizeUrl($identity_url);
        $ret = $this->fetcher->get($url);
        if ($ret === null) {
            return array($Auth_OpenID_HTTP_FAILURE, null);
        }

        list($http_code, $consumer_id, $data) = $ret;
        if ($http_code != 200) {
            return array($Auth_OpenID_HTTP_FAILURE, $http_code);
        }

        // This method is split in two this way to allow for
        // asynchronous implementations of _findIdentityInfo.
        return $this->_parseIdentityInfo($data, $consumer_id);
    }

    /**
     * @access private
     */
    function _parseIdentityInfo($data, $consumer_id)
    {
        global $Auth_OpenID_PARSE_ERROR, $Auth_OpenID_SUCCESS;

        $link_attrs = Auth_OpenID_parseLinkAttrs($data);
        $server = Auth_OpenID_findFirstHref($link_attrs, 'openid.server');
        $delegate = Auth_OpenID_findFirstHref($link_attrs, 'openid.delegate');

        if ($server === null) {
            return array($Auth_OpenID_PARSE_ERROR, null);
        }

        if ($delegate !== null) {
            $server_id = $delegate;
        } else {
            $server_id = $consumer_id;
        }

        $urls = array($consumer_id, $server_id, $server);

        $normalized = array();

        foreach ($urls as $url) {
            $normalized[] = Auth_OpenID_normalizeUrl($url);
        }

        return array($Auth_OpenID_SUCCESS, $normalized);
    }

    /**
     * @access private
     */
    function _createAssociateRequest($dh, $args = null)
    {
        global $_Auth_OpenID_DEFAULT_MOD, $_Auth_OpenID_DEFAULT_GEN;

        if ($args === null) {
            $args = array();
        }

        $cpub = Auth_OpenID_CryptUtil::longToBase64($dh->public);

        $args = array_merge($args, array(
                                         'openid.mode' =>  'associate',
                                         'openid.assoc_type' => 'HMAC-SHA1',
                                         'openid.session_type' => 'DH-SHA1',
                                         'openid.dh_consumer_public' => $cpub
                                         ));

        if (($dh->mod != $_Auth_OpenID_DEFAULT_MOD) ||
            ($dh->gen != $_Auth_OpenID_DEFAULT_GEN)) {
            $args = array_merge($args,
                     array(
                           'openid.dh_modulus' =>
                           Auth_OpenID_CryptUtil::longToBase64($dh->modulus),
                           'openid.dh_gen' =>
                           Auth_OpenID_CryptUtil::longToBase64($dh->generator)
                           ));
        }

        return Auth_OpenID_http_build_query($args);
    }

    /**
     * @access private
     */
    function _fetchAssociation($dh, $server_url, $body)
    {
        $ret = $this->fetcher->post($server_url, $body);
        if ($ret === null) {
            $fmt = 'Getting association: failed to fetch URL: %s';
            Auth_OpenID_log(sprintf($fmt, $server_url));
            return null;
        }

        list($http_code, $url, $data) = $ret;
        $results = Auth_OpenID_KVForm::kvToArray($data);
        if ($http_code == 400) {
            $server_error = Auth_OpenID_array_get($results, 'error',
                                                 '<no message from server>');

            $fmt = 'Getting association: error returned from server %s: %s';
            Auth_OpenID_log(sprintf($fmt, $server_url, $server_error));
            return null;
        } else if ($http_code != 200) {
            $fmt = 'Getting association: bad status code from server %s: %s';
            Auth_OpenID_log(sprintf($fmt, $server_url, $http_code));
            return null;
        }

        $results = Auth_OpenID_KVForm::kvToArray($data);

        return $this->_parseAssociation($results, $dh, $server_url);
    }

    /**
     * @access private
     */
    function _parseAssociation($results, $dh, $server_url)
    {
        $required_keys = array('assoc_type', 'assoc_handle',
                               'dh_server_public', 'enc_mac_key');

        foreach ($required_keys as $key) {
            if (!array_key_exists($key, $results)) {
                Auth_OpenID_log(sprintf("Getting association: missing key in ".
                                       "response from %s: %s",
                                       $server_url, $key),
                               E_USER_WARNING);
                return null;
            }
        }

        $assoc_type = $results['assoc_type'];
        if ($assoc_type != 'HMAC-SHA1') {
            $fmt = 'Unsupported assoc_type returned from server %s: %s';
            Auth_OpenID_log(sprintf($fmt, $server_url, $assoc_type));
            return null;
        }

        $assoc_handle = $results['assoc_handle'];
        $expires_in = intval(Auth_OpenID_array_get($results, 'expires_in',
                             '0'));

        $session_type = Auth_OpenID_array_get($results, 'session_type', null);
        if ($session_type === null) {
            $secret = Auth_OpenID_fromBase64($results['mac_key']);
        } else {
            $fmt = 'Unsupported session_type returned from server %s: %s';
            if ($session_type != 'DH-SHA1') {
                Auth_OpenID_log(sprintf($fmt, $server_url, $session_type));
                return null;
            }

            $spub = Auth_OpenID_CryptUtil::base64ToLong(
                         $results['dh_server_public']);

            $enc_mac_key = Auth_OpenID_CryptUtil::fromBase64(
                         $results['enc_mac_key']);

            $secret = $dh->xorSecret($spub, $enc_mac_key);
        }

        $assoc = Auth_OpenID_Association::fromExpiresIn($expires_in,
                                                       $assoc_handle,
                                                       $secret,
                                                       $assoc_type);

        $this->store->storeAssociation($server_url, $assoc);
        return $assoc;
    }
}

/**
 * This class represents an in-progress OpenID authentication request.
 * It exists to make transferring information between the beginAuth
 * and constructRedirect methods easier.  Users of the OpenID consumer
 * library will need to be aware of the $token value, and may care
 * about the $server_url value.  All other fields are internal
 * information for the library which the user of the library shouldn't
 * touch at all.
 *
 * The 'token' is the token generated by the library.  It must be
 * saved until the user's return request, via whatever mechanism works
 * best for this consumer application.
 *
 * The 'server_url' is the URL of the identity server that will be
 * used.  It isn't necessary to do anything with this value, but it is
 * available for consumers that wish to either blacklist or whitelist
 * OpenID servers.
 *
 * @package OpenID
 */
class Auth_OpenID_AuthRequest {
    function Auth_OpenID_AuthRequest($token, $server_id, $server_url, $nonce)
    {
        $this->token = $token;
        $this->server_id = $server_id;
        $this->server_url = $server_url;
        $this->nonce = $nonce;
    }
}

?>