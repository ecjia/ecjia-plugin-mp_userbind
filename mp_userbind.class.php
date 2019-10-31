<?php
//
//    ______         ______           __         __         ______
//   /\  ___\       /\  ___\         /\_\       /\_\       /\  __ \
//   \/\  __\       \/\ \____        \/\_\      \/\_\      \/\ \_\ \
//    \/\_____\      \/\_____\     /\_\/\_\      \/\_\      \/\_\ \_\
//     \/_____/       \/_____/     \/__\/_/       \/_/       \/_/ /_/
//
//   上海商创网络科技有限公司
//
//  ---------------------------------------------------------------------------------
//
//   一、协议的许可和权利
//
//    1. 您可以在完全遵守本协议的基础上，将本软件应用于商业用途；
//    2. 您可以在协议规定的约束和限制范围内修改本产品源代码或界面风格以适应您的要求；
//    3. 您拥有使用本产品中的全部内容资料、商品信息及其他信息的所有权，并独立承担与其内容相关的
//       法律义务；
//    4. 获得商业授权之后，您可以将本软件应用于商业用途，自授权时刻起，在技术支持期限内拥有通过
//       指定的方式获得指定范围内的技术支持服务；
//
//   二、协议的约束和限制
//
//    1. 未获商业授权之前，禁止将本软件用于商业用途（包括但不限于企业法人经营的产品、经营性产品
//       以及以盈利为目的或实现盈利产品）；
//    2. 未获商业授权之前，禁止在本产品的整体或在任何部分基础上发展任何派生版本、修改版本或第三
//       方版本用于重新开发；
//    3. 如果您未能遵守本协议的条款，您的授权将被终止，所被许可的权利将被收回并承担相应法律责任；
//
//   三、有限担保和免责声明
//
//    1. 本软件及所附带的文件是作为不提供任何明确的或隐含的赔偿或担保的形式提供的；
//    2. 用户出于自愿而使用本软件，您必须了解使用本软件的风险，在尚未获得商业授权之前，我们不承
//       诺提供任何形式的技术支持、使用担保，也不承担任何因使用本软件而产生问题的相关责任；
//    3. 上海商创网络科技有限公司不对使用本产品构建的商城中的内容信息承担责任，但在不侵犯用户隐
//       私信息的前提下，保留以任何方式获取用户信息及商品信息的权利；
//
//   有关本产品最终用户授权协议、商业授权与技术服务的详细内容，均由上海商创网络科技有限公司独家
//   提供。上海商创网络科技有限公司拥有在不事先通知的情况下，修改授权协议的权力，修改后的协议对
//   改变之日起的新授权用户生效。电子文本形式的授权协议如同双方书面签署的协议一样，具有完全的和
//   等同的法律效力。您一旦开始修改、安装或使用本产品，即被视为完全理解并接受本协议的各项条款，
//   在享有上述条款授予的权力的同时，受到相关的约束和限制。协议许可范围以外的行为，将直接违反本
//   授权协议并构成侵权，我们有权随时终止授权，责令停止损害，并保留追究相关责任的权力。
//
//  ---------------------------------------------------------------------------------
//
/**
 * 微信登录
 */
defined('IN_ECJIA') or exit('No permission resources.');

use Ecjia\App\Platform\Plugin\PlatformAbstract;
use Ecjia\App\Wechat\WechatRecord;
use Ecjia\App\Wechat\WechatUser;

class mp_userbind extends PlatformAbstract
{   
    /**
     * 获取插件代号
     *
     * @see \Ecjia\System\Plugin\PluginInterface::getCode()
     */
    public function getCode()
    {
        return $this->loadConfig('ext_code');
    }
    
    /**
     * 加载配置文件
     *
     * @see \Ecjia\System\Plugin\PluginInterface::loadConfig()
     */
    public function loadConfig($key = null, $default = null)
    {
        return $this->loadPluginData(RC_Plugin::plugin_dir_path(__FILE__) . 'config.php', $key, $default);
    }
    
    /**
     * 获取iconUrl
     * {@inheritDoc}
     * @see \Ecjia\App\Platform\Plugin\PlatformAbstract::getPluginIconUrl()
     */
    public function getPluginIconUrl()
    {
        if ($this->loadConfig('ext_icon')) {
            return RC_Plugin::plugin_dir_url(__FILE__) . $this->loadConfig('ext_icon');
        }
        return '';
    }

