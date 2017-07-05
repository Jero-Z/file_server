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

require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../config.php';

$app = new \Slim\App();

$app->get('/', function (Request $req, Response $res, $args = []) {
    return 'file-server';
});

// 上传图片文件 返回临时图片地址
$app->get('/upload', function (Request $req, Response $res, $args = []) {
    // 图片文件全部转为png

    // todo

    if (true) {
        return json_encode([
            'url' => '/tmp/c4ca4238a0b923820dcc509a6f75849b.png'
        ]);
    } else {
        return json_encode([
            'error' => 'error message'
        ]);
    }
});

// 切图 返回新的临时图片地址
$app->get('/crop', function (Request $req, Response $res, $args = []) {

    // todo

    if (true) {
        return json_encode([
            'url' => '/tmp/c4ca4238a0b923820dcc509a6f75849b.png'
        ]);
    } else {
        return json_encode([
            'error' => 'error message'
        ]);
    }
});

// 保存临时图片 返回永久地址
$app->get('/save', function (Request $req, Response $res, $args = []) {

    // todo

    // 图片文件全部转为png
    if (true) {
        return json_encode([
            'url' => '/upload/c4ca4238a0b923820dcc509a6f75849b.png'
        ]);
    } else {
        return json_encode([
            'error' => 'error message'
        ]);
    }
});


$app->run();