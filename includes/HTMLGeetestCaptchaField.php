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
     * @throws \MWException
     */
	public function __construct( array $params ) {
		$params += [ 'error' => null ];
		parent::__construct( $params );

		$this->error = $params['error'];

		$this->mName = 'geetest-captcha-response';
	}

	public function getInputHTML( $value ) {
		$out = $this->mParent->getOutput();

		$out->addModuleStyles('ext.confirmEdit.GeetestCaptcha.styles');
		$out->addModules('ext.confirmEdit.GeetestCaptcha');
		$out->addModules('ext.confirmEdit.GeetestCaptcha.init');

		$output = Html::openElement( 'div', [
			'class' => [
				'geetest-captcha',
				'mw-confirmedit-captcha-fail' => (bool)$this->error,
			],
		] ) . '<div class="loading">
			<div class="bounce1"></div>
			<div class="bounce2"></div>
			<div class="bounce3"></div>
		</div>' . Html::hidden( 'wpCaptchaId', false, [
			'class' => 'geetest-captcha-id',
		] ) . Html::hidden( 'wpCaptchaWord', false, [
			'class' => 'geetest-captcha-data',
		] ) . Html::closeElement( 'div' );
		return $output;
	}
}