@extends('layouts.app')

@section('title', 'Judges & Users')
@section('heading', 'Judges & Users')
@section('breadcrumbs')
    <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">Home</a></li>
    <li class="breadcrumb-item active"><strong>Users</strong></li>
@endsection
@section('actions')
    <a href="{{ route('admin.users.create') }}" class="btn btn-primary"><i class="fa fa-user-plus"></i> Add Account</a>
@endsection

@section('content')
<div class="ibox">
    <div class="ibox-content table-responsive">
        <table class="table table-hover js-datatable">
            <thead>
            <tr><th>Name</th><th>Username</th><th>Role</th><th class="text-center">Events</th><th>Status</th><th style="width:120px"></th></tr>
            </thead>
            <tbody>
            @foreach($users as $u)
                <tr>
                    <td><strong>{{ $u->name }}</strong>@if($u->email)<br><small class="text-muted">{{ $u->email }}</small>@endif</td>
                    <td>{{ $u->username }}</td>
                    <td><span class="badge {{ $u->isAdmin() ? 'badge-warning' : ($u->isTabulator() ? 'badge-primary' : 'badge-info') }}">{{ $u->roleLabel() }}</span></td>
                    <td class="text-center">{{ $u->isJudge() ? $u->judged_events_count : ($u->isTabulator() ? ($u->event?->name ?? 'All events') : '—') }}</td>
                    <td>{!! $u->is_active ? '<span class="text-navy">Active</span>' : '<span class="text-danger">Inactive</span>' !!}</td>
                    <td class="text-right">
                        @unless($u->isAdmin())
                            <form method="POST" action="{{ route('admin.users.reset-password', $u) }}" class="d-inline js-confirm"
                                  data-confirm="Generate a new password for {{ $u->name }}? The old password will stop working." data-type="info" data-color="#1ab394" data-button="Generate">
                                @csrf
                                <button class="btn btn-xs btn-white" title="Generate new password"><i class="fa fa-key"></i></button>
                            </form>
                        @endunless
                        <a href="{{ route('admin.users.edit', $u) }}" class="btn btn-xs btn-white"><i class="fa fa-pencil"></i></a>
                        @unless($u->is(auth()->user()))
                            <form method="POST" action="{{ route('admin.users.destroy', $u) }}" class="d-inline js-confirm"
                                  data-confirm="Delete {{ $u->name }}? Their scores will also be deleted.">
                                @csrf @method('DELETE')
                                <button class="btn btn-xs btn-white text-danger"><i class="fa fa-trash"></i></button>
                            </form>
                        @endunless
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
</div>
@endsection
