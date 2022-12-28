if(!window.isekai){
    window.isekai = {};
}

(function(){
    function initGeetestWidget(
        dom,
        geetestData
    ){
        return new Promise(function(resolve, reject){
            var $element = $(dom);
            if($element.find('.geetest_initized').length > 0){
                return reject(new Error('initized'));
            }
            var loadingDom = $element.find('.loading:first');
            $element.append('<span class="geetest_initized"></span>');

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
                captchaObj.appendTo(dom);
                captchaObj.onReady(function(){
                    loadingDom.hide();
                });
                captchaObj.onSuccess(function(){
                    resolve(captchaObj.getValidate());
                });
                captchaObj.onError(function(err){
                    reject(err);
                });
            });
        });
    }

    isekai.initConfirmEditGeetest = function(mode = 'normal', geetestData = false){
        var api = new mw.Api();
        switch(mode){
            case "normal": default:
                //默认的验证码
                $('.geetest-captcha').each(function(){
                    var dom = this;
                    var element = $(this);
                    if(element.find('.geetest_initized').length === 0){
                        //进行预处理
                        var submitBtn = element.parents('form').find('#wpCreateaccount,#wpLoginAttempt,#wpSave');
                        submitBtn.prop('disabled', true);

                        var responseField = element.find('.geetest-captcha-data');
                        var captchaIdField = element.find('.geetest-captcha-id');

                        api.get({
                            action:'getgeetestcode'
                        }).done(function(res){
                            var data = res.getgeetestcode;
                            captchaIdField.val(data.id);
                            initGeetestWidget(dom, data).then(function(response){
                                submitBtn.prop('disabled', false);
                                responseField.val(JSON.stringify(response));
                            });
                        });
                    }
                });
                break;
            case 'mobileFrontend':
                //进行手机端的预处理
                var element = $("#question .geetest-captcha");
                var dom = element[0];
                if(element.find('.geetest_initized').length === 0){
                    var responseField = element.parent('#question').parent('div').find('.captcha-word');
                    responseField.hide();

                    api.get({
                        action:'getgeetestcode'
                    }).done(function(res) {
                        var data = res.getgeetestcode;
                        initGeetestWidget(dom, data).then(function (response) {
                            response.geetest_id = data.id;
                            responseField.val(JSON.stringify(response));
                        });
                    });
                }
                break;
        }
    }
})();