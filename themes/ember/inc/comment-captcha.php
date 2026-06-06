@if(empty($currentAdmin) && \App\Services\CommentSettingsService::captchaEnabled())
<div class="form-row captcha-row">
    <input type="text" name="captcha" class="captcha-input" placeholder="验证码 *" autocomplete="off" maxlength="4" required>
    <img class="captcha-img" src="/captcha" alt="点击刷新验证码" title="看不清?点击刷新" width="120" height="44">
</div>
@endif
