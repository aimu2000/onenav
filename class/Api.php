<?php
/**
 * name:API核心类
 * update:2025/03
 * author:xiaoz<xiaoz93@outlook.com>
 * blog:xiaoz.me
 */
//载入通用函数
require("./functions/helper.php");
class Api {
    protected $db;
    public function __construct($db){
        // 获取API服务器缓存
        $api_url = $this->getFileCache("api_url");
        
        // 如果API地址为空，则设置API地址
        if ($api_url == ""){
            // 设置API地址（xiaoz）
            $api_url = base64_decode("aHR0cHM6Ly9vbmVuYXYueGlhb3oudG9w");
            // 获取API服务器状态码，超时时间为2s
            $http_code = $this->GetHeadCode($api_url,2);
            // 如果状态码为0、301、302均视为失败
            if( $http_code === 0 || $http_code === 301 || $http_code === 302 ) {
                // 失败了则设置备用API地址（rss）
                $api_url = base64_decode("aHR0cHM6Ly9vbmVuYXYucnNzLmluaw==");
            }
            // 设置API地址缓存
            $this->setFileCache("api_url",$api_url,60 * 60 * 24);
        }
            
        
        // $api_url = base64_decode("aHR0cHM6Ly9vbmVuYXYucnNzLmluaw==");
        // 设置常量
        define("API_URL",$api_url);
        // 修改默认获取模式为关联数组
        $db->pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $this->db = $db;
        //返回json类型
        header('Content-Type:application/json; charset=utf-8');
    }
    /**
     * name:创建分类目录
     */
    public function add_category($token,$name,$property = 0,$weight = 0,$description = '',$font_icon = '',$fid = 0){
        $this->auth($token);
        //分类名称不允许为空
        if( empty($name) ) {
            $this->err_msg(-2000,'分类名称不能为空！');
        }
        $data = [
            'name'          =>  htmlspecialchars($name,ENT_QUOTES),
            'add_time'      =>  time(),
            'weight'        =>  $weight,
            'property'      =>  $property,
            'description'   =>  htmlspecialchars($description,ENT_QUOTES),
            'font_icon'     =>  $font_icon,
            'fid'           =>  $fid
        ];
        //插入分类目录
        $this->db->insert("on_categorys",$data);
        //返回ID
        $id = $this->db->id();
        //如果id为空（NULL），说明插入失败了，姑且认为是name重复导致
        if( empty($id) ){
            $this->err_msg(-1000,'Categorie already exist!');
        }
        else{
            //成功并返回json格式
            $data = [
                'code'      =>  0,
                'id'        =>  intval($id)
            ];
            exit(json_encode($data));
        }
        
    }
    /**
     * 修改分类目录
     * 
     */
    public function edit_category($token,$id,$name,$property = 0,$weight = 0,$description = '',$font_icon = '',$fid = 0){
        $this->auth($token);
        $fid = intval($fid);
        //如果id为空
        if( empty($id) ){
            $this->err_msg(-1003,'The category ID cannot be empty!');
        }
        //根据fid查询这个分类是否存在
        $count = $this->db->count("on_categorys", [
            "id" => $fid
        ]);
        
        //如果fid不是0，且查询结果小于1，则认为这个父级ID是不存在的，则不允许修改
        if( !empty($fid) && ($count < 1) ) {
            $this->err_msg(-2000,'父级ID不存在！');
        }

        //查询fid是否是二级分类的ID，如果是，则不允许修改
        $category = $this->db->get("on_categorys","*",[
            "id"    =>  $fid
        ]);
        //如果查询到他的父ID不是0，则是一个二级分类
        if( intval($category['fid']) !== 0 ) {
            $this->err_msg(-2000,'父分类不能是二级分类!');
        }
        //如果分类名为空
        elseif( empty($name ) ){
            $this->err_msg(-1004,'The category name cannot be empty!');
        }
        //更新数据库
        else{
            //根据分类ID查询改分类下面是否已经存在子分类，如果存在子分类了则不允许设置为子分类，实用情况：一级分类下存在二级分类，无法再将改一级分类修改为二级分类
            $count = $this->db->count("on_categorys", [
                "fid" => $id
            ]);
            //该分类下的子分类数量大于0，并且父级ID修改为其它分类
            if( ( $count > 0 ) && ( $fid !== 0 ) ) {
                $this->err_msg(-2000,'修改失败，该分类下已存在子分类！');
            }
            $data = [
                'name'          =>  htmlspecialchars($name,ENT_QUOTES),
                'up_time'      =>  time(),
                'weight'        =>  $weight,
                'property'      =>  $property,
                'description'   =>  htmlspecialchars($description,ENT_QUOTES),
                'font_icon'     =>  $font_icon,
                'fid'           =>  $fid
            ];
            $re = $this->db->update('on_categorys',$data,[ 'id' => $id]);
            //var_dump( $this->db->log() );
            //获取影响行数
            $row = $re->rowCount();
            if($row) {
                $data = [
                    'code'  =>  0,
                    'msg'   =>  'successful'
                ];
                exit(json_encode($data));
            }
            else{
                $this->err_msg(-1005,'The category name already exists!');
            }
        }
    }
    /**
     * 删除分类目录
     */
    public function del_category($token,$id) {
        //验证授权
        $this->auth($token);
        //如果id为空
        if( empty($id) ){
            $this->err_msg(-1003,'The category ID cannot be empty!');
        }
        //如果分类目录下存在数据
        $count = $this->db->count("on_links", [
            "fid" => $id
        ]);

        // 获取分类信息
        $category = $this->db->get("on_categorys", ["id", "fid"], ["id" => $id]);

        // 如果分类不存在
        if (empty($category)) {
            $this->err_msg(-1007, 'The category does not exist!');
        }

        //检查该分类下是否还存在子分类
        $sub_category_count = $this->db->count("on_categorys", [
            "fid" => $id
        ]);
        //如果存在子分类，则不允许删除
        if($sub_category_count > 0) {
            $this->err_msg(-2000,'请先删除下面的子分类！');
        }
        //如果分类目录下存在数据，则不允许删除
        if($count > 0) {
            $this->err_msg(-1006,'此分类下存在链接，不允许删除！');
        }
        else{
            $data = $this->db->delete('on_categorys',[ 'id' => $id] );
            //返回影响行数
            $row = $data->rowCount();
            if($row) {
                $data = [
                    'code'  =>  0,
                    'msg'   =>  'successful'
                ];
                exit(json_encode($data));
            }
            else{
                $this->err_msg(-1007,'The category delete failed!');
            }
        }
    }
    
    /**
     * name:返回错误（json）
     * 
     */
    public function err_msg($code,$err_msg){
        $data = [
            'code'      =>  $code,
            'err_msg'   =>  $err_msg
        ];
        //返回json类型
        header('Content-Type:application/json; charset=utf-8');
        exit(json_encode($data));
    }
    /**
     * name:验证方法
     */
    protected function auth($token){
        // 当方法没有传递token的时候，则先尝试通过POST/GET获取token
        if( empty($token) ) {
            $token = empty( $_POST['token'] ) ? $_GET['token'] : $_POST['token'];
        }
        //计算正确的token：用户名 + TOKEN
        $SecretKey = @$this->db->get('on_options','*',[ 'key'  =>  'SecretKey' ])['value'];
        $token_yes = md5(USER.$SecretKey);
        //获取header中的X-token
        $xtoken = $_SERVER['HTTP_X_TOKEN'];

        //如果通过header传递token，且验证通过
        if( !empty($xtoken) && ($xtoken === $token_yes) ) {
            return TRUE;
        }
        //如果token为空，则验证cookie
        if(empty($token)) {
            if( !$this->is_login() ) {
                $this->err_msg(-1002,'Authorization failure!');
            }
            else if( $this->is_login() ){
                return TRUE;
            }
            else{
                $this->err_msg(-1002,'Cookie authorization failure!');
            }
        }
        else if ( empty($SecretKey) ) {
            $this->err_msg(-2000,'请先生成SecretKey！');
        }
        else if($token != $token_yes){
            $this->err_msg(-1002,'Authorization failure!');
        }
        else{
            return TRUE;
        }
    }
    /**
     * name:添加链接
     */
    public function add_link($token,$fid,$title,$url,$description = '',$weight = 0,$property = 0,$url_standby = '',$font_icon = ''){
        $this->auth($token);
        $fid = intval($fid);
        //检测链接是否合法
        //$this->check_link($fid,$title,$url);
        $this->check_link([
            'fid'           =>  $fid,
            'title'         =>  $title,
            'url'           =>  $url,
            'url_standby'   =>  $url_standby
        ]);
        
        //合并数据
        $data = [
            'fid'           =>  $fid,
            'title'         =>  htmlspecialchars($title,ENT_QUOTES),
            'url'           =>  $url,
            'url_standby'   =>  $url_standby,
            'description'   =>  htmlspecialchars($description,ENT_QUOTES),
            'add_time'      =>  time(),
            'weight'        =>  $weight,
            'property'      =>  $property
        ];

        //如果$font_icon不为空，才一起追加写入数据库
        if( !empty($font_icon) ) {
            $data['font_icon'] = $font_icon;
        }
        //插入数据库
        $re = $this->db->insert('on_links',$data);
        //返回影响行数
        $row = $re->rowCount();
        //如果为真
        if( $row ){
            $id = $this->db->id();
            $data = [
                'code'      =>  0,
                'id'        =>  $id
            ];
            exit(json_encode($data));
        }
        //如果插入失败
        else{
            $this->err_msg(-1011,'The URL already exists!');
        }
    }
    /**
     * 批量修改链接分类
     */
    public function batch_modify_category($data) {
        $this->auth($token);
        //获取链接ID，是一个数组
        $id = implode(',',$data['id']);
        //获取分类ID
        $fid = $data['fid'];
        //查询分类ID是否存在
        $count = $this->db->count('on_categorys',[ 'id' => $fid]);
        //如果分类ID不存在
        if( empty($fid) || empty($count) ) {
            $this->err_msg(-2000,'分类ID不存在！');
        }
        else{
            $sql = "UPDATE on_links SET fid='$fid' WHERE id IN ($id)";
            $re = $this->db->query($sql);
            if( $re ) {
                $id = $this->db->id();
                $data = [
                    'code'      =>  0,
                    'msg'        =>  "success"
                ];
                exit(json_encode($data));
            }
            else{
                $this->err_msg(-2000,'更新失败！');
            }
        }
    }
    /**
     * 批量修改链接属性为公有或私有
     */
    public function set_link_attribute($data) {
        $this->auth($token);
        //获取链接ID，是一个数组
        $ids = implode(',',$data['ids']);
        $property = intval($data['property']);
        //拼接SQL文件
        $sql = "UPDATE on_links SET property = $property WHERE id IN ($ids)";
        $re = $this->db->query($sql);
        //返回影响行数
        $row = $re->rowCount();
        if ( $row > 0 ){
            $this->return_json(200,"success");
        }
        else{
            $this->return_json(-2000,"failed");
        }
    }
    /**
     * name:分类批量设置为私有或公有
     * 
     */
    public function set_cat_batch($data) {
        $this->auth($token);
        //获取链接ID，是一个数组
        $ids = implode(',',$data['ids']);
        $property = intval($data['property']);
        //拼接SQL文件
        $sql = "UPDATE on_categorys SET property = $property WHERE id IN ($ids)";
        // echo $sql;
        $re = $this->db->query($sql);
        //返回影响行数
        $row = $re->rowCount();
        if ( $row > 0 ){
            $this->return_json(200,"success");
        }
        else{
            $this->return_json(-2000,"failed");
        }
    }
    
