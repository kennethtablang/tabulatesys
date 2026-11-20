<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Dashboard') | {{ config('app.name') }}</title>
    <link rel="icon" type="image/svg+xml" href="{{ asset('images/prism-icon.svg') }}">
    <script src="{{ asset('js/theme.js') }}"></script>

    <link href="{{ asset('inspinia/css/bootstrap.min.css') }}" rel="stylesheet">
    <link href="{{ asset('inspinia/font-awesome/css/font-awesome.css') }}" rel="stylesheet">
    <link href="{{ asset('inspinia/css/plugins/toastr/toastr.min.css') }}" rel="stylesheet">
    <link href="{{ asset('inspinia/css/plugins/sweetalert/sweetalert.css') }}" rel="stylesheet">
    <link href="{{ asset('inspinia/css/plugins/dataTables/datatables.min.css') }}" rel="stylesheet">
    <link href="{{ asset('inspinia/css/animate.css') }}" rel="stylesheet">
    <link href="{{ asset('inspinia/css/style.css') }}" rel="stylesheet">
    <link href="{{ asset('css/tabulation.css') }}" rel="stylesheet">
    <link href="{{ asset('css/dark.css') }}" rel="stylesheet">
    <link href="{{ asset('css/accent.css') }}" rel="stylesheet">
    {!! \App\Support\Theme::styleTag() !!}
    @stack('styles')
