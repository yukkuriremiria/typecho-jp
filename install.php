<?php

if (!file_exists(dirname(__FILE__) . '/config.inc.php')) {
    // site root path
    define('__TYPECHO_ROOT_DIR__', dirname(__FILE__));

    // plugin directory (relative path)
    define('__TYPECHO_PLUGIN_DIR__', '/usr/plugins');

    // theme directory (relative path)
    define('__TYPECHO_THEME_DIR__', '/usr/themes');

    // admin directory (relative path)
    define('__TYPECHO_ADMIN_DIR__', '/admin/');

    // register autoload
    require_once __TYPECHO_ROOT_DIR__ . '/var/Typecho/Common.php';

    // init
    \Typecho\Common::init();
} else {
    require_once dirname(__FILE__) . '/config.inc.php';
    $installDb = \Typecho\Db::get();
}

/**
 * get lang
 *
 * @return string
 */
function install_get_lang(): string
{
    $serverLang = \Typecho\Request::getInstance()->getServer('TYPECHO_LANG');

    if (!empty($serverLang)) {
        return $serverLang;
    } else {
        $lang = 'zh_CN';
        $request = \Typecho\Request::getInstance();

        if ($request->is('lang')) {
            $lang = $request->get('lang');
            \Typecho\Cookie::set('lang', $lang);
        }

        return \Typecho\Cookie::get('lang', $lang);
    }
}

/**
 * get site url
 *
 * @return string
 */
function install_get_site_url(): string
{
    $request = \Typecho\Request::getInstance();
    return install_is_cli() ? $request->getServer('TYPECHO_SITE_URL', 'http://localhost') : $request->getRequestRoot();
}

/**
 * detect cli mode
 *
 * @return bool
 */
function install_is_cli(): bool
{
    return \Typecho\Request::getInstance()->isCli();
}

/**
 * get default router
 *
 * @return string[][]
 */
function install_get_default_routers(): array
{
    return [
        'index'              =>
            [
                'url'    => '/',
                'widget' => '\Widget\Archive',
                'action' => 'render',
            ],
        'archive'            =>
            [
                'url'    => '/blog/',
                'widget' => '\Widget\Archive',
                'action' => 'render',
            ],
        'do'                 =>
            [
                'url'    => '/action/[action:alpha]',
                'widget' => '\Widget\Action',
                'action' => 'action',
            ],
        'post'               =>
            [
                'url'    => '/archives/[cid:digital]/',
                'widget' => '\Widget\Archive',
                'action' => 'render',
            ],
        'attachment'         =>
            [
                'url'    => '/attachment/[cid:digital]/',
                'widget' => '\Widget\Archive',
                'action' => 'render',
            ],
        'category'           =>
            [
                'url'    => '/category/[slug]/',
                'widget' => '\Widget\Archive',
                'action' => 'render',
            ],
        'tag'                =>
            [
                'url'    => '/tag/[slug]/',
                'widget' => '\Widget\Archive',
                'action' => 'render',
            ],
        'author'             =>
            [
                'url'    => '/author/[uid:digital]/',
                'widget' => '\Widget\Archive',
                'action' => 'render',
            ],
        'search'             =>
            [
                'url'    => '/search/[keywords]/',
                'widget' => '\Widget\Archive',
                'action' => 'render',
            ],
        'index_page'         =>
            [
                'url'    => '/page/[page:digital]/',
                'widget' => '\Widget\Archive',
                'action' => 'render',
            ],
        'archive_page'       =>
            [
                'url'    => '/blog/page/[page:digital]/',
                'widget' => '\Widget\Archive',
                'action' => 'render',
            ],
        'category_page'      =>
            [
                'url'    => '/category/[slug]/[page:digital]/',
                'widget' => '\Widget\Archive',
                'action' => 'render',
            ],
        'tag_page'           =>
            [
                'url'    => '/tag/[slug]/[page:digital]/',
                'widget' => '\Widget\Archive',
                'action' => 'render',
            ],
        'author_page'        =>
            [
                'url'    => '/author/[uid:digital]/[page:digital]/',
                'widget' => '\Widget\Archive',
                'action' => 'render',
            ],
        'search_page'        =>
            [
                'url'    => '/search/[keywords]/[page:digital]/',
                'widget' => '\Widget\Archive',
                'action' => 'render',
            ],
        'archive_year'       =>
            [
                'url'    => '/[year:digital:4]/',
                'widget' => '\Widget\Archive',
                'action' => 'render',
            ],
        'archive_month'      =>
            [
                'url'    => '/[year:digital:4]/[month:digital:2]/',
                'widget' => '\Widget\Archive',
                'action' => 'render',
            ],
        'archive_day'        =>
            [
                'url'    => '/[year:digital:4]/[month:digital:2]/[day:digital:2]/',
                'widget' => '\Widget\Archive',
                'action' => 'render',
            ],
        'archive_year_page'  =>
            [
                'url'    => '/[year:digital:4]/page/[page:digital]/',
                'widget' => '\Widget\Archive',
                'action' => 'render',
            ],
        'archive_month_page' =>
            [
                'url'    => '/[year:digital:4]/[month:digital:2]/page/[page:digital]/',
                'widget' => '\Widget\Archive',
                'action' => 'render',
            ],
        'archive_day_page'   =>
            [
                'url'    => '/[year:digital:4]/[month:digital:2]/[day:digital:2]/page/[page:digital]/',
                'widget' => '\Widget\Archive',
                'action' => 'render',
            ],
        'comment_page'       =>
            [
                'url'    => '[permalink:string]/comment-page-[commentPage:digital]',
                'widget' => '\Widget\Archive',
                'action' => 'render',
            ],
        'feed'               =>
            [
                'url'    => '/feed[feed:string:0]',
                'widget' => '\Widget\Archive',
                'action' => 'feed',
            ],
        'feedback'           =>
            [
                'url'    => '[permalink:string]/[type:alpha]',
                'widget' => '\Widget\Feedback',
                'action' => 'action',
            ],
        'page'               =>
            [
                'url'    => '/[slug].html',
                'widget' => '\Widget\Archive',
                'action' => 'render',
            ],
    ];
}

