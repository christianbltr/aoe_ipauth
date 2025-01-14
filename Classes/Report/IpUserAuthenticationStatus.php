<?php
namespace AOE\AoeIpauth\Report;

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

use TYPO3\CMS\Core\Type\ContextualFeedbackSeverity;
use TYPO3\CMS\Reports\StatusProviderInterface;
use AOE\AoeIpauth\Domain\Service\FeEntityService;
use TYPO3\CMS\Reports\Status;
use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Class IpUserAuthenticationStatus
 *
 * @package AOE\AoeIpauth\Report
 */
class IpUserAuthenticationStatus implements StatusProviderInterface
{

    /**
     * @var string
     */
    protected $myIp;

    /**
     *
     * @see typo3/sysext/reports/interfaces/tx_reports_StatusProvider::getStatus()
     */
    public function getStatus(): array
    {
        $reports = array();

        $this->myIp = GeneralUtility::getIndpEnv('REMOTE_ADDR');

        $this->analyseUses($reports);

        return $reports;
    }

    /**
     * Analyses users
     *
     * @param array $reports
     * @return void
     */
    protected function analyseUses(&$reports)
    {
        /** @var FeEntityService $service */
        $service = GeneralUtility::makeInstance(FeEntityService::class);

        $users = $service->findAllUsersWithIpAuthentication();

        if (empty($users)) {
            // Message that no user group has IP authentication
            $status = GeneralUtility::makeInstance(
                Status::class,
                'IP User Authentication',
                'No users with IP authentication found',
                'No users were found anywhere that are active and have an automatic IP authentication enabled.' .
                'Your current IP is: <strong>' . $this->myIp . '</strong>',
                ContextualFeedbackSeverity::INFO
            );
            $reports[] = $status;
        } else {
            $thisUrl = urlencode(GeneralUtility::getIndpEnv('TYPO3_REQUEST_URL'));

            $userInfo = '<br /><br /><table cellpadding="4" cellspacing="0" border="0">';
            $userInfo .= '<thead><tr><th style="padding-bottom: 10px;">User</th><th>IP/Range</th></tr></thead>';
            $userInfo .= '<tbody>';

            foreach ($users as $user) {
                $uid = $user['uid'];
                $ips = implode(', ', $user['tx_aoeipauth_ip']);

                $fullRecord = BackendUtility::getRecord('fe_users', $uid);
                $title = $fullRecord['username'];

                $button = '<a title="Edit record" onclick="window.location.href=\'alt_doc.php?returnUrl=' .
                            $thisUrl . '&amp;edit[fe_users][' . $uid . ']=edit\'; return false;" href="#">' .
                            '<span class="t3-icon t3-icon-actions t3-icon-actions-document t3-icon-document-open">&nbsp;</span>' .
                            '</a>';

                $userInfo .= '<tr><td style="padding: 0 20px 0 0;">' . $button . $title . '</td><td>' . $ips . '</td></tr>';
            }

            $userInfo .= '</tbody>';
            $userInfo .= '</table>';

            $userInfo .= '<br /><br />Your current IP is: <strong>' . $this->myIp . '</strong>';

            $status = GeneralUtility::makeInstance(
                Status::class,
                'IP User Authentication',
                'Some users with automatic IP authentication were found.',
                $userInfo,
                ContextualFeedbackSeverity::OK
            );
            $reports[] = $status;
        }
    }

    public function getLabel(): string
    {
        return 'IpUserAuthenticationStatus';
    }
}
