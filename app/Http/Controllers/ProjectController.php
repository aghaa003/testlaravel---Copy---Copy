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

        // Hide disabled projects from the public; creators see their own
        // disabled ones too; employers/admins see all when include_inactive=1.
        $this->applyActiveScope($query, $request, 'created_by');

        if ($request->filled('track')) {
            $query->where('track', $request->input('track'));
        }

        $projects = $query->orderBy('id', 'asc')->get();

        return response()->json(['projects' => $projects]);
    }

    // POST /api/projects — admin/employer only
    public function store(Request $request)
    {
        // Role (employer/admin) enforced by `role:` middleware in routes/api.php.

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

    // PUT/PATCH /api/projects/{project} — admin/employer only
    public function update(Request $request, Project $project)
    {
        // Role (employer/admin) enforced by `role:` middleware in routes/api.php.

        $validated = $request->validate([
            'track' => 'sometimes|in:basics,frontend,backend',
            'title' => 'sometimes|string|max:255',
            'desc' => 'nullable|string',
            'difficulty' => 'sometimes|integer|min:1|max:5',
            'tags' => 'nullable|array',
            'category' => 'nullable|string|max:100',
        ]);

        $updates = [];
        if (array_key_exists('track', $validated)) $updates['track'] = $validated['track'];
        if (array_key_exists('title', $validated)) $updates['title'] = $validated['title'];
        if ($request->has('desc')) $updates['description'] = $validated['desc'] ?? null;
        if (array_key_exists('difficulty', $validated)) $updates['difficulty'] = $validated['difficulty'];
        if ($request->has('tags')) $updates['tags'] = $validated['tags'] ?? [];
        if ($request->has('category')) $updates['category'] = $validated['category'] ?? null;

        $project->update($updates);

        return response()->json($project->fresh());
    }

    // POST /api/projects/{project}/toggle-active — admin/employer only
    public function toggleActive(Request $request, Project $project)
    {
        $project->update(['is_active' => ! $project->is_active]);

        return response()->json(['success' => true, 'is_active' => $project->is_active]);
    }

    // DELETE /api/projects/{project} — admin/employer only
    public function destroy(Request $request, Project $project)
    {
        // Role (employer/admin) enforced by `role:` middleware in routes/api.php.

        $project->delete();

        return response()->json(['message' => 'تم الحذف بنجاح']);
    }
}
