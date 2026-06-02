<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class UploadController extends Controller
{
    /**
     * POST /api/upload
     * Upload a single file
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'file' => 'required|file|max:20480', // 20MB max
        ]);

        $file = $validated['file'];
        $path = Storage::disk('public')->put('uploads', $file);

        return response()->json([
            'file' => [
                'url' => '/storage/'.$path,
                'name' => $file->getClientOriginalName(),
                'size' => $file->getSize(),
            ],
        ], 201);
    }

    /**
     * POST /api/upload/multiple
     * Upload multiple files at once
     */
    public function storeMultiple(Request $request)
    {
        $validated = $request->validate([
            'files' => 'required|array|min:1|max:10',
            'files.*' => 'file|max:20480',
        ]);

        $urls = [];
        foreach ($validated['files'] as $file) {
            $path = Storage::disk('public')->put('uploads', $file);
            $urls[] = '/storage/'.$path;
        }

        return response()->json([
            'urls' => $urls,
        ], 201);
    }
}
