<?php
declare(strict_types=1);

namespace App\Controllers\Front;

use App\Core\Request;
use App\Core\Session;

/**
 * 评论图片验证码
 * - GET /captcha 生成 4 位字符验证码图片(GD），答案(小写)存入 session。
 * - 提交评论时由 CommentController::submit() 校验,一次性使用。
 * - 字符集去除易混淆的 0/O/1/I;暖色调呼应 ember 主题。
 */
final class CaptchaController
{
    public function image(Request $request): never
    {
        $chars = '23456789ABCDEFGHJKLMNPQRSTUVWXYZ';
        $code = '';
        for ($i = 0; $i < 4; $i++) {
            $code .= $chars[random_int(0, strlen($chars) - 1)];
        }
        Session::set('_captcha', strtolower($code));

        $w = 130;
        $h = 46;

        // 先在小画布上画字符,再放大到主画布,得到更大且略带柔化的字形(无需 TTF 字体)。
        $font = 5;
        $cw = imagefontwidth($font);   // 9
        $ch = imagefontheight($font);  // 15
        $cellW = $cw + 8;

        $base = imagecreatetruecolor($cellW * 4, $ch + 8);
        $baseBg = imagecolorallocate($base, 244, 241, 237);
        imagefilledrectangle($base, 0, 0, imagesx($base), imagesy($base), $baseBg);
        for ($i = 0; $i < 4; $i++) {
            $tc = imagecolorallocate($base, random_int(170, 220), random_int(55, 110), random_int(40, 85));
            $x = $i * $cellW + 4;
            $y = 4 + random_int(-2, 2);
            imagestring($base, $font, $x, $y, $code[$i], $tc);
        }

        $img = imagecreatetruecolor($w, $h);
        $bg = imagecolorallocate($img, 244, 241, 237);
        imagefilledrectangle($img, 0, 0, $w, $h, $bg);

        // 放大字符区
        imagecopyresampled($img, $base, 6, 4, 0, 0, $w - 12, $h - 8, imagesx($base), imagesy($base));

        // 干扰线
        for ($i = 0; $i < 5; $i++) {
            $lc = imagecolorallocate($img, random_int(190, 225), random_int(150, 190), random_int(140, 180));
            imageline($img, random_int(0, $w), random_int(0, $h), random_int(0, $w), random_int(0, $h), $lc);
        }
        // 干扰点
        for ($i = 0; $i < 140; $i++) {
            $pc = imagecolorallocate($img, random_int(160, 220), random_int(140, 200), random_int(130, 190));
            imagesetpixel($img, random_int(0, $w - 1), random_int(0, $h - 1), $pc);
        }

        // 清空可能存在的输出缓冲,确保返回纯净的二进制图片
        while (ob_get_level() > 0) {
            ob_end_clean();
        }

        header('Content-Type: image/png');
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        header('Pragma: no-cache');
        imagepng($img);
        exit;
    }
}
