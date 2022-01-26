<?php
namespace addons\system_qiniu;

use ESA\AddonsHook;
use addons\system_qiniu\library\Auth;
use esa\Http;

class Hook extends AddonsHook
{
    /**
     * 附件上传配置
     * @return bool
     */
    public function esaAttachmentInit($config)
    {
        $qn_config = get_config("system_qiniu.");
        
        if(empty($qn_config) || !isset($qn_config['switch']) || $qn_config['switch'] == "false"){
            if(!empty(PLATFORM_ID)){
                // 向上查
                $qn_config = get_config("system_qiniu.",0);
                if(empty($qn_config) || !isset($qn_config['switch']) || $qn_config['switch'] == "false"){
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
        $auth = new Auth($qn_config['accesskey'], $qn_config['secretkey']);
        if(empty(intval($this->admin['id'])) && empty(intval($this->auth->id))){
            return $config;
        }
        $multipart['token'] = $auth->uploadToken($qn_config['bucket'], null, 6000, $policy);
        $multipart['x:admin'] = (int)session("admin_info.id");
        $multipart['x:user'] = (int)$this->auth->id;
        
        if(isset($qn_config['client_switch']) && $qn_config['client_switch'] == "true"){
            $config['upload_url'] = $qn_config['upload_url'];
        }
        
        $config['bucket'] = $qn_config['bucket'];
        $config['multipart'] = $multipart;
        $config['other']=$policy;
        
        return $config;
    }
    
    /**
     * 附件上传完毕
     * @return bool
     */
    public function esaAttachmentDone($id)
    {
        $qn_config = get_config("system_qiniu.");
        $info = model("attachment")->where("id",$id)->find();
        if(empty($info) || empty($qn_config) || !isset($qn_config['switch']) || $qn_config['switch'] == "false") return false;
        
        $auth = new Auth($qn_config['accesskey'], $qn_config['secretkey']);
        $token = $auth->uploadToken($qn_config['bucket'], null, $qn_config['expire'], $policy);
        $multipart = [
            ['name' => 'token', 'contents' => $token],
            [
                'name'     => 'file',
                'contents' => fopen($filePath, 'r'),
                'filename' => $fileName,
            ]
        ];
        try {
            $client = new \GuzzleHttp\Client();
            $res = $client->request('POST', $qn_config['uploadurl'], [
                'multipart' => $multipart
            ]);
            $code = $res->getStatusCode();
            //成功不做任何操作
        } catch (\GuzzleHttp\Exception\ClientException $e) {
            $attachment->delete();
            unlink($filePath);
            $this->error("上传失败");
        }
        return true;
    }
    
    /**
     * 附件删除钩子
     * @return bool
     */
    public function esaAttachmentDelete($ids)
    {
        $qn_config = get_config("system_qiniu.");
        if(!is_array($ids)){
            $ids = explode(",",$ids);
        }
        $atts = model("common/attachment")->where(["id"=>$ids])->select();
        foreach($atts as $k => $v){
            if($v['type'] == "qiniu"){
                // 七牛文件
                $file = UPLOAD_PATH.attach2local($v['path']);
            }else{
                $file = UPLOAD_PATH.$v['path'];
            }
            
            // 删除本地文件
            if(file_exists($file)){
                @unlink($file);
            }
            
            if($v['type'] == 'location'){
                return model("common/attachment")->destroy($v['id']);
            }
            
            // 删除七牛文件
            $auth = new Auth($qn_config['accesskey'], $qn_config['secretkey']);
            $entry = $qn_config['bucket'] . ':' . $v['path'];
            $encodedEntryURI = $auth->base64_urlSafeEncode($entry);
            $url = 'http://rs.qiniu.com/delete/' . $encodedEntryURI;
            $headers = $auth->authorization($url);
            //删除云储存文件
            $res = Http::sendRequest($url, [], 'POST', [CURLOPT_HTTPHEADER => ['Authorization: ' . $headers['Authorization']]]);
            $data = json_decode($res['msg'],true);
            if($res['ret'] === true && empty($data)){
                if(model("common/attachment")->destroy($v['id'])){
                    return true;
                }else{
                    return "数据库删除失败";
                }
            }else{
                return !empty($data['error']) ? $data['error'] : false;
            }
        }
        return false;
    }
    
    public function esaAttachmentHttpSrc(){
        // $url = get_config("qiniu_url");
        $qn_config = get_config("system_qiniu.");
        if(empty($qn_config) || !isset($qn_config['switch']) || $qn_config['switch'] == "false"){
            if(!empty(PLATFORM_ID)){
                // 向上查
                $qn_config = get_config("system_qiniu.",0);
                if(empty($qn_config) || !isset($qn_config['switch']) || $qn_config['switch'] == "false"){
                    $url = request()->domain()."/upload";
                }else{
                    $url = $qn_config['url'];
                }
            }else{
                $url = request()->domain()."/upload";
            }
        }else{
            $url = $qn_config['url'];
        }
        return ["qiniu"=>$url."/{path}"];
        // $url = get_config("qiniu_url");
        // return $url."/".$attachment['path'];
    }
    
    public function esaPlatformConfigs($platform_info){
        return $this->configs();
    }

    public function esaSystemConfigs(){
        return [];
        // return $this->configs();
    }
    public function configs(){
        return [
            [
                "group"    => "system_qiniu",
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
                        "type"      => "radio",
                        "title"     => "是否保留服务器端文件",
                        "param"     => [
                            "name"      => "server_file_switch",
                            "lines"     => [
                                ["text"  => "是：","value" => "true"],
                                ["text"  => "否：","value" => "false"]
                            ],
                            "value"     => "false",
                        ],
                        "explain"   => "当直传开启时此配置项失效",
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
                                ["text"  => "华东-浙江2","value" => "https://upload-cn-east-2.qiniup.com"]
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