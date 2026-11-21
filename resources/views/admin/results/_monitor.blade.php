@foreach($event->rounds as $round)
    <div class="ibox">
        <div class="ibox-title">
            <h5>{{ $round->name }}</h5>
            <div class="ibox-tools"><small class="text-muted">Updated {{ now()->format('h:i:s A') }}</small></div>
        </div>
        <div class="ibox-content table-responsive">
            @if($round->categories->isEmpty() || $event->judges->isEmpty())
                <p class="text-muted m-b-none">{{ $event->judges->isEmpty() ? 'No judges assigned yet.' : 'No categories yet.' }}</p>
            @else
                <table class="table table-bordered progress-matrix m-b-none">
                    <thead>
                    <tr>
                        <th>Category</th>
                        @foreach($event->judges as $judge)
                            @php($online = \App\Support\LiveDisplay::isOnline($judge))
                            @php($judgeLocked = \App\Support\ScreenLock::isLocked($judge, $event->id))
                            <th><span class="seat-badge seat-badge-sm">Seat {{ $judge->pivot->seat ?? '—' }}</span><br>{{ $judge->name }}<br>
                                <small class="{{ $online ? 'text-navy' : 'text-muted' }}" title="{{ $online ? 'Screen connected' : 'Not connected right now' }}">
                                    <span class="online-dot {{ $online ? 'is-online' : '' }}"></span> {{ $online ? 'Online' : 'Offline' }}
                                </small>
                                @if($judgeLocked)<span class="lock-badge d-block"><i class="fa fa-lock"></i> Locked</span>@endif
                                @php($ownLock = \App\Support\ScreenLock::judgeLocked($judge))
                                <form method="POST" action="{{ route('admin.judges.lock') }}" class="m-t-xs">
                                    @csrf <input type="hidden" name="scope" value="judge"><input type="hidden" name="judge" value="{{ $judge->id }}">
                                    <input type="hidden" name="event" value="{{ $event->id }}"><input type="hidden" name="lock" value="{{ $ownLock ? 0 : 1 }}">
                                    <button class="btn btn-xs {{ $ownLock ? 'btn-warning' : 'btn-white' }}" title="{{ $ownLock ? 'Unlock this judge\'s screen' : 'Lock this judge\'s screen' }}">
                                        <i class="fa {{ $ownLock ? 'fa-unlock' : 'fa-lock' }}"></i> {{ $ownLock ? 'Unlock screen' : 'Lock screen' }}</button>
                                </form>
                            </th>
                        @endforeach
                        <th style="width:110px">Control</th>
                    </tr>
                    </thead>
                    <tbody>
                    @foreach($round->categories as $category)
                        <tr>
                            <td>
                                <strong>{{ $category->name }}</strong>
                                <span class="badge {{ $category->statusBadge() }}">{{ ucfirst($category->status) }}</span>
                            </td>
                            @foreach($event->judges as $judge)
                                @php($p = $progress[$category->id][$judge->id])
                                <td>
                                    @if($p['submitted'])
                                        <span class="text-navy"><i class="fa fa-check-circle"></i> Submitted</span><br>
                                        <small class="text-muted">{{ $p['submitted_at'] }}</small>
                                        <form method="POST" action="{{ route('admin.categories.unlock', [$category, $judge]) }}" class="js-confirm"
                                              data-confirm="Allow {{ $judge->name }} to edit their scores for {{ $category->name }} again?" data-type="info" data-color="#f8ac59">
                                            @csrf @method('DELETE')
                                            <button class="btn btn-xs btn-link text-warning p-0"><i class="fa fa-unlock-alt"></i> Unlock</button>
                                        </form>
                                    @elseif($p['entered'] > 0)
                                        <span class="text-warning"><i class="fa fa-pencil"></i> In progress</span><br>
                                        <small>{{ $p['entered'] }} / {{ $p['expected'] }}</small>
                                    @else
                                        <span class="text-muted"><i class="fa fa-clock-o"></i> Not started</span>
                                    @endif
                                </td>
                            @endforeach
                            <td>@include('admin.events._category_status', ['category' => $category])</td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            @endif
        </div>
    </div>
@endforeach
