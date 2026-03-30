<?php namespace ProcessWire;
/** @var string $code */
/** @var int $ttlMinutes */
/** @var string $projectName */
/** @var string $projectUrl */
/** @var string $supportEmail */
?>
<!doctype html>
<html lang="ru">
<head>
  <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= htmlspecialchars($projectName, ENT_QUOTES, 'UTF-8') ?></title>
  <style>
    @font-face {
      font-family: "Inter";
      src: url("https://skfo.ru/site/templates/assets/fonts/Inter-Regular.woff2") format("woff2");
      font-weight: 400;
      font-style: normal;
    }
    @font-face {
      font-family: "Inter";
      src: url("https://skfo.ru/site/templates/assets/fonts/Inter-Bold.woff2") format("woff2");
      font-weight: 700;
      font-style: normal;
    }
    @font-face {
      font-family: "Ruberoid";
      src: url("https://skfo.ru/site/templates/assets/fonts/Ruberoid-Bold.woff2") format("woff2");
      font-weight: 700;
      font-style: normal;
    }
  </style>
</head>
<body style="margin:0; padding:0; background-color:#ebebeb; color:#1d2433;">
  <div style="display:none; max-height:0; overflow:hidden; opacity:0; mso-hide:all;">
    Код подтверждения для входа в <?= htmlspecialchars($projectName, ENT_QUOTES, 'UTF-8') ?>.
  </div>
  <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="width:100%; border-collapse:collapse; background:#ebebeb;">
    <tr>
      <td align="center" style="padding:32px 16px;">
        <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="max-width:640px; width:100%; border-collapse:collapse;">
          <tr>
            <td style="padding-bottom:16px; font-family:'Inter', Arial, sans-serif; font-size:13px; letter-spacing:1.6px; text-transform:uppercase; color:#1f52b2;">
              <?= htmlspecialchars($projectName, ENT_QUOTES, 'UTF-8') ?>
            </td>
          </tr>
          <tr>
            <td style="background-color:#ffffff; border:1px solid #d9e5fb; border-radius:24px; padding:40px 32px; box-shadow:0 20px 60px rgba(31, 79, 184, 0.12);">
              <div style="font-family:'Ruberoid', 'Inter', Arial, sans-serif; font-size:34px; line-height:1.1; color:#1d2433; margin:0 0 18px;">
                Подтвердите вход
              </div>
              <div style="font-family:'Inter', Arial, sans-serif; font-size:16px; line-height:1.7; color:#4f5b73; margin:0 0 24px;">
                Используйте этот код, чтобы завершить вход или регистрацию в <?= htmlspecialchars($projectName, ENT_QUOTES, 'UTF-8') ?>.
              </div>
              <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="margin:0 0 24px; border-collapse:separate; border-spacing:0;">
                <tr>
                  <td align="center" style="background:linear-gradient(135deg, #1f4fb8 0%, #2f66d6 100%); border-radius:20px; padding:20px 16px;">
                    <div style="font-family:'Ruberoid', 'Courier New', Courier, monospace; font-size:36px; line-height:1; letter-spacing:10px; color:#ffffff; font-weight:700;">
                      <?= htmlspecialchars($code, ENT_QUOTES, 'UTF-8') ?>
                    </div>
                  </td>
                </tr>
              </table>
              <div style="font-family:'Inter', Arial, sans-serif; font-size:15px; line-height:1.7; color:#4f5b73; margin:0 0 16px;">
                Код действует <?= (int) $ttlMinutes ?> минут. Никому его не сообщайте и не пересылайте.
              </div>
              <div style="font-family:'Inter', Arial, sans-serif; font-size:14px; line-height:1.7; color:#8f4d17; padding-top:20px; border-top:1px solid #e7eefc;">
                Если вы не запрашивали этот код, просто проигнорируйте письмо. Доступ в аккаунт без подтверждения не будет выполнен.
              </div>
            </td>
          </tr>
          <tr>
            <td style="padding:18px 8px 0; font-family:'Inter', Arial, sans-serif; font-size:12px; line-height:1.7; color:#748099; text-align:center;">
              <div style="margin-bottom:6px;">
                <a href="<?= htmlspecialchars($projectUrl, ENT_QUOTES, 'UTF-8') ?>" style="color:#1f52b2; text-decoration:none;"><?= htmlspecialchars($projectUrl, ENT_QUOTES, 'UTF-8') ?></a>
              </div>
              <div>
                Вопросы по входу: <a href="mailto:<?= htmlspecialchars($supportEmail, ENT_QUOTES, 'UTF-8') ?>" style="color:#1f52b2; text-decoration:none;"><?= htmlspecialchars($supportEmail, ENT_QUOTES, 'UTF-8') ?></a>
              </div>
            </td>
          </tr>
        </table>
      </td>
    </tr>
  </table>
</body>
</html>
