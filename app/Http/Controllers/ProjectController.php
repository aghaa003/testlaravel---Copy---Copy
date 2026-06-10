<?php

namespace App\Http\Controllers;

use App\Models\Project;
use Illuminate\Http\Request;

class ProjectController extends Controller
{
    // GET /api/projects?track=
    public function index(Request $request)
    {
        $query = Project::query()->with('creator');

        if ($request->filled('track')) {
            $query->where('track', $request->input('track'));
        }

        $projects = $query->orderBy('id', 'asc')->get();

        return response()->json(['projects' => $projects]);
    }

    // POST /api/projects — admin/employer only
    public function store(Request $request)
    {
        $this->authorizeManage($request);

        $validated = $request->validate([
            'track' => 'required|in:basics,frontend,backend',
            'title' => 'required|string|max:255',
            'desc' => 'nullable|string',
            'difficulty' => 'required|integer|min:1|max:5',
            'tags' => 'nullable|array',
            'category' => 'nullable|string|max:100',
        ]);

        $project = Project::create([
            'track' => $validated['track'],
            'title' => $validated['title'],
            'description' => $validated['desc'] ?? null,
            'difficulty' => $validated['difficulty'],
            'tags' => $validated['tags'] ?? [],
            'category' => $validated['category'] ?? null,
            'created_by' => $request->user()->id,
        ]);

        return response()->json($project, 201);
    }

    // DELETE /api/projects/{project} — admin/employer only
    public function destroy(Request $request, Project $project)
    {
        $this->authorizeManage($request);

        $project->delete();

        return response()->json(['message' => 'تم الحذف بنجاح']);
    }

    private function authorizeManage(Request $request): void
    {
        $user = $request->user();

        if (! $user || ! in_array($user->role, ['admin', 'employer'], true)) {
            abort(403, 'غير مصرح لك بإدارة المشاريع');
        }
    }
}
