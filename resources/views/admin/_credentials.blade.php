{{-- Shown once, right after accounts are generated or a password is reset. --}}
<div class="ibox credential-box">
    <div class="ibox-title">
        <h5><i class="fa fa-id-card-o"></i> Login slips: write down or print these now</h5>
        <div class="ibox-tools">
            <button type="button" class="btn btn-xs btn-primary js-print-slips"><i class="fa fa-print"></i> Print login slips</button>
        </div>
    </div>
    <div class="ibox-content">
        <p class="text-danger m-b-sm"><i class="fa fa-exclamation-triangle"></i> Passwords are shown <b>only this once</b>. If one is lost, use <i>New password</i> to make another.</p>
        <div class="credential-slips">
            @foreach(session('credentials') as $c)
                <div class="credential-slip">
                    <div class="slip-title">{{ config('app.name') }}</div>
                    @if($c['event'])<div class="slip-event">{{ $c['event'] }}</div>@endif
                    <div class="slip-name">{{ $c['name'] }} <span class="slip-role">{{ $c['role'] }}</span></div>
                    <table>
                        <tr><td>Username</td><td><code>{{ $c['username'] }}</code></td></tr>
                        <tr><td>Password</td><td><code>{{ $c['password'] }}</code></td></tr>
                        <tr><td>Open</td><td><code>{{ url('/') }}</code></td></tr>
                    </table>
                </div>
            @endforeach
        </div>
    </div>
</div>