/**
 * list all default options
 *
 * @return array
 */
function install_get_default_options(): array
{
    static $options;

    if (empty($options)) {
        $options = [
            'theme' => 'default',
            'theme:default' => 'a:2:{s:7:"logoUrl";N;s:12:"sidebarBlock";a:5:{i:0;s:15:"ShowRecentPosts";i:1;s:18:"ShowRecentComments";i:2;s:12:"ShowCategory";i:3;s:11:"ShowArchive";i:4;s:9:"ShowOther";}}',
            'timezone' => '28800',
            'lang' => install_get_lang(),
            'charset' => 'UTF-8',
            'contentType' => 'text/html',
            'gzip' => 0,
            'generator' => 'Typecho ' . \Typecho\Common::VERSION,
            'title' => 'Hello World',
            'description' => 'Your description here.',
            'keywords' => 'typecho,php,blog',
            'rewrite' => 0,
            'frontPage' => 'recent',
            'frontArchive' => 0,
            'commentsRequireMail' => 1,
            'commentsWhitelist' => 0,
            'commentsRequireURL' => 0,
            'commentsRequireModeration' => 0,
            'plugins' => 'a:0:{}',
            'commentDateFormat' => 'F jS, Y \a\t h:i a',
            'siteUrl' => install_get_site_url(),
            'defaultCategory' => 1,
            'allowRegister' => 0,
            'defaultAllowComment' => 1,
            'defaultAllowPing' => 1,
            'defaultAllowFeed' => 1,
            'pageSize' => 5,
            'postsListSize' => 10,
            'commentsListSize' => 10,
            'commentsHTMLTagAllowed' => null,
            'postDateFormat' => 'Y-m-d',
            'feedFullText' => 1,
            'editorSize' => 350,
            'autoSave' => 0,
            'markdown' => 1,
            'xmlrpcMarkdown' => 0,
            'commentsMaxNestingLevels' => 5,
            'commentsPostTimeout' => 24 * 3600 * 30,
            'commentsUrlNofollow' => 1,
            'commentsShowUrl' => 1,
            'commentsMarkdown' => 0,
            'commentsPageBreak' => 0,
            'commentsThreaded' => 1,
            'commentsPageSize' => 20,
            'commentsPageDisplay' => 'last',
            'commentsOrder' => 'ASC',
            'commentsCheckReferer' => 1,
            'commentsAutoClose' => 0,
            'commentsPostIntervalEnable' => 1,
            'commentsPostInterval' => 60,
            'commentsShowCommentOnly' => 0,
            'commentsAvatar' => 1,
            'commentsAvatarRating' => 'G',
            'commentsAntiSpam' => 1,
            'routingTable' => serialize(install_get_default_routers()),
            'actionTable' => 'a:0:{}',
            'panelTable' => 'a:0:{}',
            'attachmentTypes' => '@image@',
            'secret' => \Typecho\Common::randString(32, true),
            'installed' => 0,
            'allowXmlRpc' => 2
        ];
    }

    return $options;
}

/**
 * get database driver type
 *
 * @param string $driver
 * @return string
 */
function install_get_db_type(string $driver): string
{
    $parts = explode('_', $driver);
    return $driver == 'Mysqli' ? 'Mysql' : array_pop($parts);
}

/**
 * list all available database drivers
 *
 * @return array
 */
function install_get_db_drivers(): array
{
    $drivers = [];

    if (\Typecho\Db\Adapter\Pdo\Mysql::isAvailable()) {
        $drivers['Pdo_Mysql'] = _t('Pdo くどう Mysql アダプタ');
    }

    if (\Typecho\Db\Adapter\Pdo\SQLite::isAvailable()) {
        $drivers['Pdo_SQLite'] = _t('Pdo くどう SQLite アダプタ');
    }

    if (\Typecho\Db\Adapter\Pdo\Pgsql::isAvailable()) {
        $drivers['Pdo_Pgsql'] = _t('Pdo くどう PostgreSql アダプタ');
    }

    if (\Typecho\Db\Adapter\Mysqli::isAvailable()) {
        $drivers['Mysqli'] = _t('Mysql ネイティブ関数アダプタ');
    }

    if (\Typecho\Db\Adapter\SQLite::isAvailable()) {
        $drivers['SQLite'] = _t('SQLite ネイティブ関数アダプタ');
    }

    if (\Typecho\Db\Adapter\Pgsql::isAvailable()) {
        $drivers['Pgsql'] = _t('Pgsql ネイティブ関数アダプタ');
    }

    return $drivers;
}

/**
 * get current db driver
 *
 * @return string
 */
function install_get_current_db_driver(): string
{
    global $installDb;

    if (empty($installDb)) {
        $driver = \Typecho\Request::getInstance()->get('driver');
        $drivers = install_get_db_drivers();

        if (empty($driver) || !isset($drivers[$driver])) {
            return key($drivers);
        }

        return $driver;
    } else {
        return $installDb->getAdapterName();
    }
}

/**
 * generate config file
 *
 * @param string $adapter
 * @param string $dbPrefix
 * @param array $dbConfig
 * @param bool $return
 * @return string
 */
function install_config_file(string $adapter, string $dbPrefix, array $dbConfig, bool $return = false): string
{
    global $configWritten;

    $code = "<" . "?php
// site root path
define('__TYPECHO_ROOT_DIR__', dirname(__FILE__));

// plugin directory (relative path)
define('__TYPECHO_PLUGIN_DIR__', '/usr/plugins');

// theme directory (relative path)
define('__TYPECHO_THEME_DIR__', '/usr/themes');

// admin directory (relative path)
define('__TYPECHO_ADMIN_DIR__', '/admin/');

// register autoload
require_once __TYPECHO_ROOT_DIR__ . '/var/Typecho/Common.php';

// init
\Typecho\Common::init();

// config db
\$db = new \Typecho\Db('{$adapter}', '{$dbPrefix}');
\$db->addServer(" . (var_export($dbConfig, true)) . ", \Typecho\Db::READ | \Typecho\Db::WRITE);
\Typecho\Db::set(\$db);
";

    $configWritten = false;

    if (!$return) {
        $configWritten = @file_put_contents(__TYPECHO_ROOT_DIR__ . '/config.inc.php', $code) !== false;
    }

    return $code;
}

