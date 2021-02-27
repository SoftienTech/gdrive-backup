<?php
set_time_limit(0);
ini_set('max_execution_time', 0);
ini_set('memory_limit', -1);

$required_fields = ['google_client_id', 'google_client_secret', 'google_drive_folder_id', 'server_backup_folder'];

$is_installed = file_exists('.config');

$_CONFIG = [];

function print_log($type, $text)
{
    echo $type . ': ' . $text . ' [' . date('Y-m-d H:i:s') . ']' . PHP_EOL;
}

function is_json($str)
{
    json_decode($str);
    return (json_last_error() == JSON_ERROR_NONE);
}

if (isset($_GET['cron']) && $_GET['cron'] === 'true') {
    ob_start();
    header('Content-Type: text/plain');
    if ($is_installed) {
        set_config();
        print_log('INFO', 'Backup process has started.');
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, 'https://accounts.google.com/o/oauth2/token');
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, [
            'client_id' => $_CONFIG['google_client_id'],
            'client_secret' => $_CONFIG['google_client_secret'],
            'refresh_token' => $_CONFIG['refresh_token'],
            'grant_type' => 'refresh_token'
        ]);
        $response = curl_exec($ch);
        curl_close($ch);
        $result = json_decode($response, true);

        if (is_json($response) && !isset($result['error'])) {
            $access_token = $result['access_token'];
            print_log('INFO', 'Access token has fetched.');
            print_log('INFO', sprintf('Backup folder (%s) is processing...', $_CONFIG['server_backup_folder']));
            $zip = new ZipArchive();
            $file_name = md5(uniqid() . rand(999, 9999)) . '.zip';
            $zip->open($file_name, ZipArchive::CREATE | ZipArchive::OVERWRITE);
            $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($_CONFIG['server_backup_folder']), RecursiveIteratorIterator::LEAVES_ONLY);
            foreach ($files as $name => $file) {
                if (!$file->isDir()) {
                    $filePath = $file->getRealPath();
                    $zip->addFile($filePath, basename($filePath));
                }
            }
            $zip->close();

            print_log('INFO', sprintf('ZIP file (%s) has generated.', $file_name));

            $handle = fopen($file_name, 'rb');
            $file = fread($handle, filesize($file_name));
            fclose($handle);

            $nl = "\r\n";

            $boundary = 'xxxxxxxxxx';
            $data = '--' . $boundary . $nl;
            $data .= 'Content-Type: application/json; charset=UTF-8' . $nl . $nl;
            $data .= json_encode(['name' => 'backup.' . date('Y-m-d') . '.zip', 'parents' => [$_CONFIG['google_drive_folder_id']]], JSON_UNESCAPED_UNICODE) . $nl;
            $data .= '--' . $boundary . $nl;
            $data .= 'Content-Transfer-Encoding: base64' . $nl . $nl;
            $data .= base64_encode($file);
            $data .= $nl . '--' . $boundary . '--';

            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, 'https://www.googleapis.com/upload/drive/v3/files?uploadType=multipart');
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 0);
            curl_setopt($ch, CURLOPT_TIMEOUT, 0);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Authorization: Bearer ' . $access_token,
                'Content-Type: multipart/related; boundary=' . $boundary,
            ]);
            $response = curl_exec($ch);
            $result = json_decode($response, true);
            $error = curl_error($ch);
            curl_close($ch);
            if ($error == false && is_json($response) && isset($result['id'])) {
                print_log('SUCCESS', sprintf('ZIP file has successfully uploaded to Google Drive. (https://drive.google.com/file/d/%s)', $result['id']));
                unlink($file_name);
                print_log('INFO', 'ZIP file has been deleted from local server.');
            } else {
                print_log('ERROR', 'ZIP file could not be uploaded to Google Drive.');
            }
        } else {
            print_log('ERROR', 'Access token fetching process has failed.');
        }
    } else {
        print_log('ERROR', 'Backup tool is not configured.');
    }
    file_put_contents('.log', ob_get_flush());
    die();
}

