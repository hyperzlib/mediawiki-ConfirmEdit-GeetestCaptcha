<?php

class GeetestCaptchaHooks {
	/**
	 * Adds extra variables to the global config
	 *
	 * @param array &$vars Global variables object
	 * @return bool Always true
	 */
	public static function onResourceLoaderGetConfigVars( array &$vars ) {
		global $wgGeetestID, $wgGeetestKey;
		global $wgCaptchaClass;

		if ( $wgCaptchaClass === 'GeetestCaptcha' ) {
			$vars['wgConfirmEditConfig'] = [];
		}

		return true;
	}
}