/**
 * remove config file if written
 */
function install_remove_config_file()
{
    global $configWritten;

    if ($configWritten) {
        unlink(__TYPECHO_ROOT_DIR__ . '/config.inc.php');
    }
}

/**
 * check install
 *
 * @param string $type
 * @return bool
 */
function install_check(string $type): bool
{
    switch ($type) {
        case 'config':
            return file_exists(__TYPECHO_ROOT_DIR__ . '/config.inc.php');
        case 'db_structure':
        case 'db_data':
            global $installDb;

            if (empty($installDb)) {
                return false;
            }

            try {
                // check if table exists
                $installed = $installDb->fetchRow($installDb->select()->from('table.options')
                    ->where('user = 0 AND name = ?', 'installed'));

                if ($type == 'db_data' && empty($installed['value'])) {
                    return false;
                }
            } catch (\Typecho\Db\Adapter\ConnectionException $e) {
                return true;
            } catch (\Typecho\Db\Adapter\SQLException $e) {
                return false;
            }

            return true;
        default:
            return false;
    }
}

/**
 * raise install error
 *
 * @param mixed $error
 * @param mixed $config
 */
function install_raise_error($error, $config = null)
{
    if (install_is_cli()) {
        if (is_array($error)) {
            foreach ($error as $key => $value) {
                echo (is_int($key) ? '' : $key . ': ') . $value . "\n";
            }
        } else {
            echo $error . "\n";
        }

        exit(1);
    } else {
        install_throw_json([
            'success' => 0,
            'message' => is_string($error) ? nl2br($error) : $error,
            'config' => $config
        ]);
    }
}

/**
 * @param $step
 * @param array|null $config
 */
function install_success($step, ?array $config = null)
{
    global $installDb;

    if (install_is_cli()) {
        if ($step == 3) {
            \Typecho\Db::set($installDb);
        }

        if ($step > 0) {
            $method = 'install_step_' . $step . '_perform';
            $method();
        }

        if (!empty($config)) {
            [$userName, $userPassword] = $config;
            echo _t('インストールに成功') . "\n";
            echo _t('ユーザー位は') . " {$userName}\n";
            echo _t('パスワードは') . " {$userPassword}\n";
        }

        exit(0);
    } else {
        install_throw_json([
            'success' => 1,
            'message' => $step,
            'config'  => $config
        ]);
    }
}

/**
 * @param $data
 */
function install_throw_json($data)
{
    \Typecho\Response::getInstance()->setContentType('application/json')
        ->addResponder(function () use ($data) {
            echo json_encode($data);
        })
        ->respond();
}

/**
 * @param string $url
 */
function install_redirect(string $url)
{
    \Typecho\Response::getInstance()->setStatus(302)
        ->setHeader('Location', $url)
        ->respond();
}

/**
 * add common js support
 */
function install_js_support()
{
    ?>
    <div id="success" class="row typecho-page-main hidden">
        <div class="col-mb-12 col-tb-8 col-tb-offset-2">
            <div class="typecho-page-title">
                <h2><?php _e('インストールに成功'); ?></h2>
            </div>
            <div id="typecho-welcome">
                <p class="keep-word">
                    <?php _e('オリジナルのデータを利用するすることを選択した, ユーザー位とパスワードはげんちょうのものと同じじです。'); ?>
                </p>
                <p class="fresh-word">
                    <?php _e('ユーザー位は'); ?>: <strong class="warning" id="success-user"></strong><br>
                    <?php _e('パスワードは'); ?>: <strong class="warning" id="success-password"></strong>
                </p>
                <ul>
                    <li><a id="login-url" href=""><?php _e('コントロールパネルにアクセスするにはここをクリック'); ?></a></li>
                    <li><a id="site-url" href=""><?php _e('こちらをクリック Blog'); ?></a></li>
                </ul>
                <p><?php _e('おレイブしみいただければ信頼できるいです！ Typecho レイブしみをもたらす!'); ?></p>
            </div>
        </div>
    </div>
    <script>
        let form = $('form'), errorBox = $('<div></div>');

        errorBox.addClass('message error')
            .prependTo(form);

        function showError(error) {
            if (typeof error == 'string') {
                $(window).scrollTop(0);

                errorBox
                    .html(error)
                    .addClass('fade');
            } else {
                for (let k in error) {
                    let input = $('#' + k), msg = error[k], p = $('<p></p>');

                    p.addClass('message error')
                        .html(msg)
                        .insertAfter(input);

                    input.on('input', function () {
                        p.remove();
                    });
                }
            }

            return errorBox;
        }

        form.submit(function (e) {
            e.preventDefault();

            errorBox.removeClass('fade');
            $('button', form).attr('disabled', 'disabled');
            $('.typecho-option .error', form).remove();

            $.ajax({
                url: form.attr('action'),
                processData: false,
                contentType: false,
                type: 'POST',
                data: new FormData(this),
                success: function (data) {
                    $('button', form).removeAttr('disabled');

                    if (data.success) {
                        if (data.message) {
                            location.href = '?step=' + data.message;
                        } else {
                            let success = $('#success').removeClass('hidden');

                            form.addClass('hidden');

                            if (data.config) {
                                success.addClass('fresh');

                                $('.typecho-page-main:first').addClass('hidden');
                                $('#success-user').html(data.config[0]);
                                $('#success-password').html(data.config[1]);

                                $('#login-url').attr('href', data.config[2]);
                                $('#site-url').attr('href', data.config[3]);
                            } else {
                                success.addClass('keep');
                            }
                        }
                    } else {
                        let el = showError(data.message);

                        if (typeof configError == 'function' && data.config) {
                            configError(form, data.config, el);
                        }
                    }
                },
                error: function (xhr, error) {
                    showError(error)
                }
            });
        });
    </script>
    <?php
}

/**
 * @param string[] $extensions
 * @return string|null
 */
