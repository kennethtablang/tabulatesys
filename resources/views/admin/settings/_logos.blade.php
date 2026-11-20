{{-- Settings → Logos: school, organization, LGU... shown on the right of the judges' screen heading. --}}
<div class="ibox" id="logos">
    <div class="ibox-title">
        <h5><i class="fa fa-picture-o"></i> Logos: school, organization, LGU</h5>
        <div class="ibox-tools"><small class="text-muted">{{ $logos->count() }} logo(s)</small></div>
    </div>
    <div class="ibox-content">
        <p class="text-muted m-b-md">These appear on the <b>right side of the heading on the judges' screens</b> (and on the other pages), on printed results, and optionally on the login page. Add as many as you need, edit or replace them at any time. Judges' screens update by themselves.</p>

        <div class="row">
            {{-- ---------- Add ---------- --}}
            <div class="col-lg-4 m-b-md">
                <form method="POST" action="{{ route('admin.logos.store') }}" enctype="multipart/form-data" class="logo-add">
                    @csrf
                    <h4 class="m-t-none"><i class="fa fa-plus-circle text-navy"></i> Add a logo</h4>
                    <div class="form-group">
                        <label>Name</label>
                        <input name="name" class="form-control" value="{{ old('name') }}" placeholder="e.g. STI College Alaminos" maxlength="120" required>
                    </div>
                    <div class="form-group">
                        <label>Type</label>
                        <select name="type" class="form-control">
                            @foreach(\App\Models\Logo::TYPES as $value => $label)<option value="{{ $value }}" @selected(old('type') === $value)>{{ $label }}</option>@endforeach
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Image</label>
                        <input type="file" name="logo" class="form-control" accept=".png,.jpg,.jpeg,.webp,.gif" required>
                        <small class="text-muted">PNG with a transparent background looks best · max 4 MB.</small>
                    </div>
                    <label class="m-0"><input type="checkbox" name="show_on_all" value="1" class="js-all-events" @checked(old('show_on_all', true))> <b>Show on every event</b></label>
                    <div class="js-event-picks m-t-xs" @if(old('show_on_all', true)) style="display:none" @endif>
                        <small class="text-muted d-block">Only on:</small>
                        @foreach($events as $e)
                            <label class="d-block m-0 font-normal"><input type="checkbox" name="events[]" value="{{ $e->id }}"> {{ $e->name }}</label>
                        @endforeach
                    </div>
                    <button class="btn btn-primary btn-block m-t-sm"><i class="fa fa-upload"></i> Add Logo</button>
                </form>
            </div>

            {{-- ---------- Existing logos ---------- --}}
            <div class="col-lg-8">
                @forelse($logos as $logo)
                    <form method="POST" action="{{ route('admin.logos.update', $logo) }}" enctype="multipart/form-data" class="logo-item" id="logo-{{ $logo->id }}">
                        @csrf @method('PUT')
                        <div class="logo-preview"><img src="{{ $logo->url() }}" alt="{{ $logo->name }}"></div>
                        <div class="logo-fields">
                            <div class="row">
                                <div class="col-md-6 form-group m-b-xs">
                                    <label class="m-b-none small">Name</label>
                                    <input name="name" class="form-control form-control-sm" value="{{ $logo->name }}" maxlength="120" required>
                                </div>
                                <div class="col-12 col-md-6 form-group m-b-xs">
                                    <label class="m-b-none small">Type</label>
                                    <select name="type" class="form-control form-control-sm">
                                        @foreach(\App\Models\Logo::TYPES as $value => $label)<option value="{{ $value }}" @selected($logo->type === $value)>{{ $label }}</option>@endforeach
                                    </select>
                                </div>
                                <div class="col-6 col-md-3 form-group m-b-xs">
                                    <label class="m-b-none small" title="Lower numbers appear first (left)">Order</label>
                                    <input name="sort_order" type="number" min="1" max="999" class="form-control form-control-sm" value="{{ $logo->sort_order }}" required>
                                </div>
                                <div class="col-6 col-md-3 form-group m-b-xs">
                                    <label class="m-b-none small" title="Make this logo bigger or smaller than the others">Size %</label>
                                    <input name="scale" type="number" min="40" max="250" step="5" class="form-control form-control-sm js-logo-scale" data-logo="{{ $logo->id }}" value="{{ $logo->scale ?? 100 }}" required>
                                </div>
                            </div>
                            <div class="d-flex flex-wrap align-items-center" style="gap:4px 14px">
                                <label class="m-0"><input type="checkbox" name="show_on_all" value="1" class="js-all-events" @checked($logo->show_on_all)> Show on every event</label>
                                <label class="m-0 small text-muted" title="Choose a new image to replace this one"><i class="fa fa-refresh"></i> Replace image
                                    <input type="file" name="logo" accept=".png,.jpg,.jpeg,.webp,.gif" class="logo-replace"></label>
                            </div>
                            <div class="js-event-picks m-t-xs" @if($logo->show_on_all) style="display:none" @endif>
                                <small class="text-muted">Only on:</small>
                                @forelse($events as $e)
                                    <label class="m-0 m-r-sm font-normal"><input type="checkbox" name="events[]" value="{{ $e->id }}" @checked($e->logos->contains('id', $logo->id))> {{ $e->name }}</label>
                                @empty
                                    <small class="text-muted">no events yet</small>
                                @endforelse
                            </div>
                        </div>
                        <div class="logo-actions">
                            <button class="btn btn-xs btn-primary"><i class="fa fa-check"></i> Save</button>
                            <button type="submit" form="delete-logo-{{ $logo->id }}" class="btn btn-xs btn-white text-danger" aria-label="Delete {{ $logo->name }}"><i class="fa fa-trash"></i></button>
                        </div>
                    </form>
                    <form method="POST" action="{{ route('admin.logos.destroy', $logo) }}" id="delete-logo-{{ $logo->id }}" class="js-confirm d-none"
                          data-confirm="Delete the logo &quot;{{ $logo->name }}&quot;? It disappears from every screen.">
                        @csrf @method('DELETE')
                    </form>
                @empty
                    <div class="text-center text-muted p-lg logo-empty">
                        <i class="fa fa-picture-o fa-3x"></i>
                        <p class="m-t-sm m-b-none">No logos yet. Add your school, organization or LGU logo on the left.</p>
                    </div>
                @endforelse
            </div>
        </div>

        {{-- ---------- Display options ---------- --}}
        <form method="POST" action="{{ route('admin.logos.options') }}" class="logo-options" id="logo-options">
            @csrf @method('PUT')
            <div class="w-100">
                <label class="font-bold m-b-xs" for="heading-height">Logo height on the judges' screen heading: <span id="heading-height-value">{{ $general['heading_height'] }}</span> px</label>
                <input type="range" id="heading-height" name="heading_height" min="40" max="200" step="4" value="{{ $general['heading_height'] }}" class="custom-range">
                <div class="logo-height-preview m-t-xs" style="--preview-h: {{ $general['heading_height'] }}px" aria-label="Preview">
                    @forelse($logos as $logo)
                        <img src="{{ $logo->url() }}" alt="{{ $logo->name }}" data-logo="{{ $logo->id }}" style="--logo-scale: {{ ($logo->scale ?? 100) / 100 }}">
                    @empty
                        <small class="text-muted">Add a logo to see the preview.</small>
                    @endforelse
                </div>
                <small class="text-muted">Preview of the right side of the heading. Use <b>Size %</b> on a logo to make just that one bigger or smaller.</small>
            </div>
            <label class="m-0"><input type="checkbox" name="logos_on_print" value="1" @checked($general['logos_on_print'])> Also on printed / PDF results</label>
            <label class="m-0"><input type="checkbox" name="logos_on_login" value="1" @checked($general['logos_on_login'])> Also on the login page</label>
            <button class="btn btn-sm btn-primary"><i class="fa fa-check"></i> Save logo size &amp; options</button>
        </form>
    </div>
</div>
