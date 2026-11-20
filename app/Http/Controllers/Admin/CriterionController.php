<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Criterion;
use Illuminate\Http\Request;

class CriterionController extends Controller
{
    public function store(Request $request, Category $category)
    {
        $data = $this->validated($request);
        $data['sequence'] ??= ((int) $category->criteria()->max('sequence')) + 1;
        $category->criteria()->create($data);

        return back()->with('success', 'Criterion added to "'.$category->name.'".');
    }

    public function update(Request $request, Criterion $criterion)
    {
        $data = $this->validated($request);
        $data['sequence'] ??= $criterion->sequence;
        $criterion->update($data);

        return back()->with('success', 'Criterion updated.');
    }

    public function destroy(Criterion $criterion)
    {
        $criterion->delete();

        return back()->with('success', 'Criterion deleted (including its scores).');
    }

    protected function validated(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:255'],
            'weight' => ['required', 'numeric', 'min:0', 'max:100'],
            'min_score' => ['required', 'numeric', 'min:0'],
            'max_score' => ['required', 'numeric', 'gt:min_score'],
            'sequence' => ['nullable', 'integer', 'min:1'],
        ]);
    }
}