$_LANG = [
    'tr' => [
        'name' => 'Türkçe',
        'title' => 'Google Drive Yedekleme Aracı',
        'info' => 'Bilgi',
        'error' => 'Hata',
        'successful' => 'Başarılı',
        'not_installed' => 'Yedekleme aracı kurulmamış, lütfen kurulum işlemini gerçekleştirin.',
        'installed_text' => 'Yedekleme aracı başarıyla kurulmuştur. Otomatik yedekleme için cronu ayarlamayı unutmayın.',
        'description' => 'Bu yedekleme aracını kullanarak hosting kontrol panelinizin aldığı yedekleri otomatik olarak Google Drive\'a yükleyebilirsiniz.',
        'current_language' => 'Şu Anki Dil',
        'google_client_id' => 'Google API İstemci Kimliği',
        'google_client_secret' => 'Google API İstemci Anahtarı',
        'google_drive_folder_id' => 'Google Drive Klasör Kodu',
        'server_backup_folder' => 'Sunucu Yedek Klasörü Konumu',
        'empty_fields' => 'Lütfen tüm alanları doğru bir şekilde doldurun.',
        'server_folder_invalid' => 'Sunucu yedek klasörü konumu geçersiz veya erişilebilir durumda değil.',
        'submit' => 'Gönder',
        'previous_step' => 'Önceki Adım',
        'google_auth_key' => 'Google API Yetkilendirme Anahtarı',
        'invalid_auth_key' => 'Yetkilendirme anahtarı geçerli değil, lütfen tekrar deneyin.',
        'step_two_text' => '<b onclick="window.open(\'%s\', \'_blank\')" style="cursor:pointer" class="text-decoration-underline">Buraya tıklayın</b>, açılan ekranda Google Drive\'a bağlı hesabınız ile oturum açın. Ekranda çıkan kodu kopyalayın ve aşağıdaki <b>Google API Yetkilendirme Anahtarı</b> kısmına yapıştırın.',
        'settings_saved' => 'Ayarlar kaydedilmiştir.',
        'change_config_text' => 'Bir ayarı değiştirmek istiyorsanız tüm ayarları sıfırlamanız gereklidir.',
        'cron_url_address' => 'Cron İşi URL Adresi',
        'last_log' => 'En Yeni Günlük Kayıtları',
        'log_empty' => 'İlk çalıştırma sonrası günlük kayıtlarını burada göreceksiniz.',
        'reset_settings' => 'Ayarları Sıfırla'
    ],
    'en' => [
        'name' => 'English',
        'title' => 'Google Drive Backup Tool',
        'info' => 'Info',
        'error' => 'Error',
        'successful' => 'Successful',
        'not_installed' => 'Backup tool is not configured, please complete the setup process.',
        'installed_text' => 'Backup tool is installed successfully. Don\'t forget to setup cron for automatic backups.',
        'description' => 'You can automatically upload backups (created by your hosting control panel) to Google Drive with using this tool.',
        'current_language' => 'Current Language',
        'google_client_id' => 'Google API Client ID',
        'google_client_secret' => 'Google API Client Secret',
        'google_drive_folder_id' => 'Google Drive Folder ID',
        'server_backup_folder' => 'Server Backup Folder Path',
        'empty_fields' => 'Please fill all fields correctly.',
        'server_folder_invalid' => 'Server backup folder path is invalid or not accessible.',
        'submit' => 'Submit',
        'previous_step' => 'Previous Step',
        'google_auth_key' => 'Google API Authorization Key',
        'invalid_auth_key' => 'Authorization key is not valid, please try again.',
        'step_two_text' => '<b onclick="window.open(\'%s\', \'_blank\')" style="cursor:pointer" class="text-decoration-underline">Click here</b>, sign in with your Google account (linked to Google Drive). Copy the code on the screen and paste to <b>Google API Authorization Key</b> field.',
        'settings_saved' => 'Settings have been saved.',
        'change_config_text' => 'If you need to change a setting, you have to reset all settings.',
        'cron_url_address' => 'Cronjob URL Address',
        'last_log' => 'Latest Log Records',
        'log_empty' => 'You will see log records after first run.',
        'reset_settings' => 'Reset Settings'
    ]
];

$LANG = 'en';

if (file_exists('.lang')) {
    $content = file_get_contents('.lang');
    if (isset($_LANG[$content])) {
        $LANG = $content;
    }
}

