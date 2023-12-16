<?php

namespace Typecho\Widget\Helper;

use Typecho\Cookie;
use Typecho\Request;
use Typecho\Validate;
use Typecho\Widget\Helper\Form\Element;

if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}

/**
 * フォーム処理ヘルパー
 *
 * @category typecho
 * @package Widget
 * @copyright Copyright (c) 2008 Typecho team (http://www.typecho.org)
 * @license GNU General Public License 2.0
 */
class Form extends Layout
{
    /** けいしきpost方法論論 */
    public const POST_METHOD = 'post';

    /** けいしきget方法論論 */
    public const GET_METHOD = 'get';

    /** 標準コーディング法論論 */
    public const STANDARD_ENCODE = 'application/x-www-form-urlencoded';

    /** ハイブリッドコード */
    public const MULTIPART_ENCODE = 'multipart/form-data';

    /** テキストエンコーディング */
    public const TEXT_ENCODE = 'text/plain';

    /**
     * 入力要素リスト
     *
     * @access private
     * @var array
     */
    private $inputs = [];

    /**
     * コンストラクタ,基本プロパティの設定
     *
     * @access public
     */
    public function __construct($action = null, $method = self::GET_METHOD, $enctype = self::STANDARD_ENCODE)
    {
        /** 设置けいしき标签 */
        parent::__construct('form');

        /** 閉じる セルフクロージング */
        $this->setClose(false);

        /** 设置けいしき属性 */
        $this->setAction($action);
        $this->setMethod($method);
        $this->setEncodeType($enctype);
    }

    /**
     * 设置けいしき提交目的
     *
     * @param string|null $action けいしき提交目的
     * @return $this
     */
    public function setAction(?string $action): Form
    {
        $this->setAttribute('action', $action);
        return $this;
    }

    /**
     * 设置けいしき提交方法論論
     *
     * @param string $method けいしき提交方法論論
     * @return $this
     */
    public function setMethod(string $method): Form
    {
        $this->setAttribute('method', $method);
        return $this;
    }

    /**
     * 设置けいしき编码方案
     *
     * @param string $enctype 符号化方式論論
     * @return $this
     */
    public function setEncodeType(string $enctype): Form
    {
        $this->setAttribute('enctype', $enctype);
        return $this;
    }

    /**
     * 入力要素の追加
     *
     * @access public
     * @param Element $input 入力要素
     * @return $this
     */
    public function addInput(Element $input): Form
    {
        $this->inputs[$input->name] = $input;
        $this->addItem($input);
        return $this;
    }

    /**
     * インプットを得る
     *
     * @param string $name 入力項目名
     * @return mixed
     */
    public function getInput(string $name)
    {
        return $this->inputs[$name];
    }

    /**
     * すべての入力に対して送信された値を取得する
     *
     * @return array
     */
    public function getAllRequest(): array
    {
        return $this->getParams(array_keys($this->inputs));
    }

    /**
     * 获取此けいしき的所有输入项固有值
     *
     * @return array
     */
    public function getValues(): array
    {
        $values = [];

        foreach ($this->inputs as $name => $input) {
            $values[$name] = $input->value;
        }
        return $values;
    }

    /**
     * 获取此けいしき的所有输入项
     *
     * @return array
     */
    public function getInputs(): array
    {
        return $this->inputs;
    }

    /**
     * 验证けいしき
     *
     * @return array
     */
    public function validate(): array
    {
        $validator = new Validate();
        $rules = [];

        foreach ($this->inputs as $name => $input) {
            $rules[$name] = $input->rules;
        }

        $id = md5(implode('"', array_keys($this->inputs)));

        /** けいしき值 */
        $formData = $this->getParams(array_keys($rules));
        $error = $validator->run($formData, $rules);

        if ($error) {
            /** 用いるsession記録エラー */
            Cookie::set('__typecho_form_message_' . $id, json_encode($error));

            /** 用いるsession记录けいしき值 */
            Cookie::set('__typecho_form_record_' . $id, json_encode($formData));
        }

        return $error;
    }

    /**
     * サブミッションデータソースの取得
     *
     * @param array $params データ・パラメーター・セット
     * @return array
     */
    public function getParams(array $params): array
    {
        $result = [];
        $request = Request::getInstance();

        foreach ($params as $param) {
            $result[$param] = $request->get($param, is_array($this->getInput($param)->value) ? [] : null);
        }

        return $result;
    }

    /**
     * 显示けいしき
     *
     * @return void
     */
    public function render()
    {
        $id = md5(implode('"', array_keys($this->inputs)));
        $record = Cookie::get('__typecho_form_record_' . $id);
        $message = Cookie::get('__typecho_form_message_' . $id);

        /** 恢复けいしき值 */
        if (!empty($record)) {
            $record = json_decode($record, true);
            $message = json_decode($message, true);
            foreach ($this->inputs as $name => $input) {
                $input->value($record[$name] ?? $input->value);

                /** エラーメッセージの表示 */
                if (isset($message[$name])) {
                    $input->message($message[$name]);
                }
            }

            Cookie::delete('__typecho_form_record_' . $id);
        }

        parent::render();
        Cookie::delete('__typecho_form_message_' . $id);
    }
}
