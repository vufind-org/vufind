<?php
/*
 * THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS
 * "AS IS" AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT
 * LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR
 * A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT
 * OWNER OR CONTRIBUTORS BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL,
 * SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT
 * LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES; LOSS OF USE,
 * DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND ON ANY
 * THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT
 * (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE
 * OF THIS SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
 *
 * This software consists of voluntary contributions made by many individuals
 * and is licensed under the MIT license.
 */

namespace ZfcRbac\Options;

use Zend\Stdlib\AbstractOptions;

/**
 * Redirect strategy options
 *
 * @author  Michaël Gallego <mic.gallego@gmail.com>
 * @licence MIT
 */
class RedirectStrategyOptions extends AbstractOptions
{
    /**
     * Should the user be redirected when connected and not authorized
     *
     * @var bool
     */
    protected $redirectWhenConnected = true;

    /**
     * The name of the route to redirect when a user is connected and not authorized
     *
     * @var string
     */
    protected $redirectToRouteConnected = 'home';

    /**
     * The name of the route to redirect when a user is disconnected and not authorized
     *
     * @var string
     */
    protected $redirectToRouteDisconnected = 'login';

    /**
     * Should the previous URI should be appended as a query param?
     *
     * @var bool
     */
    protected $appendPreviousUri = true;

    /**
     * If appendPreviousUri is enabled, key to use in query params that hold the previous URI
     *
     * @var string
     */
    protected $previousUriQueryKey = 'redirectTo';

    /**
     * @param bool $redirectWhenConnected
     * @return void
     */
    public function setRedirectWhenConnected($redirectWhenConnected)
    {
        $this->redirectWhenConnected = (bool) $redirectWhenConnected;
    }

    /**
     * @return bool
     */
    public function getRedirectWhenConnected()
    {
        return $this->redirectWhenConnected;
    }

    /**
     * @param string $redirectToRouteConnected
     * @return void
     */
    public function setRedirectToRouteConnected($redirectToRouteConnected)
    {
        $this->redirectToRouteConnected = (string) $redirectToRouteConnected;
    }

    /**
     * @return string
     */
    public function getRedirectToRouteConnected()
    {
        return $this->redirectToRouteConnected;
    }

    /**
     * @param string $redirectToRouteDisconnected
     * @return void
     */
    public function setRedirectToRouteDisconnected($redirectToRouteDisconnected)
    {
        $this->redirectToRouteDisconnected = (string) $redirectToRouteDisconnected;
    }

    /**
     * @return string
     */
    public function getRedirectToRouteDisconnected()
    {
        return $this->redirectToRouteDisconnected;
    }

    /**
     * @param boolean $appendPreviousUri
     */
    public function setAppendPreviousUri($appendPreviousUri)
    {
        $this->appendPreviousUri = (bool) $appendPreviousUri;
    }

    /**
     * @return boolean
     */
    public function getAppendPreviousUri()
    {
        return $this->appendPreviousUri;
    }

    /**
     * @param string $previousUriQueryKey
     */
    public function setPreviousUriQueryKey($previousUriQueryKey)
    {
        $this->previousUriQueryKey = (string) $previousUriQueryKey;
    }

    /**
     * @return string
     */
    public function getPreviousUriQueryKey()
    {
        return $this->previousUriQueryKey;
    }
}
