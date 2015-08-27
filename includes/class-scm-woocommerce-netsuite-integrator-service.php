<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
if ( ! class_exists( 'SCM_WC_Netsuite_Integrator_Service' ) ) :

/**
 * WooCommerce NetSuite Integrator Service Functions & Calls
 */
class SCM_WC_Netsuite_Integrator_Service {

	protected $config = array();
	protected $service = null;

	protected function __construct() {
		
		$this->includes();
		$upload_dir =  wp_upload_dir();

		$this->config = apply_filters('woocommerce_netsuite_config', array(
			// Required
			"endpoint"  => "2015_1", // Current version of the NetSuite API
			"host"      => get_option('options_wni_host_endpoint'),
			"email"     => get_option('options_wni_email'),
			"password"  => get_option('options_wni_password'),
			"role"      => "3", // Must be an admin to have rights
			"account"   => get_option('options_wni_account_number'),
			// Optional
			"logging"   => true,
			"log_path"  => $upload_dir['basedir'] . '/wc-netsuite-logs/netsuite-logs',
		), $this);

		$this->connect_service();
		
	}

	/*
	 * Usage self::log_action('error', 'Check out this sweet error!');
	 *
	*/
	public static function log_action($action, $message="", $logfile=false) {
		$upload_dir = wp_upload_dir();
	    $logfile = ($logfile) ? $logfile : $upload_dir['basedir'] . '/wc-netsuite-logs/'.date("Y-m-d").'.log';
	    $new = file_exists($logfile) ? false : true;
	    if($handle = fopen($logfile, 'a')) { // append
	        $timestamp = strftime("%Y-%m-%d %H:%M:%S", time());
	        $content = "{$timestamp} | {$action}: {$message}\n";
	        fwrite($handle, $content);
	        fclose($handle);
	        if($new) { chmod($logfile, 0755); }
	    } else {
	        return false;
	    }
	}

	/**
	 * Includes.
	 */
	private function includes() {
		include_once LIB_DIR . DS . 'netsuite-phptoolkit' . DS . 'NSPHPClient.php';
		include_once LIB_DIR . DS . 'netsuite-phptoolkit' . DS . 'NetSuiteService.php';
	}

	private function connect_service() {
		$this->service = new NetSuiteService($this->config);
		return $this->service;
	}