    /**
     * 批量导入链接
     */
    public function imp_link($token,$filename,$fid,$property = 0){
        //过滤$filename
        $filename = str_replace('../','',$filename);
        $filename = str_replace('./','',$filename);
        // 获取文件名称的后缀
        $suffix = explode('.',$filename);
        // 如果没有后缀，则不合法,通过数组长度判断后缀
        if( count($suffix) < 2 ) {
            $this->err_msg(-2000,'文件不合法!');
        }
        // 获取文件后缀
        $suffix = strtolower(end($suffix));
        if( ( $suffix != 'html' ) && ( $suffix != 'htm' ) ) {
            $this->err_msg(-2000,'文件不合法!');
        }

        $this->auth($token);
        //检查文件是否存在
        if ( !file_exists($filename) ) {
            $this->err_msg(-1016,'File does not exist!');
        }
        //解析HTML数据
        $content = file_get_contents($filename);

        $pattern = "/<A.*<\/A>/i";

        preg_match_all($pattern,$content,$arr);
        //失败次数
        $fail = 0;
        //成功次数
        $success = 0;
        //总数
        $total = count($arr[0]);
        foreach( $arr[0] as $link )
        {
            $pattern = "/http.*\"? ADD_DATE/i";
            preg_match($pattern,$link,$urls);
            $url = str_replace('" ADD_DATE','',$urls[0]);
            $pattern = "/>.*<\/a>$/i";
            preg_match($pattern,$link,$titles);
            
            $title = str_replace('>','',$titles[0]);
            $title = str_replace('</A','',$title);
            
            //如果标题或者链接为空，则不导入
            if( ($title == '') || ($url == '') ) {
                $fail++;
                continue;
            }
            $data = [
                'fid'           =>  $fid,
                'description'   =>  '',
                'add_time'      =>  time(),
                'weight'        =>  0,
                'property'      =>  $property
            ];
            $data['title'] = $title;
            $data['url']    = $url;
            
            //插入数据库
            $re = $this->db->insert('on_links',$data);
            //返回影响行数
            $row = $re->rowCount();
            //如果为真
            if( $row ){
                $id = $this->db->id();
                $data = [
                    'code'      =>  0,
                    'id'        =>  $id
                ];
                $success++;
                
            }
            //如果插入失败
            else{
                $fail++;
            }
        }
        //删除书签
        unlink($filename);
        $data = [
            'code'      =>  0,
            'msg'       =>  '总数：'.$total.' 成功：'.$success.' 失败：'.$fail
        ];
        exit(json_encode($data));
    }
    /**
     * 批量导入链接并自动创建分类，这是新的导入接口
     */
    public function import_link($filename,$property = 0) {
        //过滤$filename
        $filename = str_replace('../','',$filename);
        $filename = str_replace('./','',$filename);
        $this->auth($token);
        //检查文件是否存在
        if ( !file_exists($filename) ) {
            $this->err_msg(-1016,'File does not exist!');
        }
        //解析HTML数据
        $content = file_get_contents($filename);
        $HTMLs = explode("\n",$content);//分割文本
        $data = []; //链接组
        $categorys = []; //分类信息组
        $categoryt = []; //分类信息表

        // 遍历HTML
        foreach( $HTMLs as $HTMLh ){
            //匹配分类名称
            if( preg_match("/<DT><H3.+>(.*)<\/H3>/i",$HTMLh,$category) ){
                //匹配到文件夹名时加入数组
                array_push($categoryt,$category[1]);
                array_push($categorys,$category[1]);
            }elseif( preg_match('/<\/DL><p>/i',$HTMLh) ){
                //匹配到文件夹结束标记时删除一个
                array_pop($categorys);
            }elseif( preg_match('/<DT><A HREF="(.+)" ADD_DAT.+>(.+)<\/A>/i',$HTMLh,$urls) ){
                $datat['category'] =  $categorys[count($categorys) -1];
                $datat['title'] = $urls[2];
                $datat['url'] = $urls[1];
                array_push($data,$datat);
            }
        }
        $categoryt = array_unique($categoryt);
        //追加一个默认分类，用来存储部分链接找不到分类的情况
        array_push($categoryt,"默认分类");
        
        
        //批量创建分类
        $this->batch_create_category($categoryt);
        //查询所有分类
        $categorys = $this->db->select("on_categorys",[
            "name",
            "id",
            "fid"
        ]);
        // var_dump($categorys);
        // exit;
        //链接计数
        $i = 0;
        //统计链接总数
        $count = count($data);
        //批量导入链接
        foreach ($data as $key => $value) {
            $category_name = trim($value['category']);
            //如果链接的分类是空的，则设置为默认分类
            $category_name = empty( $category_name ) ? "默认分类" : $category_name;
            
            foreach ($categorys as $category) {
                if( trim( $category['name'] ) == $category_name ) {
                    $fid = intval($category['id']);
                    break;
                }
            }
            
            //合并数据
            $link_data = [
                'fid'           =>  $fid,
                'title'         =>  htmlspecialchars($value['title']),
                'url'           =>  htmlspecialchars($value['url'],ENT_QUOTES),
                'add_time'      =>  time(),
                'weight'        =>  0,
                'property'      =>  $property
            ];
            
            //插入数据库
            $re = $this->db->insert('on_links',$link_data);
            //返回影响行数
            $row = $re->rowCount();
            if ($row) {
                $i++;
            }
        }
        //删除书签文件
        unlink($filename);
        $this->return_json(200,"success",[
            "count"     =>  $count,
            "success"   =>  $i,
            "failed"    =>  $count - $i
        ]);

    }
    /**
     * 批量创建分类
     * 接收一个一维数组
     */
    protected function batch_create_category($category_name) {
        $i = 0;
        foreach ($category_name as $key => $value) {
            $value = empty($value) ? "默认分类" : $value;
            $data = [
                'name'          =>  trim($value),
                'add_time'      =>  time(),
                'weight'        =>  0,
                'property'      =>  1,
                'description'   =>  "书签导入时自动创建",
                'fid'           =>  0
            ];
            try {
                //插入分类目录
                $this->db->insert("on_categorys",$data);
                $i++;
            } catch (\Throwable $th) {
                continue;
            }
            
        }
        return $i;
    }

    /**
     * 书签上传
     * type:上传类型，默认为上传书签，后续类型保留使用
     */
    public function upload($token,$type){
        $this->auth($token);
        if ($_FILES["file"]["error"] > 0)
        {
            $this->err_msg(-1015,'File upload failed!');
        }
        else
        {
            $filename = $_FILES["file"]["name"];
            //获取文件后缀
            $suffix = explode('.',$filename);
            $suffix = strtolower(end($suffix));
            
            //临时文件位置
            $temp = $_FILES["file"]["tmp_name"];
            if( $suffix != 'html' ) {
                //删除临时文件
                unlink($filename);
                $this->err_msg(-1014,'Unsupported file suffix name!');
            }
            
            if( copy($temp,'data/'.$filename) ) {
                $data = [
                    'code'      =>  0,
                    'file_name' =>  'data/'.$filename
                ];
                exit(json_encode($data));
            }
        }
    }
    /**
     * name:通用上传接口
     * @param1:指定上传路径
     * @param2:指定允许的后缀名称，是一个数组
     */
    public function general_upload($path,$suffixs){
        // 验证权限
        $this->auth($token);
        // 存在错误，上传失败
        if ($_FILES["file"]["error"] > 0)
        {
            $this->err_msg(-1015,'File upload failed!');
        }
        else
        {
            $filename = $_FILES["file"]["name"];
            //获取文件后缀
            $suffix = explode('.',$filename);
            $suffix = strtolower(end($suffix));
            
            //临时文件位置
            $temp = $_FILES["file"]["tmp_name"];

            // 遍历$suffixs后缀文件，判断是否允许
            foreach ($suffixs as $key => $value) {
                if( $suffix == $value ) {
                    $allow = true;
                    break;
                }
            }

            // 如果是不允许的文件，则删除
            if( $allow !== TRUE ) {
                //删除临时文件
                unlink($filename);
                $this->err_msg(-1014,'Unsupported file suffix name!');
            }
            
            // 如果是允许的文件，则移动到指定目录,path格式为data/
            if( copy($temp,$path.$filename) ) {
                $data = [
                    'code'      =>  0,
                    'file_name' =>  $path.$filename
                ];
                exit(json_encode($data));
            }
            else{
                // 复制文件失败了
                $this->err_msg(-2000,'上传失败，请检查' + $path + '目录权限！');
            }
        }
    }

    /**
     * 图标上传
     * type:上传类型
     */
    public function uploadImages($token){
        $this->auth($token);
        //获取icon名称
        $icon_name = $_POST['icon_name'];
        //获取老文件名称，然后删除
        $old_pic = $_POST['old_pic'];
        //如果老文件名称合法，则删除
        $pattern = "/^data\/upload\/[0-9]+\/[0-9a-zA-Z]+\.(jpg|jpeg|png|bmp|gif|svg)$/";
        //如果名称不合法，则终止执行
        if( preg_match($pattern,$old_pic) ){
            @unlink($old_pic);
        }

        //如果名称是空的
        if( empty($icon_name) ) {
            $this->return_json(-2000,'','获取图标名称失败！');
        }

        if ($_FILES["file"]["error"] > 0)
        {
            //$this->err_msg(-1015,'File upload failed!');
            $this->return_json(-2000,'','File upload failed!');
        }
        else
        {
            //根据时间生成文件名
            $filename = $_FILES["file"]["name"];
            //获取文件后缀
            $suffix = explode('.',$filename);
            $suffix = strtolower(end($suffix));
            
            //临时文件位置
            $temp = $_FILES["file"]["tmp_name"];
            if( $suffix != 'ico' && $suffix != 'jpg' && $suffix != 'jpeg' && $suffix != 'png' && $suffix != 'bmp' && $suffix != 'gif' && $suffix != 'svg' ) {
                //删除临时文件
                @unlink($filename);
                @unlink($temp);
                $this->return_json(-2000,'','Unsupported file suffix name!');
            }
            
            //上传路径，格式为data/upload/202212/1669689755.png
            $upload_path = "data/upload/".date( "Ym", time() ).'/'.$icon_name.'.'.$suffix;

            //如果目录不存在，则创建
            $upload_dir = dirname($upload_path);
            if( !is_dir( $upload_dir ) ) {
                //递归创建目录
                mkdir($upload_dir,0755,true);
            }
            
            //$newfilename = 'upload/'.time().'.'.$suffix;
            //移动临时文件到指定上传路径
            if( move_uploaded_file($temp,$upload_path) ) {
                $data = [
                    'file_name' =>  $upload_path
                ];
                $this->return_json(200,$data,'success');
            }
            else{
                $this->return_json(-2000,'','上传失败，请检查目录权限！');
            }
        }
    }

    /**
     * 导出HTML链接进行备份（支持二级分类）
     * 返回结构：
     * [
     *   [
     *     'id'=>1,'name'=>'父分类','links'=>[...],
     *     'children'=>[
     *        ['id'=>2,'name'=>'子分类','links'=>[...]],
     *        ...
     *     ]
     *   ],
     *   ...
     * ]
     */
    public function export_link(){
        //鉴权
        $this->auth($token);
        // 一次性获取所有分类与链接，减少查询次数
        $categories = $this->db->select("on_categorys", [
            "id","name","fid","weight","add_time"
        ]);
        $links = $this->db->select("on_links", [
            "id","fid","title","url","add_time","weight"
        ],[
            "ORDER" => ["weight"=>"DESC","id"=>"DESC"]
        ]);

        // 预分配链接到分类
        $linksByCat = [];
        foreach ($links as $l) {
            $linksByCat[$l['fid']][] = $l;
        }

        // 构建分类映射
        $catMap = [];
        foreach ($categories as $c) {
            $catMap[$c['id']] = [
                'id'        => $c['id'],
                'name'      => $c['name'],
                'fid'       => $c['fid'],
                'links'     => isset($linksByCat[$c['id']]) ? $linksByCat[$c['id']] : [],
                'children'  => []
            ];
        }

        // 组装层级（仅两级：fid=0 为顶级，其它挂到父类 children）
        $tree = [];
        foreach ($catMap as $id => &$node) {
            if ($node['fid'] == 0) {
                $tree[] = &$node;
            } else {
                if (isset($catMap[$node['fid']])) {
                    $catMap[$node['fid']]['children'][] = &$node;
                } else {
                    // 父分类缺失时降级为顶级
                    $tree[] = &$node;
                }
            }
        }
        unset($node);
        return $tree;
    }
    /**
     * name:修改链接
     */
    public function edit_link($token,$id,$fid,$title,$url,$description = '',$weight = 0,$property = 0,$url_standby = '',$font_icon = ''){
        $this->auth($token);
        $fid = intval($fid);
        /**
         * name：获取更新类型
         * description：主要是因为兼容部分之前老的接口，老的接口不用变动，只能从OneNav后台添加图标，因此增加type判断是否是OneNav后台
         * console:指从OneNav后台进行更新
         */
        $type = trim($_GET['type']);
        //检测链接是否合法
        //$this->check_link($fid,$title,$url);
        $this->check_link([
            'fid'           =>  $fid,
            'title'         =>  htmlspecialchars($title,ENT_QUOTES),
            'url'           =>  $url,
            'url_standby'   =>  $url_standby
        ]);
        //查询ID是否存在
        $count = $this->db->count('on_links',[ 'id' => $id]);
        //如果id不存在
        if( (empty($id)) || ($count == false) ) {
            $this->err_msg(-1012,'link id not exists!');
        }
        //合并数据
        $data = [
            'fid'           =>  $fid,
            'title'         =>  htmlspecialchars($title,ENT_QUOTES),
            'url'           =>  $url,
            'url_standby'   =>  $url_standby,
            'description'   =>  htmlspecialchars($description,ENT_QUOTES),
            'up_time'       =>  time(),
            'weight'        =>  $weight,
            'property'      =>  $property
        ];

        if( !empty($font_icon) ) {
            $data['font_icon'] = $font_icon;
        }
        //如果是从OneNav后台更新，则无论如何都要加上font_icon
        if( $type === 'console' ) {
            $data['font_icon'] = $font_icon;
        }
        //插入数据库
        $re = $this->db->update('on_links',$data,[ 'id' => $id]);
        //返回影响行数
        $row = $re->rowCount();
        //如果为真
        if( $row ){
            $id = $this->db->id();
            $data = [
                'code'      =>  0,
                'msg'        =>  'successful'
            ];
            exit(json_encode($data));
        }
        //如果插入失败
        else{
            $this->err_msg(-1011,'The URL already exists!');
        }
    }

