<?php
/**
 * Api module to load GeetestCaptcha
 *
 * @ingroup API
 * @ingroup Extensions
 */
class ApiGeetestCaptchaCode extends ApiBase {
	public function execute() {
		# Get a new FancyCaptcha form data
		$captcha = new GeetestCaptcha();
		$code = $captcha->getCode();

		$result = $this->getResult();
		$result->addValue( null, $this->getModuleName(), $code );
		return true;
	}

	public function getAllowedParams() {
		return [];
	}

	/**
	 * @see ApiBase::getExamplesMessages()
	 * @return array
	 */
	protected function getExamplesMessages() {
		return [
			'action=getgeetestcode'
				=> 'apihelp-geetestcaptchareload-example-1',
		];
	}
}