    /**
     * 事件回复
     * {@inheritDoc}
     * @see \Ecjia\App\Platform\Plugin\PlatformAbstract::eventReply()
     */
    public function eventReply() {

        $type = $this->getStoreType();

        if ($type == self::TypeAdmin) {
            $content = $this->handleAdminBindUser();
        } else if ($type == self::TypeMerchant) {
            $content = $this->handleMerchantBindUser();
        }
        
        return WechatRecord::News_reply($this->getMessage(), $content['title'], $content['description'], $content['url'], $content['image']);
    }

    /**
     * 处理平台绑定用户
     */
    public function handleAdminBindUser()
    {
        $wechatUUID = new \Ecjia\App\Wechat\WechatUUID();

        $wechat_id = $wechatUUID->getWechatID();
        $uuid   = $wechatUUID->getUUID();
        $openid = $this->getMessage()->get('FromUserName');

        //已经绑定用户
        if ($this->hasBindUser()) {
            $wechat_user = new WechatUser($wechat_id, $openid);

            if ($wechat_user->getEcjiaUserId()) {
                $userid = $wechat_user->getEcjiaUserId();
            } else {
                $connect_user = $wechat_user->getConnectUser();
                $userid = $connect_user->getUserId();
                //更新wechat_user里的ect_id
                $wechat_user->setEcjiaUserId($userid);
            }

            //获取用户名
            $username = RC_DB::table('users')->where('user_id', $userid)->value('user_name');

            $content = [
                'title' => __('已绑定', 'mp_userbind'),
                'description' => sprintf(__('您已拥有帐号，用户名为【%s】点击该链接可进入用户中心哦', 'mp_userbind'), $username),
                'url' => RC_Uri::url('wechat/mobile_profile/init', array('openid' => $openid, 'uuid' => $uuid)),
                'image' => RC_Plugin::plugin_dir_url(__FILE__) . '/images/wechat_thumb_userbind.png',
            ];
        }
        //未绑定用户
        else {
            $content = [
                'title' => __('未绑定', 'mp_userbind'),
                'description' => __('抱歉，目前您还未进行账号绑定，需点击该链接进行绑定操作', 'mp_userbind'),
                'url' => RC_Uri::url('wechat/mobile_userbind/init',array('openid' => $openid, 'uuid' => $uuid)),
                'image' => RC_Plugin::plugin_dir_url(__FILE__) . '/images/wechat_thumb_userbind.png',
            ];

        }

        return $content;
    }

    /**
     * 处理商家绑定用户
     */
    public function handleMerchantBindUser()
    {
        $wechatUUID = new \Ecjia\App\Wechat\WechatUUID();

        $wechat_id = $wechatUUID->getWechatID();
        $uuid   = $wechatUUID->getUUID();
        $openid = $this->getMessage()->get('FromUserName');

        //如果已经有了公众号用户且绑定了同一个开放平台，自动关联原有的用户ID
        if ($this->hasBindUser()) {
            $wechat_user = new WechatUser($wechat_id, $openid);
            $unionid = $wechat_user->getUnionid();

            $userid = $wechat_user->getEcjiaUserId();

            if (! $userid) {
                if ($unionid) {
                    $UnionidUser = $wechat_user->findUnionidUser($unionid);
                    $wechat_user->setEcjiaUserId($UnionidUser->ect_uid);
                } else {
                    $connect_user = $wechat_user->getConnectUser();
                    $userid = $connect_user->getUserId();
                    $wechat_user->setEcjiaUserId($userid);
                }
            }

            //获取用户名
            $username = RC_DB::table('users')->where('user_id', $userid)->value('user_name');
            $content = [
                'title' => __('已绑定', 'mp_userbind'),
                'description' => sprintf(__('您已拥有帐号，用户名为【%s】点击该链接可进入用户中心哦', 'mp_userbind'), $username),
                'url' => RC_Uri::url('wechat/mobile_profile/init', array('openid' => $openid, 'uuid' => $uuid)),
                'image' => RC_Plugin::plugin_dir_url(__FILE__) . '/images/wechat_thumb_userbind.png',
            ];
        }
        //未绑定用户
        else {
            $content = [
                'title' => __('未绑定', 'mp_userbind'),
                'description' => __('抱歉，目前您还未进行账号绑定，需点击该链接进行绑定操作', 'mp_userbind'),
                'url' => RC_Uri::url('wechat/mobile_userbind/init',array('openid' => $openid, 'uuid' => $uuid)),
                'image' => RC_Plugin::plugin_dir_url(__FILE__) . '/images/wechat_thumb_userbind.png',
            ];
        }

        return $content;
    }
    
}

// end