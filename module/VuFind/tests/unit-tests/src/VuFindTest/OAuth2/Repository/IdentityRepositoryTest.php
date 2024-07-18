<?php

/**
 * OAuth2 IdentityRepository tests.
 *
 * PHP version 8
 *
 * Copyright (C) The National Library of Finland 2022.
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License version 2,
 * as published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301  USA
 *
 * @category VuFind
 * @package  Tests
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */

namespace VuFindTest\OAuth2\Repository;

use PHPUnit\Event\NoPreviousThrowableException;
use PHPUnit\Framework\InvalidArgumentException;
use PHPUnit\Framework\MockObject\Exception;
use PHPUnit\Framework\MockObject\MockObject;
use VuFind\Auth\ILSAuthenticator;
use VuFind\Db\Entity\UserEntityInterface;
use VuFind\Db\Row\User;
use VuFind\Db\Service\UserServiceInterface;
use VuFind\ILS\Connection;
use VuFind\OAuth2\Entity\UserEntity;
use VuFind\OAuth2\Repository\IdentityRepository;

/**
 * OAuth2 IdentityRepository tests.
 *
 * @category VuFind
 * @package  Tests
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */
class IdentityRepositoryTest extends AbstractTokenRepositoryTestCase
{
    /**
     * OAuth2 configuration
     *
     * @var array
     */
    protected $oauth2Config = [
        'Server' => [
            'userIdentifierField' => 'id',
            'encryptionKey' => 'testkey',
            'hashSalt' => 'superSalty',
        ],
        'ClaimMappings' => [
            'id' => 'id',
            'sub' => 'id',
            'nonce' => 'nonce',
            'name' => 'full_name',
            'given_name' => 'firstname',
            'family_name' => 'lastname',
            'email' => 'email',
            'age' => 'age',
            'birthdate' => 'birthdate',
            'locale' => 'last_language',
            'phone' => 'phone',
            'address' => 'address_json',
            'block_status' => 'block_status',
            'library_user_id' => 'library_user_id_hash',
        ],
    ];

    /**
     * User's birth date
     *
     * @var string
     */
    protected $userBirthDate;

    /**
     * Setup tests
     *
     * @return void
     */
    public function setUp(): void
    {
        $this->userBirthDate = (new \DateTimeImmutable('18 years 6 months ago'))
            ->format('Y-m-d');
    }

    /**
     * Data provider for testIdentityRepository
     *
     * @return array
     */
    public static function getTestIdentityRepositoryData(): array
    {
        return [
            [null],
            [false],
            [true],
        ];
    }

    /**
     * Test identity repository
     *
     * @param ?bool $blocks Blocks status
     *
     * @return void
     *
     * @dataProvider getTestIdentityRepositoryData
     */
    public function testIdentityRepository(?bool $blocks): void
    {
        $accessTokenService = $this->getMockAccessTokenService();
        $nonce = bin2hex(random_bytes(5));
        $accessTokenService->storeNonce(2, $nonce);
        $repo = new IdentityRepository(
            $this->getMockUserService(),
            $accessTokenService,
            $this->getMockILSConnection($blocks),
            $this->oauth2Config,
            $this->getMockILSAuthenticator()
        );

        $this->assertNull($repo->getUserEntityByIdentifier(1));
        $user = $repo->getUserEntityByIdentifier(2);
        $this->assertInstanceOf(UserEntity::class, $user);

        $this->assertEquals(
            [
                'id' => 2,
                'sub' => 2,
                'name' => 'Lib Rarian',
                'given_name' => 'Lib',
                'family_name' => 'Rarian',
                'email' => 'Lib.Rarian@library.not',
                'age' => 18,
                'birthdate' => $this->userBirthDate,
                'locale' => 'en-GB',
                'phone' => '1900 CALL ME',
                'address' => '{"street_address":"Somewhere...\\nOver the Rainbow","locality":"City",'
                    . '"postal_code":"12345","country":"Country"}',
                'block_status' => $blocks,
                'nonce' => $nonce,
                'library_user_id' => $this->getCatUsernameHash('user'),
            ],
            $user->getClaims()
        );
    }

    /**
     * Get a mock ILSAuthenticator
     *
     * @return ILSAuthenticator
     * @throws InvalidArgumentException
     * @throws Exception
     * @throws NoPreviousThrowableException
     */
    protected function getMockILSAuthenticator(): ILSAuthenticator
    {
        $mock = $this->createMock(ILSAuthenticator::class);
        $mock->expects($this->any())->method('getCatPasswordForUser')->willReturnCallback(
            function ($user) {
                return $user->getRawCatPassword();
            }
        );
        return $mock;
    }

