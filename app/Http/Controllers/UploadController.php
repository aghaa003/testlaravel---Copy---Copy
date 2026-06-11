<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class UploadController extends Controller
{
    public function store(Request $request)
    {
        $fieldName = 'file';
        if ($request->hasFile('image')) {
            $fieldName = 'image';
        }
        if ($request->hasFile('avatar')) {
            $fieldName = 'avatar';
        }
        if ($request->hasFile('video')) {
            $fieldName = 'video';
        }
        if ($request->hasFile('pdf')) {
            $fieldName = 'pdf';
        }

        $request->validate([
            // Allowlist safe types only — block svg/html/php and other active content.
            $fieldName => 'required|file|max:102400|mimes:jpg,jpeg,png,gif,webp,pdf,mp4,webm,mov,zip,txt,doc,docx,ppt,pptx,xls,xlsx',
        ]);

        $file = $request->file($fieldName);
        $path = Storage::disk('public')->put('uploads', $file);

        return response()->json([
            'file' => [
                'url' => '/storage/'.$path,
                'name' => $file->getClientOriginalName(),
                'size' => $file->getSize(),
                'type' => $file->getMimeType(),
            ],
            'url' => '/storage/'.$path,
        ], 201);
    }

    public function storeMultiple(Request $request)
    {
        $files = [];

        if ($request->hasFile('files')) {
            $input = $request->file('files');
            $files = is_array($input) ? $input : [$input];
        } elseif ($request->hasFile('file')) {
            $input = $request->file('file');
            $files = is_array($input) ? $input : [$input];
        } else {
            foreach ($request->files->all() as $value) {
                if (is_array($value)) {
                    $files = array_merge($files, $value);
                } else {
                    $files[] = $value;
                }
            }
        }

        if (empty($files)) {
            return response()->json(['message' => 'لم يتم إرسال أي ملفات'], 422);
        }

        $allowedExt = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'pdf', 'mp4', 'webm', 'mov', 'zip', 'txt', 'doc', 'docx', 'ppt', 'pptx', 'xls', 'xlsx'];

        $urls = [];
        foreach ($files as $file) {
            if (! $file || ! $file->isValid()) {
                continue;
            }
            // Skip files that aren't in the allowlist or exceed 100MB.
            if (! in_array(strtolower($file->getClientOriginalExtension()), $allowedExt, true)
                || $file->getSize() > 102400 * 1024) {
                continue;
            }
            $path = Storage::disk('public')->put('uploads', $file);
            $urls[] = [
                'url' => '/storage/'.$path,
                'name' => $file->getClientOriginalName(),
                'size' => $file->getSize(),
                'type' => $file->getMimeType(),
            ];
        }

        return response()->json([
            'urls' => array_column($urls, 'url'),
            'files' => $urls,
        ], 201);
    }
}