    /**
     * name:单行链接修改
     */
    public function edit_link_row(){
        //验证授权
        $this->auth($token);
        
        // 获取POST请求中的JSON数据
        $json_data = file_get_contents('php://input');

        // 解析JSON数据为PHP对象
        $obj = json_decode($json_data);

        $id = intval($obj->id);
        $fid = intval($obj->fid);
        
        //查询ID是否存在
        $count = $this->db->count('on_links',[ 'id' => $id]);
        //如果id不存在
        if( (empty($id)) || ($count == false) ) {
            $this->err_msg(-1012,'link id not exists!');
        }

        // 拼接需要更新的数据
        $data = [
            'title'     =>  trim($obj->title),
            'weight'    =>  intval($obj->weight)
        ];
        
        //插入数据库
        $re = $this->db->update('on_links',$data,[ 'id' => $id]);
        //返回影响行数
        $row = $re->rowCount();
        //如果为真
        if( $row ){
            $id = $this->db->id();
            $data = [
                'code'      =>  0,
                'msg'        =>  'successful'
            ];
            exit(json_encode($data));
        }
        //如果插入失败
        else{
            $this->err_msg(-1011,'The URL already exists!');
        }
    }
    
    /**
     * 删除链接
     */
    public function del_link($token,$id){
        //验证token是否合法
        $this->auth($token);
        //查询ID是否存在
        $count = $this->db->count('on_links',[ 'id' => $id]);
        //如果id不存在
        if( (empty($id)) || ($count == false) ) {
            $this->err_msg(-1010,'link id not exists!');
        }
        else{
            $re = $this->db->delete('on_links',[ 'id' =>  $id] );
            if($re) {
                $data = [
                    'code'  =>  0,
                    'msg'   =>  'successful'
                ];
                exit(json_encode($data));
            }
            else{
                $this->err_msg(-1010,'link id not exists!');
            }
        }
    }
    /**
     * 验证链接合法性
     * 接收一个数组作为参数
     */
    protected function check_link($data){
        $fid = $data['fid'];
        $title = $data['title'];
        $url = $data['url'];
        $url_standby = @$data['url_standby'];

        //如果父及（分类）ID不存在
        if( empty($fid )) {
            $this->err_msg(-1007,'The category id(fid) not exist!');
        }
        //如果父及ID不存在数据库中
        //验证分类目录是否存在
        $count = $this->db->count("on_categorys", [
            "id" => $fid
        ]);
        if ( empty($count) ){
            $this->err_msg(-1007,'The category not exist!');
        }
        //如果链接标题为空
        if( empty($title) ){
            $this->err_msg(-1008,'The title cannot be empty!');
        }
        //链接不能为空
        if( empty($url) ){
            $this->err_msg(-1009,'URL cannot be empty!');
        }
        //通过正则匹配链接是否合法，支持http/https/ftp/magnet:?|ed2k|tcp/udp/thunder/rtsp/rtmp/sftp
        $pattern = "/^(http:\/\/|https:\/\/|ftp:\/\/|ftps:\/\/|magnet:?|ed2k:\/\/|tcp:\/\/|udp:\/\/|thunder:\/\/|rtsp:\/\/|rtmp:\/\/|sftp:\/\/).+/";
        // if( !filter_var($url, FILTER_VALIDATE_URL) ) {
        //     $this->err_msg(-1010,'URL is not valid!');
        // }
        if ( !preg_match($pattern,$url) ) {
            $this->err_msg(-1010,'URL is not valid!');
        }
        //备用链接不合法
        if ( ( !empty($url_standby) ) && ( !preg_match($pattern, $url_standby) ) ) {
            $this->err_msg(-1010,'URL is not valid!');
        }
        return true;
    }
    /**
     * 查询分类目录
     */
    public function category_list($page,$limit){
        $token = @$_POST['token'];
        $offset = ($page - 1) * $limit;

        // 验证登录或 token
        if ($this->is_login() || (!empty($token) && $this->auth($token))) {
            $sql = "SELECT *,(SELECT name FROM on_categorys WHERE id = a.fid LIMIT 1) AS fname,(SELECT COUNT(id) FROM on_links WHERE fid = a.id) AS link_num FROM on_categorys as a ORDER BY fid ASC, weight DESC, id ASC";
            $count = $this->db->count('on_categorys', '*');
        } elseif (!empty($token)) {
            $this->auth($token);
            $sql = "SELECT *,(SELECT name FROM on_categorys WHERE id = a.fid LIMIT 1) AS fname,(SELECT COUNT(id) FROM on_links WHERE fid = a.id) AS link_num FROM on_categorys as a ORDER BY fid ASC, weight DESC, id ASC";
            $count = $this->db->count('on_categorys', '*');
        } else {
            $sql = "SELECT *,(SELECT name FROM on_categorys WHERE id = a.fid LIMIT 1) AS fname,(SELECT COUNT(id) FROM on_links WHERE fid = a.id) AS link_num FROM on_categorys as a WHERE property = 0 ORDER BY fid ASC, weight DESC, id ASC";
            $count = $this->db->count('on_categorys', '*', ['property' => 0]);
        }

        $all_categories = $this->db->query($sql)->fetchAll();

        // 构建排序规则
        $sorted_categories = [];
        $categories_by_fid = [];

        foreach ($all_categories as $category) {
            if ($category['fid'] == 0) {
                // 父级分类
                $sorted_categories[] = $category;
            } else {
                // 子分类，按 fid 分组
                $categories_by_fid[$category['fid']][] = $category;
            }
        }

        // 对每个子分类组按 weight 排序
        foreach ($categories_by_fid as &$sub_categories) {
            usort($sub_categories, function ($a, $b) {
                return $b['weight'] <=> $a['weight'];
            });
        }

        // 将子分类插入到对应父级分类后
        $final_sorted_categories = [];
        foreach ($sorted_categories as $parent_category) {
            $final_sorted_categories[] = $parent_category;
            if (isset($categories_by_fid[$parent_category['id']])) {
                $final_sorted_categories = array_merge($final_sorted_categories, $categories_by_fid[$parent_category['id']]);
            }
        }

        // 截取分页数据
        $paged_data = array_slice($final_sorted_categories, $offset, $limit);

        $result = [
            'code' => 0,
            'msg' => '',
            'count' => $count,
            'data' => $paged_data,
        ];

        exit(json_encode($result));
    }
    /**
     * 生成
     */
    public function create_sk() {
        //验证是否登录
        $this->auth('');
        $sk = md5(USER.USER.time());
        
        $result = $this->set_option_bool('SecretKey',$sk);
        if( $result ){
            $datas = [
                'code'      =>  0,
                'data'      =>  $sk
            ];
            exit(json_encode($datas));
        }
        else{
            $this->err_msg(-2000,'SecretKey生成失败！');
        }
        
    }
    /**
     * 查询链接
     * 接收一个数组作为参数
     */
    public function link_list($data){
        $limit = $data['limit'];
        $token = $data['token'];
        $offset = ($data['page'] - 1) * $data['limit'];
        //$fid = @$data['category_id'];
        $count = $this->db->count('on_links','*');
        
        //如果成功登录，但token为空，获取所有
        if( $this->is_login() || ( !empty($token) && $this->auth($token) ) ){
            $sql = "SELECT *,(SELECT name FROM on_categorys WHERE id = on_links.fid) AS category_name FROM on_links ORDER BY weight DESC,id DESC LIMIT {$limit} OFFSET {$offset}";
        }
        
        //如果token验证通过
        elseif( (!empty($token)) && ($this->auth($token)) ) {
            $sql = "SELECT *,(SELECT name FROM on_categorys WHERE id = on_links.fid) AS category_name FROM on_links ORDER BY weight DESC,id DESC LIMIT {$limit} OFFSET {$offset}";
        }
        //如果通过header传递的token验证成功，则获取所有
        // else if( $this->auth("") === TRUE ) {
        //     $sql = "SELECT *,(SELECT name FROM on_categorys WHERE id = on_links.fid) AS category_name FROM on_links ORDER BY weight DESC,id DESC LIMIT {$limit} OFFSET {$offset}";
        // }
        //如果即没有登录成功，又没有token，则默认为游客,游客查询链接属性为公有，分类为公有，不查询私有
        else{
            $c_sql = "SELECT COUNT(*) AS num FROM on_links WHERE property = 0 AND fid IN (SELECT id FROM on_categorys WHERE property = 0)";
            $count = $this->db->query($c_sql)->fetchAll()[0]['num'];
            $count = intval($count);
            
            $sql = "SELECT *,(SELECT name FROM on_categorys WHERE id = on_links.fid) AS category_name FROM on_links WHERE property = 0 AND fid IN (SELECT id FROM on_categorys WHERE property = 0) ORDER BY weight DESC,id DESC LIMIT {$limit} OFFSET {$offset}";
        }

       
        //原生查询
        $datas = $this->db->query($sql)->fetchAll();
        $datas = [
            'code'      =>  0,
            'msg'       =>  '',
            'count'     =>  $count,
            'data'      =>  $datas
        ];
        exit(json_encode($datas));
    }
    /**
     * 查询某个分类下面的链接
     * 接收一个数组作为参数
     */
    public function q_category_link($data){
        $limit = $data['limit'];
        $token = $data['token'];
        $offset = ($data['page'] - 1) * $data['limit'];
        $fid = @$data['category_id'];
        //$fid = @$data['category_id'];
        $count = $this->db->count('on_links','*',[
            'fid'   =>  $fid
        ]);

        //如果FID是空的，则直接终止
        if( empty($fid) ) {
            $datas = [
                'code'      =>  -2000,
                'msg'       =>  '分类ID不能为空！',
                'count'     =>  0,
                'data'      =>  []
            ];
            exit(json_encode($datas));
        }
        
        //如果成功登录，但token为空
        if( ($this->is_login()) && (empty($token)) ){
            $sql = "SELECT *,(SELECT name FROM on_categorys WHERE id = on_links.fid) AS category_name  FROM on_links WHERE fid = $fid ORDER BY weight DESC,id DESC LIMIT {$limit} OFFSET {$offset}";
        }
        //通过header获取token成功
        // else if( $this->auth("") ) {
        //     $sql = "SELECT *,(SELECT name FROM on_categorys WHERE id = on_links.fid) AS category_name  FROM on_links WHERE fid = $fid ORDER BY weight DESC,id DESC LIMIT {$limit} OFFSET {$offset}";
        // }
        
        //如果token验证通过
        elseif( (!empty($token)) && ($this->auth($token)) ) {
            $sql = "SELECT *,(SELECT name FROM on_categorys WHERE id = on_links.fid) AS category_name FROM on_links WHERE fid = $fid ORDER BY weight DESC,id DESC LIMIT {$limit} OFFSET {$offset}";
        }
        //如果即没有登录成功，又没有token，则默认为游客,游客查询链接属性为公有，分类为公有，不查询私有
        else{
            $c_sql = "SELECT COUNT(*) AS num FROM on_links WHERE property = 0 AND fid = $fid";
            $count = $this->db->query($c_sql)->fetchAll()[0]['num'];
            $count = intval($count);
            
            $sql = "SELECT *,(SELECT name FROM on_categorys WHERE id = on_links.fid) AS category_name FROM on_links WHERE property = 0 AND fid = $fid ORDER BY weight DESC,id DESC LIMIT {$limit} OFFSET {$offset}";
        }

       
        //原生查询
        $datas = $this->db->query($sql)->fetchAll();
        $datas = [
            'code'      =>  0,
            'msg'       =>  '',
            'count'     =>  $count,
            'data'      =>  $datas
        ];
        exit(json_encode($datas));
    }
    /**
     * 查询单个链接
     * 此函数接收一个数组
     */
    public function get_a_link($data) {
        $id = $data['id'];
        $token = $data['token'];
        $link_info = $this->db->get("on_links","*",[
            "id"    =>  $id
        ]);
        //打印链接信息
        //var_dump($link_info);
        //如果是公开链接，则直接返回
        if ( $link_info['property'] == "0" ) {
            //链接是公开的，但是分类是私有的，则不显示
            $category_property = $this->db->get("on_categorys","property",[
                "id"    =>  $link_info['fid']
            ]);
            $category_property = intval($category_property);
            //分类属性为1，则说明是私有链接，则未认证用户不允许查询
            if( $category_property === 1 ){
                //进行认证
                $this->auth($token);
            }
            $datas = [
                'code'      =>  0,
                'data'      =>  $link_info
            ];
            
        }
        //如果是私有链接，并且认证通过
        elseif( $link_info['property'] == "1" ) {
            if ( ( $this->auth($token) ) || ( $this->is_login() ) ) {
                $datas = [
                    'code'      =>  0,
                    'data'      =>  $link_info
                ];
            }
            
            //exit(json_encode($datas));
        }
        //如果是其它情况，则显示为空
        else{
            $datas = [
                'code'      =>  0,
                'data'      =>  []
            ];
            //exit(json_encode($datas));
        }
        exit(json_encode($datas));
    }
    /**
     * 查询单个分类信息
     * 此函数接收一个数组
     */
    public function get_a_category($data) {
        $id = $data['id'];
        $token = $data['token'];

        $category_info = $this->db->get("on_categorys","*",[
            "id"    =>  $id
        ]);

        //var_dump($category_info);

        //如果是公开分类，则直接返回
        if ( $category_info['property'] == "0" ) {
            $datas = [
                'code'      =>  0,
                'data'      =>  $category_info
            ];
            
        }
        //如果是私有链接，并且认证通过
        elseif( $category_info['property'] == "1" ) {
            if ( ( $this->auth($token) ) || ( $this->is_login() ) ) {
                $datas = [
                    'code'      =>  0,
                    'data'      =>  $category_info
                ];
            }
            
            //exit(json_encode($datas));
        }
        //如果是其它情况，则显示为空
        else{
            $datas = [
                'code'      =>  0,
                'data'      =>  []
            ];
            //exit(json_encode($datas));
        }
        exit(json_encode($datas));
    }
    /**
     * 验证是否登录
     */
    protected function is_login(){
        $key = md5(USER.ENCRYPTED_PASSWORD.'onenav'.$_SERVER['HTTP_USER_AGENT']);
        //获取session
        $session = $_COOKIE['key'];
        //如果已经成功登录
        if($session == $key) {
            return true;
        }
        else{
            return false;
        }
    }
    /**
         * 获取链接信息
         */
        /**
     * 获取链接的标题和描述信息 (优化版)
     *
     * @param string $token 用于认证的令牌
     * @param string $url   需要获取信息的URL
     * @return void         直接输出JSON格式的返回结果
     */
    public function get_link_info($token, $url)
    {
        $this->auth($token);

        // 步骤 1: 验证URL的有效性
        if (empty($url)) {
            $this->err_msg(-2000, 'URL不能为空!');
        }
        // 使用 filter_var 和正则表达式进行双重验证，确保是有效的http/https链接
        if (!filter_var($url, FILTER_VALIDATE_URL) || !preg_match("/^https?:\/\//i", $url)) {
            $this->err_msg(-1010, '只支持识别http/https协议的链接!');
        }

        // 初始化返回数据结构，确保即使获取失败也有完整的字段
        $link = [
            'title'       => '',
            'description' => ''
        ];

        // 步骤 2: 使用cURL获取网页内容（已优化）
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        // 返回内容但不直接输出
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        // 获取HTTP头信息，用于编码检测
        curl_setopt($ch, CURLOPT_HEADER, 1);
        // 自动跟随重定向
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        // 最大重定向次数
        curl_setopt($ch, CURLOPT_MAXREDIRS, 5);
        // 连接超时时间（秒）
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
        // 总执行超时时间（秒）
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        // 设置浏览器User Agent，防止被部分网站屏蔽
        curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/108.0.0.0 Safari/537.36');
        // 兼容https，原逻辑保留，但注意在生产环境中这可能带来安全风险
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        // 性能优化：只下载前500KB内容，通常足以获取head信息
        curl_setopt($ch, CURLOPT_RANGE, '0-512000');
        
        $response = curl_exec($ch);

        // 增加cURL错误处理
        if (curl_errno($ch)) {
            $this->err_msg(-2001, '链接无法访问或超时: ' . curl_error($ch));
        }

        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        // 增加HTTP状态码判断
        if ($http_code < 200 || $http_code >= 400) {
            $this->err_msg(-2002, '链接返回无效的HTTP状态码: ' . $http_code);
        }

        // 分离响应头和响应体
        $header_size = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        $header_str = substr($response, 0, $header_size);
        $body = substr($response, $header_size);
        
        curl_close($ch);

        // 如果响应体为空，直接返回空信息
        if (empty($body)) {
            exit(json_encode(['code' => 0, 'data' => $link]));
        }

        // 步骤 3: 智能检测编码并转换为UTF-8
        $encoding = '';
        // 方法A: 从HTTP响应头中提取编码
        if (preg_match('/charset=([\w-]+)/i', $header_str, $matches)) {
            $encoding = strtoupper($matches[1]);
        }
        // 方法B: 如果响应头没有，则从HTML的meta标签中提取
        if (empty($encoding) && preg_match('/<meta.*?charset=["\']?([\w-]+)["\']?/i', $body, $matches)) {
            $encoding = strtoupper($matches[1]);
        }
        // 方法C: 如果以上都没有，则自动检测
        if (empty($encoding)) {
            $encoding = mb_detect_encoding($body, ['UTF-8', 'GBK', 'GB2312', 'BIG5', 'ISO-8859-1'], true);
        }
        // 如果检测到编码且不是UTF-8，则进行转换
        if ($encoding && $encoding !== 'UTF-8') {
            $body = @mb_convert_encoding($body, 'UTF-8', $encoding);
        }

        // 步骤 4: 使用DOMDocument解析HTML，更稳定可靠
        $doc = new DOMDocument();
        // 抑制因HTML不规范而产生的警告
        libxml_use_internal_errors(true);
        // 明确告知DOMDocument以UTF-8编码加载，防止二次乱码
        $doc->loadHTML('<?xml encoding="UTF-8">' . $body);
        // 清理错误缓存
        libxml_clear_errors();
        
        $xpath = new DOMXPath($doc);

        // 步骤 5: 提取标题（带后备方案）
        // 方案1: 获取 <title> 标签
        $title_node = $xpath->query('//title')->item(0);
        if ($title_node) {
            $link['title'] = trim($title_node->nodeValue);
        }
        // 方案2: 获取 og:title
        if (empty($link['title'])) {
            $og_title_node = $xpath->query('//meta[@property="og:title"]/@content')->item(0);
            if ($og_title_node) {
                $link['title'] = trim($og_title_node->nodeValue);
            }
        }
        // 方案3: 获取第一个 <h1> 标签
        if (empty($link['title'])) {
            $h1_node = $xpath->query('//h1')->item(0);
            if ($h1_node) {
                $link['title'] = trim($h1_node->nodeValue);
            }
        }

        // 步骤 6: 提取描述（带后备方案）
        // 方案1: 获取 <meta name="description">
        $desc_node = $xpath->query('//meta[@name="description"]/@content')->item(0);
        if ($desc_node) {
            $link['description'] = trim($desc_node->nodeValue);
        }
        // 方案2: 获取 og:description
        if (empty($link['description'])) {
            $og_desc_node = $xpath->query('//meta[@property="og:description"]/@content')->item(0);
            if ($og_desc_node) {
                $link['description'] = trim($og_desc_node->nodeValue);
            }
        }
        // 方案3: 获取第一个有意义的 <p> 标签内容
        if (empty($link['description'])) {
            $p_nodes = $xpath->query('//p');
            foreach ($p_nodes as $p_node) {
                $p_text = trim($p_node->nodeValue);
                // 选取一个长度较长的段落作为描述
                if (mb_strlen($p_text, 'UTF-8') > 30) {
                    $link['description'] = $p_text;
                    break;
                }
            }
        }
        
        // 对获取到的结果进行HTML实体解码，使显示更友好
        if($link['title']) {
            $link['title'] = html_entity_decode($link['title'], ENT_QUOTES | ENT_HTML5, 'UTF-8');
        }
        if($link['description']) {
            $link['description'] = html_entity_decode($link['description'], ENT_QUOTES | ENT_HTML5, 'UTF-8');
        }

        // 步骤 7: 按指定格式返回成功结果
        $data = [
            'code'    =>  0,
            'data'    =>  $link
        ];
        exit(json_encode($data));
    }
    /**
     * 自定义js
     */
    public function add_js($token,$content){
        $this->auth($token);
        //如果内容为空
        // if( $content == '' ){
        //     $this->err_msg(-1013,'The content cannot be empty!');
        // }
        //写入文件
        try{
            file_put_contents("data/extend.js",$content);
            $data = [
                'code'      =>  0,
                'data'      =>  'success'
            ];
            exit(json_encode($data));
        }
        catch(Exception $e){
            $this->err_msg(-2000,$e->getMessage());
        }
    }
    /**
     * 获取IP
     */
    //获取访客IP
    protected function getIP() { 
    if (getenv('HTTP_CLIENT_IP')) { 
    $ip = getenv('HTTP_CLIENT_IP'); 
    } 
    elseif (getenv('HTTP_X_FORWARDED_FOR')) { 
        $ip = getenv('HTTP_X_FORWARDED_FOR'); 
    } 
        elseif (getenv('HTTP_X_FORWARDED')) { 
        $ip = getenv('HTTP_X_FORWARDED'); 
    } 
    elseif (getenv('HTTP_FORWARDED_FOR')) { 
    $ip = getenv('HTTP_FORWARDED_FOR'); 
    } 
    elseif (getenv('HTTP_FORWARDED')) { 
    $ip = getenv('HTTP_FORWARDED'); 
    } 
    else { 
        $ip = $_SERVER['REMOTE_ADDR']; 
    } 
        return $ip; 
    } 