    /**
     * Test identity repository with a failing ILS connection
     *
     * @return void
     */
    public function testIdentityRepositoryWithFailingILS(): void
    {
        $accessTokenService = $this->getMockAccessTokenService();
        $nonce = bin2hex(random_bytes(5));
        $accessTokenService->storeNonce(2, $nonce);
        $repo = new IdentityRepository(
            $this->getMockUserService(),
            $accessTokenService,
            $this->getMockFailingIlsConnection(),
            $this->oauth2Config,
            $this->getMockILSAuthenticator()
        );

        $user = $repo->getUserEntityByIdentifier(2);
        $this->assertInstanceOf(UserEntity::class, $user);

        $this->assertEquals(
            [
                'id' => 2,
                'sub' => 2,
                'name' => 'Lib Rarian',
                'given_name' => 'Lib',
                'family_name' => 'Rarian',
                'email' => 'Lib.Rarian@library.not',
                'locale' => 'en-GB',
                'nonce' => $nonce,
                'block_status' => null,
                'library_user_id' => $this->getCatUsernameHash('user'),
            ],
            $user->getClaims()
        );
    }

    /**
     * Get a mock user object
     *
     * @return MockObject&UserEntityInterface
     */
    protected function getMockUser(): UserEntityInterface
    {
        $user = $this->createMock(User::class);
        $user->expects($this->any())->method('getId')->willReturn(2);
        $user->expects($this->any())->method('getFirstname')->willReturn('Lib');
        $user->expects($this->any())->method('getLastname')->willReturn('Rarian');
        $user->expects($this->any())->method('getLastLanguage')->willReturn('en-gb');
        $user->expects($this->any())->method('getEmail')->willReturn('Lib.Rarian@library.not');
        $user->expects($this->any())->method('getCatUsername')->willReturn('user');
        $user->expects($this->any())->method('getRawCatPassword')->willReturn('pass');
        return $user;
    }

    /**
     * Create a mock user service that returns a fake user object.
     *
     * @return MockObject&\VuFind\Db\Service\UserServiceInterface
     */
    protected function getMockUserService(): MockObject&UserServiceInterface
    {
        $user = $this->getMockUser();
        $userService = $this->createMock(UserServiceInterface::class);
        $userService->expects($this->any())->method('getUserByField')
            ->willReturnMap(
                [
                    ['id', 1, null],
                    ['id', 2, $user],
                ]
            );
        return $userService;
    }

    /**
     * Get mock ILS connection.
     *
     * @param ?bool $blocks Whether to support blocks and what to return
     *
     * @return MockObject&Connection
     */
    protected function getMockIlsConnection(?bool $blocks): Connection
    {
        $patron = [
            'id' => 1,
            'firstname'    => 'Lib',
            'lastname'     => 'Rarian',
            'cat_username' => 'user',
            'cat_password' => 'pass',
            'email'        => 'Lib.Rarian@library.not',
            'major'        => null,
            'college'      => null,
        ];

        $profile = [
            'firstname'       => 'Lib',
            'lastname'        => 'Rarian',
            'address1'        => 'Somewhere...',
            'address2'        => 'Over the Rainbow',
            'zip'             => '12345',
            'city'            => 'City',
            'country'         => 'Country',
            'phone'           => '1900 CALL ME',
            'mobile_phone'    => '1234567890',
            'group'           => 'Library Staff',
            'expiration_date' => 'Someday',
            'birthdate'       => $this->userBirthDate,
        ];

        $ils = $this->getMockBuilder(Connection::class)
            ->disableOriginalConstructor()
            ->addMethods(['getAccountBlocks', 'getMyProfile', 'patronLogin'])
            ->onlyMethods(['checkCapability'])
            ->getMock();

        $ils->expects($this->once())
            ->method('patronLogin')
            ->with('user', 'pass')
            ->will($this->returnValue($patron));

        $ils->expects($this->once())
            ->method('getMyProfile')
            ->with($patron)
            ->will($this->returnValue($profile));

        if (null === $blocks) {
            $ils->expects($this->once())
                ->method('checkCapability')
                ->with('getAccountBlocks', compact('patron'), false)
                ->willReturn(false);
        } else {
            $ils->expects($this->once())
                ->method('checkCapability')
                ->with('getAccountBlocks', compact('patron'), false)
                ->willReturn(true);

            $ils->expects($this->once())
                ->method('getAccountBlocks')
                ->with($patron)
                ->will($this->returnValue($blocks ? ['Simulated block'] : []));
        }

        return $ils;
    }

    /**
     * Get mock ILS connection that throws an exception for any actual ILS request.
     *
     * @return MockObject&Connection
     */
    protected function getMockFailingIlsConnection(): Connection
    {
        $ils = $this->getMockBuilder(Connection::class)
            ->disableOriginalConstructor()
            ->addMethods(['getAccountBlocks', 'getMyProfile', 'patronLogin'])
            ->onlyMethods(['checkCapability'])
            ->getMock();

        $exception = new \VuFind\Exception\ILS('Simulated failure');

        $ils->expects($this->once())
            ->method('patronLogin')
            ->will($this->throwException($exception));

        return $ils;
    }

    /**
     * Create a hash from a user name
     *
     * @param string $username User name
     *
     * @return string
     */
    protected function getCatUsernameHash(string $username): string
    {
        return hash(
            'sha256',
            'user' . $this->oauth2Config['Server']['hashSalt']
        );
    }
}
