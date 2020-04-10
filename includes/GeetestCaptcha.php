<?php

use MediaWiki\Auth\AuthenticationRequest;

class GeetestCaptcha extends SimpleCaptcha {
	protected static $messagePrefix = 'geetest-';

	private $error = null;

	public function getCode() {
		global $wgGeetestID, $wgGeetestKey, $wgRequest, $wgUser;
		if(!empty($wgUser)){
			$uid = strval($wgUser->getID());
		} else {
			$uid = 'guest';
		}
		$GtSdk = new GeetestLib($wgGeetestID, $wgGeetestKey);
		$data = [
			"user_id" => $uid,
			"client_type" => "web",
			"ip_address" => $wgRequest->getIP(),
		];
		$status = $GtSdk->pre_process($data, 1);
		$session = [
			'gtserver' => $status,
			'user_id' => $uid,
		];
		$sessId = $this->storeCaptcha($session);
		$code = json_decode($GtSdk->get_response_str(), true);
		$code['id'] = $sessId;
		return $code;
	}

	/**
	 * Get the captcha form.
	 * @param int $tabIndex
	 * @return array
	 */
	public function getFormInformation( $tabIndex = 1 ) {
		$output = Html::openElement( 'div', [
			'class' => [
				'geetest-captcha',
				'mw-confirmedit-captcha-fail' => (bool)$this->error,
			],
		] ) . Html::hidden( 'geetest_id', false, [
			'class' => 'geetest-captcha-id',
		] ) . Html::closeElement( 'div' );
		return [
			'html' => $output,
			'modules' => ['ext.confirmEdit.GeetestCaptcha'],
		];
	}

	/**
	 * @param Status|array|string $info
	 */
	protected function logCheckError( $info ) {
		if ( $info instanceof Status ) {
			$errors = $info->getErrorsArray();
			$error = $errors[0][0];
		} elseif ( is_array( $info ) ) {
			$error = implode( ',', $info );
		} else {
			$error = $info;
		}

		wfDebugLog( 'captcha', 'Unable to validate response: ' . $error );
	}

	/**
	 * @param WebRequest $request
	 * @return array
	 */
	protected function getCaptchaParamsFromRequest( WebRequest $request ) {
		// ReCaptchaNoCaptcha combines captcha ID + solution into a single value
		// API is hardwired to return captchaWord, so use that if the standard isempty
		// "captchaWord" is sent as "captchaword" by visual editor
		$index = [
			'id' => $request->getVal( 'geetest_id' ),
			'challenge' => $request->getVal( 'geetest_challenge' ),
			'validate' => $request->getVal( 'geetest_validate' ),
			'seccode' => $request->getVal( 'geetest_seccode' ),
		];
		$response = 'not used';
		return [ $index, $response ];
	}

	/**
	 * Check, if the user solved the captcha.
	 *
	 * Based on reference implementation:
	 * https://github.com/google/recaptcha#php
	 *
	 * @param mixed $request datas
	 * @param string $word captcha solution
	 * @return bool
	 */
	protected function passCaptcha( $request, $word ) {
		global $wgRequest, $wgUser, $wgGeetestID, $wgGeetestKey;
		// Build data to append to request
		if(!empty($wgUser)){
			$uid = strval($wgUser->getID());
		} else {
			$uid = 'guest';
		}

		$session = $this->retrieveCaptcha($request['id']);
		$this->clearCaptcha($request['id']);

		$GtSdk = new GeetestLib($wgGeetestID, $wgGeetestKey);
		$data = [
			"user_id" => $uid,
			"client_type" => "web",
			"ip_address" => $wgRequest->getIP(),
		];

		if ($session['gtserver'] == 1) { //在线验证
			return $GtSdk->success_validate($request['challenge'], $request['validate'], $request['seccode'], $data);
		} else { //离线验证
			return $GtSdk->fail_validate($request['challenge'], $request['validate'], $request['seccode']);
		}
	}

	public function passCaptchaLimited($index, $word, User $user){
		global $wgRequest;
		$request = [
			'id' => $wgRequest->getVal( 'geetest_id' ),
			'challenge' => $wgRequest->getVal( 'geetest_challenge' ),
			'validate' => $wgRequest->getVal( 'geetest_validate' ),
			'seccode' => $wgRequest->getVal( 'geetest_seccode' ),
		];
		return $this->passCaptcha($request, '');
	}

	/**
	 * @param array &$resultArr
	 */
	protected function addCaptchaAPI( &$resultArr ) {
		$resultArr['captcha'] = $this->describeCaptchaType();
		$resultArr['captcha']['error'] = $this->error;
	}

	/**
	 * @return array
	 */
	public function describeCaptchaType() {
		global $wgReCaptchaSiteKey;
		return [
			'type' => 'recaptchanocaptcha',
			'mime' => 'image/png',
			'key' => $wgReCaptchaSiteKey,
		];
	}

	/**
	 * Show a message asking the user to enter a captcha on edit
	 * The result will be treated as wiki text
	 *
	 * @param string $action Action being performed
	 * @return string Wikitext
	 */
	public function getMessage( $action ) {
		$msg = parent::getMessage( $action );
		if ( $this->error ) {
			$msg = new RawMessage( '<div class="error">$1</div>', [ $msg ] );
		}
		return $msg;
	}

	/**
	 * @param ApiBase &$module
	 * @param array &$params
	 * @param int $flags
	 * @return bool
	 */
	public function apiGetAllowedParams( &$module, &$params, $flags ) {
		if ( $flags && $this->isAPICaptchaModule( $module ) ) {
			$params['g-recaptcha-response'] = [
				ApiBase::PARAM_HELP_MSG => 'renocaptcha-apihelp-param-g-recaptcha-response',
			];
		}

		return true;
	}

	public function getError() {
		return $this->error;
	}

	public function getCaptcha() {
		return [];
	}

	/**
	 * @param array $captchaData
	 * @param string $id
	 * @return Message
	 */
	public function getCaptchaInfo( $captchaData, $id ) {
		return wfMessage( 'renocaptcha-info' );
	}

	/**
	 * @return GeetestCaptchaAuthenticationRequest
	 */
	public function createAuthenticationRequest() {
		return new GeetestCaptchaAuthenticationRequest();
	}

	/**
	 * @param array $requests
	 * @param array $fieldInfo
	 * @param array &$formDescriptor
	 * @param string $action
	 */
	public function onAuthChangeFormFields(
		array $requests, array $fieldInfo, array &$formDescriptor, $action
	) {
		$req = AuthenticationRequest::getRequestByClass( $requests,
			CaptchaAuthenticationRequest::class, true );
		if ( !$req ) {
			return;
		}

		// ugly way to retrieve error information
		$captcha = ConfirmEditHooks::getInstance();

		$formDescriptor['captchaWord'] = [
			'class' => HTMLGeetestCaptchaField::class,
			'error' => $captcha->getError(),
		] + $formDescriptor['captchaWord'];
	}
}
