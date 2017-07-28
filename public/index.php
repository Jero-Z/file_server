<?php
/**
 * 上传流程
 * 1. /upload 接口上传 给前端返回临时文件路径
 * 2. 前端进行通过 /crop 接口裁切， crop接口返回新的临时图片地址
 * 3. 前端确定裁切完成 通过/save 用临时文件地址交换永久图片地址
 *
 * wechat api证书上传
 * 1. /fileUpload 接口上传 判断是否为私密上传
 * 2. 上传成功后返回私密地址
 *
 * 上传目录
 * 永久文件保存到files/contexts/{context}目录 context值由/save传入，默认为为default
 * 临时文件保存到files/temp目录
 * wechat api 文件保存到wechat_api_file
 */

use Slim\Http\Request;
use Slim\Http\Response;
use Slim\Http\UploadedFile;
use Intervention\Image\ImageManagerStatic as Image;

require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../config.php';

$app = new \Slim\App();

$container = $app->getContainer();

//网站url
$container['host'] = $_SERVER["HTTP_HOST"];
//临时文件路径
$container['tmp_file_dir'] = '/files/temp';
//永久文件目录
$container['files_contexts_dir'] = '/files/contexts/';
//微信API 存储地址
$container['wechat_api_file_path'] = 'wechat_api_file';

$app->get('/', function (Request $req, Response $res, $args = []) {
    $comments = <<<comment
图片上传
1.
/upload 
@params {resource} name=file  文件名为file的图片资源
@return {string}  temp file path 返回临时图片地址
2.
/crop
@params {string} width,height [x,y] 裁剪宽高位置
@params {string} temp url path [url] 临时图片地址
@return {string} crop temp file path 裁剪后临时图片地址
3.
/save
@param {string} context dir name 自定义保存的文件目录,默认为为default
@param {string} crop temp file path  裁剪后临时图片地址
@return {string} permanent file path 永久文件地址

文件上传
/fileUpload  {wechat api证书上传}
@params {resource} file   
@return {string} private file name

数据流上传图片
/streamUploadImage {bash64 数据流上传}
@params {json} stream 
@return {string} image url path

comment;
    return '<pre>'.$comments.'</pre>';
});
//创建文件路径
function Directory($dir)
{
    return is_dir($dir) or Directory(dirname($dir)) and mkdir($dir, 0777);

}

//判断路径是否存在
function dirIsExists($path)
{
    if (!file_exists($path)) {
        Directory($path);
    }
}

//url转为系统路径
function convertUrl($url, $host)
{
    return WEB_ROOT . mb_substr($url, strlen($host));
}

//判断上传资源是否合法
function isLawful($uploadedFiles, $res)
{
    if (!is_array($uploadedFiles) || empty($uploadedFiles)) {
        $data['message'] = '上传有误，请重新上传';
        return $res->withJson($data);
    }
    //弹出上传数据为一维数组
    return array_pop($uploadedFiles);
}

/*
 * 上传图片文件 返回临时图片地址
  * @param resource
  * @return string temp file path
 */
$app->post('/upload', function (Request $req, Response $res, $args = []) {

    $temp_file_name = $this->get('tmp_file_dir') . '/' . uniqid() . '.png';
    $temp_file_path = WEB_ROOT . $temp_file_name;
    //判断文件夹是否存在
    dirIsExists(WEB_ROOT . $this->get('tmp_file_dir'));

    // 图片文件全部转为png
    $allow_type = [
        'image/gif',
        'image/jpeg',
        'image/png',
        'image/bmp'
    ];
    // todo
    $data = [
        'message' => '',
        'url' => ''
    ];
    //获取上传资源
    $uploadedFiles = $req->getUploadedFiles();

    $file = isLawful($uploadedFiles, $res);

    $resource = Image::make($file->file);

    //判断mime类型
    if (!in_array($resource->mime(), $allow_type)) {
        $data['message'] = '上传类型错误，请确定类型！';
        return json_encode($data);
    }
    //验证通过后转为PNG类型图片 存储于临时目录下
    $result = $resource->save($temp_file_path);

    if (!$result) {
        $data['message'] = 'error';
        $data['url'] = '';
        return $res->withJson($data);
    }
    $data['message'] = 'success';
    $data['url'] = $_SERVER["HTTP_HOST"] . $temp_file_name;

    return $res->withJson($data);
});

/*
 *  切图 返回新的临时图片地址
 * @param string width,height,x,y
 * @param string temp url path
 * @return crop temp file path
 */
