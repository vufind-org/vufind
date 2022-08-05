<?php
/**
 * OAuth2 IdentityRepository tests.
 *
 * PHP version 7
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

use VuFind\Db\Row\User as UserRow;
use VuFind\Db\Table\User as UserTable;
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
class IdentityRepositoryTest extends AbstractTokenRepositoryTest
{
    /**
     * Data provider for testIdentityRepository
     *
     * @return array
     */
    public function getTestIdentityRepositoryData(): array
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
     * @dataProvider getTestIdentityRepositoryData
     *
     * @param ?bool $blocks Blocks status
     *
     * @return void
     */
    public function testIdentityRepository(?bool $blocks): void
    {
        $config = [
            'ClaimMappings' => [
                'id' => 'id',
                'name' => 'full_name',
                'given_name' => 'firstname',
                'family_name' => 'lastname',
                'email' => 'email',
                'birthdate' => 'birthdate',
                'locale' => 'last_language',
                'phone' => 'phone',
                'address' => 'address_json',
                'block_status' => 'block_status',
            ],
        ];
        $repo = new IdentityRepository(
            $this->getMockUserTable(),
            $this->getMockAccessTokenTable(),
            $this->getMockILSConnection($blocks),
            $config
        );
        $repo->setNonce('N-O-N-C-E');

        $this->assertNull($repo->getUserEntityByIdentifier(1));
        $user = $repo->getUserEntityByIdentifier(2);
        $this->assertInstanceOf(UserEntity::class, $user);

        $this->assertEquals(
            [
                'sub' => 2,
                'id' => 2,
                'name' => 'Lib Rarian',
                'given_name' => 'Lib',
                'family_name' => 'Rarian',
                'birthdate' => '2022-08-05',
                'locale' => 'en',
                'phone' => '1900 CALL ME',
                'address' => '{"street_address":"Somewhere...\\nOver the Rainbow","locality":"City","postal_code":"12345","country":"Country"}',
                'block_status' => $blocks,
            ],
            $user->getClaims()
        );
    }

    /**
     * Get a mock user object
     *
     * @return UserRow
     */
    protected function getMockUser(): UserRow
    {
        $user = $this->getMockBuilder(UserRow::class)
            ->disableOriginalConstructor()
            ->onlyMethods([])
            ->getMock();

        $user->id = 2;
        $user->last_language = 'en';
        $user->firstname = 'Lib';
        $user->lastname = 'Rarian';
        $user->cat_username = 'user';
        $user->cat_password = 'pass';
        return $user;
    }

    /**
     * Create a mock user table that returns a fake user object.
     *
     * @return \VuFind\Db\Table\User
     */
    protected function getMockUserTable(): UserTable
    {
        $user = $this->getMockUser();
        $userTable = $this->getMockBuilder(UserTable::class)
            ->disableOriginalConstructor()
            ->getMock();
        $userTable->expects($this->any())->method('getById')
            ->willReturnMap(
                [
                    [1, null],
                    [2, $user]
                ]
            );
        return $userTable;
    }

    /**
     * Get mock ILS connection.
     *
     * @param ?bool $blocks Whether to support blocks and what to return
     *
     * @return Connection
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
            'college'      => null
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
            'birthdate'       => '2022-08-05',
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
}