if (isset($_GET['lang']) && isset($_LANG[$_GET['lang']])) {
    file_put_contents('.lang', $_GET['lang']);
    header('Location: ' . explode('?', $_SERVER['REQUEST_URI'])[0]);
    die();
}

function lang($key)
{
    global $_LANG, $LANG;
    return isset($_LANG[$LANG][$key]) ? $_LANG[$LANG][$key] : $key;
}

function set_config()
{
    global $required_fields, $_CONFIG;
    $content = file_get_contents('.config');
    if (is_json($content)) {
        $_CONFIG = json_decode($content, true);
        foreach ($required_fields as $required_field) {
            if (!isset($_CONFIG[$required_field])) {
                $error = true;
                break;
            }
        }
        if (!isset($_CONFIG['refresh_token'])) {
            $error = true;
        }
        if (isset($error)) {
            unlink('.config');
            header('Location: ' . $_SERVER['REQUEST_URI']);
            die();
        } else {
            $_CONFIG['cron_url_address'] = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
            $_CONFIG['cron_url_address'] = explode('?', $_CONFIG['cron_url_address'])[0] . '?cron=true';
        }
    } else {
        unlink('.config');
        header('Location: ' . $_SERVER['REQUEST_URI']);
        die();
    }
}

if ($is_installed) {
    set_config();
    $alert = [
        'type' => 'info',
        'text' => '<b>' . lang('info') . ':</b> ' . lang('installed_text')
    ];
} else {
    $alert = [
        'type' => 'info',
        'text' => '<b>' . lang('info') . ':</b> ' . lang('not_installed')
    ];
}

if (!$is_installed && isset($_POST['action']) && $_POST['action'] === 'settings') {
    $settings_data = [];
    foreach ($required_fields as $required_field) {
        if (isset($_POST[$required_field])) {
            if ($required_field == 'server_backup_folder') {
                $_POST[$required_field] = rtrim($_POST[$required_field], '/') . '/';
            }
            $settings_data[$required_field] = $_POST[$required_field];
        } else {
            $alert = [
                'type' => 'danger',
                'text' => '<b>' . lang('error') . ':</b> ' . lang('empty_fields')
            ];
            $error = true;
            break;
        }
    }
    if (!$error) {
        $path = realpath($settings_data['server_backup_folder']);
        if ($path === false || !is_dir($path)) {
            $alert = [
                'type' => 'danger',
                'text' => '<b>' . lang('error') . ':</b> ' . lang('server_folder_invalid')
            ];
        } else {
            $step_two = true;

            $login_url = 'https://accounts.google.com/o/oauth2/auth?' . http_build_query([
                'response_type' => 'code',
                'client_id' => $settings_data['google_client_id'],
                'redirect_uri' => 'urn:ietf:wg:oauth:2.0:oob',
                'access_type' => 'offline',
                'scope' => 'https://www.googleapis.com/auth/drive'
            ]);
            $alert = [
                'type' => 'info',
                'text' => '<b>' . lang('info') . ':</b> ' . sprintf(lang('step_two_text'), $login_url)
            ];
            if (isset($_POST['google_auth_key'])) {
                $ch = curl_init();
                curl_setopt($ch, CURLOPT_URL, 'https://accounts.google.com/o/oauth2/token');
                curl_setopt($ch, CURLOPT_POST, true);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_POSTFIELDS, [
                    'code' => $_POST['google_auth_key'],
                    'client_id' => $settings_data['google_client_id'],
                    'client_secret' => $settings_data['google_client_secret'],
                    'redirect_uri' => 'urn:ietf:wg:oauth:2.0:oob',
                    'grant_type' => 'authorization_code'
                ]);
                $response = curl_exec($ch);
                curl_close($ch);
                $result = json_decode($response, true);
                if (!is_json($response) || isset($result['error'])) {
                    $_POST['google_auth_key'] = lang('invalid_auth_key');
                } else {
                    $settings_data['refresh_token'] = $result['refresh_token'];
                    $alert = [
                        'type' => 'success',
                        'text' => '<b>' . lang('successful') . ':</b> ' . lang('settings_saved')
                    ];
                    file_put_contents('.config', json_encode($settings_data, JSON_UNESCAPED_UNICODE));
                    $is_installed = true;
                    set_config();
                }
            }
        }
    }
} else if ($is_installed && isset($_POST['action']) && $_POST['action'] === 'reset_settings') {
    if(file_exists('.log')) {
        unlink('.log');
    }
    unlink('.config');
    header('Location: ' . $_SERVER['REQUEST_URI']);
    die();
}
?>
<!DOCTYPE html>
<html lang="<?php echo htmlspecialchars($LANG) ?>">