$app->post('/crop', function (Request $req, Response $res, $args = []) {
    $data = [
        'message' => '',
        'url' => ''
    ];
    // todo
    $crop_temp_name = '/files/temp/' . uniqid() . '.png';
    $crop_temp_path = WEB_ROOT . $crop_temp_name;
    /*
     * 1.接受裁剪参数
     * 2.进行裁剪，生成临时图片地址并保存到临时目录
     * 3.返回临时图片地址
     */
    $parsedBody = $req->getParsedBody();

    $path = convertUrl($parsedBody['url'], $this->get('host'));
    //异常处理
    try {
        $image = Image::make($path);

        $result = $image->crop($parsedBody['width'], $parsedBody['height'], $parsedBody['x'], $parsedBody['y'])->save($crop_temp_path);
        if (!$result) {
            $data['message'] = 'error';
            $data['url'] = '';
            return json_encode($data);
        }
        $data['message'] = 'success';
        $data['url'] = $_SERVER["HTTP_HOST"] . $crop_temp_name;
        return json_encode($data);

    } catch (Exception $exception) {
        $data['message'] = 'error';
        $data['url'] = '';
        return $res->withJson($data);
    }


    $data['message'] = 'success';
    $data['url'] = $crop_temp;

    return $res->withJson($data);
});

/*
 *  保存临时图片 返回永久地址
 * @param string context dir name
 * @param string crop temp file path
 * @return permanent file path
 */
$app->post('/save', function (Request $req, Response $res, $args = []) {

    $data = [
        'message' => '',
        'url' => ''
    ];
    // todo
    $params = $req->getParsedBody();

    $path = convertUrl($params['temp_path'], $this->get('host'));
    //用户选择保存路径用户
    $save_name = $params['context'] ? $params['context'] : 'default';
    //生成永久链接地址 路径
    $permanent_file_path = $this->get('files_contexts_dir') . $save_name . '/' . uniqid() . '.png';
    $permanent_file_url = $_SERVER["HTTP_HOST"] . $permanent_file_path;

    //文件目录创建
    dirIsExists(WEB_ROOT . $this->get('files_contexts_dir') . $save_name);

    //生成永久文件
    $result = Image::make($path)->save(WEB_ROOT . $permanent_file_path);

    //返回结果
    if (!$result) {
        $data['message'] = 'error';
        $data['url'] = '';
        return $res->withJson($data);
    }

    $data['message'] = 'success';
    $data['url'] = $permanent_file_url;

    return $res->withJson($data);

});

/*
 *  上传微信API证书
 *  @param file
 *  @return private url path
 */
$app->post('/fileUpload', function (Request $req, Response $res, $args = []) {
    //获取用户携带token
    /*
     * 判断token是否合法
    if (!true) {
        $data['message'] = 'User unverified';
        $data['name'] = '';
        return $res->withJson($data);
    }
    */
    //文件唯一ID
    $uniqid = uniqid();

    $uploadedFiles = $req->getUploadedFiles();
    //判断文件夹是否存在
    dirIsExists('./../' . $this->get('wechat_api_file_path'));
    //判断文件是否合法
    $file = isLawful($uploadedFiles, $res);
    //获取文件名
    $fileName = $file->getClientFilename();
    //生成唯一文件名
    $uniquenessName = $uniqid . substr($fileName, strrpos($fileName, '.'));
    //生成保存地址
    $filePath = './../' . $this->get('wechat_api_file_path') . '/' . $uniquenessName;
    //返回json数据
    try {

        $file->moveTo($filePath);
        $data['message'] = 'success';
        $data['name'] = $uniquenessName;
        return $res->withJson($data);

    } catch (Exception $exception) {
        $data['message'] = 'error';
        $data['name'] = '';
        return $res->withJson($data);
    }

});

/**
 * bash64图片上传
 * @param stream{json}
 * @return image uniqid
 */
$app->post('/streamUploadImage', function (Request $req, Response $res, $args = []) {

    $data = [];

    $stream = $req->getParsedBody();

    $name = uniqid().'.png';
    $filePath = WEB_ROOT.$this->get('files_contexts_dir').'default/';
    $fullPath = $filePath.$name;

    $fullStream = array_pop($stream);

    if (empty($fileStream)){
        $data['message'] = 'error';
        $data['name'] = '';
    }
    //文件目录创建
    dirIsExists($filePath);

    $imgStream = base64_decode(array_pop(explode(',',$fullStream)));

    $result = file_put_contents($fullPath,$imgStream);
    //检查图片写入是否成功
    $imgSize = getimagesize($fullPath);

    if ($result<=0 || !$imgSize){
        $data['message'] = 'error';
        $data['name'] = '';
    }
    $permanent_file_path = $this->get('files_contexts_dir')  . 'default/' . $name;

    $permanent_file_url = $_SERVER["HTTP_HOST"] . $permanent_file_path;

    $data['message'] = 'success';
    $data['name'] = $permanent_file_url;

    return $res->withJson($data);

});
$app->run();