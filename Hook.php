<?php
namespace addons\system_qiniu;

use ESA\AddonsHook;
use addons\system_qiniu\library\Auth;

class Hook extends AddonsHook
{
    /**
     * 附件上传配置
     * @return bool
     */
    public function esaAttachmentInit($config)
    {
        $qn_config = get_config("qiniu_*");
        if(empty($qn_config) || !isset($qn_config['qiniu_switch']) || $qn_config['qiniu_switch'] == "false"){
            if(!empty(PLATFORM_ID)){
                // 向上查
                $qn_config = get_config("qiniu_*",0);
                if(empty($qn_config) || !isset($qn_config['qiniu_switch']) || $qn_config['qiniu_switch'] == "false"){
                    return $config;
                }
            }else{
                return $config;
            }
        }
        // $platform_id = !empty(request()->param("PLATFORM_ID")) ? request()->param("PLATFORM_ID") : PLATFORM_ID;
        $policy = array(
            'saveKey' => ltrim("qiniu/$(year)/$(mon)/$(day)/$(etag)$(ext)", '/'),
            'callbackUrl'  => request()->domain()."/addons/".PLATFORM_ID."/system_qiniu.index.index/callback",
            'callbackBody' => 'filename=$(fname)&hash=$(etag)&key=$(key)&imageInfo=$(imageInfo)&filesize=$(fsize)&admin=$(x:admin)&user=$(x:user)',
        );
        $auth = new Auth($qn_config['qiniu_accesskey'], $qn_config['qiniu_secretkey']);
        $multipart['token'] = $auth->uploadToken($qn_config['qiniu_bucket'], null, 6000, $policy);
        $multipart['x:admin'] = (int)session("admin_info.id");
        $multipart['x:user'] = (int)session("user_info.id");
        
        if(isset($qn_config['qiniu_client_switch']) && $qn_config['qiniu_client_switch'] == "true"){
            $config['upload_url'] = $qn_config['qiniu_upload_url'];
        }
        
        $config['bucket'] = $qn_config['qiniu_bucket'];
        $config['multipart'] = $multipart;
        $config['other']=$policy;
        return $config;
    }
    
    /**
     * 附件上传完毕
     * @return bool
     */
    public function esaAttachmentDone()
    {
        return true;
    }
    
    public function esaAttachmentHttpSrc(){
        // $url = get_config("qiniu_url");
        $qn_config = get_config("qiniu_*");
        if(empty($qn_config) || !isset($qn_config['qiniu_switch']) || $qn_config['qiniu_switch'] == "false"){
            if(!empty(PLATFORM_ID)){
                // 向上查
                $qn_config = get_config("qiniu_*",0);
                if(empty($qn_config) || !isset($qn_config['qiniu_switch']) || $qn_config['qiniu_switch'] == "false"){
                    $url = "/upload";
                }else{
                    $url = $qn_config['qiniu_url'];
                }
            }else{
                return $url="/upload";
            }
        }else{
            $url = $qn_config['qiniu_url'];
        }
        return ["qiniu"=>$url."/{path}"];
        // $url = get_config("qiniu_url");
        // return $url."/".$attachment['path'];
    }
    
    public function esaPlatformConfigs($platform_info){
        return $this->configs();
    }

    public function esaSystemConfigs(){
        return $this->configs();
    }
    public function configs(){
        return [
            [
                "group"    => "qiniu",
                "icon"  => "fa fa-home",
                "title" => "七牛配置",
                "list"  => [
                    [
                        "type"      => "radio",
                        "title"     => "是否开启七牛云",
                        "param"     => [
                            "name"      => "switch",
                            "lines"     => [
                                [
                                    "text"  => "开启：",
                                    "value" => "true"
                                ],
                                [
                                    "text"  => "关闭：",
                                    "value" => "false"
                                ]
                            ],
                            "value"     => "false",
                        ],
                        "explain"   => "",
                        "require"   => "require",
                    ],
                    [
                        "type"      => "input",
                        "title"     => "Accesskey",
                        "param"     => [
                            "name"      => "accesskey",
                            "value"     => "",
                        ],
                        "explain"   => "用于签名的公钥",
                        "require"   => "require",
                    ],
                    [
                        "type"      => "input",
                        "title"     => "Secretkey",
                        "param"     => [
                            "name"      => "secretkey",
                            "value"     => "",
                        ],
                        "explain"   => "用于签名的私钥",
                        "require"   => "require",
                    ],
                    [
                        "type"      => "input",
                        "title"     => "Bucket",
                        "param"     => [
                            "name"      => "bucket",
                            "value"     => "",
                        ],
                        "explain"   => "请保证bucket为可公共读取的",
                        "require"   => "require",
                    ],
                    [
                        "type"      => "input",
                        "title"     => "Url",
                        "param"     => [
                            "name"      => "url",
                            "value"     => "",
                        ],
                        "explain"   => "七牛支持用户自定义访问域名。注：url开头加http://或https://结尾不加 ‘/’例：http://abc.com",
                        "require"   => "require",
                    ],
                    [
                        "type"      => "radio",
                        "title"     => "是否客户端直传",
                        "param"     => [
                            "name"      => "client_switch",
                            "lines"     => [
                                ["text"  => "是：","value" => "true"],
                                ["text"  => "否：","value" => "false"]
                            ],
                            "value"     => "false",
                        ],
                        "explain"   => "",
                        "require"   => "require",
                    ],
                    [
                        "type"      => "select",
                        "title"     => "存储区域选择",
                        "param"     => [
                            "name"      => "upload_url",
                            "lines"     => [
                                ["text"  => "华东-z0","value" => "https://upload.qiniup.com"],
                                ["text"  => "华北-z1","value" => "https://upload-z1.qiniup.com"],
                                ["text"  => "华南-z2","value" => "https://upload-z2.qiniup.com"],
                                ["text"  => "北美-na0","value" => "https://upload-na0.qiniup.com"],
                                ["text"  => "东南亚-as0","value" => "https://upload-as0.qiniup.com"],
                            ],
                            "value"     => "https://upload.qiniup.com",
                        ],
                        "explain"   => "确定服务器上传/客户端上传的存储区域",
                        "require"   => "require",
                    ],
                ]
            ]
        ];
    }
}