</head>
@php($user = auth()->user())
<body class="{{ $user->isJudge() ? 'judge-mode' : '' }}">
<div id="wrapper">
    @unless($user->isJudge())
    <nav class="navbar-default navbar-static-side" role="navigation">
        <div class="sidebar-collapse">
            <ul class="nav metismenu" id="side-menu">
                <li class="nav-header">
                    <a href="{{ route('home') }}" class="sidebar-brand">@include('partials.brand', ['size' => 34, 'subtitle' => 'Tabulation System'])</a>
                    <div class="dropdown profile-element">
                        <div class="tab-avatar">{{ strtoupper(substr($user->name, 0, 1)) }}</div>
                        <a data-toggle="dropdown" class="dropdown-toggle" href="#">
                            <span class="block m-t-xs font-bold">{{ $user->name }}</span>
                            <span class="text-muted text-xs block">{{ $user->roleLabel() }} <b class="caret"></b></span>
                        </a>
                        <ul class="dropdown-menu animated fadeInRight m-t-xs">
                            @if($user->isAdmin())
                                <li><a class="dropdown-item" href="{{ route('admin.users.edit', $user) }}">My account</a></li>
                                <li class="dropdown-divider"></li>
                            @endif
                            <li><a class="dropdown-item js-logout" href="#">Logout</a></li>
                        </ul>
                    </div>
                    <div class="logo-element"><img src="{{ asset('images/prism-icon-dark.svg') }}" alt="PRISM" width="34" height="34"></div>
                </li>

                @if($user->isAdmin())
                    <li class="{{ request()->routeIs('admin.dashboard') ? 'active' : '' }}">
                        <a href="{{ route('admin.dashboard') }}"><i class="fa fa-th-large"></i> <span class="nav-label">Dashboard</span></a>
                    </li>
                    <li class="{{ request()->routeIs('admin.events.*', 'admin.contestants.*', 'admin.rounds.*') ? 'active' : '' }}">
                        <a href="#"><i class="fa fa-trophy"></i> <span class="nav-label">Events</span><span class="fa arrow"></span></a>
                        <ul class="nav nav-second-level collapse">
                            <li class="{{ request()->routeIs('admin.events.index') ? 'active' : '' }}"><a href="{{ route('admin.events.index') }}">All Events</a></li>
                            <li class="{{ request()->routeIs('admin.events.create') ? 'active' : '' }}"><a href="{{ route('admin.events.create') }}"><i class="fa fa-plus"></i> Add Event</a></li>
                        </ul>
                    </li>
                    @php($sidebarEvents = \App\Models\Event::where('status', 'ongoing')->orderBy('name')->get(['id', 'name']))
                    @if($sidebarEvents->isNotEmpty())
                        <li class="{{ request()->routeIs('admin.events.results', 'admin.events.monitor') ? 'active' : '' }}">
                            <a href="#"><i class="fa fa-bar-chart"></i> <span class="nav-label">Live Tabulation</span><span class="label label-primary float-right">{{ $sidebarEvents->count() }}</span></a>
                            <ul class="nav nav-second-level collapse">
                                @foreach($sidebarEvents as $se)
                                    <li><a href="{{ route('admin.events.results', $se) }}">{{ \Illuminate\Support\Str::limit($se->name, 26) }}</a></li>
                                @endforeach
                            </ul>
                        </li>
                    @endif
                    <li class="{{ request()->routeIs('admin.history') ? 'active' : '' }}">
                        <a href="{{ route('admin.history') }}"><i class="fa fa-history"></i> <span class="nav-label">Tabulation History</span></a>
                    </li>
                    <li class="{{ request()->routeIs('admin.users.*') ? 'active' : '' }}">
                        <a href="{{ route('admin.users.index') }}"><i class="fa fa-users"></i> <span class="nav-label">Users</span></a>
                    </li>
                    <li class="{{ request()->routeIs('admin.settings*') ? 'active' : '' }}">
                        <a href="{{ route('admin.settings') }}"><i class="fa fa-cogs"></i> <span class="nav-label">Settings</span></a>
                    </li>
                @elseif($user->isTabulator())
                    <li class="{{ request()->routeIs('tabulator.dashboard') ? 'active' : '' }}">
                        <a href="{{ route('tabulator.dashboard') }}"><i class="fa fa-th-large"></i> <span class="nav-label">Live Events</span></a>
                    </li>
                    @foreach(\App\Models\Event::visibleTo($user)->when(! $user->event_id, fn ($q) => $q->where('status', 'ongoing'))->orderBy('name')->get(['id', 'name']) as $se)
                        <li class="{{ request()->route('event')?->id === $se->id ? 'active' : '' }}">
                            <a href="#"><i class="fa fa-bar-chart"></i> <span class="nav-label">{{ \Illuminate\Support\Str::limit($se->name, 22) }}</span><span class="fa arrow"></span></a>
                            <ul class="nav nav-second-level collapse">
                                <li><a href="{{ route('admin.events.monitor', $se) }}">Scoring Monitor</a></li>
                                <li><a href="{{ route('admin.events.results', $se) }}">Results</a></li>
                            </ul>
                        </li>
                    @endforeach
                @else
                    <li class="{{ request()->routeIs('judge.*') ? 'active' : '' }}">
                        <a href="{{ route('judge.dashboard') }}"><i class="fa fa-gavel"></i> <span class="nav-label">My Events</span></a>
                    </li>
                @endif
            </ul>
        </div>
    </nav>
    @endunless

    <div id="page-wrapper" class="gray-bg">
        <div class="row border-bottom">
            <nav class="navbar navbar-static-top white-bg" role="navigation" style="margin-bottom: 0">
                <div class="navbar-header">
                    @if($user->isJudge())
                        <a href="{{ route('judge.dashboard') }}" class="judge-brand">
                            @include('partials.brand', ['size' => 38, 'iconOnly' => true])
                            <span><b>{{ $user->name }}</b><small class="d-block text-muted">Judge · {{ config('app.name') }} {{ config('app.subtitle') }}</small></span></a>
                    @else
                        <a class="navbar-minimalize minimalize-styl-2 btn btn-primary" href="#"><i class="fa fa-bars"></i></a>
                        <span class="navbar-brand-text d-none d-md-inline-block">{{ config('app.name') }} <small class="text-muted font-weight-normal">· {{ config('app.subtitle') }}</small></span>
                    @endif
                </div>
                <ul class="nav navbar-top-links navbar-right">
                    @if($user->isJudge())
                        <li><a href="{{ route('judge.dashboard') }}" class="{{ request()->routeIs('judge.dashboard') ? 'text-navy' : '' }}"><i class="fa fa-gavel"></i> My Events</a></li>
                    @else
                        <li><span class="m-r-sm text-muted welcome-message">{{ now()->format('l, F d, Y') }}</span></li>
                    @endif
                    <li><a href="#" class="theme-toggle" data-theme-toggle title="Switch to dark mode"><i class="fa fa-moon-o theme-icon-dark"></i><i class="fa fa-sun-o theme-icon-light"></i><span class="d-none d-sm-inline"> Theme</span></a></li>
                    <li><a href="#" class="js-logout"><i class="fa fa-sign-out"></i> Log out</a></li>
                </ul>
            </nav>
        </div>

        @php($headingEvent = request()->route('event') ?? optional(request()->route('category'))->event ?? optional(request()->route('round'))->event)
        @php($headingLogos = $headingEvent instanceof \App\Models\Event ? $headingEvent->displayLogos() : \App\Models\Logo::global())
        <div class="row wrapper border-bottom white-bg page-heading page-heading-flex {{ $headingLogos->isNotEmpty() ? 'has-logos' : '' }}"
             style="--heading-logo-h: {{ \App\Models\Logo::headingHeight() }}px">
            <div class="heading-main">
                <h2>@yield('heading', 'Dashboard')</h2>
                @hasSection('breadcrumbs')
                    <ol class="breadcrumb">@yield('breadcrumbs')</ol>
                @endif
            </div>
            @hasSection('actions')
                <div class="heading-actions title-action">@yield('actions')</div>
            @endif
            {{-- Admin-uploaded logos (school, organization, LGU): this event's logos on event pages, otherwise the "every event" logos. --}}
            @if($headingLogos->isNotEmpty())
                <div class="heading-logos">
                    @include('partials.logo_band', ['logos' => $headingLogos, 'class' => 'logo-band-heading'])
                </div>
            @endif
        </div>

        <div class="wrapper wrapper-content animated fadeInRight">
            @if($errors->any())
                <div class="alert alert-danger">
                    <strong>Please fix the following:</strong>
                    <ul class="m-b-none">
                        @foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach
                    </ul>
                </div>
            @endif
            @if(session('credentials') && $user->isAdmin())
                @include('admin._credentials')
            @endif
            @yield('content')
        </div>

        <div class="footer">
            <div class="float-right d-none d-md-block"><b>{{ config('app.name') }}</b> · {{ config('app.tagline') }}</div>
            <div class="credit"><img src="{{ asset('images/sti-alaminos-small.png') }}" alt="STI Alaminos" class="credit-logo">
                &copy; {{ date('Y') }} <strong>Team Tabulators of STI Alaminos</strong>. All rights reserved.</div>
        </div>
    </div>
</div>

<form id="logout-form" action="{{ route('logout') }}" method="POST" class="d-none">@csrf</form>
@stack('modals')

@if($user->isJudge())
    @php($liveEventId = optional(request()->route('event'))->id ?? optional(request()->route('category'))->event_id)
    <script>
        window.TMS_LIVE = {
            url: @json(route('judge.ping', array_filter(['event' => $liveEventId, 'page' => request()->route()?->getName()]))),
            token: @json(\App\Support\LiveDisplay::token(\App\Support\LiveDisplay::eventIdsFor($user, $liveEventId), $user))
        };
    </script>
@endif
<script src="{{ asset('inspinia/js/jquery-3.1.1.min.js') }}"></script>
<script src="{{ asset('inspinia/js/popper.min.js') }}"></script>
<script src="{{ asset('inspinia/js/bootstrap.js') }}"></script>
<script src="{{ asset('inspinia/js/plugins/metisMenu/jquery.metisMenu.js') }}"></script>
<script src="{{ asset('inspinia/js/plugins/slimscroll/jquery.slimscroll.min.js') }}"></script>
<script src="{{ asset('inspinia/js/plugins/toastr/toastr.min.js') }}"></script>
<script src="{{ asset('inspinia/js/plugins/sweetalert/sweetalert.min.js') }}"></script>
<script src="{{ asset('inspinia/js/plugins/dataTables/datatables.min.js') }}"></script>
<script src="{{ asset('inspinia/js/inspinia.js') }}"></script>
<script src="{{ asset('inspinia/js/plugins/pace/pace.min.js') }}"></script>
<script src="{{ asset('js/tabulation.js') }}"></script>
<script>
    toastr.options = {closeButton: true, progressBar: true, positionClass: 'toast-top-right', timeOut: 5000};
    @if(session('success')) toastr.success(@json(session('success'))); @endif
    @if(session('error')) toastr.error(@json(session('error'))); @endif
</script>
@stack('scripts')
</body>
</html>
