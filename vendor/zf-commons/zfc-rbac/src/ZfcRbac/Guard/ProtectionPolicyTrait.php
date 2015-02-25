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

namespace ZfcRbac\Guard;

/**
 * Trait that is can be used for any guard that uses the protection policy pattern
 *
 * @author  Michaël Gallego <mic.gallego@gmail.com>
 * @licence MIT
 */
trait ProtectionPolicyTrait
{
    /**
     * @var string
     */
    protected $protectionPolicy = GuardInterface::POLICY_DENY;

    /**
     * Set the protection policy
     *
     * @param  string $protectionPolicy
     * @return void
     */
    public function setProtectionPolicy($protectionPolicy)
    {
        $this->protectionPolicy = (string) $protectionPolicy;
    }

    /**
     * Get the protection policy
     *
     * @return string
     */
    public function getProtectionPolicy()
    {
        return $this->protectionPolicy;
    }
}
