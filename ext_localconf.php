<?php
defined('TYPO3') or die();

(function () {
    // Do not show the IP records in the listing
    $allowedTablesTs = '
		mod.web_list.deniedNewTables := addToList(tx_aoeipauth_domain_model_ip)
		mod.web_list.hideTables := addToList(tx_aoeipauth_domain_model_ip)
	';
    \TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addPageTSConfig($allowedTablesTs);

    // Hooks
    $GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['t3lib/class.t3lib_tcemain.php']['processDatamapClass']['aoe_ipauth'] = \AOE\AoeIpauth\Hooks\Tcemain::class;

    $extensionConfiguration = \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance(
        \TYPO3\CMS\Core\Configuration\ExtensionConfiguration::class
    )->get('aoe_ipauth');

    $GLOBALS['TYPO3_CONF_VARS']['SVCONF']['auth']['setup']['FE_fetchUserIfNoSession'] =
        isset($extensionConfiguration['fetchFeUserIfNoSession']) ?
            boolval($extensionConfiguration['fetchFeUserIfNoSession']) : 1;

    // IP Authentication Service
    \TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addService('aoe_ipauth', 'auth', 'tx_aoeipauth_typo3_service_authentication',
        array(
            'title' => 'IP Authentication',
            'description' => 'Authenticates against IP addresses and ranges.',
            'subtype' => 'authUserFE,getUserFE',
            'available' => true,
            // Must be higher than for tx_sv_auth (50) or tx_sv_auth will deny request unconditionally
            'priority' => 80,
            'quality' => 50,
            'os' => '',
            'exec' => '',
            'classFile' => \TYPO3\CMS\Core\Utility\ExtensionManagementUtility::extPath('aoe_ipauth') . 'Classes/Typo3/Service/Authentication.php',
            'className' => 'AOE\AoeIpauth\Typo3\Service\Authentication',
        )
    );

})();