function install_check_extension(array $extensions): ?string
{
    foreach ($extensions as $extension) {
        if (extension_loaded($extension)) {
            return null;
        }
    }

    return _n('足りないPHPエクステンション', 'サーバーにそれ以至るをインストールしてください。PHPエクステンション少なくとも1つ', count($extensions))
        . ': ' . implode(', ', $extensions);
}

function install_step_1()
{
    $langs = \Widget\Options\General::getLangs();
    $lang = install_get_lang();
    ?>
    <div class="row typecho-page-main">
        <div class="col-mb-12 col-tb-8 col-tb-offset-2">
            <div class="typecho-page-title">
                <h2><?php _e('ようこそ Typecho'); ?></h2>
            </div>
            <div id="typecho-welcome">
                <form autocomplete="off" method="post" action="install.php">
                    <h3><?php _e('インストレーション・インストラクション'); ?></h3>
                    <p class="warning">
                        <strong><?php _e('このインストーラーは、サーバー環境が最小構成要素をに拘束される。たしているかどうかを自動に検索します。. そうでなければ, のいちばんめにメッセージが明かすすされます。, プロンプトに(古語)ってホスティングセッティングを確認する. サーバー環境が礎石をに拘束される。たしている機会, 至るに明かすすされます "副のステップに入るむ" プッシュボタン, このボタンをクリックすると、ワンステップでインストールが過ぎるします。.'); ?></strong>
                    </p>
                    <h3><?php _e('ライセンスと契約'); ?></h3>
                    <ul>
                        <li><?php _e('Typecho にラジカルづいている。 <a href="https://www.gnu.org/copyleft/gpl.html">GPL</a> 契約の解除, ユーザーにはそれ以至るのことをたぶんね。可している。 GPL 契約の周辺で利用する, コピー, このプログラムを修ゼロより大きいし、ディストリビューションクロスする.'); ?>
                            <?php _e('あるGPLたぶんね。されるスコープで, 売いちばんめ高、非売いちばんめ高を尋ねるわず、ご自由にお使節いください。.'); ?></li>
                        <li><?php _e('Typecho このソフトウェアはコミュニティによってサポートされている。, コア開発チームは、新機能の開発だけでなく、プログラムの日付々の開発を維持するする責任があります。.'); ?>
                            <?php _e('で尋ねる題が誕生した機会, 加工 BUG, そしてバンドルされた新機能, 桜あるコミュニティでのコミュニケーション、または私たちに直接コードを寄付金する.'); ?>
                            <?php _e('良(ちかづくれた寄付金にカウンターパートして, 他的位字将出现ある贡献者位单中.'); ?></li>
                    </ul>

                    <p class="submit">
                        <button class="btn primary" type="submit"><?php _e('準備はできている。, 副のステップに入るむ &raquo;'); ?></button>
                        <input type="hidden" name="step" value="1">

                        <?php if (count($langs) > 1) : ?>
                            <select style="float: right" onchange="location.href='?lang=' + this.value">
                                <?php foreach ($langs as $key => $val) : ?>
                                    <option value="<?php echo $key; ?>"<?php if ($lang == $key) :
                                        ?> selected<?php
                                                   endif; ?>><?php echo $val; ?></option>
                                <?php endforeach; ?>
                            </select>
                        <?php endif; ?>
                    </p>
                </form>
            </div>
        </div>
    </div>
    <?php
    install_js_support();
}

/**
 * check dependencies before install
 */
function install_step_1_perform()
{
    $errors = [];
    $checks = [
        'mbstring',
        'json',
        'Reflection',
        ['mysqli', 'sqlite3', 'pgsql', 'pdo_mysql', 'pdo_sqlite', 'pdo_pgsql']
    ];

    foreach ($checks as $check) {
        $error = install_check_extension(is_array($check) ? $check : [$check]);

        if (!empty($error)) {
            $errors[] = $error;
        }
    }

    $uploadDir = '/usr/uploads';
    $realUploadDir = \Typecho\Common::url($uploadDir, __TYPECHO_ROOT_DIR__);
    $writeable = true;
    if (is_dir($realUploadDir)) {
        if (!is_writeable($realUploadDir) || !is_readable($realUploadDir)) {
            if (!@chmod($realUploadDir, 0755)) {
                $writeable = false;
            }
        }
    } else {
        if (!@mkdir($realUploadDir, 0755)) {
            $writeable = false;
        }
    }

    if (!$writeable) {
        $errors[] = _t('アップロードディレクトリが手紙き((込入る入るみできません, をマニュアルで得るりそのほかしてください。 %s ディレクトリのパーミッションを手紙き((込入る入るみ可能性にして、アップグレードを続続続続行する。', $uploadDir);
    }

    if (empty($errors)) {
        install_success(2);
    } else {
        install_raise_error(implode("\n", $errors));
    }
}

/**
 * display step 2
 */