    /**
     * name:检查弱密码
     */
    public function check_weak_password($token){
        $this->auth($token);
        //如果用户名、密码为初始密码，则提示修改
        if ( ( USER == 'xiaoz' ) && ( PASSWORD == 'xiaoz.me' ) ) {
            $this->err_msg(-1,'Weak password!');
        }
    }
    /**
     * 获取SQL更新列表
     * 循环读取db/sql/目录下的.sql文件
     */
    public function get_sql_update_list($data) {
        //鉴权
        if( !$this->is_login() ) {
            $this->err_msg(-1002,'Authorization failure!');
        }
        //待更新的数据库文件目录
        $sql_dir = 'db/sql/';
        //待更新的sql文件列表，默认为空
        $sql_files_all = [];
        //打开一个目录，读取里面的文件列表
        if (is_dir($sql_dir)){
            if ($dh = opendir($sql_dir)){
                while (($file = readdir($dh)) !== false){
                //排除.和..
                if ( ($file != ".") && ($file != "..") ) {
                    array_push($sql_files_all,$file);

                }
            }
                //关闭句柄
                closedir($dh);
            }
        }
        //判断数据库日志表是否存在
        $sql = "SELECT count(*) AS num FROM sqlite_master WHERE type='table' AND name='on_db_logs'";
        //查询结果
        $q_result = $this->db->query($sql)->fetchAll();
        //如果数量为0，则说明on_db_logs这个表不存在，需要提前导入
        $num = intval($q_result[0]['num']);
        if ( $num === 0 ) {
            $data = [
                "code"      =>  0,
                "data"      =>  ['on_db_logs.sql']
            ];
            exit(json_encode($data));
        }else{
            //如果不为0，则需要查询数据库更新表里面的数据进行差集比对
            $get_on_db_logs = $this->db->select("on_db_logs",[
                "sql_name"
            ],[
                "status"    =>  "TRUE"
            ]);
            //声明一个空数组，存储已更新的数据库列表
            $already_dbs = [];
            foreach ($get_on_db_logs as $value) {
                array_push($already_dbs,$value['sql_name']);
            }
            
            //array_diff() 函数返回两个数组的差集数组
            $diff_result = array_diff($sql_files_all,$already_dbs);
            //去掉键
            $diff_result = array_values($diff_result);
            sort($diff_result);
            
            $data = [
                "code"      =>  0,
                "data"      =>  $diff_result
            ];
            exit(json_encode($data));
        }

    }
    /**
     * 执行SQL更新语句，只执行单条更新
     */
    public function exe_sql($data) {
        //鉴权
        if( !$this->is_login() ) {
            $this->err_msg(-1002,'Authorization failure!');
        }
        //数据库sql目录
        $sql_dir = 'db/sql/';
        $name = $data['name'];
        //查询sql是否已经执行过
        $count = $this->db->count("on_db_logs",[
            "sql_name"  =>  $name
        ]);
        if( $count >= 1 ) {
            $this->err_msg(-2000,$name."已经更新过！");
        }
        $sql_name = $sql_dir.$name;
        //如果文件不存在，直接返回错误
        if ( !file_exists($sql_name) ) {
            $this->err_msg(-2000,$name.'不存在!');
        }
        //读取需要更新的SQL内容
        try {
            //读取一个SQL文件，并将单个SQL文件拆分成单条SQL语句循环执行
            switch ($name) {
                case '20220414.sql':
                    $sql_content = explode("\n",file_get_contents($sql_name));
                    break;
                default:
                    $sql_content = explode(';',file_get_contents($sql_name));
                    break;
            }
            
            //计算SQL总数
            $num = count($sql_content) - 1;
            //初始数量设置为0
            $init_num = 0;
            //遍历执行SQL语句
            foreach ($sql_content as $sql) {
                //如果SQL为空，则跳过此次循环不执行
                if( empty($sql) ) {
                    continue;
                }
                $result = $this->db->query($sql);
                //只要单条SQL执行成功了就增加初始数量
                if( $result ) {
                    $init_num++;
                }
            }

            //无论最后结果如何，都将更新信息写入数据库
            $insert_re = $this->db->insert("on_db_logs",[
                "sql_name"      =>  $name,
                "update_time"   =>  time(),
                "status"        =>  "TRUE"
            ]);
            if( $insert_re ) {
                $data = [
                    "code"      =>  0,
                    "data"      =>  $name."更新完成！总数${num}，成功：${init_num}"
                ];
                exit(json_encode($data));
            }
            else {
                $this->err_msg(-2000,$name."更新失败，请人工检查！");
            }
            
        } catch(Exception $e){
            $this->err_msg(-2000,$e->getMessage());
        }
    }
    /**
     * 保存主题参数
     */
    public function save_theme_config($data) {
        $this->auth($token);
        //获取主题名称
        $name = $data['name'];
        //获取config参数,是一个对象
        $config = $data['config'];
        
        //获取主题配置文件config.json
        if ( is_dir("templates/".$name) ) {
            $config_file = "templates/".$name."/config.json";
        }
        else{
            $config_file = "data/templates/".$name."/config.json";
        }
        
        $config_content = json_encode($config,JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT);
        //写入配置
        try {
            $re = @file_put_contents($config_file,$config_content);
            $this->return_json(0,"success");
        } catch (\Throwable $th) {
            $this->err_msg(-2000,"写入配置失败！");
        }
    }
    /**
     * 删除主题
     */
    public function delete_theme($name) {
        //验证授权
        $this->auth($token);
        //正则判断主题名称是否合法
        $pattern = "/^[a-zA-Z0-9][a-zA-Z0-9-_]+[a-zA-Z0-9]$/";
        if ( !preg_match($pattern,$name) ) {
            $this->return_json(-2000,'',"主题名称不合法！");
        }
        //如果是默认主题，则不允许删除
        if( ($name === 'default') || ($name === 'admin') || ($name === 'default2') ) {
            $this->return_json(-2000,'',"默认主题不允许删除！");
        }
        //查询当前使用中的主题
        $current_theme = $this->db->get('on_options','value',[ 'key'  =>  "theme" ]);
        //如果是当前使用中的主题也不允许删除
        if ( $current_theme == $name ) {
            $this->return_json(-2000,'',"使用中的主题不允许删除！");
        }
        //删除主题
        $this->deldir("templates/".$name);
        
        $this->deldir("data/templates/".$name);
        //判断主题文件夹是否还存在
        if( is_dir("templates/".$name) || is_dir("data/templates/".$name) ) {
            $this->return_json(-2000,'',"删除失败，可能是权限不足！");
        }
        else{
            $this->return_json(200,'',"主题已删除！");
        }
    }
    /**
     * 删除一个目录
     */
    protected function deldir($dir) {
        //先删除目录下的文件：
        $dh=opendir($dir);
        while ($file=readdir($dh)) {
          if($file!="." && $file!="..") {
            $fullpath=$dir."/".$file;
            if(!is_dir($fullpath)) {
                unlink($fullpath);
            } else {
                $this->deldir($fullpath);
            }
          }
        }
       
        closedir($dh);
        //删除当前文件夹：
        if(rmdir($dir)) {
          return true;
        } else {
          return false;
        }
    }
    /**
     * 获取主题参数
     */
    public function get_theme_config() {
        $template = $this->db->get("on_options","value",[
            "key"   =>  "theme"
        ]);
        //获取主题配置信息
        //获取主题配置
        if( file_exists("templates/".$template."/config.json") ) {
            $config_file = "templates/".$template."/config.json";
        }
        else if( file_exists("data/templates/".$template."/config.json") ) {
            $config_file = "data/templates/".$template."/config.json";
        }
        else if( file_exists("templates/".$template."/info.json") ) {
            $config_file = "templates/".$template."/info.json";
        }
        else {
            $config_file = "data/templates/".$template."/info.json";
        }

        //读取主题配置
        $config_content = @file_get_contents($config_file);
        
        //如果是info.json,则特殊处理下
        if ( strstr($config_file,"info.json") ) {
            $config_content = json_decode($config_content);
            $theme_config = $config_content->config;
        }
        else{
            $theme_config = $config_content;
            $theme_config = json_decode($theme_config);
        }
        
        $this->return_json(200,$theme_config,"");
    }

