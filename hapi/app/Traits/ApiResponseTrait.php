<?php 

namespace App\Traits;

use Illuminate\Http\JsonResponse;

trait ApiResponseTrait {

    protected Array $errorResponse = [
        "NotImplemented" => "该功能尚未实现",

        // Authentication Errors
        "Unauthenticated" => "登录失效",
        "NoPermission" => "无权查看此页面。此事件将被报告。",
        "NoPermissionAction" => "本操作超出权限范围",

        // Request Errors
        "InsufficientParameters" => "参数错误",

        // PostsController
        "Banned" => "您被系统封禁了, 因此无法完成此操作",
        "BridgeUnacceptableReference" => '您的互通树洞中使用 $id 格式提到了一个不互通或不存在的树洞, 请删除后发送',
        "CannotFlagAlreadyBanned" => "本贴已经完成处理，无需再次举报",
        "CannotMakeEmptyPost" => "不可以发送空的树洞",
        
        "ExceededMaximumFileSize1MB" => "图片大小超过系统上限 (1 MiB)",
        "FloodControl" => "您的发帖速度太快了！请至少间隔10秒钟发帖",

        "IncorrectBase64EncodingLegacy" => "图片前端编码错误",
        "IncorrectBinaryEncoding" => "图片前后端校验上传不一致",
        "IncorrectDataType" => "图片前端指定编码与实际编码不一致",
        "IncorrectImageFormat" => "图片格式不被系统支持",

        "InternalAtomicTransactionError" => "服务器内部数据库事务操作错误, 回复失败",
        "InternalBridgeTransactionError" => "无法与Bridge服务器同步, 回复失败",

        "NotBannedCannotUndo" => "无法解封, 找不到封禁记录",
        "NotFound" => "找不到该树洞",
        "NotFoundPriviledge" => "访问树洞权限不足",

        "UnsupportedType" => "该发帖Type不被支持",
        "UnsupportedUploadImageFormat" => "该图片格式不被允许",

        // FlagsController
        "AlreadyFlagged" => "已经举报过了"


    ];

    protected function successResponse(Array $response = [], Int $code = 200): JsonResponse {
        $response["error"] = null;

        return response()->json($response, $code);
    }

    protected function errorResponse(String $error, String $errorMessage = "", Array $extra = [], Int $code = 200): JsonResponse {
        $response = $extra;
        $response["error"] = $error;
        if(!empty($errorMessage)) {
            $response["error_msg"] = __($errorMessage);
        }
        else {
            if(isset($this->errorResponse[$error])) $response["error_msg"] = __($this->errorResponse[$error]);
            else $response["error_msg"] = __("未知错误");
        }

        return response()->json($response, $code);
    }
}