	public static function compareCountryCode($code){
		$codesArray = array(
			'AF'	=>	'_afghanistan',
			'AX'	=>	'_alandIslands',
			'AL'	=>	'_albania',
			'DZ'	=>	'_algeria',
			'AS'	=>	'_americanSamoa',
			'AD'	=>	'_andorra',
			'AO'	=>	'_angola',
			'AI'	=>	'_anguilla',
			'AQ'	=>	'_antarctica',
			'AG'	=>	'_antiguaAndBarbuda',
			'AR'	=>	'_argentina',
			'AM'	=>	'_armenia',
			'AW'	=>	'_aruba',
			'AU'	=>	'_australia',
			'AT'	=>	'_austria',
			'AZ'	=>	'_azerbaijan',
			'BS'	=>	'_bahamas',
			'BH'	=>	'_bahrain',
			'BD'	=>	'_bangladesh',
			'BB'	=>	'_barbados',
			'BY'	=>	'_belarus',
			'BE'	=>	'_belgium',
			'BZ'	=>	'_belize',
			'BJ'	=>	'_benin',
			'BM'	=>	'_bermuda',
			'BT'	=>	'_bhutan',
			'BO'	=>	'_bolivia',
			'BQ'	=>	'_bonaireSaintEustatiusAndSaba',
			'BA'	=>	'_bosniaAndHerzegovina',
			'BW'	=>	'_botswana',
			'BV'	=>	'_bouvetIsland',
			'BR'	=>	'_brazil',
			'IO'	=>	'_britishIndianOceanTerritory',
			'BN'	=>	'_bruneiDarussalam',
			'BG'	=>	'_bulgaria',
			'BF'	=>	'_burkinaFaso',
			'BI'	=>	'_burundi',
			'KH'	=>	'_cambodia',
			'CM'	=>	'_cameroon',
			'CA'	=>	'_canada',
			'CV'	=>	'_capeVerde',
			'KY'	=>	'_caymanIslands',
			'CF'	=>	'_centralAfricanRepublic',
			'TD'	=>	'_chad',
			'CL'	=>	'_chile',
			'CN'	=>	'_china',
			'CX'	=>	'_christmasIsland',
			'CC'	=>	'_cocosKeelingIslands',
			'CO'	=>	'_colombia',
			'KM'	=>	'_comoros',
			'CG'	=>	'_congoDemocraticPeoplesRepublic',
			'CD'	=>	'_congoRepublicOf',
			'CK'	=>	'_cookIslands',
			'CR'	=>	'_costaRica',
			'CI'	=>	'_coteDIvoire',
			'HR'	=>	'_croatiaHrvatska',
			'CU'	=>	'_cuba',
			'CW'	=>	'_curacao',
			'CY'	=>	'_cyprus',
			'CZ'	=>	'_czechRepublic',
			'DK'	=>	'_denmark',
			'DJ'	=>	'_djibouti',
			'DM'	=>	'_dominica',
			'DO'	=>	'_dominicanRepublic',
			'EC'	=>	'_ecuador',
			'EG'	=>	'_egypt',
			'SV'	=>	'_elSalvador',
			'GQ'	=>	'_equatorialGuinea',
			'ER'	=>	'_eritrea',
			'EE'	=>	'_estonia',
			'ET'	=>	'_ethiopia',
			'FK'	=>	'_falklandIslands',
			'FO'	=>	'_faroeIslands',
			'FJ'	=>	'_fiji',
			'FI'	=>	'_finland',
			'FR'	=>	'_france',
			'GF'	=>	'_frenchGuiana',
			'PF'	=>	'_frenchPolynesia',
			'TF'	=>	'_frenchSouthernTerritories',
			'GA'	=>	'_gabon',
			'GM'	=>	'_gambia',
			'GE'	=>	'_georgia',
			'DE'	=>	'_germany',
			'GH'	=>	'_ghana',
			'GI'	=>	'_gibraltar',
			'GR'	=>	'_greece',
			'GL'	=>	'_greenland',
			'GD'	=>	'_grenada',
			'GP'	=>	'_guadeloupe',
			'GU'	=>	'_guam',
			'GT'	=>	'_guatemala',
			'GG'	=>	'_guernsey',
			'GN'	=>	'_guinea',
			'GW'	=>	'_guineaBissau',
			'GY'	=>	'_guyana',
			'HT'	=>	'_haiti',
			'HM'	=>	'_heardAndMcDonaldIslands',
			'VA'	=>	'_holySeeCityVaticanState',
			'HN'	=>	'_honduras',
			'HK'	=>	'_hongKong',
			'HU'	=>	'_hungary',
			'IS'	=>	'_iceland',
			'IN'	=>	'_india',
			'ID'	=>	'_indonesia',
			'IR'	=>	'_iranIslamicRepublicOf',
			'IQ'	=>	'_iraq',
			'IE'	=>	'_ireland',
			'IM'	=>	'_isleOfMan',
			'IL'	=>	'_israel',
			'IT'	=>	'_italy',
			'JM'	=>	'_jamaica',
			'JP'	=>	'_japan',
			'JE'	=>	'_jersey',
			'JO'	=>	'_jordan',
			'KZ'	=>	'_kazakhstan',
			'KE'	=>	'_kenya',
			'KI'	=>	'_kiribati',
			'KP'	=>	'_koreaDemocraticPeoplesRepublic',
			'KR'	=>	'_koreaRepublicOf',
			'KW'	=>	'_kuwait',
			'KG'	=>	'_kyrgyzstan',
			'LA'	=>	'_laoPeoplesDemocraticRepublic',
			'LV'	=>	'_latvia',
			'LB'	=>	'_lebanon',
			'LS'	=>	'_lesotho',
			'LR'	=>	'_liberia',
			'LY'	=>	'_libyanArabJamahiriya',
			'LI'	=>	'_liechtenstein',
			'LT'	=>	'_lithuania',
			'LU'	=>	'_luxembourg',
			'MO'	=>	'_macau',
			'MK'	=>	'_macedonia',
			'MG'	=>	'_madagascar',
			'MW'	=>	'_malawi',
			'MY'	=>	'_malaysia',
			'MV'	=>	'_maldives',
			'ML'	=>	'_mali',
			'MT'	=>	'_malta',
			'MH'	=>	'_marshallIslands',
			'MQ'	=>	'_martinique',
			'MR'	=>	'_mauritania',
			'MU'	=>	'_mauritius',
			'YT'	=>	'_mayotte',
			'MX'	=>	'_mexico',
			'FM'	=>	'_micronesiaFederalStateOf',
			'MD'	=>	'_moldovaRepublicOf',
			'MC'	=>	'_monaco',
			'MN'	=>	'_mongolia',
			'ME'	=>	'_montenegro',
			'MS'	=>	'_montserrat',
			'MA'	=>	'_morocco',
			'MZ'	=>	'_mozambique',
			'MM'	=>	'_myanmar',
			'NA'	=>	'_namibia',
			'NR'	=>	'_nauru',
			'NP'	=>	'_nepal',
			'NL'	=>	'_netherlands',
			'NC'	=>	'_newCaledonia',
			'NZ'	=>	'_newZealand',
			'NI'	=>	'_nicaragua',
			'NE'	=>	'_niger',
			'NG'	=>	'_nigeria',
			'NU'	=>	'_niue',
			'NF'	=>	'_norfolkIsland',
			'MP'	=>	'_northernMarianaIslands',
			'NO'	=>	'_norway',
			'OM'	=>	'_oman',
			'PK'	=>	'_pakistan',
			'PW'	=>	'_palau',
			'PS'	=>	'_palestinianTerritories',
			'PA'	=>	'_panama',
			'PG'	=>	'_papuaNewGuinea',
			'PY'	=>	'_paraguay',
			'PE'	=>	'_peru',
			'PH'	=>	'_philippines',
			'PN'	=>	'_pitcairnIsland',
			'PL'	=>	'_poland',
			'PT'	=>	'_portugal',
			'PR'	=>	'_puertoRico',
			'QA'	=>	'_qatar',
			'RE'	=>	'_reunionIsland',
			'RO'	=>	'_romania',
			'RU'	=>	'_russianFederation',
			'RW'	=>	'_rwanda',
			'BL'	=>	'_saintBarthelemy',
			'SH'	=>	'_saintHelena',
			'KN'	=>	'_saintKittsAndNevis',
			'LC'	=>	'_saintLucia',
			'MF'	=>	'_saintMartin',
			'VC'	=>	'_saintVincentAndTheGrenadines',
			'WS'	=>	'_samoa',
			'SM'	=>	'_sanMarino',
			'ST'	=>	'_saoTomeAndPrincipe',
			'SA'	=>	'_saudiArabia',
			'SN'	=>	'_senegal',
			'RS'	=>	'_serbia',
			'SC'	=>	'_seychelles',
			'SL'	=>	'_sierraLeone',
			'SG'	=>	'_singapore',
			'SX'	=>	'_sintMaarten',
			'SK'	=>	'_slovakRepublic',
			'SI'	=>	'_slovenia',
			'SB'	=>	'_solomonIslands',
			'SO'	=>	'_somalia',
			'ZA'	=>	'_southAfrica',
			'GS'	=>	'_southGeorgia',
			'SS'	=>	'_southSudan',
			'ES'	=>	'_spain',
			'LK'	=>	'_sriLanka',
			'PM'	=>	'_stPierreAndMiquelon',
			'SD'	=>	'_sudan',
			'SR'	=>	'_suriname',
			'SJ'	=>	'_svalbardAndJanMayenIslands',
			'SZ'	=>	'_swaziland',
			'SE'	=>	'_sweden',
			'CH'	=>	'_switzerland',
			'SY'	=>	'_syrianArabRepublic',
			'TW'	=>	'_taiwan',
			'TJ'	=>	'_tajikistan',
			'TZ'	=>	'_tanzania',
			'TH'	=>	'_thailand',
			'TG'	=>	'_togo',
			'TK'	=>	'_tokelau',
			'TO'	=>	'_tonga',
			'TT'	=>	'_trinidadAndTobago',
			'TN'	=>	'_tunisia',
			'TR'	=>	'_turkey',
			'TM'	=>	'_turkmenistan',
			'TC'	=>	'_turksAndCaicosIslands',
			'TV'	=>	'_tuvalu',
			'UG'	=>	'_uganda',
			'UA'	=>	'_ukraine',
			'AE'	=>	'_unitedArabEmirates',
			'GB'	=>	'_unitedKingdomGB',
			'US'	=>	'_unitedStates',
			'UY'	=>	'_uruguay',
			'UM'	=>	'_uSMinorOutlyingIslands',
			'UZ'	=>	'_uzbekistan',
			'VU'	=>	'_vanuatu',
			'VE'	=>	'_venezuela',
			'VN'	=>	'_vietnam',
			'VG'	=>	'_virginIslandsBritish',
			'VI'	=>	'_virginIslandsUSA',
			'WF'	=>	'_wallisAndFutunaIslands',
			'EH'	=>	'_westernSahara',
			'YE'	=>	'_yemen',
			'ZM'	=>	'_zambia',
			'ZW'	=>	'_zimbabwe'
		);

		return $codesArray[$code];
	}

}

endif;