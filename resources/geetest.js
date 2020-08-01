if(!window.isekai){
    window.isekai = {};
}

(function(){
    isekai.initConfirmEditGeetest = function(){
        var api = new mw.Api();
        $('.geetest-captcha').each(function(){
            var element = $(this);
            var dom = this
            if(element.find('.geetest_initized').length == 0){
                var submitBtn = element.parents('form').find('#wpCreateaccount,#wpLoginAttempt,#wpSave');
                submitBtn.prop('disabled', true);

                var responseField = element.find('.geetest-captcha-data');

                element.append('<span class="geetest_initized"></span>');

                api.get({
                    action:'getgeetestcode'
                }).done(function(res){
                    var data = res.getgeetestcode;
                    element.find('.geetest-captcha-id').val(data.id);
                    initGeetest({
                        // 以下配置参数来自服务端 SDK
                        gt: data.gt,
                        challenge: data.challenge,
                        offline: !data.success,
                        new_captcha: true,
                        width: '100%',
                        product: 'float',
                        lang: mw.config.get('wgUserLanguage'),
                    }, function (captchaObj) {
                        captchaObj.appendTo(dom);
                        captchaObj.onSuccess(function(){
                            responseField.val(JSON.stringify(captchaObj.getValidate()));
                            submitBtn.prop('disabled', false);
                        });
                    });
                });
            }
        });
    }

    $(function(){
        isekai.initConfirmEditGeetest();
    });
})();