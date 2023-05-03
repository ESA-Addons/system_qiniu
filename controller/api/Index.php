<?php
namespace addons\system_qiniu\controller\api;

use addons\system_qiniu\Main;

class Index extends Main
{
    protected $ESA_TYPE     = "INDEX";
    protected $EXPOSURE     = ["callback"];
    
    public function callback()
    {
        // admin: "1"
        // filename: "6366473954df81d3feac697bbef0220e.png"
        // filesize: "6532"
        // imageInfo: "{"colorModel":"nrgba","format":"png","height":54,"size":6532,"width":87}"
        // hash: "asdfad"
        // key: "20201025/FhhyUTK8uF8ldGACqN7jQ99iLkJz.png"
        // user: "0"
        // return $this->result("上传成功",$this->request->param());
        $data = $this->request->param();
        // trace(json_encode($data));
        $qn_config = get_platform_config("system_qiniu.");
        if(empty($qn_config['switch'])){
            if(!empty(PLATFORM_ID)){
                // 向上查
                $qn_config = get_platform_config("system_qiniu.",0);
            }
        }

        $url = $qn_config["url"]."/";
        $where = [
            "storage_type"  => "qiniu",
            "pfid"          => PLATFORM_ID,
            // "adid"          => ADDON_ID,
            "aid"           => $data['admin'],
            "uid"           => $data['user'],
            "hash"          => $data['hash'],
            "name"          => $data['filename'],
        ];
        $old_file = model("Attachment")->where($where)->find();
        
        if(!empty($old_file)){
            $res = [
                'id'	=> $old_file['id'],
                'name'	=> $old_file['name'],
                'path'	=> $old_file['path'],
                'src'   => $url.$old_file['path'],
        	];
            return $this->result("文件已存在",$res);
        }else{
            $type = explode("/", $data['type']);
            $insert = [
                "storage_type"  => "qiniu",
                "pfid"          => PLATFORM_ID,
                "uid"           => $data['user'],
                "aid"           => $data['admin'],
                "name"          => $data['filename'],
                "type"          => $type[0],
                "size"          => $data['filesize'],
                "exten"         => $type[1],
                "path"          => $data['key'],
                "hash"          => $data['hash'],
                "create_time"   => time()
            ];
            $id = model("Attachment")->insertGetId($insert);
            if(!$id){
                return $this->result("数据库插入失败","",110);
            }
            $res = [
                'id'	=> $id,
                'name'	=> $data['filename'],
                'path'	=> $data['key'],
                'src'   => $url.$data['key'],
        	];
            return $this->result("上传成功",$res);
        }
        
        return $this->result("上传成功",$this->request->param());
    }
}