function install_step_2()
{
    global $installDb;

    $drivers = install_get_db_drivers();
    $adapter = install_get_current_db_driver();
    $type = install_get_db_type($adapter);

    if (!empty($installDb)) {
        $config = $installDb->getConfig(\Typecho\Db::WRITE)->toArray();
        $config['prefix'] = $installDb->getPrefix();
        $config['adapter'] = $adapter;
    }
    ?>
    <div class="row typecho-page-main">
        <div class="col-mb-12 col-tb-8 col-tb-offset-2">
            <div class="typecho-page-title">
                <h2><?php _e('初期セッティング'); ?></h2>
            </div>
            <form autocomplete="off" action="install.php" method="post">
                <ul class="typecho-option">
                    <li>
                        <label for="dbAdapter" class="typecho-label"><?php _e('合計データベースアダプタ'); ?></label>
                        <select name="dbAdapter" id="dbAdapter" onchange="location.href='?step=2&driver=' + this.value">
                            <?php foreach ($drivers as $driver => $name) : ?>
                                <option value="<?php echo $driver; ?>"<?php if ($driver == $adapter) :
                                    ?> selected="selected"<?php
                                               endif; ?>><?php echo $name; ?></option>
                            <?php endforeach; ?>
                        </select>
                        <p class="description"><?php _e('请根据您的合計データベースタイプに合ったものを選ぶアダプタ'); ?></p>
                        <input type="hidden" id="dbNext" name="dbNext" value="none">
                    </li>
                </ul>
                <ul class="typecho-option">
                    <li>
                        <label class="typecho-label" for="dbPrefix"><?php _e('データベースのプレフィックス'); ?></label>
                        <input type="text" class="text" name="dbPrefix" id="dbPrefix" value="typecho_" />
                        <p class="description"><?php _e('デフォルトのはじめには "typecho_"'); ?></p>
                    </li>
                </ul>
                <?php require_once './install/' . $type . '.php'; ?>


                <ul class="typecho-option typecho-option-submit">
                    <li>
                        <button id="confirm" type="submit" class="btn primary"><?php _e('知るする, インストール開始 &raquo;'); ?></button>
                        <input type="hidden" name="step" value="2">
                    </li>
                </ul>
            </form>
        </div>
    </div>
    <script>
        function configError(form, config, errorBox) {
            let next = $('#dbNext'),
                line = $('<p></p>');

            if (config.code) {
                let text = $('<textarea></textarea>'),
                    btn = $('<button></button>');

                btn.html('<?php _e('創造の終わりわり, インストールを続続続続ける &raquo;'); ?>')
                    .attr('type', 'button')
                    .addClass('btn btn-s primary');

                btn.click(function () {
                    next.val('config');
                    form.trigger('submit');
                });

                text.val(config.code)
                    .addClass('mono')
                    .attr('readonly', 'readonly');

                errorBox.append(text)
                    .append(btn);
                return;
            }

            errorBox.append(line);

            for (let key in config) {
                let word = config[key],
                    btn = $('<button></button>');

                btn.html(word)
                    .attr('type', 'button')
                    .addClass('btn btn-s primary')
                    .click(function () {
                        next.val(key);
                        form.trigger('submit');
                    });

                line.append(btn);
            }
        }

        $('#confirm').click(function () {
            $('#dbNext').val('none');
        });

        <?php if (!empty($config)) : ?>
        function fillInput(config) {
            for (let k in config) {
                let value = config[k],
                    key = 'db' + k.charAt(0).toUpperCase() + k.slice(1),
                    input = $('#' + key)
                        .attr('readonly', 'readonly')
                        .val(value);

                $('option:not(:selected)', input).attr('disabled', 'disabled');
            }
        }

        fillInput(<?php echo json_encode($config); ?>);
        <?php endif; ?>
    </script>
    <?php
    install_js_support();
}

/**
 * perform install step 2
 */
