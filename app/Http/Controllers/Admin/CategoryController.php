<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Category;
use App\Models\Round;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CategoryController extends Controller
{
    public function store(Request $request, Round $round)
    {
        $data = $this->validated($request);
        $data['event_id'] = $round->event_id;
        $data['sequence'] ??= ((int) $round->categories()->max('sequence')) + 1;
        $round->categories()->create($data);

        return back()->with('success', 'Category added.');
    }

    public function update(Request $request, Category $category)
    {
        $data = $this->validated($request);
        $data['sequence'] ??= $category->sequence;
        $category->update($data);

        return back()->with('success', 'Category updated.');
    }

    public function destroy(Category $category)
    {
        $category->delete();

        return back()->with('success', 'Category deleted (including its scores).');
    }

    public function status(Request $request, Category $category)
    {
        $this->authorizeEvent($category->event);
        $data = $request->validate(['status' => ['required', Rule::in(array_keys(Category::STATUSES))]]);

        if ($data['status'] === 'open') {
            $category->load('criteria', 'round.contestants');
            if ($category->criteria->isEmpty()) {
                return back()->with('error', 'Add at least one criterion before opening "'.$category->name.'".');
            }
            if (abs($category->criteriaWeight() - 100) > 0.01) {
                return back()->with('error', 'Criteria weights of "'.$category->name.'" must total 100% (currently '.$category->criteriaWeight().'%).');
            }
            if ($category->round->contestants->isEmpty()) {
                return back()->with('error', 'Round "'.$category->round->name.'" has no contestants yet.');
            }
        }

        $category->update($data);
        ActivityLog::record('category', '"'.$category->name.'" set to '.Category::STATUSES[$data['status']].'.', $category->event);
        \App\Support\LiveDisplay::refresh($category->event);   // judges see it open / lock right away

        return back()->with('success', '"'.$category->name.'" is now '.strtolower(Category::STATUSES[$data['status']]).'.');
    }

    /** Re-open a judge's submitted score sheet so they can make corrections. */
    public function unlock(Category $category, User $judge)
    {
        $this->authorizeEvent($category->event);
        $category->submissions()->where('judge_id', $judge->id)->delete();
        ActivityLog::record('submission', 'Unlocked the score sheet of '.$judge->name.' for "'.$category->name.'".', $category->event);
        \App\Support\LiveDisplay::refreshJudge($judge);   // their screen reloads so they can edit at once

        return back()->with('success', $judge->name.' can now edit "'.$category->name.'" again. Their screen reloads by itself.');
    }

    protected function validated(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'weight' => ['required', 'numeric', 'min:0', 'max:100'],
            'sequence' => ['nullable', 'integer', 'min:1'],
        ]);
    }
}
