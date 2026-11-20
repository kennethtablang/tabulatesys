@extends('layouts.app')

@php($editing = $user->exists)
@section('title', $editing ? 'Edit Account' : 'Add Account')
@section('heading', $editing ? 'Edit Account' : 'Add Account')
@section('breadcrumbs')
    <li class="breadcrumb-item"><a href="{{ route('admin.users.index') }}">Users</a></li>
    <li class="breadcrumb-item active"><strong>{{ $editing ? $user->name : 'New' }}</strong></li>
@endsection

@push('scripts')
<script>
    $('select[name=role]').on('change', function () { $('#event-scope').toggle(this.value === 'tabulator'); }).trigger('change');
</script>
@endpush

@section('content')
<div class="row justify-content-center">
    <div class="col-lg-6">
        <form method="POST" action="{{ $editing ? route('admin.users.update', $user) : route('admin.users.store') }}">
            @csrf @if($editing) @method('PUT') @endif
            <div class="ibox">
                <div class="ibox-title"><h5>Account details</h5></div>
                <div class="ibox-content">
                    <div class="form-group"><label>Full name</label>
                        <input name="name" class="form-control" value="{{ old('name', $user->name) }}" required></div>
                    <div class="row">
                        <div class="col-md-6 form-group"><label>Username</label>
                            <input name="username" class="form-control" value="{{ old('username', $user->username) }}" required></div>
                        <div class="col-md-6 form-group"><label>Email <small class="text-muted">(optional)</small></label>
                            <input name="email" type="email" class="form-control" value="{{ old('email', $user->email) }}"></div>
                    </div>
                    <div class="form-group"><label>Role</label>
                        <select name="role" class="form-control" @disabled($user->is(auth()->user()))>
                            <option value="judge" @selected(old('role', $user->role) === 'judge')>Judge — scores assigned events</option>
                            <option value="tabulator" @selected(old('role', $user->role) === 'tabulator')>Tabulator — watches live scoring, opens/locks categories, prints results</option>
                            <option value="admin" @selected(old('role', $user->role) === 'admin')>Administrator — manages everything</option>
                        </select>
                        @if($user->is(auth()->user()))<input type="hidden" name="role" value="admin">@endif
                    </div>
                    <div class="form-group" id="event-scope">
                        <label>Tabulator for</label>
                        <select name="event_id" class="form-control">
                            <option value="">All events</option>
                            @foreach(\App\Models\Event::orderBy('name')->get(['id', 'name']) as $e)
                                <option value="{{ $e->id }}" @selected((int) old('event_id', $user->event_id) === $e->id)>{{ $e->name }} only</option>
                            @endforeach
                        </select>
                        <small class="text-muted">A tabulator tied to one event can't see any other event.</small>
                    </div>
                    <div class="row">
                        <div class="col-md-6 form-group"><label>Password @if($editing)<small class="text-muted">(leave blank to keep)</small>@endif</label>
                            <input name="password" type="password" class="form-control" @required(! $editing) autocomplete="new-password"></div>
                        <div class="col-md-6 form-group"><label>Confirm password</label>
                            <input name="password_confirmation" type="password" class="form-control" autocomplete="new-password"></div>
                    </div>
                    <label class="m-0"><input type="checkbox" name="is_active" value="1" @checked(old('is_active', $user->is_active))> Account is active</label>
                </div>
                <div class="ibox-footer">
                    <a href="{{ route('admin.users.index') }}" class="btn btn-white">Cancel</a>
                    <button class="btn btn-primary float-right"><i class="fa fa-check"></i> Save</button>
                </div>
            </div>
        </form>
    </div>
</div>
@endsection