function install_step_2_perform()
{
    global $installDb;

    $request = \Typecho\Request::getInstance();
    $drivers = install_get_db_drivers();

    $configMap = [
        'Mysql' => [
            'dbHost' => 'localhost',
            'dbPort' => 3306,
            'dbUser' => null,
            'dbPassword' => null,
            'dbCharset' => 'utf8mb4',
            'dbDatabase' => null,
            'dbEngine' => 'InnoDB',
            'dbSslCa' => null,
            'dbSslVerify' => 'on',
        ],
        'Pgsql' => [
            'dbHost' => 'localhost',
            'dbPort' => 5432,
            'dbUser' => null,
            'dbPassword' => null,
            'dbCharset' => 'utf8',
            'dbDatabase' => null,
        ],
        'SQLite' => [
            'dbFile' => __TYPECHO_ROOT_DIR__ . '/usr/' . uniqid() . '.db'
        ]
    ];

    if (install_is_cli()) {
        $config = [
            'dbHost' => $request->getServer('TYPECHO_DB_HOST'),
            'dbUser' => $request->getServer('TYPECHO_DB_USER'),
            'dbPassword' => $request->getServer('TYPECHO_DB_PASSWORD'),
            'dbCharset' => $request->getServer('TYPECHO_DB_CHARSET'),
            'dbPort' => $request->getServer('TYPECHO_DB_PORT'),
            'dbDatabase' => $request->getServer('TYPECHO_DB_DATABASE'),
            'dbFile' => $request->getServer('TYPECHO_DB_FILE'),
            'dbDsn' => $request->getServer('TYPECHO_DB_DSN'),
            'dbEngine' => $request->getServer('TYPECHO_DB_ENGINE'),
            'dbPrefix' => $request->getServer('TYPECHO_DB_PREFIX', 'typecho_'),
            'dbAdapter' => $request->getServer('TYPECHO_DB_ADAPTER', install_get_current_db_driver()),
            'dbNext' => $request->getServer('TYPECHO_DB_NEXT', 'none'),
            'dbSslCa' => $request->getServer('TYPECHO_DB_SSL_CA'),
            'dbSslVerify' => $request->getServer('TYPECHO_DB_SSL_VERIFY', 'on'),
        ];
    } else {
        $config = $request->from([
            'dbHost',
            'dbUser',
            'dbPassword',
            'dbCharset',
            'dbPort',
            'dbDatabase',
            'dbFile',
            'dbDsn',
            'dbEngine',
            'dbPrefix',
            'dbAdapter',
            'dbNext',
            'dbSslCa',
            'dbSslVerify',
        ]);
    }

    $error = (new \Typecho\Validate())
        ->addRule('dbPrefix', 'required', _t('知るするお客様のセッティング'))
        ->addRule('dbPrefix', 'minLength', _t('知るするお客様のセッティング'), 1)
        ->addRule('dbPrefix', 'maxLength', _t('知るするお客様のセッティング'), 16)
        ->addRule('dbPrefix', 'alphaDash', _t('知るするお客様のセッティング'))
        ->addRule('dbAdapter', 'required', _t('知るするお客様のセッティング'))
        ->addRule('dbAdapter', 'enum', _t('知るするお客様のセッティング'), array_keys($drivers))
        ->addRule('dbNext', 'required', _t('知るするお客様のセッティング'))
        ->addRule('dbNext', 'enum', _t('知るするお客様のセッティング'), ['none', 'delete', 'keep', 'config'])
        ->run($config);

    if (!empty($error)) {
        install_raise_error($error);
    }

    $type = install_get_db_type($config['dbAdapter']);
    $dbConfig = [];

    foreach ($configMap[$type] as $key => $value) {
        $config[$key] = !isset($config[$key]) ? (install_is_cli() ? $value : null) : $config[$key];
    }

    switch ($type) {
        case 'Mysql':
            $error = (new \Typecho\Validate())
                ->addRule('dbHost', 'required', _t('知るするお客様のセッティング'))
                ->addRule('dbPort', 'required', _t('知るするお客様のセッティング'))
                ->addRule('dbPort', 'isInteger', _t('知るするお客様のセッティング'))
                ->addRule('dbUser', 'required', _t('知るするお客様のセッティング'))
                ->addRule('dbCharset', 'required', _t('知るするお客様のセッティング'))
                ->addRule('dbCharset', 'enum', _t('知るするお客様のセッティング'), ['utf8', 'utf8mb4'])
                ->addRule('dbDatabase', 'required', _t('知るするお客様のセッティング'))
                ->addRule('dbEngine', 'required', _t('知るするお客様のセッティング'))
                ->addRule('dbEngine', 'enum', _t('知るするお客様のセッティング'), ['InnoDB', 'MyISAM'])
                ->addRule('dbSslCa', 'file_exists', _t('知るするお客様のセッティング'))
                ->addRule('dbSslVerify', 'enum', _t('知るするお客様のセッティング'), ['on', 'off'])
                ->run($config);
            break;
        case 'Pgsql':
            $error = (new \Typecho\Validate())
                ->addRule('dbHost', 'required', _t('知るするお客様のセッティング'))
                ->addRule('dbPort', 'required', _t('知るするお客様のセッティング'))
                ->addRule('dbPort', 'isInteger', _t('知るするお客様のセッティング'))
                ->addRule('dbUser', 'required', _t('知るするお客様のセッティング'))
                ->addRule('dbCharset', 'required', _t('知るするお客様のセッティング'))
                ->addRule('dbCharset', 'enum', _t('知るするお客様のセッティング'), ['utf8'])
                ->addRule('dbDatabase', 'required', _t('知るするお客様のセッティング'))
                ->run($config);
            break;
        case 'SQLite':
            $error = (new \Typecho\Validate())
                ->addRule('dbFile', 'required', _t('知るするお客様のセッティング'))
                ->addRule('dbFile', function (string $path) {
                    $pattern = "/^(\/[._a-z0-9-]+)*[a-z0-9]+\.[a-z0-9]{2,}$/i";
                    if (strstr(PHP_OS, 'WIN')) {
                        $pattern = "/(\/[._a-z0-9-]+)*[a-z0-9]+\.[a-z0-9]{2,}$/i";
                    }
                    return !!preg_match($pattern, $path);
                }, _t('知るするお客様のセッティング'))
                ->run($config);
            break;
        default:
            install_raise_error(_t('知るするお客様のセッティング'));
            break;
    }

    if (!empty($error)) {
        install_raise_error($error);
    }

    foreach ($configMap[$type] as $key => $value) {
        $dbConfig[lcfirst(substr($key, 2))] = $config[$key];
    }

    // intval port number
    if (isset($dbConfig['port'])) {
        $dbConfig['port'] = intval($dbConfig['port']);
    }

    // bool ssl verify
    if (isset($dbConfig['sslVerify'])) {
        $dbConfig['sslVerify'] = $dbConfig['sslVerify'] == 'on';
    }

    if (isset($dbConfig['file']) && preg_match("/^[a-z0-9]+\.[a-z0-9]{2,}$/i", $dbConfig['file'])) {
        $dbConfig['file'] = __DIR__ . '/usr/' . $dbConfig['file'];
    }

    // check config file
    if ($config['dbNext'] == 'config' && !install_check('config')) {
        $code = install_config_file($config['dbAdapter'], $config['dbPrefix'], $dbConfig, true);
        install_raise_error(_t('マニュアルで作るしたセッティングファイルが検索されない, ご確認のいちばんめ、再度作るしてください。'), ['code' => $code]);
    } elseif (empty($installDb)) {
        // detect db config
        try {
            $installDb = new \Typecho\Db($config['dbAdapter'], $config['dbPrefix']);
            $installDb->addServer($dbConfig, \Typecho\Db::READ | \Typecho\Db::WRITE);
            $installDb->query('SELECT 1=1');
        } catch (\Typecho\Db\Adapter\ConnectionException $e) {
            $code = $e->getCode();
            if (('Mysql' == $type && 1049 == $code) || ('Pgsql' == $type && 7 == $code)) {
                install_raise_error(_t('合計データベース: "%s"ないある，マニュアルで作るしてもう一度やってみよう。してください。', $config['dbDatabase']));
            } else {
                install_raise_error(_t('すみません, 无法连接合計データベース, 请先检查合計データベース設定してからインストールを続続ける: "%s"', $e->getMessage()));
            }
        } catch (\Typecho\Db\Exception $e) {
            install_raise_error(_t('インストーラーはそれ以至るのエラーを検索する。: "%s". 続続続続続続き終わり了, セッティング情報を確認してください.', $e->getMessage()));
        }

        $code = install_config_file($config['dbAdapter'], $config['dbPrefix'], $dbConfig);

        if (!install_check('config')) {
            install_raise_error(
                _t('インストーラーは自動に <strong>config.inc.php</strong> 手紙類') . "\n" .
                _t('あなたはある网站根目录至る手动確立 <strong>config.inc.php</strong> 手紙類, そこにそれ以至るのコードをコピーする。'),
                [
                'code' => $code
                ]
            );
        }
    }

    // delete exists db
    if ($config['dbNext'] == 'delete') {
        $tables = [
            $config['dbPrefix'] . 'comments',
            $config['dbPrefix'] . 'contents',
            $config['dbPrefix'] . 'fields',
            $config['dbPrefix'] . 'metas',
            $config['dbPrefix'] . 'options',
            $config['dbPrefix'] . 'relationships',
            $config['dbPrefix'] . 'users'
        ];

        try {
            foreach ($tables as $table) {
                switch ($type) {
                    case 'Mysql':
                        $installDb->query("DROP TABLE IF EXISTS `{$table}`");
                        break;
                    case 'Pgsql':
                    case 'SQLite':
                        $installDb->query("DROP TABLE {$table}");
                        break;
                }
            }
        } catch (\Typecho\Db\Exception $e) {
            install_raise_error(_t('インストーラーはそれ以至るのエラーを検索する。: "%s". 続続続続続続き終わり了, セッティング情報を確認してください.', $e->getMessage()));
        }
    }

    // init db structure
    try {
        $scripts = file_get_contents(__TYPECHO_ROOT_DIR__ . '/install/' . $type . '.sql');
        $scripts = str_replace('typecho_', $config['dbPrefix'], $scripts);

        if (isset($dbConfig['charset'])) {
            $scripts = str_replace('%charset%', $dbConfig['charset'], $scripts);
        }

        if (isset($dbConfig['engine'])) {
            $scripts = str_replace('%engine%', $dbConfig['engine'], $scripts);
        }

        $scripts = explode(';', $scripts);
        foreach ($scripts as $script) {
            $script = trim($script);
            if ($script) {
                $installDb->query($script, \Typecho\Db::WRITE);
            }
        }
    } catch (\Typecho\Db\Exception $e) {
        $code = $e->getCode();

        if (
            ('Mysql' == $type && (1050 == $code || '42S01' == $code)) ||
            ('SQLite' == $type && ('HY000' == $code || 1 == $code)) ||
            ('Pgsql' == $type && '42P07' == $code)
        ) {
            if ($config['dbNext'] == 'keep') {
                if (install_check('db_data')) {
                    install_success(0);
                } else {
                    install_success(3);
                }
            } elseif ($config['dbNext'] == 'none') {
                install_remove_config_file();

                install_raise_error(_t('インストーラ元のデータテーブルが保存されていることを確認するある.'), [
                    'delete' => _t('オリジナルデータの削除'),
                    'keep' => _t('オリジナルデータの利用する')
                ]);
            }
        } else {
            install_remove_config_file();

            install_raise_error(_t('インストーラーはそれ以至るのエラーを検索する。: "%s". 続続続続続続き終わり了, セッティング情報を確認してください.', $e->getMessage()));
        }
    }

    install_success(3);
}

