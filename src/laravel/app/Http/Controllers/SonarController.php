<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class SonarController extends Controller
{
    public function analyze(Request $request)
    {
        $code = $request->input('code');

        
        $projectKey = 'review_' . Str::random(10);
        $tempDir = storage_path("app/sonar/{$projectKey}");
        mkdir($tempDir, 0777, true);
        file_put_contents("{$tempDir}/index.php", $code);


        return response()->json([
            'status' => 'success',
            'message' => 'Код сохранён. Анализ будет добавлен позже.',
            'project_key' => $projectKey,
            'code_length' => strlen($code)
        ]);
    }
}
