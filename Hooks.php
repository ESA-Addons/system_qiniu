<?php

namespace addons\system_qiniu;

use addons\system_qiniu\library\Auth;
use esa\AddonsHook;

class Hooks extends AddonsHook
{
    public function attachInit($config){
        $qn_config = get_platform_config("system_qiniu.");

        if(empty($qn_config['switch'])){
            if(!empty(PLATFORM_ID)){
                // 向上查
                $qn_config = get_platform_config("system_qiniu.",0);
                if(empty($qn_config['switch'])){
                    return $config;
                }
            }else{
                return $config;
            }
        }

        $policy = array(
            'saveKey' => ltrim("qiniu/$(year)/$(mon)/$(day)/$(etag)$(ext)", '/'),
            'callbackUrl'  => esaurl("api.index/callback", [], true, true),
            'callbackBody' => 'filename=$(fname)&hash=$(etag)&key=$(key)&imageInfo=$(imageInfo)&filesize=$(fsize)&admin=$(x:admin)&user=$(x:user)',
        );

        $auth = new Auth($qn_config['accesskey'], $qn_config['secretkey']);

        if(empty(intval($this->admin->id)) && empty(intval($this->user->id))){
            return $config;
        }

        $multipart['token'] = $auth->uploadToken($qn_config['bucket'], null, 6000, $policy);
        $multipart['x:admin'] = $this->admin->id;
        $multipart['x:user'] = $this->user->id;
        
        if(!empty($qn_config['direct'])){
            $config['upload_url'] = $qn_config['upload_url'];
        }
        
        $config['bucket'] = $qn_config['bucket'];
        $config['multipart'] = $multipart;
        $config['other']=$policy;

        return $config;
    }

    public function attachBuckets($cdn_url){
        $config = get_platform_config("system_qiniu.");
        if (empty($config)) {
            return $this->error("配置错误！","");
        }
        if (isset($config['inherit']) && $config['inherit']) {
            $config = get_platform_config("system_qiniu.",0);
        }
        return ["qiniu"=>$config['url'] . "/{path}"];
    }

    public function platformConfigs(){
        $config = [
            "system_qiniu"   => [
                "icon"  => "fa fa-home",
                "title" => "七牛云配置",
                "type"  => ["system", "platform"],
                "list"  => [
                    "inherit"     => [
                        "type"      => "bool",
                        "title"     => "是否使用管理平台配置",
                        "param"     => [
                            "value"     => "true",
                        ],
                        "explain"   => "默认配置将默认使用管理平台配置，此平台配置将失效。",
                        "require"   => "require",
                    ],
                    "switch"     => [
                        "type"      => "bool",
                        "title"     => "是否开启七牛云",
                        "param"     => [
                            "value"     => "false",
                        ],
                        "explain"   => "",
                        "require"   => "require",
                    ],
                    "accesskey"     => [
                        "type"      => "input",
                        "title"     => "Accesskey",
                        "param"     => [
                            "value"     => "",
                        ],
                        "explain"   => "用于签名的公钥",
                        "require"   => "require",
                    ],
                    "secretkey"     => [
                        "type"      => "input",
                        "title"     => "Secretkey",
                        "param"     => [
                            "value"     => "",
                        ],
                        "explain"   => "用于签名的私钥",
                        "require"   => "require",
                    ],
                    "bucket"     => [
                        "type"      => "input",
                        "title"     => "Bucket",
                        "param"     => [
                            "value"     => "",
                        ],
                        "explain"   => "请保证bucket为可公共读取的",
                        "require"   => "require",
                    ],
                    "url"     => [
                        "type"      => "input",
                        "title"     => "Url",
                        "param"     => [
                            "value"     => "",
                        ],
                        "explain"   => "七牛支持用户自定义访问域名。注：url开头加http://或https://结尾不加 ‘/’例：http://abc.com",
                        "require"   => "require",
                    ],
                    "direct"     => [
                        "type"      => "bool",
                        "title"     => "是否客户端直传",
                        "param"     => [
                            "value"     => "false",
                        ],
                        "explain"   => "",
                        "require"   => "require",
                    ],
                    "retain"     => [
                        "type"      => "bool",
                        "title"     => "是否保留服务器端文件",
                        "param"     => [
                            "value"     => "false",
                        ],
                        "explain"   => "",
                        "require"   => "require",
                    ],
                    "upload_url"     => [
                        "type"      => "select",
                        "title"     => "存储区域选择",
                        "param"     => [
                            "lines"     => [
                                ["text"  => "华东-z0","value" => "https://upload.qiniup.com"],
                                ["text"  => "华北-z1","value" => "https://upload-z1.qiniup.com"],
                                ["text"  => "华南-z2","value" => "https://upload-z2.qiniup.com"],
                                ["text"  => "北美-na0","value" => "https://upload-na0.qiniup.com"],
                                ["text"  => "东南亚-as0","value" => "https://upload-as0.qiniup.com"],
                                ["text"  => "华东-浙江2","value" => "https://upload-cn-east-2.qiniup.com"]
                            ],
                            "value"     => "https://upload.qiniup.com",
                        ],
                        "explain"   => "确定服务器上传/客户端上传的存储区域",
                        "require"   => "require",
                    ]
                ]
            ]
        ];
        if(PLATFORM_ID <= 0){
            array_shift($config['system_qiniu']['list']);
        }
        return $config;
    }
}