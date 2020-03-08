$(function(){
    var api = new mw.Api();
    $('.geetest-captcha').each(function(){
        var element = $(this);
        var dom = this
        var submitBtn = element.parents('form').find('#wpCreateaccount,#wpLoginAttempt,#wpSave');
        submitBtn.prop('disabled', true);

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
                // 这里可以调用验证实例 captchaObj 的实例方法
                captchaObj.appendTo(dom);
                captchaObj.onSuccess(function(){
                    submitBtn.prop('disabled', false);
                });
            });
        });
    });
});