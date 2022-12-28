<?php

use MediaWiki\Auth\AuthenticationRequest;
use MediaWiki\Extension\ConfirmEdit\Auth\CaptchaAuthenticationRequest;
use MediaWiki\Extension\ConfirmEdit\SimpleCaptcha\SimpleCaptcha;

class GeetestCaptcha extends SimpleCaptcha {
	protected static $messagePrefix = 'geetest-';

	private static $passedId = [];

	private $error = null;

	public function getCode() {
		global $wgGeetestID, $wgGeetestKey;
		
		$requestCtx = RequestContext::getMain()->getUser();
		$wgUser = $requestCtx->getUser();
		$wgRequest = $requestCtx->getRequest();

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

	private function buildGeetestWidget($scene = 'normal'){
	    $jsParam = "'" . $scene . "'";
	    return Html::openElement( 'script', [
                'type' => 'text/javascript',
            ]) . 'if(typeof mw !== "undefined") {
			mw.loader.using(["ext.confirmEdit.GeetestCaptcha.styles", "ext.confirmEdit.GeetestCaptcha"], function(){
				isekai.initConfirmEditGeetest(' . $jsParam . ');
			});
		}' . Html::closeElement( 'script' ) . Html::openElement( 'div', [
                'class' => [
                    'geetest-captcha',
                    'mw-confirmedit-captcha-fail' => (bool)$this->error,
                ],
            ] ) . '<div class="loading">
			<div class="bounce1"></div>
			<div class="bounce2"></div>
			<div class="bounce3"></div>
		</div>';
    }

	/**
	 * Get the captcha form.
	 * @param int $tabIndex
	 * @return array
	 */
	public function getFormInformation( $tabIndex = 1 ) {
		$output = $this->buildGeetestWidget() . Html::hidden( 'wpCaptchaId', false, [
			'class' => 'geetest-captcha-id',
		] ) . Html::hidden( 'wpCaptchaWord', false, [
			'class' => 'geetest-captcha-data',
		] ) . Html::closeElement( 'div' );
		return [
			'html' => $output,
			'modules' => [
				'ext.confirmEdit.GeetestCaptcha.styles',
				'ext.confirmEdit.GeetestCaptcha',
				'ext.confirmEdit.GeetestCaptcha.init',
			],
		];
	}

	/**
	 * @param Status|array|string $info
	 */
	protected function logCheckError( $info ) {
		if ( $info instanceof Status ) {
			$errors = $info->getErrors();
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
		$index = $request->getVal('wpCaptchaId', $request->getVal('captchaid'));
		$response = json_decode($request->getVal('wpCaptchaWord',
            $request->getVal('captchaword')), true);
		
		return [ $index, $response ];
	}

    /**
     * Check, if the user solved the captcha.
     *
     * @param $index
     * @param mixed $request datas
     * @return bool
     * @throws \MWException
     */
	protected function passCaptcha( $index, $request ) {
		global $wgGeetestID, $wgGeetestKey;

		$requestCtx = RequestContext::getMain()->getUser();
		$wgUser = $requestCtx->getUser();
		$wgRequest = $requestCtx->getRequest();

        if(!$request || !is_array($request)){
            list($index, $request) = $this->getCaptchaParamsFromRequest($wgRequest);
        }
        if(isset($request['geetest_id'])) $index = $request['geetest_id'];
        if(!isset($request['geetest_challenge'])) return false;
		// 缓存的结果
		if(in_array($index, self::$passedId)){
			return true;
		}
		// Build data to append to request
		if(!empty($wgUser)){
			$uid = strval($wgUser->getID());
		} else {
			$uid = 'guest';
		}

		$session = $this->retrieveCaptcha($index);
		$this->clearCaptcha($index);

		$GtSdk = new GeetestLib($wgGeetestID, $wgGeetestKey);
		$data = [
			"user_id" => $uid,
			"client_type" => "web",
			"ip_address" => $wgRequest->getIP(),
		];

		if ($session['gtserver'] == 1) { //在线验证
			$status = $GtSdk->success_validate($request['geetest_challenge'], $request['geetest_validate'], $request['geetest_seccode'], $data);
		} else { //离线验证
			$status = $GtSdk->fail_validate($request['geetest_challenge'], $request['geetest_validate'], $request['geetest_seccode']);
		}

		if($status){
			self::$passedId[] = $index;
			return true;
		} else {
			return false;
		}
	}

	public function passCaptchaLimited($index, $request, User $user){
		return $this->passCaptcha($index, $request);
	}

	/**
	 * @param array &$resultArr
	 */
	protected function addCaptchaAPI( &$resultArr ) {
		$resultArr['captcha'] = $this->describeCaptchaType();
		$resultArr['captcha']['error'] = $this->error;
        $resultArr['captcha']['id'] = md5(uniqid());
        $resultArr['captcha']['question'] = $this->buildGeetestWidget('mobileFrontend');
	}

	/**
	 * @return array
	 */
	public function describeCaptchaType() {
		global $wgReCaptchaSiteKey;
		return [
			'type' => 'geetestcaptcha',
			'mime' => 'text/html',
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
	public function apiGetAllowedParams( ApiBase $module, &$params, $flags ) {
		if ( $this->isAPICaptchaModule( $module ) ) {
            $params['captchaword'] = [
                ApiBase::PARAM_HELP_MSG => 'captcha-apihelp-param-captchaword',
            ];
            $params['captchaid'] = [
                ApiBase::PARAM_HELP_MSG => 'captcha-apihelp-param-captchaid',
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