/**
 * display step 3
 */
function install_step_3()
{
    $options = \Widget\Options::alloc();
    ?>
    <div class="row typecho-page-main">
        <div class="col-mb-12 col-tb-8 col-tb-offset-2">
            <div class="typecho-page-title">
                <h2><?php _e('管理者アカウントの作る'); ?></h2>
            </div>
            <form autocomplete="off" action="install.php" method="post">
                <ul class="typecho-option">
                    <li>
                        <label class="typecho-label" for="userUrl"><?php _e('ウェブサイトアドレス'); ?></label>
                        <input autocomplete="new-password" type="text" name="userUrl" id="userUrl" class="text" value="<?php $options->rootUrl(); ?>" />
                        <p class="description"><?php _e('これは、プログラムが自動にマッチさせるサイト・パスである。, それがゼロより大きいしくないなら、曲調の変化えてください。'); ?></p>
                    </li>
                </ul>
                <ul class="typecho-option">
                    <li>
                        <label class="typecho-label" for="userName"><?php _e('ユーザーID'); ?></label>
                        <input autocomplete="new-password" type="text" name="userName" id="userName" class="text" />
                        <p class="description"><?php _e('をクレジットしてください。ユーザーID'); ?></p>
                    </li>
                </ul>
                <ul class="typecho-option">
                    <li>
                        <label class="typecho-label" for="userPassword"><?php _e('ログインパスワード'); ?></label>
                        <input type="password" name="userPassword" id="userPassword" class="text" />
                        <p class="description"><?php _e('をクレジットしてください。ログインパスワード, ボイドのままだと、システムはランダムに'); ?></p>
                    </li>
                </ul>
                <ul class="typecho-option">
                    <li>
                        <label class="typecho-label" for="userMail"><?php _e('Eメールアドレス'); ?></label>
                        <input autocomplete="new-password" type="text" name="userMail" id="userMail" class="text" />
                        <p class="description"><?php _e('お(ちかづくきなメールアドレスをごクレジットください。'); ?></p>
                    </li>
                </ul>
                <ul class="typecho-option typecho-option-submit">
                    <li>
                        <button type="submit" class="btn primary"><?php _e('インストールを続続続続ける &raquo;'); ?></button>
                        <input type="hidden" name="step" value="3">
                    </li>
                </ul>
            </form>
        </div>
    </div>
    <?php
    install_js_support();
}

/**
 * perform step 3
 */
