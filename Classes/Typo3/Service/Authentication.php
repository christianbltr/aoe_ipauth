<?php
namespace AOE\AoeIpauth\Typo3\Service;

/***************************************************************
 *  Copyright notice
 *
 *  (c) 2019 AOE GmbH <dev@aoe.com>
 *
 *  All rights reserved
 *
 *  This script is part of the TYPO3 project. The TYPO3 project is
 *  free software; you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation; either version 3 of the License, or
 *  (at your option) any later version.
 *
 *  The GNU General Public License can be found at
 *  http://www.gnu.org/copyleft/gpl.html.
 *
 *  This script is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU General Public License for more details.
 *
 *  This copyright notice MUST APPEAR in all copies of the script!
 ***************************************************************/
use AOE\AoeIpauth\Service\IpMatchingService;
use AOE\AoeIpauth\Domain\Service\FeEntityService;
use AOE\AoeIpauth\Domain\Service\IpService;
use TYPO3\CMS\Core\Domain\Repository\PageRepository;
use TYPO3\CMS\Core\Information\Typo3Version;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Authentication\AbstractAuthenticationService;

/**
 * Class Authentication
 *
 * @package AOE\AoeIpauth\Typo3\Service
 */
class Authentication extends AbstractAuthenticationService
{

    /**
     * @var IpMatchingService
     */
    protected $ipMatchingService = null;

    /**
     * @var FeEntityService
     */
    protected $feEntityService = null;

    /**
     * @var IpService
     */
    protected $ipService = null;

    /**
     * Makes sure the TCA is readable, necessary for enableFields to work
     * Is de-facto called when using the Preview BE Module
     *
     * @return void
     */
    protected function safeguardContext()
    {
        if (!isset($GLOBALS['TSFE'])) {
            return;
        }

        if (!isset($GLOBALS['TCA'][FeEntityService::TABLE_USER])) {
            // @extensionScannerIgnoreLine
            if (empty($GLOBALS['TSFE']->sys_page)) {
                // @extensionScannerIgnoreLine
                $GLOBALS['TSFE']->sys_page = GeneralUtility::makeInstance(PageRepository::class);
            }
        }
    }

    /**
     * Gets the user automatically
     *
     * @return bool
     */
    public function getUser()
    {
        // Do not respond to non-fe users and login attempts
        if ('getUserFE' != $this->mode || 'login' == $this->login['status']) {
            return false;
        }

        $this->safeguardContext();

        $clientIp = $this->authInfo['REMOTE_ADDR'];
        $ipAuthenticatedUsers = $this->findAllUsersByIpAuthentication($clientIp);
        if (empty($ipAuthenticatedUsers)) {
            return false;
        }

        $user = array_pop($ipAuthenticatedUsers);
        return $user;
    }

    /**
     * Authenticate a user
     * Return 200 if the IP is right.
     * This means that no more checks are needed.
     * Otherwise authentication may fail because we may don't have a password.
     *
     * @param array Data of user.
     * @return int
     */
    public function authUser($user)
    {
        $this->safeguardContext();

        $authCode = 100;

        // Do not respond to non-fe users and login attempts
        if ('FE' != $this->authInfo['loginType'] || 'login' == $this->login['status']) {
            return $authCode;
        }
        if (!isset($user['uid'])) {
            return $authCode;
        }

        $clientIp = $this->authInfo['REMOTE_ADDR'];
        $userId = $user['uid'];

        $ipMatches = $this->doesCurrentUsersIpMatch($userId, $clientIp);

        if ($ipMatches) {
            $authCode = 200;

            // hook which will be fired after user has been authenticated
            if (!empty($GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['aoe_ipauth']['authUserIpMatches'])) {
                foreach ($GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['aoe_ipauth']['authUserIpMatches'] as $className) {
                    $hookObject = GeneralUtility::makeInstance($className);
                    if (method_exists($hookObject, 'process')) {
                        $hookObject->process($user);
                    }
                }
            }
        }

        return $authCode;
    }

    /**
     * Returns TRUE if the userId's associated IPs match the client IP
     *
     * @param int $userId
     * @param string $clientIp
     * @return bool
     */
    protected function doesCurrentUsersIpMatch($userId, $clientIp)
    {
        $isMatch = false;
        $ips = $this->getIpService()->findIpsByFeUserId($userId);

        foreach ($ips as $ipWhitelist) {
            if ($this->getIpMatchingService()->isIpAllowed($clientIp, $ipWhitelist)) {
                $isMatch = true;
                break;
            }
        }
        return $isMatch;
    }

    /**
     * Finds all users with IP authentication enabled
     *
     * @param string $ip
     * @return array
     */
    protected function findAllUsersByIpAuthentication($ip)
    {
        $users = $this->getFeEntityService()->findAllUsersAuthenticatedByIp($ip);
        return $users;
    }

    /**
     * @return FeEntityService
     */
    protected function getFeEntityService()
    {
        if (null === $this->feEntityService) {
            $this->feEntityService = GeneralUtility::makeInstance('AOE\\AoeIpauth\\Domain\\Service\\FeEntityService');
        }
        return $this->feEntityService;
    }

    /**
     * @return IpService
     */
    protected function getIpService()
    {
        if (null === $this->ipService) {
            $this->ipService = GeneralUtility::makeInstance('AOE\\AoeIpauth\\Domain\\Service\\IpService');
        }
        return $this->ipService;
    }

    /**
     * @return IpMatchingService
     */
    protected function getIpMatchingService()
    {
        if (null === $this->ipMatchingService) {
            $this->ipMatchingService = GeneralUtility::makeInstance('AOE\\AoeIpauth\\Service\\IpMatchingService');
        }
        return $this->ipMatchingService;
    }
}