    // 获取设置的主题
    public function get_themes() {
        $theme = $this->db->get("on_options","value",[
            "key"   =>  "s_themes"
        ]);
        // 字符串转换为数组
        $theme = json_decode($theme, true);
        //如果主题不存在，则返回默认主题
        if( empty($theme) ) {
            $theme = [
                "pc_theme"   =>  "default2",
                "mobile_theme"   =>  "default2"
            ];
        }
        $this->return_json(200,$theme,"");
    }
    /**
     * 通用json消息返回
     */
    public function return_json($code,$data,$msg = "") {
        $return = [
            "code"  =>  intval($code),
            "data"  =>  $data,
            "msg"   =>  $msg
        ];
        exit(json_encode($return));
    }
    /**
     * 更新option
     */
    public function set_option($key,$value = '') {
        // 验证授权
        $this->auth($token);
        $key = htmlspecialchars(trim($key));
        //如果key是空的
        if( empty($key) ) {
            $this->err_msg(-2000,'键不能为空！');
        }
        

        $count = $this->db->count("on_options", [
            "key" => $key
        ]);
        
        //如果数量是0，则插入，否则就是更新
        if( $count === 0 ) {
            try {
                $this->db->insert("on_options",[
                    "key"   =>  $key,
                    "value" =>  $value
                ]);
                $data = [
                    "code"      =>  0,
                    "data"      =>  "设置成功！"
                ];
                exit(json_encode($data));
            } catch (\Throwable $th) {
                $this->err_msg(-2000,$th);
            }
        }
        //更新数据
        else if( $count === 1 ) {
            try {
                $this->db->update("on_options",[
                    "value"     =>  $value
                ],[
                    "key"       =>  $key
                ]);
                $data = [
                    "code"      =>  0,
                    "data"      =>  "设置已更新！"
                ];
                exit(json_encode($data));
            } catch (\Throwable $th) {
                $this->err_msg(-2000,$th);
            }
        }

    }
    /**
     * 新的设置接口
     */
    public function new_set_option(){
        // 验证授权
        $this->auth($token);
        // 获取key
        $key = trim(@$_POST['key']);
        // 获取value
        $value = trim(@$_POST['value']);
        $key = htmlspecialchars(trim($key));
        //如果key是空的
        if( empty($key) ) {
            $this->return_json(-2000,'','key不能为空！');
        }
        

        $count = $this->db->count("on_options", [
            "key" => $key
        ]);
        
        //如果数量是0，则插入，否则就是更新
        if( $count === 0 ) {
            try {
                $this->db->insert("on_options",[
                    "key"   =>  $key,
                    "value" =>  $value
                ]);
                $this->return_json(200,'','设置成功！');
            } catch (\Throwable $th) {
                $this->return_json(-2000,'','设置失败！');
            }
        }
        //更新数据
        else if( $count === 1 ) {
            try {
                $this->db->update("on_options",[
                    "value"     =>  $value
                ],[
                    "key"       =>  $key
                ]);
                
                $this->return_json(200,'','设置已更新！');
            } catch (\Throwable $th) {
                $this->return_json(-2000,'','设置失败！');
            }
        }
    }
    /**
     * 更新option，返回BOOL值
     */
    protected function set_option_bool($key,$value = '') {
        $key = htmlspecialchars(trim($key));
        //如果key是空的
        if( empty($key) ) {
            return FALSE;
        }

        $count = $this->db->count("on_options", [
            "key" => $key
        ]);
        
        //如果数量是0，则插入，否则就是更新
        if( $count === 0 ) {
            try {
                $this->db->insert("on_options",[
                    "key"   =>  $key,
                    "value" =>  $value
                ]);
                $data = [
                    "code"      =>  0,
                    "data"      =>  "设置成功！"
                ];
                return TRUE;
            } catch (\Throwable $th) {
                return FALSE;
            }
        }
        //更新数据
        else if( $count === 1 ) {
            try {
                $this->db->update("on_options",[
                    "value"     =>  $value
                ],[
                    "key"       =>  $key
                ]);
                $data = [
                    "code"      =>  0,
                    "data"      =>  "设置已更新！"
                ];
                return TRUE;
            } catch (\Throwable $th) {
                return FALSE;
            }
        }

    }
    /**
     * 用户状态
     */
    public function check_login($token){
        $re = $this->auth($token);
        
        if( $re ) {
            $this->return_json(200,"true","success");
        }
    }
    /**
     * 验证订阅是否有效
     */
    public function check_subscribe() {
        //验证token是否合法
        $this->auth($token);
        //获取订阅信息
        //获取当前站点信息
        $subscribe = $this->db->get('on_options','value',[ 'key'  =>  "s_subscribe" ]);
        $domain = $_SERVER['HTTP_HOST'];
    
        $subscribe = unserialize($subscribe);
        //api请求地址
        $api_url = API_URL."/v1/check_subscribe.php?order_id=".$subscribe['order_id']."&email=".$subscribe['email']."&domain=".$domain;

        // 如果邮箱或者订单号为空，则返回提示
        if( empty($subscribe['order_id']) || empty($subscribe['email']) ) {
            $this->return_json(-2000,'','此功能需要订阅！');
        }
        
        try {
            #GET HTTPS
            $curl = curl_init($api_url);
            #设置useragent
            curl_setopt($curl, CURLOPT_USERAGENT, "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/102.0.5005.63 Safari/537.36");
            curl_setopt($curl, CURLOPT_FAILONERROR, true);
            curl_setopt($curl, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);
            #设置超时时间，最小为1s（可选）
            curl_setopt($curl , CURLOPT_TIMEOUT, 30);

            $html = curl_exec($curl);
            curl_close($curl);
            //解析json
            $data = json_decode($html);
            //var_dump($data->data->end_time);
            //echo strtotime($data->data->end_time);
            //var_dump($data->code);
            //如果状态码返回200，并且订阅没有到期
            if( (intval($data->code) === 200) && ( $data->data->end_time > ( strtotime( date("Y-m-d",time()) ) )) ) {
                $this->return_json(200,$data->data,'success');
            }
            else if( intval($data->code === -1000 ) ) {
                $this->return_json(-2000,'',$data->msg);
            }
            else{
                $this->return_json(-2000,'',"请求接口失败，请重试！");
            }
        } catch (\Throwable $th) {
            $this->return_json(-2000,'','网络请求失败，请重试！');
        }
    }
    /**
     * 下载主题
     */
    public function down_theme($data) {
        //主题名称
        $name = $data['name'];
        //key-value
        $key = $data['key'];
        $value = $data['value'];
        //拼接主题URL
        $url = API_URL."/v1/down_theme.php?name=${name}&key=${key}&value=${value}";
        //验证token是否合法
        $this->auth($token);
        //检查主题是否已经存在
        if ( $data['type'] == 'download' ) {
            $theme1 = "templates/".$name;
            $theme2 = "data/templates/".$name;

            if( is_dir($theme1) || is_dir($theme2) ) {
                $this->return_json(-2000,'','主题已存在，无需重复下载！');
            }
        }
        //如果返回404状态
        $res = get_headers($url,1);
        if( strstr($res[0],'404') ) {
            $this->return_json(-2000,'','远程服务器上不存在此主题！');
        }
        //判断主题目录是否存在,如果curl_host是alpine，则视为容器，容器则将主题目录设置为data/templates
        $curl_host = curl_version()['host'];
        if( strstr($curl_host,'alpine') ) {
            // 默认主题一律保存到templates目录
            if( $name == "default2" ) {
                $theme_dir = "templates";
            }
            else{
                $theme_dir = "data/templates";
            }
        }
        else{
            $theme_dir = "templates";
        }
        //主题完整压缩包路径
        $file_name = $theme_dir."/${name}.tar.gz";
        if( !is_dir($theme_dir) ) {
            mkdir($theme_dir,0755);
        }
        
        //尝试下载主题
        try {
            //下载主题，并设置超时时间为120s
            $content = $this->curl_get($url,120);
            //写入主题
            $re = file_put_contents($theme_dir."/${name}.tar.gz",$content);
            //如果写入主题失败了，说明权限不粗糙
            if( !$re ) {
                $this->return_json(-2000,'','主题写入失败，请检查目录权限！');
            }
            else{
                //解压文件
                $phar = new PharData($file_name);
                //路径 要解压的文件 是否覆盖
                $phar->extractTo($theme_dir."/${name}", null, true);
                //删除主题
                unlink($file_name);
                $this->return_json(200,'','主题下载成功！');
            }

        } catch (\Throwable $th) {
            $this->return_json(-2000,'','主题下载失败，请检查目录权限！');
        }
        finally{
            unlink($file_name);
        }

    }
    /**
     * 验证订阅是否存在
     */
    public function is_subscribe() {
        //获取订阅SESSION状态
        session_start();
        //获取session订阅状态
        $is_subscribe = $_SESSION['subscribe'];
        //如果订阅是空的，则请求接口获取订阅状态
        if ( !isset($is_subscribe) ) {
            //获取当前站点信息
            $subscribe = $this->db->get('on_options','value',[ 'key'  =>  "s_subscribe" ]);
            $domain = $_SERVER['HTTP_HOST'];
        
            $subscribe = unserialize($subscribe);
            //api请求地址
            $api_url = API_URL."/v1/check_subscribe.php?order_id=".$subscribe['order_id']."&email=".$subscribe['email']."&domain=".$domain;
            // echo $api_url;
            try {
                #GET HTTPS
                $curl = curl_init($api_url);
                #设置useragent
                curl_setopt($curl, CURLOPT_USERAGENT, "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/102.0.5005.63 Safari/537.36");
                curl_setopt($curl, CURLOPT_FAILONERROR, true);
                curl_setopt($curl, CURLOPT_FOLLOWLOCATION, true);
                curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
                curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);
                #设置超时时间，最小为1s（可选）
                curl_setopt($curl , CURLOPT_TIMEOUT, 30);
    
                $html = curl_exec($curl);
                curl_close($curl);
                //解析json
                $data = json_decode($html);
                //var_dump($data->data->end_time);
                //echo strtotime($data->data->end_time);
                //var_dump($data->code);
                //如果状态码返回200，并且订阅没有到期
                if( (intval($data->code) === 200) && ( $data->data->end_time > ( strtotime( date("Y-m-d",time()) ) )) ) {
                    $_SESSION['subscribe'] = TRUE;
                    return TRUE;
                }
                else if( intval($data->code === -1000 ) ) {
                    $_SESSION['subscribe'] = FALSE;
                    return FALSE;
                }
                else{
                    $_SESSION['subscribe'] = NULL;
                }
            } catch (\Throwable $th) {
                $_SESSION['subscribe'] = NULL;
            }
        }
        if( $is_subscribe == TRUE ) {
            return TRUE;
        }
        else{
            return FALSE;
        }
    }
    /**
     * name:验证订阅，订阅不存在，则阻止
     */
    public function check_is_subscribe(){
        $result = $this->is_subscribe();

        if( $result === FALSE ) {
            $this->return_json(-2000,'','该功能需要订阅后才能使用！');
        }
        else if( $result === TRUE ) {
            return TRUE;
        }
        else{
            $this->return_json(-2000,'','该功能需要订阅后才能使用！');
        }
    }
    /**
     * 无脑下载更新程序
     */
    public function down_updater() {
        $url = API_URL."/update.tar.gz";
        // echo $url;
        // exit;
        try {
            //检查本地是否存在更新程序
            $curl = curl_init($url);
            #设置useragent
            curl_setopt($curl, CURLOPT_USERAGENT, "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/102.0.5005.63 Safari/537.36");
            curl_setopt($curl, CURLOPT_FAILONERROR, true);
            curl_setopt($curl, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);
            #设置超时时间，最小为1s（可选）
            curl_setopt($curl , CURLOPT_TIMEOUT, 60);

            $html = curl_exec($curl);
            curl_close($curl);
            //var_dump($html);
            //return $html;
            //写入文件
            file_put_contents("update.tar.gz",$html);
            //解压覆盖文件
            //解压文件
            $phar = new PharData('update.tar.gz');
            //路径 要解压的文件 是否覆盖
            $phar->extractTo('./', null, true);
            return TRUE;
        } catch (\Throwable $th) {
            $this->return_json(-2000,"","更新程序下载失败！");
        }
        finally{
            //再次判断更新程序是否存在
            if( is_file("update.php") ) {
                //判断是否大约0
                $file_size = filesize("update.php");
                if( $file_size < 100 ) {
                    $this->return_json(-2000,"","更新程序异常，请检查目录权限！");
                }
                else{
                    return TRUE;
                }
            }
            else{
                $this->return_json(-2000,"","更新程序下载失败，请检查目录权限！");
            }
        }
    }
    /**更新升级程序 */
    public function up_updater() {
        
        //如果不存在，则下载更新程序
        if( !is_file("update.php") ) {
            if ( $this->down_updater() ) {
                $this->return_json(200,"","更新程序准备就绪！");
            }

        }
        //如果存在更新程序，验证大小，大小不匹配时进行更新
        if( is_file("update.tar.gz") ) {
            //获取header头
            $header = get_headers(API_URL."/update.tar.gz",1);
            $lentgh = $header['Content-Length'];
            //获取文件大小
            $file_size = filesize("update.tar.gz");
            //如果本地文件大小和远程文件大小不一致，则下载更新
            if ( $file_size != $lentgh ) {
                if ( $this->down_updater() ) {
                    //更新完毕后提示
                    $this->return_json(200,"","更新程序更新完毕！");
                }
                else{
                    $this->return_json(-2000,"","更新程序下载失败，请检查目录权限！");
                }
                
            }
            else {
                $this->return_json(200,"","更新程序（压缩包）准备就绪！");
            }
        }
        else if( is_file("update.php") ) {
            $this->return_json(200,"","更新程序（PHP）准备就绪！");
        }
        else{
            $this->return_json(200,"","更新程序（其它）准备就绪！");
        }
    }
    /**
     * 校验更新程序
     */
    public function check_version($version) {
        //获取当前版本信息
        $current_version = explode("-",file_get_contents("version.txt"));
        $current_version = str_replace("v","",$current_version[0]);

        //获取用户传递的版本
        //$version = $_REQUEST['version'];

        if( $version == $current_version ) {
            $this->return_json(200,"","success");
        }
        else{
            $this->return_json(-2000,"","更新失败，版本校验不匹配，请检查目录权限！");
        }
    }

    //curl get请求
    protected function curl_get($url,$timeout = 10) {
        $curl = curl_init($url);
        #设置useragent
        curl_setopt($curl, CURLOPT_USERAGENT, "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/102.0.5005.63 Safari/537.36");
        curl_setopt($curl, CURLOPT_FAILONERROR, true);
        curl_setopt($curl, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);
        #设置超时时间，最小为1s（可选）
        curl_setopt($curl , CURLOPT_TIMEOUT, $timeout);

        $html = curl_exec($curl);
        curl_close($curl);
        return $html;
    }

    /**
     * name:数据库备份接口
     */
    public function backup_db(){
        //验证请求
        $this->auth($token);

        //验证订阅
        $this->check_is_subscribe();

        $backup_dir = 'data/backup/';

        //判断目录是否存在，不存在则创建
        if( !is_dir($backup_dir) ) {
            try {
                mkdir($backup_dir,0755);
            } catch (\Throwable $th) {
                $this->return_json(-2000,'','备份目录创建失败，请检查目录权限！');
            }
        }
        //尝试拷贝数据库进行备份
        try {
            //获取当前版本信息
            $current_version = explode("-",file_get_contents("version.txt"));
            $current_version = str_replace("v","",$current_version[0]);
            $db_name = 'onenav_'.date("YmdHi",time()).'_'.$current_version.'.db3';
            $backup_db_path = $backup_dir.$db_name;
            copy('data/onenav.db3',$backup_db_path);
            $this->return_json(200,$db_name,'success');
        } catch (\Throwable $th) {
            $this->return_json(-2000,'','备份目录创建失败，请检查目录权限！');
        }
        
    }
    /**
     * name:数据库备份列表
     */
    public function backup_db_list() {
        //验证请求
        $this->auth($token);
        //验证订阅
        $this->check_is_subscribe();

        //备份目录
        $backup_dir = 'data/backup/';

        //遍历备份列表
        $dbs = scandir($backup_dir);
        $newdbs = $dbs;
        
        //去除.和..
        for ($i=0; $i < count($dbs); $i++) { 
            if( ($dbs[$i] == '.') || ($dbs[$i] == '..') ) {
                unset($newdbs[$i]);
            }
        }

        //将删除后的数组重新赋值
        $dbs = $newdbs;

        //获取备份列表个数
        $num = count($dbs);
        
        //排序处理，按时间从大到小排序
        rsort($dbs,2);

        //如果大于10个，则删减为10个
        if( $num > 10 ) {
            for ($i=$num; $i > 10; $i--) { 
                //物理删除数据库
                unlink($backup_dir.$dbs[$i-1]);
                //删除数组最后一个元素
                array_pop($dbs);
            }
            $count = 10;
        }
        else{
            $count = $num;
        }

        //声明一个空数组
        $data = [];
        //遍历数据库，获取时间，大小
        foreach ($dbs as $key => $value) {
            $arr['id'] = $key;
            $arr['name'] =   $value;
            $arr['mtime'] = date("Y-m-d H:i:s",filemtime($backup_dir.$value));
            $arr['size'] = (filesize($backup_dir.$value) / 1024).'KB';

            $data[$key] = $arr;
        }

        $datas = [
            'code'      =>  0,
            'msg'       =>  '',
            'count'     =>  $count,
            'data'      =>  $data
        ];
        exit(json_encode($datas));
    }
    /**
     * name:删除单个数据库备份
     * @param $name：数据库名称
     */
    public function del_backup_db($name) {
        //验证请求
        $this->auth($token);

        //验证订阅
        $this->check_is_subscribe();

        //使用正则表达式判断数据库名称是否合法
        $pattern = '/^onenav_[0-9\-]+_[0-9.]+(db3)$/';

        if( !preg_match_all($pattern,$name) ) {
            $this->return_json(-2000,'','数据库名称不合法！');
        }

        //数据库目录
        $backup_dir = 'data/backup/';

        //删除数据库
        try {
            unlink($backup_dir.$name);
            $this->return_json(200,'',"备份数据库已被删除！");
        } catch (\Throwable $th) {
            $this->return_json(-2000,'',"删除失败，请检查目录权限！");
        }
    }

    /**
     * name:恢复数据库备份
     * @param $name：备份数据库名称
     */
    public function restore_db($name) {
        //验证请求
        $this->auth($token);

        //验证订阅
        $this->check_is_subscribe();

        //使用正则表达式判断数据库名称是否合法
        $pattern = '/^onenav_[0-9\-]+_[0-9.]+(db3)$/';

        if( !preg_match_all($pattern,$name) ) {
            $this->return_json(-2000,'','数据库名称不合法！');
        }

        //数据库目录
        $backup_dir = 'data/backup/';

        //恢复数据库
        try {
            copy($backup_dir.$name,'data/onenav.db3');
            $this->return_json(200,'','数据库已回滚为'.$name);
        } catch (\Throwable $th) {
            $this->return_json(-2000,'',"回滚失败，请检查目录权限！");
        }
    }

    /**
     * name:获取OneNav信息
     */
    public function app_info($token) {
        //验证请求
        $this->auth($token);
        //获取PHP版本
        $data['php_version'] = PHP_VERSION;
        //获取OneNav版本
        $data['onenav_version'] = file_get_contents("version.txt");
        //获取分类数量
        $data['cat_num'] = $this->db->count("on_categorys");
        //获取链接数量
        $data['link_num'] = $this->db->count("on_links");
        //获取用户名
        $data['username'] = USER;
        // 获取用户邮箱
        $data['email'] = EMAIL;

        //返回JSON数据
        $this->return_json(200,$data,"success");
    }

    /**
     * name:下载数据库
     */
    public function down_db($name) {
        //验证请求
        $this->auth($token);

        //使用正则表达式判断数据库名称是否合法
        $pattern = '/^onenav_[0-9\-]+_[0-9.]+(db3)$/';

        if( !preg_match_all($pattern,$name) ) {
            $this->return_json(-2000,'','数据库名称不合法！');
        }

        //数据库目录
        $backup_dir = 'data/backup/';
        
        //拼接数据库路径
        $full_path = $backup_dir.$name;

        if( !file_exists($full_path) ) {
            header('HTTP/1.1 404 NOT FOUND');
        }
        else{
            // 以只读和二进制模式打开文件
            $file = fopen($full_path, "rb");

            // 告诉浏览器这是一个文件流格式的文件
            Header("Content-type: application/octet-stream");
            // 请求范围的度量单位
            Header("Accept-Ranges: bytes");
            // Content-Length是指定包含于请求或响应中数据的字节长度
            Header("Accept-Length: " . filesize($full_path));
            // 用来告诉浏览器，文件是可以当做附件被下载，下载后的文件名称为$file_name该变量的值。
            Header("Content-Disposition: attachment; filename=" . $name);

            // 读取文件内容并直接输出到浏览器
            echo fread($file, filesize($full_path));
            fclose($file);

            exit();
        }

    }

    /**
     * name:创建分享
     */
    public function create_share($data) {
        //验证请求
        $this->auth($token);

        //如果订阅不存在
        if ( $this->is_subscribe() === FALSE ) {
            $this->return_json(-2000,'','此功能需要订阅后才能使用！');
        }

        //设置默认数据
        //随机8位分享ID
        $data['sid'] = GetRandStr(8);
        
        /**
         * 判断到期时间
         */
        //获取当前时间
        $c_time = strtotime( $data['add_time'] );
        $e_time = strtotime( $data['expire_time'] );

        if( $c_time > $e_time ) {
            $this->return_json(-2000,'','到期日期不能小于当前日期！');
        }

        /**
         * 判断密码
         */
        if( strlen($data['password']) > 16 ) {
            $this->return_json(-2000,'','密码长度不能超过16位！');
        }
        $pattern = "/[A-Za-z0-9]{4,16}/";
        //var_dump(preg_match($pattern,$data['password']));
        if( !empty($data['password']) && !preg_match($pattern,$data['password']) ) {
            $this->return_json(-2000,'','密码只能由4-16位字母和数字组成！');
        }

        //插入数据库
        $result = $this->db->insert("on_shares",$data);

        if( $result ) {
            $this->return_json(200,'','success');
        }
        else{
            $this->return_json(-2000,'','写入数据库失败！');
        }
    }
    /**
     * 分享列表
     */
    public function share_list($data){
        //验证请求
        $this->auth($token);

        $limit = $data['limit'];
        $offset = ($data['page'] - 1) * $data['limit'];
        //$fid = @$data['category_id'];
        $count = $this->db->count('on_shares','*');
        
        $sql = "SELECT *,(SELECT name FROM on_categorys WHERE id = on_shares.cid) AS category_name FROM on_shares ORDER BY id DESC LIMIT {$limit} OFFSET {$offset}";

       
        //原生查询
        $datas = $this->db->query($sql)->fetchAll();

        $datas = [
            'code'      =>  0,
            'msg'       =>  '',
            'count'     =>  $count,
            'data'      =>  $datas
        ];
        exit(json_encode($datas));
    }

    /**
     * name:根据分享的SID查询指定分类下的所有链接
     */
    public function get_sid_links($data) {
        //获得SID
        $sid = $data['sid'];
        //获得密码
        $password = $data['password'];
        //判断SID是否合法
        $pattern = "/[A-Za-z0-9]{8}$/";

        if( (strlen($sid) !== 8) || !preg_match($pattern,$sid) ) {
            $this->return_json(-2000,'','SID不合法！');
        }

        //根据SID查询得到分类ID
        $share_data = $this->db->get("on_shares","*",[
            "sid"   =>  $sid
        ]);

        //如果没有查询到数据
        if( empty($share_data) ) {
            $this->return_json(-2000,'','SID不存在！');
        }

        $cid = $share_data['cid'];

        //查询分类名称
        $category_info = $this->db->get("on_categorys",["name"],[
            "id"    =>  $cid
        ]);
        $category_name = $category_info["name"];

        //如果链接已经过期
        $c_time = strtotime( date("Y-m-d H:i:s",time()) );
        
        if ( $c_time > strtotime($share_data['expire_time']) ) {
            $this->return_json(-2000,'','链接已过期！');
        }
        //如果分享密码不为空，则验证密码
        if ( !empty($share_data['password']) && ( $share_data['password'] ==  $password) ) {
            //根据分类ID（cid）查询该分类下的所有链接
            $results = $this->db->select("on_links","*",[
                "fid"   =>  $cid,
                "ORDER" => ["weight" => "DESC","id" => "DESC"]
            ]);

            $data = [
                "category_name" =>  $category_name,
                "expire_time"   =>  $share_data["expire_time"],
                "results"       =>  $results
            ];

            $this->return_json(200,$data,'success');
        }
        else if ( empty($share_data['password']) ) {
            //根据分类ID（cid）查询该分类下的所有链接
            $results = $this->db->select("on_links","*",[
                "fid"   =>  $cid,
                "ORDER" => ["weight" => "DESC","id" => "DESC"]
            ]);

            $data = [
                "category_name" =>  $category_name,
                "expire_time"   =>  $share_data["expire_time"],
                "results"       =>  $results
            ];

            $this->return_json(200,$data,'success');
        }
        else{
            $this->return_json(401,'','密码错误！');
        }

    }

    /**
     * name：删除分享
     */
    public function del_share($data) {
        //验证请求
        $this->auth($token);

        $id = $data['id'];

        

        //如果id为空
        if( empty($id) ){
            $this->return_json(-2000,$results,'ID不能为空！');
        }

        $data = $this->db->delete('on_shares',[ 'id' => $id] );
        
        if( $data ) {
            $this->return_json(200,'','success');
        }
        else{
            $this->return_json(-2000,'','删除失败！');
        }

    }

    /**
     * name:获取站点信息，不需要授权
     */
    public function site_info() {
        //获取当前站点信息
        $site = $this->db->get('on_options','value',[ 'key'  =>  "s_site" ]);
        $site = unserialize($site);

        $this->return_json(200,$site,'success');
    }

    /**
     * name：删除链接图标
     */
    public function del_link_icon(){
        //验证授权
        $this->auth($token);

        //获取图标路径
        $icon_path = trim($_POST['icon_path']);
        //正则判断路径是否合法
        $pattern = "/^data\/upload\/[0-9]+\/[0-9a-zA-Z]+\.(jpg|jpeg|png|bmp|gif|svg)$/";
        //如果名称不合法，则终止执行
        if( !preg_match($pattern,$icon_path) ){
            $this->return_json(-2000,'','非法路径！');
        }

        //继续执行
        //检查图标是否存在
        if( !is_file($icon_path) ) {
            $this->return_json(-2000,'','图标文件不存在，无需删除！');
        }

        //执行删除操作
        if( unlink($icon_path) ) {
            $this->return_json(200,'','success');
        }
        else{
            $this->return_json(-2000,'','图标删除失败，请检查目录权限！');
        }
    }

    /**
     * name:优先使用POST获取数据，其次GET获取数据
     */
    protected function getData($param) {
        if(isset($_POST[$param])) {
            return $_POST[$param];
        } elseif(isset($_GET[$param])) {
            return $_GET[$param];
        } else {
            return null;
        }
    }

    /**
     * name: 全局搜索
     */
    public function global_search() {
        //验证授权
        $this->auth($token);
        // 获取关键词
        $keyword = htmlspecialchars( $this->getData("keyword") );

        // 判断关键词长度
        if( strlen($keyword) < 2 ) {
            $this->return_json(-2000,'','The length of the keyword is too short.');
        }
        else if( strlen($keyword) > 32 ) {
            $this->return_json(-2000,'','The keyword length is too long');
        }

        $keyword = '%'.$keyword.'%';

        // 通过标题、链接、备用链接、描述进行模糊匹配
        $data = $this->db->select('on_links', '*', [
            "OR" => [
                "title[~]" => $keyword,
                "url[~]" => $keyword,
                "url_standby[~]" => $keyword,
                "description[~]" => $keyword,
            ],
            "ORDER" => [
                "weight" => "DESC"
            ]
        ]);

        
        // 查询出分类名称
        $categorys = $this->db->select("on_categorys",[
            'id',
            'name'
        ]);
        // 遍历分类，以id作为键名
        foreach ($categorys as $category) {
            
            $newCategorys[$category['id']] = $category['name'];
        }

        // 遍历查询的数据，然后添加父级分类名称
        foreach ($data as $key => $value) {
            $data[$key]['category_name'] = $newCategorys[$value['fid']];
        }

        // 返回数据
        $datas = [
            'code'      =>  0,
            'msg'       =>  '',
            'count'     =>  count($data),
            'data'      =>  $data
        ];

        exit( json_encode($datas) );
    }

    /**
     * 批量更新链接排序
     * 
     */
    public function update_link_order(){
        //验证授权
        $this->auth($token);
        // 获取分类ID
        $cid = intval($this->getData("category_id"));
        // 获取json对象数据
        // 获取前端传递的原始 JSON 数据
        $rawData = file_get_contents('php://input');
        // 将 JSON 数据转换为 PHP 关联数组
        $jsonData = json_decode($rawData, true);
        // 检查解析是否成功
        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->return_json(-2000,'','JSON解析失败！');
        }

        // 批量更新on_links表的weight字段
        foreach ($jsonData as $key => $value) {
            $this->db->update('on_links', [
                'weight' => $value['weight']
            ], [
                'fid' => $cid,
                'id' => intval($value['id'])
            ]);
        }

        // 返回成功
        $this->return_json(200,'','success');
    }

    /**
     * name:获取订阅信息
     */
    public function get_subscribe() {
        //验证授权
        $this->auth($token);
        //获取当前站点信息
        $subscribe = $this->db->get('on_options','value',[ 'key'  =>  "s_subscribe" ]);
        $subscribe = unserialize($subscribe);

        $this->return_json(200,$subscribe,'success');
    }

    /**
     * 验证订阅是否有效
     */
    public function get_subscribe_status(){
        // var_dump($_SERVER['HTTP_HOST']);
        //验证授权
        $this->auth($token);
        // 获取订阅结果
        $result = $this->is_subscribe();

        if( $result === TRUE ) {
            $this->return_json(200,'Active','success');
        }
        else{
            $this->return_json(-2000,'','failure');
        }
    }

    /**
     * name: 批量检测链接
     * description:check_status，0：未检测，1：正常，2：异常，3：未知（比如启用了cf）
     */
    public function batch_check_links(){
        set_time_limit(1200); // 设置执行最大时间为20分钟
        // 验证授权
        $this->auth($token);

        // 验证订阅
        $this->check_is_subscribe();

        // 记录开始时间
        $start_time = microtime(true);

        // 获取所有链接
        $links = $this->db->select('on_links', '*');

        // 设置并发限制（最大30个并发）
        $max_concurrent_requests = 30;
        $multi_curl = curl_multi_init();  // 初始化curl_multi
        $curl_handles = [];  // 存储curl句柄
        $link_count = count($links);  // 获取链接数量
        $completed_links = 0;  // 已完成检测的链接数

        // 设置curl超时时间为20分钟（1200秒）
        $timeout = 10;

        // 错误链接
        $error_num = 0;

        // 并发处理每个链接
        foreach ($links as $link) {
            // 创建一个curl句柄
            $ch = curl_init();

            // 设置curl选项
            curl_setopt($ch, CURLOPT_URL, $link['url']);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);  // 返回内容而不是输出
            curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);  // 设置请求超时时间
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);  // 设置连接超时时间
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);  // 跟随重定向
            // 设置UA
            curl_setopt($ch, CURLOPT_USERAGENT, "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/102.0.5005.63 Safari/537.36");
            curl_setopt($ch, CURLOPT_HTTPGET, true);  // 确保使用GET请求
            curl_setopt($ch, CURLOPT_HEADER, true);  // 获取响应头

            // 将curl句柄加入到multi句柄中
            curl_multi_add_handle($multi_curl, $ch);

            // 存储每个链接的ID，方便回调时更新状态
            $curl_handles[$link['id']] = $ch;
        }

        // 执行curl请求并监听
        do {
            $status = curl_multi_exec($multi_curl, $active);  // 执行请求
            if ($status > 0) {
                // 如果发生错误，输出错误信息
                // echo "Curl error: " . curl_multi_strerror($status);
            }

            // 等待活动的请求完成
            if ($active) {
                curl_multi_select($multi_curl, 1);  // 阻塞直到有活动的请求
            }
        } while ($active);

        // 处理每个请求的返回结果
        foreach ($curl_handles as $id => $ch) {
            $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);  // 获取HTTP状态码
            $error = curl_error($ch);  // 获取cURL错误信息
            
            $header = curl_multi_getcontent($ch);  // 获取完整的响应内容，包括头部
            // 获取Server头
            preg_match('/^Server:\s*(.*)$/mi', $header, $matches);
            $server_header = isset($matches[1]) ? $matches[1] : '';  // 获取Server头的值
            // 获取当前时间戳
            $last_checked_time = date('Y-m-d H:i:s');

            // 定义 Web 服务器列表
            $web_servers = [
                'cloudflare',
                'waf',
                // 可以根据需要添加更多的 Web 服务器类型
                'AkamaiGHost',
                'JDCloudStarshield'
            ];

            // 假设 $error 和 $http_code 已经定义
            // 假设 $server_header 已经从响应头中获取

            if ($error || $http_code >= 400) {
                // 默认状态为异常
                $check_status = 2;  // 异常
                // 遍历 Web 服务器列表，检查是否包含任何已知的 Web 服务器标识
                foreach ($web_servers as $server) {
                    if (stripos($server_header, $server) !== false) {
                        // 如果找到匹配的服务器，设置为未知状态
                        $check_status = 3;  // 未知
                        break;  // 一旦找到匹配的服务器，跳出循环
                    }
                }
                // 如果没有匹配到任何已知的 Web 服务器，则认为是异常
                if ($check_status == 2) {
                    // 错误数量+1
                    $error_num++;
                }
            }
            else if( $http_code === 0 ) {
                // HTTP 状态码为 0，表示链接超时，或者SSL证书过期
                $check_status = 2;  // 异常
                // 错误数量+1
                $error_num++;
            }
            else {
                // HTTP 状态码小于 400，表示正常
                $check_status = 1;  // 正常
            }

            // 更新数据库字段
            $this->db->update('on_links', [
                'check_status' => $check_status,
                'last_checked_time' => $last_checked_time
            ], ['id' => $id]);

            // 删除curl句柄，释放资源
            curl_multi_remove_handle($multi_curl, $ch);
            curl_close($ch);  // 关闭curl句柄

            // 完成一个链接的检测
            $completed_links++;
        }

        // 关闭multi句柄
        curl_multi_close($multi_curl);

        // 记录结束时间
        $end_time = microtime(true);

        // 计算总共花费的时间
        $elapsed_time = $end_time - $start_time;
        // 精确到s就行
        $elapsed_time = round($elapsed_time, 2);

        // 返回成功
        $this->return_json(200, [
            'completed_links' => $completed_links,
            'elapsed_time' => $elapsed_time,
            'error_num' => $error_num
        ], 'success');
    }

    // 获取过渡页API
    public function transition_page(){
        //获取当前站点信息
        $transition_page = $this->db->get('on_options','value',[ 'key'  =>  "s_transition_page" ]);
        $transition_page = unserialize($transition_page);
        // 返回数据
        $this->return_json(200,$transition_page,'success');
    }
    
    /**
     * AI检索 (流式输出)
     */
    public function ai_search() {
        // 设置超时时间为120s
        set_time_limit(120);
        // 验证授权
        $this->auth($token);

        // 验证订阅
        $this->check_is_subscribe();

        // 从数据库获取API信息
        $api = $this->get_options("ai_setting");
        // 如果查询失败
        if( !$api ) {
            $this->return_json(-2000,'','获取参数失败！');
        }
        // 如果没有启用
        if( $api->status === 'off' ) {
            $this->return_json(-2000,'','AI功能未启用！');
        }
        // 查询到了结果
        $url = $api->url;
        $key = $api->sk;
        $model = $api->model;
        // 如果model = custom，则使用自定义模型
        if( $model === 'custom' ) {
            $model = $api->custom_model;
        }


        while (ob_get_level()) {
            ob_end_flush();
        }
        ob_implicit_flush(true);
        ini_set('zlib.output_compression', 'Off');
        header('X-Accel-Buffering: no');

        // 设置适当的响应头部
        header('Content-Type: text/event-stream');
        header('Cache-Control: no-cache');
        header('Connection: keep-alive');
        

        // 获取用户输入
        $content = $_POST['content'];
        $feature = empty($_POST['feature']) ? 'search' : $_POST['feature'];

        // 查询出所有链接，只需要url, title, description, url_standby字段
        $links = $this->db->select('on_links', ['url', 'title', 'description']);

        if($feature == "search") {
            // 设置温度
            $temperature = 0.1;
            $top_p = 0.7;
            // 将链接数据转换为AI需要的JSON格式
            $bookmarks = [];
            foreach ($links as $link) {
                $bookmarks[] = [
                    'title' => $link['title'],
                    'url' => $link['url'],
                    'description' => $link['description']
                ];
            }

            // 将数据转换为JSON格式
            $bookmarks = json_encode($bookmarks);

            // 创建AI请求的消息内容
            $messages = [
                [
                    "role" => "system",
                    "content" => "
                    用户会给你一段JSON格式的书签数据，其中包含每个链接的标题、URL和描述（描述可能为空）等信息。你需要根据用户提供的关键词，智能匹配与之相关的链接，并推荐3个额外的相关链接。请严格遵循以下规则：

### 规则：

1. **匹配链接**：
    - **仅使用提供的书签数据进行匹配**，不得引用或使用任何其他外部信息。
    - **对于没有描述或描述不充分的URL**，请基于你的知识推断该URL的含义，以便进行准确匹配。
    - 返回的匹配链接必须与用户提供的关键词高度相关，优先匹配精确相关的内容。
    - 根据相关性将匹配结果按从高到低排序，确保最相关的链接出现在列表前面。
    - 匹配的结果不超过5个链接，如果无法匹配任何链接，请提醒用户。

2. **推荐额外链接**：
    - **推荐的额外3个链接不来自用户提供的JSON数据**，应基于你所学的知识进行推荐。
    - 确保这些推荐的链接与用户的需求相关，补充和扩展匹配结果。
    - 不要推荐用户提供的JSON内容中的任何链接。

3. **输出格式**：
    - **匹配结果**：以列表形式返回，应包含链接标题、URL和简短的链接描述，优先使用用户JSON中的标题和描述，如果没有或不完善你可以补充。
    - **推荐结果**：以列表形式返回，应包含链接标题、URL和简短的链接描述。
                    "
                ],
                [
                    "role" => "user",
                    "content" => "JSON书签列表为：" . $bookmarks  // 你可以根据实际需求修改用户输入
                ],
                [
                    "role" => "user",
                    "content" => $content  // 你可以根据实际需求修改用户输入
                ]
            ];
        }else{
            // 设置温度
            $temperature = 0.2;
            $top_p = 1;
            $messages = [
                [
                    "role" => "system",
                    "content" => "请自动检测用户输入的语言。当输入内容是中文时，翻译成英文；当输入内容不属于中文时，翻译成中文。只需返回翻译后的内容，不需要额外解释或描述。"
                ],
                [
                    "role" => "user",
                    "content" => $content
                ]
            ];
        }


        // cURL 请求设置
        $ch = curl_init();

        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, false);  // 不返回结果，进行流式输出
        // curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_HEADER, false);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "Authorization: Bearer " . $key,
            "Content-Type: application/json"
        ]);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1);
        curl_setopt($ch, CURLOPT_BUFFERSIZE, 128); // 例如，128字节
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
            "model" => $model,
            "temperature" => $temperature,
            "top_p" => $top_p,
            "messages" => $messages,
            "stream" => true
        ]));
        
        // 设置输出流方式
        curl_setopt($ch, CURLOPT_WRITEFUNCTION, function($ch, $data) {
            // 实时输出流数据
            echo $data;
            flush(); // 强制刷新缓冲区，确保数据实时输出
            return strlen($data); // 返回已写入的数据长度

            
            // 查找数据中的 content 部分
            // $pos = strpos($data, '"content":');
            
            // // 如果 content 存在，提取其内容
            // if ($pos !== false) {
            //     // 截取 content 字段内容
            //     $content_start = strpos($data, '"content":', $pos) + 10;
            //     $content_end = strpos($data, '"', $content_start + 1);
                
            //     // 提取 content 内容
            //     $content = substr($data, $content_start, $content_end - $content_start);
                
            //     // 去除 content 前后的双引号（如果存在）
            //     $content = trim($content, '"');

            //     // 输出符合 SSE 格式的数据：以 'data:' 开头
            //     echo "data: " . $content . "\n\n";
            //     flush(); // 强制刷新缓冲区，确保数据实时输出
            // }

            // // 返回原始数据的字节长度
            // return strlen($data);
        });

        // 执行 cURL 请求
        curl_exec($ch);

        // 关闭 cURL 会话
        curl_close($ch);
    }

    /**
     * name:内部获取设置选项
     */
    private function get_options($key) {
        //验证授权
        $this->auth($token);
        //获取当前站点信息
        $options = $this->db->get('on_options','value',[ 'key'  =>  $key ]);
        // 判断查询结果是否为空
        if( empty($options) ) {
            return false;
        }

        // 把选项转为对象
        $options = json_decode($options);
        return $options;
    }

    /**
     * name：外部通用获取设置选项
     */
    public function get_option_base(){
        //验证授权
        $this->auth($token);
        // 获取key 
        $key = htmlspecialchars( trim( $_GET['key'] ));
        $options = $this->db->get('on_options','value',[ 'key'  =>  $key ]);
        // 判断查询结果是否为空
        if( empty($options) ) {
            $this->return_json(-2000,'','获取参数失败！');
        }
        $result = json_decode($options);
        // 获取成功
        $this->return_json(200,$result,'success');
    }

    /**
     * name：后端检查验证授权
     */
    public function forward_order(){
        //验证token是否合法
        $this->auth($token);
        // 声明一个空数组，作为请求体
        $data = [];
        if (!empty($_GET)) {
            // 使用 http_build_query() 函数生成查询字符串
            $query_string = http_build_query($_GET);
            // 向https://onenav.xiaoz.top/v1/check_subscribe.php 发起GET请求，然后返回内容
            // 拼接完整的 URL
            $target_url = API_URL . '/v1/check_subscribe.php?' . $query_string;
            echo curl_get($target_url);

        } else {
            // 返回错误
            $this->return_json(-2000,'','参数不能为空！');
        }
    }

    /**
     * name:封装header方法
     */
    private function GetHeadCode($url, $timeout = 5) {
        $ch = curl_init($url);
    
        // 设置请求方法为 HEAD
        curl_setopt($ch, CURLOPT_NOBODY, true);
    
        // 设置超时时间
        curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
    
        // 设置连接超时时间
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $timeout);
    
        // 忽略 SSL 证书校验
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    
        // 执行请求
        $result = curl_exec($ch);
    
        // 获取 HTTP 状态码
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    
        // 获取错误信息
        $error = curl_error($ch);
    
        // 关闭 curl 资源
        curl_close($ch);
    
        return $httpCode;
    }
    // 读取缓存
    private function getFileCache($key) {
        // 定义缓存文件的路径
        $cacheDir = __DIR__ . '/cache/';
        $filePath = $cacheDir . md5($key) . '.cache';
    
        // 检查文件是否存在
        if (file_exists($filePath)) {
            // 读取文件内容
            $data = unserialize(file_get_contents($filePath));
    
            // 检查是否过期
            if ($data['expire'] > time()) {
                return $data['value'];
            } else {
                // 如果过期，删除文件
                unlink($filePath);
            }
        }
    
        // 如果文件不存在或已过期，返回空字符串
        return '';
    }
    // 设置缓存
    private function setFileCache($key, $value, $ttl) {
        // 定义缓存文件的路径
        $cacheDir = __DIR__ . '/cache/';
        if (!is_dir($cacheDir)) {
            mkdir($cacheDir, 0755, true);
        }
        $filePath = $cacheDir . md5($key) . '.cache';
    
        // 计算过期时间戳
        $expireTime = time() + $ttl;
    
        // 将数据和过期时间一起写入文件
        file_put_contents($filePath, serialize(['value' => $value, 'expire' => $expireTime]));
    }

    // 获取相临的版本
    public function getNextVersion(){
        $this->auth($token);
        // 获取当前版本
        $version = trim($_GET['version']);
        // 正则判断版本是否合法，只能是数组或.组成
        $pattern = "/^[0-9.]+$/";
        if( !preg_match($pattern,$version) ) {
            echo "0.0.0";
            exit;
        }
        // 请求：https://onenav.xiaoz.top/v1/get_version.php?version=1.1.1
        $url = API_URL . '/v1/get_version.php?version=' . $version;
        // 设置响应头为text类型
        header('Content-Type: text/plain');
        // 输出结果
        echo curl_get($url);
    }
}



