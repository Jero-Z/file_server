<?php
/**
 * 上传流程
 * 1. /upload 接口上传 给前端返回临时文件路径
 * 2. 前端进行通过 /crop 接口裁切， crop接口返回新的临时图片地址
 * 3. 前端确定裁切完成 通过/save 用临时文件地址交换永久图片地址
 *
 *
 * 上传目录
 * 永久文件保存到files/contexts/{context}目录 context值由/save传入，默认为为default
 * 临时文件保存到files/temp目录
 */

use Slim\Http\Request;
use Slim\Http\Response;
use Slim\Http\UploadedFile;
use Intervention\Image\ImageManagerStatic as Image;

require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../config.php';

$app = new \Slim\App();

$container = $app->getContainer();

//临时文件路径
$container['tmp_file_dir'] = __DIR__ .'/files/temp';

//永久文件目录
$container['files_contexts_dir'] = __DIR__ .'/files/contexts/';

$app->get('/', function (Request $req, Response $res, $args = []) {
    return 'file-server';
});
function Directory($dir){

    return  is_dir ( $dir ) or Directory(dirname( $dir )) and  mkdir ( $dir , 0777);

}
/*
 * 上传图片文件 返回临时图片地址
  * @param resource
  * @return string temp file path
 */
$app->post('/upload', function (Request $req, Response $res, $args = []) {
    $temp_file_name = $this->get('tmp_file_dir').'/'.uniqid().'.png';
    //判断文件夹是否存在
    if(!file_exists($this->get('tmp_file_dir'))){
        Directory($this->get('tmp_file_dir'));
    }

    // 图片文件全部转为png
    $allow_type = [
        'image/gif',
        'image/jpeg',
        'image/png',
        'image/bmp'
    ];
    // todo
    $data = [
        'message'=>'',
        'url'=>''
    ];
    //获取上传资源
    $uploadedFiles = $req->getUploadedFiles();

    //判定资源是否合法
    if (!is_array($uploadedFiles) || empty($uploadedFiles)){
        $data['message']='上传有误，请重新上传';
        return json_encode($data);
    }
    //弹出上传数据为一维数组
    $file = array_pop($uploadedFiles);

    $resource =Image::make($file->file);

    //判断mime类型
    if (!in_array($resource->mime(),$allow_type)){
        $data['message']='上传类型错误，请确定类型！';
        return json_encode($data);
    }
    //验证通过后转为PNG类型图片 存储于临时目录下
    $result = $resource->save($temp_file_name);
    if (!$result) {
        $data['message'] = 'error';
        $data['url'] = '';
        return json_encode($data);
    }

    $data['message'] = 'success';
    $data['url'] = $temp_file_name;
    return json_encode($data);
});

/*
 *  切图 返回新的临时图片地址
 * @param string width,height,x,y
 * @param string temp url path
 * @return crop temp file path
 */
$app->post('/crop', function (Request $req, Response $res, $args = []) {

    // todo
    $crop_temp = __DIR__ .'/files/temp/'.uniqid().'.png';
    /*
     * 1.接受裁剪参数
     * 2.进行裁剪，生成临时图片地址并保存到临时目录
     * 3.返回临时图片地址
     */
    $parsedBody = $req->getParsedBody();

    $image = Image::make($parsedBody['url']);

    $result = $image->crop($parsedBody['width'],$parsedBody['height'],$parsedBody['x'],$parsedBody['y'])->save($crop_temp);
    if (!$result) {
        $data['message'] ='error';
        $data['url'] = '';
        return json_encode($data);
    }
    $data['message'] = 'success';
    $data['url'] = $crop_temp;
    return json_encode($data);
});

/*
 *  保存临时图片 返回永久地址
 * @param string context dir name
 * @param string crop temp file path
 * @return permanent file path
 */
$app->post('/save', function (Request $req, Response $res, $args = []) {

    // todo
    $params = $req->getParsedBody();
    $temp_path = $params['temp_path'];
    $save_name = $params['context'] ? $params['context'] : 'default';
    $permanent_file_path = $this->get('files_contexts_dir').'/'.$save_name.'/'.uniqid().'.png';

    Directory($this->get('files_contexts_dir').$save_name);

    $result = Image::make($temp_path)->save($permanent_file_path);

    if (!$result) {
        $data['message'] ='error';
        $data['url'] = '';
        return json_encode($data);
    }

    $data['message'] = 'success';
    $data['url'] = $permanent_file_path;
    return json_encode($data);
});


$app->run();