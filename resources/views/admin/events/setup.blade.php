@extends('layouts.app')

@section('title', 'Setup · '.$event->name)
@section('heading', 'Rounds, Categories & Criteria')
@section('breadcrumbs')
    <li class="breadcrumb-item"><a href="{{ route('admin.events.index') }}">Events</a></li>
    <li class="breadcrumb-item"><a href="{{ route('admin.events.show', $event) }}">{{ $event->name }}</a></li>
    <li class="breadcrumb-item active"><strong>Setup</strong></li>
@endsection
@section('actions')
    <button class="btn btn-primary" data-toggle="modal" data-target="#roundModal"
            data-action="{{ route('admin.rounds.store', $event) }}" data-title="Add Round"
            data-values="{{ json_encode(['sequence' => $event->rounds->max('sequence') + 1]) }}">
        <i class="fa fa-plus"></i> Add Round
    </button>
@endsection

@section('content')
@include('admin.events._nav')

<div class="alert alert-info">
    <i class="fa fa-info-circle"></i>
    <b>How scores are computed:</b> each criterion's score is converted using <code>score ÷ max × weight</code>, so a category is scored out of 100.
    The round total is the sum of <code>category score × category weight</code>. Criteria weights in each category, and category weights in each round, should total <b>100%</b>.
</div>

@foreach($event->rounds as $round)
    @php($roundWeight = $round->totalWeight())
    <div class="ibox round-ibox">
        <div class="ibox-title">
            <h5>
                <span class="badge badge-info m-r-xs">{{ $round->sequence }}</span> {{ $round->name }}
                <small class="m-l-sm">Category weights: <span class="{{ weight_class($roundWeight) }}">{{ num($roundWeight) }}%</span></small>
                @if($round->sequence > $event->rounds->min('sequence'))
                    @if($round->carry_over > 0)
                        <span class="badge badge-info m-l-xs" title="Final score includes the previous round"><i class="fa fa-link"></i> {{ num($round->carry_over) }}% bearing of previous round</span>
                    @else
                        <span class="badge badge-light m-l-xs" title="Scores start from zero"><i class="fa fa-refresh"></i> Starts from zero</span>
                    @endif
                @endif
            </h5>
            <div class="ibox-tools">
                <a href="{{ route('admin.rounds.participants', $round) }}" class="btn btn-xs btn-white">
                    <i class="fa fa-users"></i> Contestants ({{ $round->contestants->count() }})
                </a>
                <a href="{{ route('admin.rounds.titles', $round) }}" class="btn btn-xs btn-white" title="Titles for the top finishers (Winner, 1st Runner-Up...)">
                    <i class="fa fa-trophy"></i> Titles{{ $round->titles()->count() ? ' ('.$round->titles()->max('rank').')' : '' }}
                </a>
                <button class="btn btn-xs btn-primary" data-toggle="modal" data-target="#categoryModal"
                        data-action="{{ route('admin.categories.store', $round) }}" data-title="Add Category to {{ $round->name }}"
                        data-values="{{ json_encode(['weight' => max(0, 100 - $roundWeight), 'sequence' => $round->categories->max('sequence') + 1]) }}">
                    <i class="fa fa-plus"></i> Category
                </button>
                <button class="btn btn-xs btn-white" data-toggle="modal" data-target="#roundModal"
                        data-action="{{ route('admin.rounds.update', $round) }}" data-method="PUT" data-title="Edit Round"
                        data-values="{{ json_encode($round->only('name', 'sequence', 'carry_over')) }}">
                    <i class="fa fa-pencil"></i>
                </button>
                <form method="POST" action="{{ route('admin.rounds.destroy', $round) }}" class="d-inline js-confirm"
                      data-confirm="Delete round {{ $round->name }} with all its categories and scores?">
                    @csrf @method('DELETE')
                    <button class="btn btn-xs btn-white text-danger"><i class="fa fa-trash"></i></button>
                </form>
            </div>
        </div>
        <div class="ibox-content">
            @forelse($round->categories as $category)
                @php($critWeight = $category->criteriaWeight())
                <div class="category-block">
                    <div class="category-head">
                        <h4>
                            {{ $category->name }}
                            <span class="badge badge-light m-l-xs">{{ num($category->weight) }}% of round</span>
                            <span class="badge {{ $category->statusBadge() }}">{{ ucfirst($category->status) }}</span>
                        </h4>
                        <small>Criteria total: <span class="{{ weight_class($critWeight) }}">{{ num($critWeight) }}%</span></small>
                        @include('admin.events._category_status', ['category' => $category])
                        <button class="btn btn-xs btn-white" data-toggle="modal" data-target="#criterionModal"
                                data-action="{{ route('admin.criteria.store', $category) }}" data-title="Add Criterion to {{ $category->name }}"
                                data-values="{{ json_encode(['weight' => max(0, 100 - $critWeight), 'min_score' => 0, 'max_score' => max(0, 100 - $critWeight) ?: 100, 'sequence' => $category->criteria->max('sequence') + 1]) }}">
                            <i class="fa fa-plus"></i> Criterion
                        </button>
                        <button class="btn btn-xs btn-white" data-toggle="modal" data-target="#categoryModal"
                                data-action="{{ route('admin.categories.update', $category) }}" data-method="PUT" data-title="Edit Category"
                                data-values="{{ json_encode($category->only('name', 'weight', 'sequence')) }}">
                            <i class="fa fa-pencil"></i>
                        </button>
                        <form method="POST" action="{{ route('admin.categories.destroy', $category) }}" class="d-inline js-confirm"
                              data-confirm="Delete category {{ $category->name }} and all of its scores?">
                            @csrf @method('DELETE')
                            <button class="btn btn-xs btn-white text-danger"><i class="fa fa-trash"></i></button>
                        </form>
                    </div>
                    @if($category->criteria->isEmpty())
                        <div class="p-sm text-muted">No criteria yet. Add at least one before opening this category for scoring.</div>
                    @else
                        <table class="table table-sm table-hover">
                            <thead>
                            <tr>
                                <th style="width:40px">#</th><th>Criterion</th>
                                <th class="text-center">Weight</th><th class="text-center">Score range</th><th style="width:90px"></th>
                            </tr>
                            </thead>
                            <tbody>
                            @foreach($category->criteria as $criterion)
                                <tr>
                                    <td>{{ $criterion->sequence }}</td>
                                    <td>
                                        <strong>{{ $criterion->name }}</strong>
                                        @if($criterion->description)<br><small class="text-muted">{{ $criterion->description }}</small>@endif
                                    </td>
                                    <td class="text-center">{{ num($criterion->weight) }}%</td>
                                    <td class="text-center">{{ num($criterion->min_score) }} – {{ num($criterion->max_score) }}</td>
                                    <td class="text-right">
                                        <button class="btn btn-xs btn-white" data-toggle="modal" data-target="#criterionModal"
                                                data-action="{{ route('admin.criteria.update', $criterion) }}" data-method="PUT" data-title="Edit Criterion"
                                                data-values="{{ json_encode($criterion->only('name', 'description', 'weight', 'min_score', 'max_score', 'sequence')) }}">
                                            <i class="fa fa-pencil"></i>
                                        </button>
                                        <form method="POST" action="{{ route('admin.criteria.destroy', $criterion) }}" class="d-inline js-confirm"
                                              data-confirm="Delete criterion {{ $criterion->name }} and its scores?">
                                            @csrf @method('DELETE')
                                            <button class="btn btn-xs btn-white text-danger"><i class="fa fa-trash"></i></button>
                                        </form>
                                    </td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    @endif
                </div>
            @empty
                <p class="text-muted m-b-none">No categories in this round yet.</p>
            @endforelse
        </div>
    </div>
