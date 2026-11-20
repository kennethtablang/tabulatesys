<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login | {{ config('app.name') }}</title>
    <link rel="icon" type="image/svg+xml" href="{{ asset('images/prism-icon.svg') }}">
    <script src="{{ asset('js/theme.js') }}"></script>
    <link href="{{ asset('inspinia/css/bootstrap.min.css') }}" rel="stylesheet">
    <link href="{{ asset('inspinia/font-awesome/css/font-awesome.css') }}" rel="stylesheet">
    <link href="{{ asset('inspinia/css/animate.css') }}" rel="stylesheet">
    <link href="{{ asset('inspinia/css/style.css') }}" rel="stylesheet">
    <link href="{{ asset('css/tabulation.css') }}" rel="stylesheet">
    <link href="{{ asset('css/dark.css') }}" rel="stylesheet">
    <link href="{{ asset('css/accent.css') }}" rel="stylesheet">
    {!! \App\Support\Theme::styleTag() !!}
</head>
<body class="gray-bg">
<button type="button" class="theme-toggle theme-toggle-floating" data-theme-toggle title="Switch to dark mode" aria-label="Toggle dark mode">
    <i class="fa fa-moon-o theme-icon-dark"></i><i class="fa fa-sun-o theme-icon-light"></i>
</button>
<div class="middle-box text-center loginscreen animated fadeInDown">
    <div>
        @php($loginLogos = \App\Models\Setting::get('logos.on_login', '1') === '1' ? \App\Models\Logo::global() : collect())
        <div class="login-brand">
            @include('partials.brand', ['size' => 84, 'stacked' => true, 'full' => true])
            <div class="prism-tagline">{{ config('app.tagline') }}</div>
        </div>
        @if($loginLogos->isNotEmpty())
            @include('partials.logo_band', ['logos' => $loginLogos, 'class' => 'logo-band-login'])
        @endif
        @if($org = \App\Models\Setting::get('general.organization'))<p class="m-b-xs"><b>{{ $org }}</b></p>@endif
        <p>Real-time scoring and tabulation for pageants and competitions.</p>

        @if($errors->any())
            <div class="alert alert-danger text-left">{{ $errors->first() }}</div>
        @endif

        <form class="m-t" role="form" method="POST" action="{{ route('login') }}">
            @csrf
            <div class="form-group">
                <input type="text" name="username" value="{{ old('username') }}" class="form-control" placeholder="Username or email" required autofocus>
            </div>
            <div class="form-group">
                <input type="password" name="password" class="form-control" placeholder="Password" required>
            </div>
            <div class="form-group text-left">
                <label class="m-0"><input type="checkbox" name="remember" value="1"> Remember me</label>
            </div>
            <button type="submit" class="btn btn-primary block full-width m-b">Login</button>
        </form>
        <div class="login-credit m-t">
            <small class="d-block text-muted m-b-xs">Provided by</small>
            <img src="{{ asset('images/sti-alaminos.png') }}" alt="STI Alaminos" class="login-credit-logo">
            <small class="d-block m-t-xs">&copy; {{ date('Y') }} Team Tabulators of STI Alaminos.<br>All rights reserved.</small>
        </div>
    </div>
</div>
</body>
</html>
