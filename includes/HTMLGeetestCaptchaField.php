<?php

/**
 * Creates a ReCaptcha v2 widget. Does not return any data; handling the data submitted by the
 * widget is callers' responsibility.
 */
class HTMLGeetestCaptchaField extends HTMLFormField {
	/** @var string Error returned by ReCaptcha in the previous round. */
	protected $error;

	/**
	 * Parameters:
	 * - key: (string, required) ReCaptcha public key
	 * - error: (string) ReCaptcha error from previous round
	 * @param array $params
	 */
	public function __construct( array $params ) {
		$params += [ 'error' => null ];
		parent::__construct( $params );

		$this->error = $params['error'];

		$this->mName = 'geetest-captcha-response';
	}

	public function getInputHTML( $value ) {
		$out = $this->mParent->getOutput();
		
		$out->addModules('ext.confirmEdit.GeetestCaptcha');

		$output = Html::openElement( 'div', [
			'class' => [
				'geetest-captcha',
				'mw-confirmedit-captcha-fail' => (bool)$this->error,
			],
		] ) . Html::hidden( 'geetest_id', false, [
			'class' => 'geetest-captcha-id',
		] ) . Html::closeElement( 'div' );
		return $output;
	}
}