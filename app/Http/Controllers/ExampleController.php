<?php

namespace App\Http\Controllers;

use App\Models\Example;
use Illuminate\Http\Request;

class ExampleController extends Controller
{
    // GET /api/examples?search=&category=
    public function index(Request $request)
    {
        $query = Example::query()->with('creator');

        $this->applyActiveScope($query, $request, 'created_by');

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            });
        }

        if ($request->filled('category') && $request->input('category') !== 'الكل') {
            $query->where('category', $request->input('category'));
        }

        $examples = $query->orderBy('created_at', 'desc')->get();

        return response()->json(['examples' => $examples]);
    }

    // POST /api/examples — admin/employer only
    public function store(Request $request)
    {
        // Role (employer/admin) enforced by `role:` middleware in routes/api.php.

        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'category' => 'required|string|max:100',
            'code' => 'required|string',
            'install_command' => 'nullable|string|max:255',
            'technologies' => 'nullable|array',
        ]);

        $validated['created_by'] = $request->user()->id;

        $example = Example::create($validated);

        return response()->json($example, 201);
    }

    // PUT/PATCH /api/examples/{example} — admin/employer only
    public function update(Request $request, Example $example)
    {
        // Role (employer/admin) enforced by `role:` middleware in routes/api.php.

        $validated = $request->validate([
            'title' => 'sometimes|string|max:255',
            'description' => 'sometimes|nullable|string',
            'category' => 'sometimes|string|max:100',
            'code' => 'sometimes|string',
            'install_command' => 'sometimes|nullable|string|max:255',
            'technologies' => 'sometimes|nullable|array',
        ]);

        $example->update($validated);

        return response()->json($example);
    }

    // DELETE /api/examples/{example} — admin/employer only
    public function destroy(Request $request, Example $example)
    {
        // Role (employer/admin) enforced by `role:` middleware in routes/api.php.

        $example->delete();

        return response()->json(['message' => 'تم الحذف بنجاح']);
    }

    // POST /api/examples/{example}/toggle-active — admin/employer only
    public function toggleActive(Request $request, Example $example)
    {
        $example->update(['is_active' => ! $example->is_active]);

        return response()->json(['success' => true, 'is_active' => $example->is_active]);
    }
}
