mw.libs.ve.targetLoader.addPlugin( function () {

	ve.init.mw.GeetestCaptchaSaveErrorHandler = function () {};

	OO.inheritClass( ve.init.mw.GeetestCaptchaSaveErrorHandler, ve.init.mw.SaveErrorHandler );

	ve.init.mw.GeetestCaptchaSaveErrorHandler.static.name = 'confirmEditGeetestCaptcha';

	ve.init.mw.GeetestCaptchaSaveErrorHandler.static.getReadyPromise = function () {
		return new Promise((resolve, reject) => {
			var api = new mw.Api();
			api.get({
				action:'getgeetestcode'
			}).done((res) => {
				this.geetestData = res.getgeetestcode;
				resolve(this.geetestData);
			});
		})
	};

	ve.init.mw.GeetestCaptchaSaveErrorHandler.static.matchFunction = function ( data ) {
		var captchaData = ve.getProp( data, 'visualeditoredit', 'edit', 'captcha' );

		return !!( captchaData && captchaData.type === 'recaptchanocaptcha' );
	};

	ve.init.mw.GeetestCaptchaSaveErrorHandler.static.process = function ( data, target ) {
		var self = this,
			$container = $( '<div>' );

		self.captchaValidate = {};

		// Register extra fields
		target.saveFields.wpCaptchaId = function () {
			// eslint-disable-next-line no-jquery/no-global-selector
			return self.captchaId;
		};

		target.saveFields.wpCaptchaWord = function () {
			// eslint-disable-next-line no-jquery/no-global-selector
			return JSON.stringify(self.captchaValidate);
		};

		if ( self.captchaObj ) {
			self.captchaObj.reset();
			target.emit( 'saveErrorCaptcha' );
		} else {
			this.getReadyPromise().then( function (geetestData) {
				target.saveDialog.showMessage( 'api-save-error', $container );

				$container.parent('p').find('strong').text(mw.message('captcha-label').parse());

				self.captchaId = geetestData.id;

				initGeetest({
					// 以下配置参数来自服务端 SDK
					gt: geetestData.gt,
					challenge: geetestData.challenge,
					offline: !geetestData.success,
					new_captcha: true,
					width: '100%',
					product: 'float',
					lang: mw.config.get('wgUserLanguage'),
				}, function (captchaObj) {
					self.captchaObj = captchaObj;
					// 这里可以调用验证实例 captchaObj 的实例方法
					captchaObj.appendTo($container[0]);
					captchaObj.onSuccess(function(){
						self.captchaValidate = captchaObj.getValidate();
						target.saveDialog.executeAction( 'save' );
					});
				});

				target.saveDialog.updateSize();

				target.emit( 'saveErrorCaptcha' );
			} );
		}
	};

	ve.init.mw.saveErrorHandlerFactory.register( ve.init.mw.GeetestCaptchaSaveErrorHandler );

} );