@endforeach
@endsection

@push('modals')
    <div class="modal inmodal fade js-form-modal" id="roundModal" tabindex="-1" role="dialog" aria-hidden="true">
        <div class="modal-dialog modal-sm"><div class="modal-content">
            <form method="POST">@csrf
                <div class="modal-header"><h4 class="modal-title" data-default="Round">Round</h4></div>
                <div class="modal-body">
                    <div class="form-group"><label>Round name</label><input name="name" class="form-control" placeholder="e.g. Top 5 Finals" required></div>
                    <div class="form-group"><label>Order</label><input name="sequence" type="number" min="1" class="form-control" required></div>
                    <div class="form-group m-b-none">
                        <label>Bearing of the previous round (%)</label>
                        <input name="carry_over" type="number" step="0.01" min="0" max="100" class="form-control" value="0">
                        <small class="text-muted"><b>0</b> = scores go back to zero (earlier rounds have no bearing).<br>
                            e.g. <b>30</b> = final score is 70% this round + 30% of the previous round's score. Ignored for the first round.</small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-white" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save</button>
                </div>
            </form>
        </div></div>
    </div>

    <div class="modal inmodal fade js-form-modal" id="categoryModal" tabindex="-1" role="dialog" aria-hidden="true">
        <div class="modal-dialog"><div class="modal-content">
            <form method="POST">@csrf
                <div class="modal-header"><h4 class="modal-title" data-default="Category">Category</h4></div>
                <div class="modal-body">
                    <div class="form-group"><label>Category name</label><input name="name" class="form-control" placeholder="e.g. Evening Gown" required></div>
                    <div class="row">
                        <div class="col-6 form-group"><label>Weight (% of round)</label><input name="weight" type="number" step="0.01" min="0" max="100" class="form-control" required></div>
                        <div class="col-6 form-group"><label>Order</label><input name="sequence" type="number" min="1" class="form-control"></div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-white" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save</button>
                </div>
            </form>
        </div></div>
    </div>

    <div class="modal inmodal fade js-form-modal" id="criterionModal" tabindex="-1" role="dialog" aria-hidden="true">
        <div class="modal-dialog"><div class="modal-content">
            <form method="POST">@csrf
                <div class="modal-header"><h4 class="modal-title" data-default="Criterion">Criterion</h4></div>
                <div class="modal-body">
                    <div class="form-group"><label>Criterion</label><input name="name" class="form-control" placeholder="e.g. Poise and Bearing" required></div>
                    <div class="form-group"><label>Description <small class="text-muted">(shown to judges)</small></label><input name="description" class="form-control"></div>
                    <div class="row">
                        <div class="col-6 col-md-3 form-group"><label>Weight %</label><input name="weight" type="number" step="0.01" min="0" max="100" class="form-control" required></div>
                        <div class="col-6 col-md-3 form-group"><label>Min score</label><input name="min_score" type="number" step="0.01" min="0" class="form-control" required></div>
                        <div class="col-6 col-md-3 form-group"><label>Max score</label><input name="max_score" type="number" step="0.01" min="0.01" class="form-control" required></div>
                        <div class="col-6 col-md-3 form-group"><label>Order</label><input name="sequence" type="number" min="1" class="form-control"></div>
                    </div>
                    <small class="text-muted">Tip: set the max score equal to the weight (e.g. 40%, max 40) so judges enter points directly, or use 1–10 / 1–100 scales. Scores are normalized either way.</small>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-white" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save</button>
                </div>
            </form>
        </div></div>
    </div>
@endpush