<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex">
    <title><?php echo lang('title') ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.0.0-beta2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300&display=swap" rel="stylesheet">
    <link rel="shortcut icon" href="data:image/png;base64,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" type="image/png">
    <style>
        body {
            font-family: 'Poppins', sans-serif;
        }
    </style>
</head>

<body>
    <div class="container my-5">
        <div class="mb-3">
            <img height="50" src="data:image/png;base64,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" alt="<?php echo lang('title') ?>">
            <div class="row align-items-start">
                <div class="col-lg-9">
                    <h3 class="mt-3"><?php echo lang('title') ?></h3>
                    <p class="m-0"><?php echo lang('description') ?></p>
                </div>
                <div class="col-lg-3 mt-3 mt-lg-0">
                    <h5 class="mb-3"><?php echo lang('current_language') ?>:</h5>
                    <form action="" method="get" id="language_form">
                        <select name="lang" class="form-control" onchange="document.getElementById('language_form').submit()">
                            <?php foreach ($_LANG as $key => $value) { ?><option value="<?php echo htmlspecialchars($key) ?>" <?php echo $key === $LANG ? ' selected' : '' ?>><?php echo htmlspecialchars($value['name']) ?></option><?php } ?>
                        </select>
                    </form>
                </div>
            </div>
        </div>
        <?php if (isset($alert)) { ?>
            <div class="alert alert-<?php echo htmlspecialchars($alert['type']) ?> mb-3"><?php echo $alert['text'] ?></div>
        <?php } ?>
        <?php if (!$is_installed) { ?>
            <form action="" method="post">
                <input type="hidden" name="action" value="settings">
                <div class="row">
                    <?php if (!isset($step_two)) { ?>
                        <div class="col-lg-6 mb-3">
                            <div class="mb-2"><?php echo lang('google_client_id') ?>:</div>
                            <input type="text" class="form-control" name="google_client_id" value="<?php echo isset($_POST['google_client_id']) ? htmlspecialchars($_POST['google_client_id']) : '' ?>" required>
                        </div>
                        <div class="col-lg-6 mb-3">
                            <div class="mb-2"><?php echo lang('google_client_secret') ?>:</div>
                            <input type="text" class="form-control" name="google_client_secret" value="<?php echo isset($_POST['google_client_secret']) ? htmlspecialchars($_POST['google_client_secret']) : '' ?>" required>
                        </div>
                        <div class="col-lg-6 mb-3">
                            <div class="mb-2"><?php echo lang('google_drive_folder_id') ?>:</div>
                            <input type="text" class="form-control" name="google_drive_folder_id" value="<?php echo isset($_POST['google_drive_folder_id']) ? htmlspecialchars($_POST['google_drive_folder_id']) : '' ?>" required>
                        </div>
                        <div class="col-lg-6 mb-3">
                            <div class="mb-2"><?php echo lang('server_backup_folder') ?>:</div>
                            <input type="text" class="form-control" name="server_backup_folder" value="<?php echo isset($_POST['server_backup_folder']) ? htmlspecialchars($_POST['server_backup_folder']) : '' ?>" required>
                        </div>
                        <div class="col-lg-6">
                            <button type="submit" class="btn btn-primary"><?php echo lang('submit') ?></button>
                        </div>
                    <?php } else { ?>
                        <div class="col-lg-6 mb-3">
                            <div class="mb-2"><?php echo lang('google_client_id') ?>:</div>
                            <input readonly type="text" class="form-control" name="google_client_id" value="<?php echo isset($_POST['google_client_id']) ? htmlspecialchars($_POST['google_client_id']) : '' ?>" required>
                        </div>
                        <div class="col-lg-6 mb-3">
                            <div class="mb-2"><?php echo lang('google_client_secret') ?>:</div>
                            <input readonly type="text" class="form-control" name="google_client_secret" value="<?php echo isset($_POST['google_client_secret']) ? htmlspecialchars($_POST['google_client_secret']) : '' ?>" required>
                        </div>
                        <div class="col-lg-6 mb-3">
                            <div class="mb-2"><?php echo lang('google_drive_folder_id') ?>:</div>
                            <input readonly type="text" class="form-control" name="google_drive_folder_id" value="<?php echo isset($_POST['google_drive_folder_id']) ? htmlspecialchars($_POST['google_drive_folder_id']) : '' ?>" required>
                        </div>
                        <div class="col-lg-6 mb-3">
                            <div class="mb-2"><?php echo lang('server_backup_folder') ?>:</div>
                            <input readonly type="text" class="form-control" name="server_backup_folder" value="<?php echo isset($_POST['server_backup_folder']) ? htmlspecialchars($_POST['server_backup_folder']) : '' ?>" required>
                        </div>
                        <div class="col-lg-12 mb-3">
                            <div class="mb-2"><?php echo lang('google_auth_key') ?>:</div>
                            <input type="text" class="form-control" name="google_auth_key" value="<?php echo isset($_POST['google_auth_key']) ? htmlspecialchars($_POST['google_auth_key']) : '' ?>" required>
                        </div>
                        <div class="col-lg-6">
                            <button type="submit" class="btn btn-primary"><?php echo lang('submit') ?></button>
                            <button type="button" class="btn btn-secondary" onclick="history.back()"><?php echo lang('previous_step') ?></button>
                        </div>
                    <?php } ?>
                </div>
            </form>
        <?php } else { ?>
            <div class="row">
                <div class="col-lg-12 mb-3">
                    <div class="mb-2"><?php echo lang('cron_url_address') ?>:</div>
                    <input readonly type="text" class="form-control" name="cron_url_address" value="<?php echo htmlspecialchars($_CONFIG['cron_url_address']) ?>">
                </div>
                <div class="col-lg-12 mb-3">
                    <div class="mb-2"><?php echo lang('last_log') ?>:</div>
                    <textarea readonly class="form-control" name="last_log" rows="4" style="resize:none"><?php echo file_exists('.log') ? htmlspecialchars(rtrim(file_get_contents('.log'), PHP_EOL)) : lang('log_empty') ?></textarea>
                </div>
                <div class="col-lg-6 mb-3">
                    <div class="mb-2"><?php echo lang('google_client_id') ?>:</div>
                    <input readonly type="text" class="form-control" name="google_client_id" value="<?php echo htmlspecialchars($_CONFIG['google_client_id']) ?>">
                </div>
                <div class="col-lg-6 mb-3">
                    <div class="mb-2"><?php echo lang('google_client_secret') ?>:</div>
                    <input readonly type="text" class="form-control" name="google_client_secret" value="<?php echo htmlspecialchars($_CONFIG['google_client_secret']) ?>">
                </div>
                <div class="col-lg-6 mb-3">
                    <div class="mb-2"><?php echo lang('google_drive_folder_id') ?>:</div>
                    <input readonly type="text" class="form-control" name="google_drive_folder_id" value="<?php echo htmlspecialchars($_CONFIG['google_drive_folder_id']) ?>">
                </div>
                <div class="col-lg-6 mb-3">
                    <div class="mb-2"><?php echo lang('server_backup_folder') ?>:</div>
                    <input readonly type="text" class="form-control" name="server_backup_folder" value="<?php echo htmlspecialchars($_CONFIG['server_backup_folder']) ?>">
                </div>
                <div class="col-lg-12 mb-3">
                    <?php echo lang('change_config_text') ?>
                </div>
                <div class="col-lg-12">
                    <form action="" method="post">
                        <input type="hidden" name="action" value="reset_settings">
                        <input type="submit" name="submit" value="<?php echo lang('reset_settings') ?>" class="btn btn-danger">
                    </form>
                </div>
            </div>
        <?php } ?>
    </div>
</body>

</html>