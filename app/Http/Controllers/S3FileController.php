<?php

namespace App\Http\Controllers;

use App\Models\S3File;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Aws\Sts\StsClient;
use Illuminate\Support\Str;

class S3FileController extends Controller
{
    public function upload(Request $request)
    {

        $fileName = $request->header('X-File-Name');
        $table = $request->header('X-Table-Name');
        $recordId = $request->header('X-Record-Id');

        $content = $request->getContent();

        $path = "attachments/$table/$recordId/$fileName";

        Storage::disk('s3')->put($path, $content);

        return response()->json([
            'success' => true,
            'path' => $path,
            'size' => strlen($content),
            'filename' => $fileName
        ], 201);
    }

public function download(Request $request)
{
    $path = $request->input('path');

    try {

        $exists = Storage::disk('s3')->exists($path);

        $url = Storage::disk('s3')->temporaryUrl(
            $path,
            now()->addMinutes(15)
        );

        return response()->json([
            'path' => $path,
            'exists' => $exists,
            'download_url' => $url
        ]);

    } catch (\Exception $e) {

        return response()->json([
            'path' => $path,
            'error' => $e->getMessage()
        ], 500);

    }
}

    /**
     * 2.3 下载接口
     */
    public function download3(Request $request)
    {
        $request->validate([
            'url' => 'required|string',
            'user_uuid' => 'required|uuid',
        ]);

        $url = $request->input('url');
        $userUuid = $request->input('user_uuid');

        // 1. 验证记录是否存在，且属于该用户
        $s3File = S3File::where('s3_url', $url)
            ->where('user_uuid', $userUuid)
            ->first();

        if (!$s3File) {
            return response()->json(['message' => 'File not found or access denied'], 404);
        }

        // 2. 使用 STS 获取临时凭证
        $stsClient = new StsClient([
            'region' => config('filesystems.disks.s3.region'),
            'version' => 'latest',
            'credentials' => [
                'key'    => config('filesystems.disks.s3.key'),
                'secret' => config('filesystems.disks.s3.secret'),
            ],
        ]);

        try {
            $result = $stsClient->getSessionToken();
            $credentials = [
                'key'    => $result['Credentials']['AccessKeyId'],
                'secret' => $result['Credentials']['SecretAccessKey'],
                'token'  => $result['Credentials']['SessionToken'],
            ];

            // 3. 使用临时凭证创建临时的 S3 Client 并生成预签名 URL
            $s3Client = new \Aws\S3\S3Client([
                'region' => config('filesystems.disks.s3.region'),
                'version' => 'latest',
                'credentials' => $credentials,
                'endpoint' => config('filesystems.disks.s3.endpoint'),
                'use_path_style_endpoint' => config('filesystems.disks.s3.use_path_style_endpoint'),
            ]);

            $command = $s3Client->getCommand('GetObject', [
                'Bucket' => config('filesystems.disks.s3.bucket'),
                'Key'    => $s3File->s3_path,
                'ResponseContentDisposition' => 'attachment; filename="' . $s3File->filename . '"'
            ]);

            $presignedRequest = $s3Client->createPresignedRequest($command, '+15 minutes');
            $presignedUrl = (string)$presignedRequest->getUri();

            return response()->json([
                'message' => 'Presigned URL generated successfully',
                'download_url' => $presignedUrl,
                'expires_at' => now()->addMinutes(15)->toIso8601String(),
                'credentials_used' => 'temporary'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to generate secure download link',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * 2.4 删除接口
     */
    public function delete(Request $request)
    {
        $request->validate([
            'url' => 'required|string',
        ]);

        $url = $request->input('url');
        $s3File = S3File::where('s3_url', $url)->first();

        if (!$s3File) {
            return response()->json(['message' => 'File not found in record'], 404);
        }

        if (Storage::disk('s3')->exists($s3File->s3_path)) {
            Storage::disk('s3')->delete($s3File->s3_path);
        }

        $s3File->delete();

        return response()->json(['message' => 'File deleted successfully']);
    }

    /**
     * 2.5 列表接口
     */
    public function list(Request $request)
    {
        $request->validate([
            'user_uuid' => 'required|uuid',
        ]);

        $userUuid = $request->input('user_uuid');
        $files = S3File::where('user_uuid', $userUuid)->get([
            's3_url',
            'filename',
            'file_size',
            'mime_type',
            'extension',
            'created_at'
        ]);

        return response()->json([
            'user_uuid' => $userUuid,
            'files' => $files
        ]);
    }

    /**
     * 2.6 获取访问S3文件所用的临时凭证接口
     */
    public function getTemporaryCredentials(Request $request)
    {
        $dir = $request->input('dir');
        $files = Storage::disk('s3')->files(
            $dir
        );

        $result = [];

        foreach ($files as $file) {
            $result[] = [
                'path' => $file,
                'url' => Storage::disk('s3')->temporaryUrl(
                    $file,
                    now()->addHour()
                )
            ];
        }

        return response()->json($result);
    }

    public function download2(Request $request)
    {
        $request->validate([
            'url' => 'required|string',
        ]);

        $url = $request->input('url');
        $s3File = S3File::where('s3_url', $url)->first();

        if (!$s3File || !Storage::disk('s3')->exists($s3File->s3_path)) {
            return response()->json(['message' => 'File not found'], 404);
        }

        return Storage::disk('s3')->download($s3File->s3_path, $s3File->filename);
    }
}
