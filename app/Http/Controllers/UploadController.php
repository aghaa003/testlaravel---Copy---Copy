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
            // ✅ Fixed: "mimes:" rejects most code-file extensions outright because
            // Laravel's internal extension→MIME map has no entry for .py/.cpp/.cs/
            // .java/etc, so every project-solution upload using these extensions
            // failed validation before reaching this controller at all. "extensions:"
            // checks the file's extension directly instead, which works for any
            // extension we explicitly allow here. Still blocks svg/html/php/exe and
            // other active-content types not in this list.
            // .html is deliberately excluded: files here are served as static
            // assets from the same origin, so an uploaded .html file would run
            // as same-origin script if opened — a stored-XSS vector. .css is
            // safe (declarative, no script execution).
            $fieldName => 'required|file|max:102400|extensions:jpg,jpeg,png,gif,webp,pdf,mp4,webm,mov,zip,rar,gz,txt,doc,docx,ppt,pptx,xls,xlsx,py,js,ts,cpp,c,cs,java,css',
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

        $allowedExt = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'pdf', 'mp4', 'webm', 'mov', 'zip', 'rar', 'gz', 'txt', 'doc', 'docx', 'ppt', 'pptx', 'xls', 'xlsx', 'py', 'js', 'ts', 'cpp', 'c', 'cs', 'java', 'css'];

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