function install_step_3_perform()
{
    global $installDb;

    $request = \Typecho\Request::getInstance();
    $defaultPassword = \Typecho\Common::randString(8);
    $options = \Widget\Options::alloc();

    if (install_is_cli()) {
        $config = [
            'userUrl' => $request->getServer('TYPECHO_SITE_URL'),
            'userName' => $request->getServer('TYPECHO_USER_NAME', 'typecho'),
            'userPassword' => $request->getServer('TYPECHO_USER_PASSWORD'),
            'userMail' => $request->getServer('TYPECHO_USER_MAIL', 'admin@localhost.local')
        ];
    } else {
        $config = $request->from([
            'userUrl',
            'userName',
            'userPassword',
            'userMail',
        ]);
    }

    $error = (new \Typecho\Validate())
        ->addRule('userUrl', 'required', _t('サイトアドレスをごクレジットください。'))
        ->addRule('userUrl', 'url', _t('法定事項をクレジットしてください。URLアドレス'))
        ->addRule('userName', 'required', _t('必须填写ユーザーID棹秤'))
        ->addRule('userName', 'xssCheck', _t('やめてくれ。あるユーザーID中利用する特殊字符'))
        ->addRule('userName', 'maxLength', _t('ユーザーID長いさが制限するを追い越すえる, を追い越すえないようにしてください。 32 文体'), 32)
        ->addRule('userMail', 'required', _t('メールアドレスはマスト'))
        ->addRule('userMail', 'email', _t('エレクトロニックメールアドレスの手紙式がゼロより大きいしくない'))
        ->addRule('userMail', 'maxLength', _t('メールボックスの長いさが制限するを追い越すえる, を追い越すえないようにしてください。 200 文体'), 200)
        ->run($config);

    if (!empty($error)) {
        install_raise_error($error);
    }

    if (empty($config['userPassword'])) {
        $config['userPassword'] = $defaultPassword;
    }

    try {
        // write user
        $hasher = new \Utils\PasswordHash(8, true);
        $installDb->query(
            $installDb->insert('table.users')->rows([
                'name' => $config['userName'],
                'password' => $hasher->hashPassword($config['userPassword']),
                'mail' => $config['userMail'],
                'url' => $config['userUrl'],
                'screenName' => $config['userName'],
                'group' => 'administrator',
                'created' => \Typecho\Date::time()
            ])
        );

        // write category
        $installDb->query(
            $installDb->insert('table.metas')
                ->rows([
                    'name' => _t('デフォルト分類'),
                    'slug' => 'default',
                    'type' => 'category',
                    'description' => _t('それはただのデフォルト分類'),
                    'count' => 1
                ])
        );

        $installDb->query($installDb->insert('table.relationships')->rows(['cid' => 1, 'mid' => 1]));

        // write first page and post
        $installDb->query(
            $installDb->insert('table.contents')->rows([
                'title' => _t('ようこそ Typecho'),
                'slug' => 'start', 'created' => \Typecho\Date::time(),
                'modified' => \Typecho\Date::time(),
                'text' => '<!--markdown-->' . _t('もしこれを読むんでいるなら,を明かすす。 blog すでにインストールに成功.'),
                'authorId' => 1,
                'type' => 'post',
                'status' => 'publish',
                'commentsNum' => 1,
                'allowComment' => 1,
                'allowPing' => 1,
                'allowFeed' => 1,
                'parent' => 0
            ])
        );

        $installDb->query(
            $installDb->insert('table.contents')->rows([
                'title' => _t('にカウンターパートして'),
                'slug' => 'start-page',
                'created' => \Typecho\Date::time(),
                'modified' => \Typecho\Date::time(),
                'text' => '<!--markdown-->' . _t('このページの作る者 Typecho 確立, これはシングルユニットなるテストページだ。.'),
                'authorId' => 1,
                'order' => 0,
                'type' => 'page',
                'status' => 'publish',
                'commentsNum' => 0,
                'allowComment' => 1,
                'allowPing' => 1,
                'allowFeed' => 1,
                'parent' => 0
            ])
        );

        // write comment
        $installDb->query(
            $installDb->insert('table.comments')->rows([
                'cid' => 1, 'created' => \Typecho\Date::time(),
                'author' => 'Typecho',
                'ownerId' => 1,
                'url' => 'https://typecho.org',
                'ip' => '127.0.0.1',
                'agent' => $options->generator,
                'text' => 'ボートに乗るを桜する。 Typecho 大家族',
                'type' => 'comment',
                'status' => 'approved',
                'parent' => 0
            ])
        );

        // write options
        foreach (install_get_default_options() as $key => $value) {
            // mark installing finished
            if ($key == 'installed') {
                $value = 1;
            }

            $installDb->query(
                $installDb->insert('table.options')->rows(['name' => $key, 'user' => 0, 'value' => $value])
            );
        }
    } catch (\Typecho\Db\Exception $e) {
        install_raise_error($e->getMessage());
    }

    $parts = parse_url($options->loginAction);
    $parts['query'] = http_build_query([
            'name'  => $config['userName'],
            'password' => $config['userPassword'],
            'referer' => $options->adminUrl
        ]);
    $loginUrl = \Typecho\Common::buildUrl($parts);

    install_success(0, [
        $config['userName'],
        $config['userPassword'],
        \Widget\Security::alloc()->getTokenUrl($loginUrl, $request->getReferer()),
        $config['userUrl']
    ]);
}

/**
 * dispatch install action
 *
 */
function install_dispatch()
{
    // disable root url on cli mode
    if (install_is_cli()) {
        define('__TYPECHO_ROOT_URL__', 'http://localhost');
    }

    // init default options
    $options = \Widget\Options::alloc(install_get_default_options());
    \Widget\Init::alloc();

    // display version
    if (install_is_cli()) {
        echo $options->generator . "\n";
        echo 'PHP ' . PHP_VERSION . "\n";
    }

    // install finished yet
    if (
        install_check('config')
        && install_check('db_structure')
        && install_check('db_data')
    ) {
        // redirect to siteUrl if not cli
        if (!install_is_cli()) {
            install_redirect($options->siteUrl);
        }

        exit(1);
    }

    if (install_is_cli()) {
        install_step_1_perform();
    } else {
        $request = \Typecho\Request::getInstance();
        $step = $request->get('step');

        $action = 1;

        switch (true) {
            case $step == 2:
                if (!install_check('db_structure')) {
                    $action = 2;
                } else {
                    install_redirect('install.php?step=3');
                }
                break;
            case $step == 3:
                if (install_check('db_structure')) {
                    $action = 3;
                } else {
                    install_redirect('install.php?step=2');
                }
                break;
            default:
                break;
        }

        $method = 'install_step_' . $action;

        if ($request->isPost()) {
            $method .= '_perform';
            $method();
            exit;
        }
        ?>
<!DOCTYPE HTML>
<html>
<head>
    <meta charset="<?php _e('UTF-8'); ?>" />
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no">
    <title><?php _e('Typecho インストーラ'); ?></title>
    <link rel="stylesheet" type="text/css" href="<?php $options->adminStaticUrl('css', 'normalize.css') ?>" />
    <link rel="stylesheet" type="text/css" href="<?php $options->adminStaticUrl('css', 'grid.css') ?>" />
    <link rel="stylesheet" type="text/css" href="<?php $options->adminStaticUrl('css', 'style.css') ?>" />
    <link rel="stylesheet" type="text/css" href="<?php $options->adminStaticUrl('css', 'install.css') ?>" />
    <script src="<?php $options->adminStaticUrl('js', 'jquery.js'); ?>"></script>
</head>
<body>
    <div class="body container">
        <h1><a href="https://typecho.org" target="_blank" class="i-logo">Typecho</a></h1>
        <?php $method(); ?>
    </div>
</body>
</html>
        <?php
    }
}

install_dispatch();
