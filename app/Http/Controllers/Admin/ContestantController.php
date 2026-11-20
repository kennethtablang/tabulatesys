<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Contestant;
use App\Models\Event;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class ContestantController extends Controller
{
    public function index(Event $event)
    {
        $contestants = $event->contestants()->with('rounds')->get();
        $divisions = $contestants->pluck('division')->filter()->unique()->values();

        return view('admin.events.contestants', compact('event', 'contestants', 'divisions'));
    }

    public function store(Request $request, Event $event)
    {
        $data = $this->validated($request, $event);
        $data['photo'] = $this->storePhoto($request);
        $contestant = $event->contestants()->create($data);

        if ($first = $event->rounds()->first()) {
            $first->contestants()->syncWithoutDetaching([$contestant->id]);
        }

        return back()->with('success', 'Contestant '.$contestant->label().' added.');
    }

    public function update(Request $request, Contestant $contestant)
    {
        $data = $this->validated($request, $contestant->event, $contestant);
        if ($photo = $this->storePhoto($request)) {
            $this->deletePhoto($contestant);
            $data['photo'] = $photo;
        }
        $contestant->update($data);

        return back()->with('success', 'Contestant updated.');
    }

    public function destroy(Contestant $contestant)
    {
        $this->deletePhoto($contestant);
        $contestant->delete();

        return back()->with('success', 'Contestant removed.');
    }

    protected function validated(Request $request, Event $event, ?Contestant $contestant = null): array
    {
        $data = $request->validate([
            'number' => ['required', 'integer', 'min:1'],
            'name' => ['required', 'string', 'max:255'],
            'division' => ['nullable', 'string', 'max:50'],
            'representing' => ['nullable', 'string', 'max:255'],
            'details' => ['nullable', 'string', 'max:2000'],
            'photo' => ['nullable', 'image', 'max:4096'],
        ]);
        unset($data['photo']);
        $data['division'] = filled($data['division'] ?? null) ? trim($data['division']) : null;

        $duplicate = $event->contestants()
            ->where('number', $data['number'])
            ->where(fn ($q) => $data['division'] === null ? $q->whereNull('division') : $q->where('division', $data['division']))
            ->when($contestant, fn ($q) => $q->where('id', '!=', $contestant->id))
            ->exists();
        if ($duplicate) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'number' => 'Contestant number '.$data['number'].' is already used'.($data['division'] ? ' in '.$data['division'] : '').'.',
            ]);
        }

        return $data;
    }

    protected function storePhoto(Request $request): ?string
    {
        if (! $request->hasFile('photo')) {
            return null;
        }
        $file = $request->file('photo');
        $name = 'contestants/'.Str::uuid().'.'.$file->extension();
        File::ensureDirectoryExists(public_path('uploads/contestants'));
        $file->move(public_path('uploads/contestants'), basename($name));

        return $name;
    }

    protected function deletePhoto(Contestant $contestant): void
    {
        if ($contestant->photo) {
            File::delete(public_path('uploads/'.$contestant->photo));
        }
    }
}
